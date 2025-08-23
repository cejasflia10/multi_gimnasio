<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
include __DIR__ . '/menu_cliente.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

if (!$cliente_id || !$gimnasio_id) {
  echo "<div style='color:red; text-align:center; font-size:18px; padding:12px'>❌ Acceso denegado.</div>";
  exit;
}

/* ----------------- Helpers ----------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function num($n, $dec=1){ return number_format((float)$n, $dec, ',', '.'); }

function db_has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = @$db->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}
function db_has_col(mysqli $db, string $t, string $c): bool {
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $res = @$db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return ($res && $res->num_rows > 0);
}
function pick_col(mysqli $db, string $t, array $cands): ?string {
  foreach ($cands as $c) if (db_has_col($db,$t,$c)) return $c;
  return null;
}

/* ----------------- Cargar cliente ----------------- */
$cliente = null;
if ($st = $conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1")) {
  $st->bind_param("ii", $cliente_id, $gimnasio_id);
  $st->execute();
  $cliente = $st->get_result()->fetch_assoc();
  $st->close();
}
if (!$cliente) {
  echo "<p style='color:red; padding:20px;'>⚠️ No se encontró el cliente.</p>";
  exit;
}
$nombre_cliente = trim(($cliente['apellido'] ?? '').' '.($cliente['nombre'] ?? ''));

/* Altura: soporta altura en cm (altura_cm) o en m (altura) */
$altura_raw = $cliente['altura_cm'] ?? $cliente['altura'] ?? null;
$altura_m = ($altura_raw && (float)$altura_raw > 0)
  ? ((float)$altura_raw > 3 ? ((float)$altura_raw / 100.0) : (float)$altura_raw)
  : 1.70; // fallback

/* ----------------- Último progreso para peso (robusto) ----------------- */
$peso_ref = null;
if (db_has_table($conexion,'progreso_cliente')) {
  $pc_fecha = pick_col($conexion,'progreso_cliente',['fecha','created_at','fecha_registro','dia']);
  $pc_peso  = pick_col($conexion,'progreso_cliente',['peso_despues','peso_fin','peso_post','peso']);
  $pc_cli   = pick_col($conexion,'progreso_cliente',['cliente_id','id_cliente']);
  $pc_gym   = pick_col($conexion,'progreso_cliente',['gimnasio_id','id_gimnasio']);

  if ($pc_peso && $pc_cli && $pc_gym) {
    $sql = "SELECT `$pc_peso` AS p FROM `progreso_cliente` WHERE `$pc_cli`=? AND `$pc_gym`=? ";
    if ($pc_fecha) $sql .= "ORDER BY `$pc_fecha` DESC ";
    elseif (db_has_col($conexion,'progreso_cliente','id')) $sql .= "ORDER BY `id` DESC ";
    $sql .= "LIMIT 1";
    if ($st = @$conexion->prepare($sql)) {
      $st->bind_param("ii", $cliente_id, $gimnasio_id);
      if ($st->execute()) {
        if ($r = $st->get_result()->fetch_assoc()) $peso_ref = (float)($r['p'] ?? 0);
      }
      $st->close();
    }
  }
}
if (!$peso_ref || $peso_ref <= 0) {
  $peso_ref = isset($cliente['peso']) ? (float)$cliente['peso'] : 70.0; // fallback
}

/* ----------------- Objetivos (integración con asistente) ----------------- */
$imc = ($altura_m > 0) ? round($peso_ref / ($altura_m*$altura_m), 1) : 0.0;
$objetivo = strtolower(trim((string)($_GET['objetivo'] ?? '')));
if (!in_array($objetivo, ['bajar peso','mantener','subir peso'], true)) {
  if     ($imc >= 25)  $objetivo = 'bajar peso';
  elseif ($imc < 18.5) $objetivo = 'subir peso';
  else                 $objetivo = 'mantener';
}

$agua_l = max(1.5, round($peso_ref * 0.035, 1)); // 35 ml/kg
$prot_gkg = ($objetivo === 'bajar peso') ? 1.6 : (($objetivo === 'subir peso') ? 2.0 : 1.4);
$proteinas_obj = (int)round($peso_ref * $prot_gkg);

/* Calorías objetivo (aprox): 30 kcal/kg con ajuste por objetivo */
$kcal_base = (int)round($peso_ref * 30);
$kcal_obj  = $kcal_base + (($objetivo === 'subir peso') ? +300 : (($objetivo === 'bajar peso') ? -400 : 0));

/* ----------------- Procesar imagen -> Gemini ----------------- */
/* 👇 PONÉ TU API KEY ACÁ */
$apiKey = 'AIzaSyDVMv4gliTqbrHqdgNcql7P8eP8jQL7Iwo';

$resultado_modelo = '';
$error_modelo = '';
$nombre_detectado = 'Comida detectada';
$kcal_detectadas  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imagen_base64'])) {
  $base64 = (string)$_POST['imagen_base64'];
  $mime   = (stripos($base64, 'image/png') !== false) ? 'image/png' : 'image/jpeg';
  $payload_b64 = preg_replace('#^data:image/[^;]+;base64,#', '', $base64);

  if (!$apiKey) {
    $error_modelo = "⚠️ Falta configurar la API Key de Gemini. Editá \$apiKey en el archivo.";
  } else {
    // IMPORTANTE: claves en snake_case (inline_data, mime_type)
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
    $json_payload = json_encode([
      "contents" => [[
        "parts" => [
          ["inline_data" => ["mime_type" => $mime, "data" => $payload_b64]],
          ["text" => "En español: indica qué comida es y su valor nutricional. Devuelve una sola oración con el nombre y las calorías aproximadas (kcal)."]
        ]
      ]],
      "generationConfig" => [
        "temperature" => 0.4,
        "maxOutputTokens" => 150
      ]
    ]);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS     => $json_payload,
      CURLOPT_TIMEOUT        => 25
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
      $error_modelo = "⚠️ Error de conexión con Gemini: ".$err;
    } else {
      $data = json_decode($resp, true);
      if ($code === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $resultado_modelo = trim($data['candidates'][0]['content']['parts'][0]['text']);

        // extraer kcal
        if (preg_match('/(\d{2,5})\s?k?cal/i', $resultado_modelo, $m)) {
          $kcal_detectadas = (int)$m[1];
        }

        // nombre (primeras palabras)
        if (preg_match('/^(.{3,80}?)(?:\s+(?:contiene|tiene|aprox|aprox\.|≈))/iu', $resultado_modelo, $n)) {
          $nombre_detectado = trim($n[1]);
        } elseif (preg_match('/^([A-ZÁÉÍÓÚÑa-záéíóúñ ]{3,80})/u', $resultado_modelo, $n2)) {
          $nombre_detectado = trim($n2[1]);
        }
      } else {
        $error_modelo = "⚠️ No se pudo procesar la imagen (HTTP {$code}).";
      }
    }
  }
}

/* ----------------- Autodetección tabla/columnas: registro de comidas ----------------- */
$rc_table = null;
foreach (['registro_comidas','comidas','registro_dietas'] as $tb) {
  if (db_has_table($conexion,$tb)) { $rc_table = $tb; break; }
}
if (!$rc_table) $rc_table = 'registro_comidas'; // si no existe, luego creamos estándar

$cId     = db_has_table($conexion,$rc_table) ? pick_col($conexion,$rc_table,['id','comida_id','registro_id']) : null;
$cFecha  = db_has_table($conexion,$rc_table) ? pick_col($conexion,$rc_table,['fecha','fecha_registro','created_at']) : null;
$cNombre = db_has_table($conexion,$rc_table) ? pick_col($conexion,$rc_table,['comida','alimento','nombre','descripcion']) : null;
$cPorc   = db_has_table($conexion,$rc_table) ? pick_col($conexion,$rc_table,['porciones','cantidad','porcion']) : null;
$cKcal   = db_has_table($conexion,$rc_table) ? pick_col($conexion,$rc_table,['calorias','kcal','cal']) : null;
$cTotal  = db_has_table($conexion,$rc_table) ? pick_col($conexion,$rc_table,['total_calorias','calorias_total','kcal_total','total_kcal']) : null;
$cCli    = db_has_table($conexion,$rc_table) ? pick_col($conexion,$rc_table,['cliente_id','id_cliente']) : null;
$cGym    = db_has_table($conexion,$rc_table) ? pick_col($conexion,$rc_table,['gimnasio_id','id_gimnasio']) : null;

/* ----------------- Guardar comida ----------------- */
$mensaje_guardado = '';
if (isset($_POST['guardar']) && !empty($_POST['nombre']) && !empty($_POST['porciones']) && !empty($_POST['calorias'])) {
  $nombre = trim((string)$_POST['nombre']);
  $porc   = (float)$_POST['porciones'];
  $kcal   = (float)$_POST['calorias'];
  $total  = $porc * $kcal;

  // crear tabla estándar si no existe
  if (!db_has_table($conexion,$rc_table) || $rc_table === 'registro_comidas') {
    @$conexion->query("CREATE TABLE IF NOT EXISTS registro_comidas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      cliente_id INT NOT NULL,
      gimnasio_id INT NOT NULL,
      fecha DATE NOT NULL,
      comida VARCHAR(255) NOT NULL,
      porciones DECIMAL(6,2) NOT NULL,
      calorias DECIMAL(8,2) NOT NULL,
      total_calorias DECIMAL(10,2) NOT NULL,
      INDEX idx_cli_gym_fecha (cliente_id, gimnasio_id, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $rc_table = 'registro_comidas';
    $cId='id'; $cFecha='fecha'; $cNombre='comida'; $cPorc='porciones'; $cKcal='calorias';
    $cTotal='total_calorias'; $cCli='cliente_id'; $cGym='gimnasio_id';
  }

  if ($cNombre && (($cPorc && $cKcal) || $cTotal)) {
    $cols = []; $qs = []; $types=''; $vals=[];

    if ($cCli)   { $cols[]="`$cCli`";   $qs[]='?';        $types.='i'; $vals[]=$cliente_id; }
    if ($cGym)   { $cols[]="`$cGym`";   $qs[]='?';        $types.='i'; $vals[]=$gimnasio_id; }
    if ($cFecha) { $cols[]="`$cFecha`"; $qs[]='CURDATE()'; } // sin placeholder
    $cols[]="`$cNombre`"; $qs[]='?'; $types.='s'; $vals[]=$nombre;

    if ($cPorc)  { $cols[]="`$cPorc`";  $qs[]='?'; $types.='d'; $vals[]=$porc; }
    if ($cKcal)  { $cols[]="`$cKcal`";  $qs[]='?'; $types.='d'; $vals[]=$kcal; }
    if ($cTotal) { $cols[]="`$cTotal`"; $qs[]='?'; $types.='d'; $vals[]=$total; }

    $sql = "INSERT INTO `{$rc_table}` (".implode(',',$cols).") VALUES (".implode(',',$qs).")";
    if ($st = $conexion->prepare($sql)) {
      if ($types!=='') { $st->bind_param($types, ...$vals); }
      if ($st->execute()) $mensaje_guardado = "✅ Comida registrada correctamente.";
      $st->close();
    }
  } else {
    $mensaje_guardado = "⚠️ No se pudo registrar: faltan columnas mínimas en la tabla.";
  }
}

/* ----------------- Totales de hoy ----------------- */
$tz = new DateTimeZone('America/Argentina/San_Luis');
$hoy = (new DateTime('today', $tz))->format('Y-m-d');

$consumidas_hoy = 0;
if (db_has_table($conexion,$rc_table) && $cNombre) {
  if     ($cTotal)            { $exprTotal = "`$cTotal`"; }
  elseif ($cPorc && $cKcal)   { $exprTotal = "(`$cPorc` * `$cKcal`)"; }
  else                        { $exprTotal = "0"; }

  $sql = "SELECT COALESCE(SUM($exprTotal),0) AS t FROM `{$rc_table}` WHERE 1";
  $types=''; $vals=[];
  if ($cCli)   { $sql.=" AND `$cCli`=?";   $types.='i'; $vals[]=$cliente_id; }
  if ($cGym)   { $sql.=" AND `$cGym`=?";   $types.='i'; $vals[]=$gimnasio_id; }
  if ($cFecha) { $sql.=" AND `$cFecha`=?"; $types.='s'; $vals[]=$hoy; }

  if ($st = $conexion->prepare($sql)) {
    if ($types!=='') $st->bind_param($types, ...$vals);
    if ($st->execute()) { $r = $st->get_result()->fetch_assoc(); $consumidas_hoy = (int)($r['t'] ?? 0); }
    $st->close();
  }
}

/* Quemadas hoy desde progreso_cliente */
$quemadas_hoy = 0;
if (db_has_table($conexion, 'progreso_cliente')) {
  $cFechaPC = pick_col($conexion, 'progreso_cliente', ['fecha','created_at']);
  $cCalPC   = pick_col($conexion, 'progreso_cliente', ['calorias_estimadas','calorias_quemadas','kcal']);
  $cCliPC   = pick_col($conexion, 'progreso_cliente', ['cliente_id','id_cliente']);
  $cGymPC   = pick_col($conexion, 'progreso_cliente', ['gimnasio_id','id_gimnasio']);

  if ($cFechaPC && $cCalPC && $cCliPC && $cGymPC) {
    $sqlQ = "SELECT COALESCE(SUM(`$cCalPC`),0) AS t FROM `progreso_cliente` WHERE `$cCliPC`=? AND `$cGymPC`=? AND `$cFechaPC`=?";
    if ($st = @$conexion->prepare($sqlQ)) {
      $st->bind_param("iis", $cliente_id, $gimnasio_id, $hoy);
      if ($st->execute()) { $r = $st->get_result()->fetch_assoc(); $quemadas_hoy = (int)($r['t'] ?? 0); }
      $st->close();
    }
  }
}

/* Últimos registros de hoy */
$comidas_hoy = [];
if (db_has_table($conexion,$rc_table) && $cNombre) {
  $parts = [];
  $parts[] = $cNombre ? "`$cNombre` AS comida" : "'(sin nombre)' AS comida";
  $parts[] = $cPorc   ? "`$cPorc` AS porciones" : "NULL AS porciones";
  $parts[] = $cKcal   ? "`$cKcal` AS calorias"  : "NULL AS calorias";
  if     ($cTotal)             $parts[] = "`$cTotal` AS total_calorias";
  elseif ($cPorc && $cKcal)    $parts[] = "(`$cPorc` * `$cKcal`) AS total_calorias";
  else                         $parts[] = "NULL AS total_calorias";
  if ($cId) $parts[]="`$cId` AS id";

  $sql = "SELECT ".implode(", ",$parts)." FROM `{$rc_table}` WHERE 1";
  $types=''; $vals=[];
  if ($cCli)   { $sql.=" AND `$cCli`=?";   $types.='i'; $vals[]=$cliente_id; }
  if ($cGym)   { $sql.=" AND `$cGym`=?";   $types.='i'; $vals[]=$gimnasio_id; }
  if ($cFecha) { $sql.=" AND `$cFecha`=?"; $types.='s'; $vals[]=$hoy; }
  if     ($cId)    $sql.=" ORDER BY `$cId` DESC";
  elseif ($cFecha) $sql.=" ORDER BY `$cFecha` DESC";
  $sql.=" LIMIT 10";

  if ($st = $conexion->prepare($sql)) {
    if ($types!=='') $st->bind_param($types, ...$vals);
    if ($st->execute()) {
      $res = $st->get_result();
      while ($r = $res->fetch_assoc()) $comidas_hoy[] = $r;
    }
    $st->close();
  }
}

$balance_neto = $consumidas_hoy - $quemadas_hoy;
$diff_vs_obj  = $consumidas_hoy - $kcal_obj;
$estado_neto  = ($balance_neto > 250) ? 'Superávit' : (($balance_neto < -250) ? 'Déficit' : 'Equilibrado');

/* ----------------- HTML ----------------- */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>📷 Escanear comida (IA)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>/* (estilos iguales a tu versión) */</style>
</head>
<body>
<!-- (HTML igual al tuyo; omitido por brevedad) -->
</body>
</html>
