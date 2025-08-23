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
$apiKey = 'TU_API_KEY'; // ⚠️ Reemplazá por tu API key real de Gemini
$resultado_modelo = '';
$error_modelo = '';
$nombre_detectado = 'Comida detectada';
$kcal_detectadas  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imagen_base64'])) {
  $base64 = (string)$_POST['imagen_base64'];
  $mime   = (stripos($base64, 'image/png') !== false) ? 'image/png' : 'image/jpeg';
  $payload_b64 = preg_replace('#^data:image/[^;]+;base64,#', '', $base64);

  if (!$apiKey || $apiKey === 'TU_API_KEY') {
    $error_modelo = "⚠️ Falta configurar la API Key de Gemini. Editá \$apiKey en el archivo.";
  } else {
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
    $json_payload = json_encode([
      "contents" => [[
        "parts" => [
          ["inlineData" => ["mimeType" => $mime, "data" => $payload_b64]],
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
      CURLOPT_POSTFIELDS     => $json_payload
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($code === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
      $resultado_modelo = trim($data['candidates'][0]['content']['parts'][0]['text']);

      // extraer kcal
      if (preg_match('/(\d{2,5})\s?k?cal/i', $resultado_modelo, $m)) {
        $kcal_detectadas = (int)$m[1];
      }

      // nombre (primeras palabras antes de "contiene"/"tiene"/"aprox")
      if (preg_match('/^(.{3,80}?)(?:\s+(?:contiene|tiene|aprox|aprox\.|≈))/iu', $resultado_modelo, $n)) {
        $nombre_detectado = trim($n[1]);
      } elseif (preg_match('/^([A-ZÁÉÍÓÚÑa-záéíóúñ ]{3,80})/u', $resultado_modelo, $n2)) {
        $nombre_detectado = trim($n2[1]);
      }
    } else {
      $error_modelo = "⚠️ No se pudo procesar la imagen. Probá con otra foto.";
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

/* ----------------- Guardar comida (dinámico por columnas) ----------------- */
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

  // Debe existir al menos columna de nombre y de porciones/calorías o total
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

/* ----------------- Totales de hoy (dinámico) ----------------- */
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

/* Quemadas hoy desde progreso_cliente (calorias_est/quemadas) */
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

/* Últimos registros de hoy (dinámico) */
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

$balance_neto = $consumidas_hoy - $quemadas_hoy;       // ingerido - quemado
$diff_vs_obj  = $consumidas_hoy - $kcal_obj;           // ingerido vs objetivo
$estado_neto  = ($balance_neto > 250) ? 'Superávit' : (($balance_neto < -250) ? 'Déficit' : 'Equilibrado');

/* ----------------- HTML ----------------- */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>📷 Escanear comida (IA)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0b0b0b; --card:#111; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12); }
    *{box-sizing:border-box}
    body{ margin:0; background:var(--bg); color:var(--fg); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; padding:16px; text-align:center }
    .container{ max-width:1100px; margin:0 auto }
    h2{ margin:8px 0 12px }
    .row{ display:grid; grid-template-columns: 1fr; gap:12px; text-align:left }
    @media (min-width:900px){ .row{ grid-template-columns: 1fr 1fr; } }
    .card{ background:var(--card); border:1px solid var(--border); border-radius:16px; padding:14px }
    video, canvas, img.preview{ width:100%; max-height:420px; object-fit:cover; border-radius:12px; border:1px solid var(--border) }
    .controls{ display:flex; gap:8px; flex-wrap:wrap; justify-content:center; margin-top:8px }
    .btn{ padding:10px 14px; border-radius:12px; border:1px solid var(--border); background:#1a1d24; color:#fff; font-weight:700; cursor:pointer; text-decoration:none }
    .btn-primary{ background:var(--acc); color:#111; border:none }
    .grid4{ display:grid; gap:10px; grid-template-columns:1fr 1fr; }
    @media (min-width:760px){ .grid4{ grid-template-columns: repeat(4,1fr); } }
    .kpi{ background:#1a1d24; border:1px solid var(--border); border-radius:12px; padding:10px; text-align:center }
    .kpi b{ display:block; font-size:18px; margin-top:4px }
    .muted{ color:var(--muted) }
    table{ width:100%; border-collapse:collapse; font-size:14px; margin-top:10px }
    th,td{ padding:10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:center }
    th{ color:var(--muted); font-weight:700; background:#0f1118 }
    .progress{ height:10px; background:#1f2430; border-radius:999px; overflow:hidden; border:1px solid var(--border) }
    .progress > span{ display:block; height:100%; background:var(--acc); width:0% }
    .pill{ display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid var(--border); font-size:12px; margin-left:6px }
    label{ font-weight:700 }
    input[type="number"], input[type="text"]{ width:100%; padding:10px; border-radius:12px; border:1px solid var(--border); background:#1a1d24; color:#fff; }
    .form-mini{ display:grid; gap:8px; grid-template-columns: 1fr 1fr; margin-top:8px }
    .form-mini .full{ grid-column: 1 / -1; }
  </style>
</head>
<body>
<div class="container">
  <h2>📷 Escanear comida con IA <span class="pill"><?= h($objetivo) ?></span></h2>

  <!-- KPIs / Integración con asistente -->
  <section class="grid4" style="margin-bottom:10px">
    <div class="kpi">IMC<b><?= num($imc,1) ?></b></div>
    <div class="kpi">Objetivo ingesta<b><?= (int)$kcal_obj ?> kcal</b></div>
    <div class="kpi">Proteínas<b><?= (int)$proteinas_obj ?> g/día</b></div>
    <div class="kpi">Agua<b><?= num($agua_l,1) ?> L/día</b></div>
  </section>

  <!-- Resumen diario -->
  <section class="card" style="margin-bottom:12px; text-align:left">
    <h3 style="margin:0 0 8px">📅 Hoy</h3>
    <div class="grid4">
      <div class="kpi">Ingeridas<b><?= (int)$consumidas_hoy ?> kcal</b></div>
      <div class="kpi">Quemadas<b><?= (int)$quemadas_hoy ?> kcal</b></div>
      <div class="kpi">Balance neto<b><?= (int)$balance_neto ?> kcal</b></div>
      <div class="kpi">Estado<b><?= h($estado_neto) ?></b></div>
    </div>
    <div style="margin-top:10px">
      <div class="muted" style="display:flex; justify-content:space-between">
        <span>Progreso de ingesta</span><span><?= (int)$consumidas_hoy ?> / <?= (int)$kcal_obj ?> kcal</span>
      </div>
      <?php $pct = max(0, min(100, $kcal_obj>0 ? round($consumidas_hoy*100/$kcal_obj) : 0)); ?>
      <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
    </div>
  </section>

  <div class="row">
    <!-- Cámara / captura -->
    <section class="card">
      <h3 style="margin:0 0 6px">📸 Cámara</h3>
      <video id="video" autoplay playsinline muted></video>
      <div class="controls">
        <button class="btn btn-primary" onclick="capturar()">Tomar foto</button>
        <!-- Fallback iOS / subir archivo -->
        <label class="btn">
          Subir imagen
          <input type="file" id="fileInput" accept="image/*" capture="environment" style="display:none">
        </label>
      </div>
      <canvas id="canvas" style="display:none"></canvas>
      <form method="POST" id="form_enviar" style="display:none;">
        <input type="hidden" name="imagen_base64" id="imagen_base64">
        <button type="submit" class="btn btn-primary" style="margin-top:8px">Enviar imagen</button>
      </form>
    </section>

    <!-- Resultado IA + guardar -->
    <section class="card">
      <h3 style="margin:0 0 6px">🔎 Resultado</h3>

      <?php if ($resultado_modelo): ?>
        <p><?= nl2br(h($resultado_modelo)) ?></p>
      <?php elseif ($error_modelo): ?>
        <p style="color:#ff6b6b"><?= h($error_modelo) ?></p>
      <?php else: ?>
        <p class="muted">Tomá una foto o subí una imagen para analizar la comida.</p>
      <?php endif; ?>

      <hr style="border-color:#222">

      <h4 style="margin:6px 0">💾 Registrar manualmente</h4>
      <form method="POST" class="form-mini" autocomplete="off">
        <input type="hidden" name="guardar" value="1">
        <div class="full">
          <label>Comida</label>
          <input type="text" name="nombre" placeholder="Ej: Ensalada de pollo" value="<?= h($nombre_detectado) ?>" required>
        </div>
        <div>
          <label>Porciones</label>
          <input type="number" name="porciones" min="0.1" step="0.1" value="1" required>
        </div>
        <div>
          <label>Calorías por porción</label>
          <input type="number" name="calorias" min="1" step="1" value="<?= (int)$kcal_detectadas ?>" required>
        </div>
        <div class="full" style="text-align:right">
          <button type="submit" class="btn btn-primary">Guardar comida</button>
        </div>
      </form>

      <?php if (!empty($mensaje_guardado)): ?>
        <p style="color:#22c55e; margin-top:6px"><?= h($mensaje_guardado) ?></p>
      <?php endif; ?>
    </section>
  </div>

  <!-- Lista del día -->
  <section class="card" style="margin-top:12px; text-align:left">
    <h3 style="margin:0 0 6px">🍽️ Comidas de hoy</h3>
    <table>
      <thead>
        <tr>
          <th>Comida</th>
          <th>Porciones</th>
          <th>Cal/porción</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($comidas_hoy)): ?>
          <?php foreach ($comidas_hoy as $c): ?>
            <tr>
              <td><?= h($c['comida']) ?></td>
              <td><?= num($c['porciones'],2) ?></td>
              <td><?= is_null($c['calorias']) ? '-' : (num($c['calorias'],0).' kcal') ?></td>
              <td><strong><?= is_null($c['total_calorias']) ? '-' : (num($c['total_calorias'],0).' kcal') ?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4" class="muted">Aún no registraste comidas hoy.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-top:8px">
      Tip: el objetivo de ingesta se calcula con tu peso y objetivo (<?= h($objetivo) ?>). Podés cambiarlo agregando en la URL: <code>?objetivo=bajar%20peso</code> | <code>?objetivo=mantener</code> | <code>?objetivo=subir%20peso</code>.
    </p>
  </section>
</div>

<script>
  // ----------- Cámara -----------
  const video  = document.getElementById('video');
  const canvas = document.getElementById('canvas');
  const imgB64 = document.getElementById('imagen_base64');
  const formEnviar = document.getElementById('form_enviar');
  const fileInput  = document.getElementById('fileInput');

  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: "environment" } }, audio:false })
      .then(stream => { video.srcObject = stream; })
      .catch(err => { console.warn("No se pudo acceder a la cámara:", err); });
  }

  function capturar(){
    if (!video.videoWidth) { alert("Esperá a que cargue la cámara..."); return; }
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    const base64 = canvas.toDataURL('image/jpeg', 0.9);
    imgB64.value = base64;
    formEnviar.style.display = 'block';
    formEnviar.scrollIntoView({ behavior: 'smooth' });
  }

  // Fallback subir archivo
  fileInput.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      imgB64.value = reader.result;
      formEnviar.style.display = 'block';
      formEnviar.scrollIntoView({ behavior:'smooth' });
    };
    reader.readAsDataURL(file);
  });
</script>
</body>
</html>
