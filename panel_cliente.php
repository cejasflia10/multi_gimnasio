<?php
// panel_cliente.php — versión moderna + PROMOS "flash" + NOTIFICACIONES (rutinas/planes)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

if ($cliente_id === 0 || $gimnasio_id === 0) {
    header("Location: cliente_acceso.php");
    exit;
}

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function col_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $sql = "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  $rs = $db->query($sql);
  return $rs && $rs->num_rows > 0;
}
function fmt_bytes($b){
  $b=(float)$b; $u=['B','KB','MB','GB','TB']; $i=0;
  while($b>=1024 && $i<count($u)-1){ $b/=1024; $i++; }
  return number_format($b, ($i>1?2:0), ',', '.') . ' ' . $u[$i];
}

/* Polyfill str_starts_with para PHP < 8 */
if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle) {
    return $needle === '' || strpos($haystack, $needle) === 0;
  }
}

/* ===== Resolver de foto de cliente (robusto) ===== */
function resolve_cliente_foto(array $cli): string {
  // 1) Si hay base64 válida, usarla
  $b64 = (string)($cli['foto_base64'] ?? '');
  if ($b64 !== '' && str_starts_with($b64, 'data:image')) return $b64;

  // 2) Candidatos de columna (foto_path primero)
  $candidatos = [];
  foreach (['foto_path','foto_url','foto','avatar','imagen','perfil_foto'] as $k) {
    if (!empty($cli[$k])) $candidatos[] = (string)$cli[$k];
  }

  // 3) Probar cada candidato
  $carpetas = [
    '', // por si el valor ya incluye la subcarpeta correcta
    'fotos_clientes',
    'uploads/clientes',   // ✅ agregado
    'uploads',
    'public/uploads',
    'img',
    'images',
    'fotos',
    'clientes',
    'media',
  ];

  foreach ($candidatos as $cand) {
    $cand = trim($cand);

    // URL absoluta
    if (preg_match('#^https?://#i', $cand)) return $cand;

    // Si ya viene con subcarpeta (ej: uploads/clientes/nico.jpg), probar tal cual
    if (strpos($cand, '/') !== false) {
      $abs = __DIR__ . '/' . $cand;
      if (is_file($abs)) {
        $mtime = @filemtime($abs) ?: time();
        $parts = array_map('rawurlencode', array_filter(explode('/', $cand), 'strlen'));
        return implode('/', $parts) . '?v=' . $mtime;
      }
    }

    // Probar en carpetas conocidas
    foreach ($carpetas as $dir) {
      $rel = ($dir ? ($dir . '/') : '') . $cand;
      $abs = __DIR__ . '/' . $rel;
      if (is_file($abs)) {
        $mtime = @filemtime($abs) ?: time();
        $parts = array_map('rawurlencode', array_filter(explode('/', $rel), 'strlen'));
        return implode('/', $parts) . '?v=' . $mtime;
      }
    }
  }

  // 4) Fallback: default local si existe
  $default = __DIR__ . '/fotos_clientes/default.png';
  if (is_file($default)) {
    $v = @filemtime($default) ?: time();
    return 'fotos_clientes/default.png?v='.$v;
  }

  // 5) Fallback final: data-URI SVG (círculo gris con iniciales)
  $nombre = trim(($cli['apellido'] ?? '').' '.($cli['nombre'] ?? ''));
  $inic   = '';
  foreach (explode(' ', $nombre) as $w) { $w = trim($w); if ($w!=='') { $inic .= mb_strtoupper(mb_substr($w,0,1,'UTF-8'),'UTF-8'); } }
  $inic = $inic !== '' ? $inic : '👤';
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">
    <rect width="200" height="200" rx="100" ry="100" fill="#2b2f36"/>
    <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Inter, Arial" font-size="72" fill="#f5c542">'.$inic.'</text>
  </svg>';
  return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
}

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Validar cliente ===== */
$cliente = null;
if ($stmt = $conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1")) {
    $stmt->bind_param("ii", $cliente_id, $gimnasio_id);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$cliente) {
    header("Location: cliente_acceso.php");
    exit;
}

/* ===== Completar Datos Físicos ===== */
if ((int)($cliente['datos_completos'] ?? 0) === 0) {
    $mensaje = "";
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_datos_fisicos'])) {
        $peso         = trim((string)($_POST['peso'] ?? ''));
        $altura       = trim((string)($_POST['altura'] ?? ''));
        $remera       = trim((string)($_POST['talle_remera'] ?? ''));
        $pantalon     = trim((string)($_POST['talle_pantalon'] ?? ''));
        $calzado      = trim((string)($_POST['talle_calzado'] ?? ''));
        $observaciones= trim((string)($_POST['observaciones'] ?? ''));
        $enfermedades = trim((string)($_POST['enfermedades'] ?? ''));
        $medicacion   = trim((string)($_POST['medicacion'] ?? ''));
        $fecha        = date('Y-m-d');

        if ($stmtInsert = $conexion->prepare("
            INSERT INTO datos_fisicos 
              (cliente_id, gimnasio_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, observaciones, enfermedades, medicacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")) {
            $stmtInsert->bind_param(
              "iisssssssss",
              $cliente_id, $gimnasio_id, $fecha, $peso, $altura, $remera, $pantalon, $calzado, $observaciones, $enfermedades, $medicacion
            );
            if ($stmtInsert->execute()) {
                $conexion->query("UPDATE clientes SET datos_completos=1 WHERE id={$cliente_id} AND gimnasio_id={$gimnasio_id}");
                header("Location: panel_cliente.php");
                exit;
            } else {
                $mensaje = "❌ Error al guardar los datos. Intente nuevamente.";
            }
            $stmtInsert->close();
        } else {
            $mensaje = "❌ Error interno al preparar el guardado.";
        }
    }
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8" />
      <title>Completar Datos Físicos</title>
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <style>
        :root{ --bg:#0b0b0b; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12); }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial}
        .wrap{min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:100%;max-width:420px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:20px}
        h2{margin:0 0 12px;font:800 22px/1.2 Inter,system-ui} p{margin:0 0 16px;color:var(--muted)}
        label{display:block;margin:10px 0 6px;font-weight:700;font-size:14px}
        input,textarea{width:100%;padding:10px;border-radius:12px;border:1px solid var(--border);background:#0f1115;color:var(--fg);font-size:14px}
        textarea{min-height:70px}
        .btn{width:100%;margin-top:12px;padding:12px;border:none;border-radius:14px;background:var(--acc);color:#111;font-weight:800;cursor:pointer}
        .msg{margin-bottom:10px;color:#ff6b6b;font-weight:700}
      </style>
    </head>
    <body>
      <div class="wrap">
        <form class="card" method="POST" autocomplete="off">
          <h2>📋 Completar Datos Físicos</h2>
          <p>Completá tus medidas y observaciones para personalizar tus entrenamientos.</p>
          <?php if (!empty($mensaje)): ?><div class="msg"><?= h($mensaje) ?></div><?php endif; ?>
          <input type="hidden" name="guardar_datos_fisicos" value="1" />
          <label>Peso (kg)</label><input name="peso" required />
          <label>Altura (cm)</label><input name="altura" required />
          <label>Talle Remera</label><input name="talle_remera" />
          <label>Talle Pantalón</label><input name="talle_pantalon" />
          <label>Talle Calzado</label><input name="talle_calzado" />
          <label>Observaciones</label><textarea name="observaciones"></textarea>
          <label>Enfermedades (si tiene)</label><textarea name="enfermedades"></textarea>
          <label>Medicaciones (si toma)</label><textarea name="medicacion"></textarea>
          <button class="btn" type="submit">Guardar datos</button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

/* ===== Datos base para panel ===== */
$cliente_nombre = trim(($cliente['apellido'] ?? '').' '.($cliente['nombre'] ?? ''));
$tz   = new DateTimeZone('America/Argentina/San_Luis');
$hoyD = new DateTime('today', $tz);
$hoy  = $hoyD->format('Y-m-d');
$fecha_filtro = isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha']) ? $_GET['fecha'] : $hoy;

/* ===== Membresía ===== */
$membresia = null;
if ($stmtM1 = $conexion->prepare("
  SELECT clases_disponibles, fecha_vencimiento
  FROM membresias
  WHERE cliente_id=? AND gimnasio_id=? AND fecha_vencimiento >= ?
  ORDER BY fecha_vencimiento ASC
  LIMIT 1
")) {
  $stmtM1->bind_param("iis", $cliente_id, $gimnasio_id, $hoy);
  $stmtM1->execute();
  $membresia = $stmtM1->get_result()->fetch_assoc();
  $stmtM1->close();
}
if (!$membresia && $stmtM2 = $conexion->prepare("
  SELECT clases_disponibles, fecha_vencimiento
  FROM membresias
  WHERE cliente_id=? AND gimnasio_id=?
  ORDER BY fecha_vencimiento DESC
  LIMIT 1
")) {
  $stmtM2->bind_param("ii", $cliente_id, $gimnasio_id);
  $stmtM2->execute();
  $membresia = $stmtM2->get_result()->fetch_assoc();
  $stmtM2->close();
}

/* ===== Foto (usando el resolver robusto) ===== */
$foto_url = resolve_cliente_foto($cliente);

/* ===== Alertas membresía ===== */
$alerta_membresia_html = '';
if ($membresia) {
  $clases    = max(0, (int)($membresia['clases_disponibles'] ?? 0));
  $vto_raw   = (string)($membresia['fecha_vencimiento'] ?? '');
  $vtoD      = DateTime::createFromFormat('Y-m-d', $vto_raw, $tz) ?: new DateTime($vto_raw ?: 'now', $tz);
  $diffSigned= (int)$hoyD->diff($vtoD)->format('%r%a');
  $dias_rest = max(0, $diffSigned);

  if ($clases <= 2 || $dias_rest <= 3) {
    $t_clase = ($clases === 1 ? 'clase' : 'clases');
    $t_dia   = ($dias_rest === 1 ? 'día' : 'días');
    $estado  = ($diffSigned < 0) ? 'Vencida' : "Vence en <strong>{$dias_rest} {$t_dia}</strong>";
    $alerta_membresia_html = '
    <div class="alerta alerta-amarilla">
      <div class="ico">⚠️</div>
      <div>
        <strong>¡Atención!</strong> Te quedan <strong>'.h((string)$clases).'</strong> '.$t_clase.'.<br>
        '.$estado.' (vence: <strong>'.h($vtoD->format('Y-m-d')).'</strong>)
      </div>
    </div>';
  }
} else {
  $alerta_membresia_html = '
  <div class="alerta alerta-gris">
    No encontramos una membresía registrada. ¿Querés activar un plan?
  </div>';
}

/* ===== PROMOCIONES (idéntico a tu lógica) ===== */
$promos = [];
$hasCols = [
  'gimnasio_id' => col_exists($conexion,'promociones','gimnasio_id'),
  'titulo'      => col_exists($conexion,'promociones','titulo'),
  'descripcion' => col_exists($conexion,'promociones','descripcion'),
  'imagen_url'  => col_exists($conexion,'promociones','imagen_url') || col_exists($conexion,'promociones','imagen'),
  'link_url'    => col_exists($conexion,'promociones','link_url'),
  'color_fondo' => col_exists($conexion,'promociones','color_fondo'),
  'color_texto' => col_exists($conexion,'promociones','color_texto'),
  'fecha_inicio'=> col_exists($conexion,'promociones','fecha_inicio'),
  'fecha_fin'   => col_exists($conexion,'promociones','fecha_fin'),
  'prioridad'   => col_exists($conexion,'promociones','prioridad'),
  'activo'      => col_exists($conexion,'promociones','activo'),
];

$colsSelect = ['id'];
foreach (['titulo','descripcion','link_url','color_fondo','color_texto'] as $c) {
  if ($hasCols[$c]) $colsSelect[] = $c;
}
if ($hasCols['imagen_url']) {
  if (!col_exists($conexion,'promociones','imagen_url') && col_exists($conexion,'promociones','imagen')) {
    $colsSelect[] = "imagen AS imagen_url";
  } else {
    $colsSelect[] = "imagen_url";
  }
}
if ($hasCols['fecha_inicio']) $colsSelect[] = 'fecha_inicio';
if ($hasCols['fecha_fin'])    $colsSelect[] = 'fecha_fin';
if ($hasCols['prioridad'])    $colsSelect[] = 'prioridad';
if ($hasCols['activo'])       $colsSelect[] = 'activo';
if ($hasCols['gimnasio_id'])  $colsSelect[] = 'gimnasio_id';

$select = implode(',', $colsSelect);
$where  = [];
if ($hasCols['gimnasio_id']) $where[] = "gimnasio_id = {$gimnasio_id}";
if ($hasCols['activo'])      $where[] = "activo = 1";
if ($hasCols['fecha_inicio'])$where[] = "(fecha_inicio IS NULL OR fecha_inicio <= '{$hoy}')";
if ($hasCols['fecha_fin'])   $where[] = "(fecha_fin IS NULL OR fecha_fin >= '{$hoy}')";

$order = "ORDER BY ";
$orderParts = [];
if ($hasCols['activo'])    $orderParts[] = "activo DESC";
if ($hasCols['prioridad']) $orderParts[] = "prioridad DESC";
if ($hasCols['fecha_fin']) $orderParts[] = "fecha_fin DESC";
$orderParts[] = "id DESC";
$order .= implode(', ', $orderParts);

$sqlPromos = "SELECT {$select} FROM promociones " .
             (empty($where) ? "" : ("WHERE ".implode(' AND ', $where)." ")).$order;

$rsP = $conexion->query($sqlPromos);
if ($rsP) {
  while($p = $rsP->fetch_assoc()){
    if (empty($p['color_fondo'])) $p['color_fondo'] = '#111111';
    if (empty($p['color_texto'])) $p['color_texto'] = '#FFD700';
    $img = trim((string)($p['imagen_url'] ?? ''));
    if ($img !== '') {
      if (!preg_match('#^https?://#i', $img)) {
        $localPath = __DIR__ . '/promos/' . $img;
        $img = is_file($localPath) ? ('promos/' . rawurlencode($img)) : '';
      }
    }
    $p['imagen_resuelta'] = $img;
    $promos[] = $p;
  }
  $rsP->free();
}

/* ====== NOTIFICACIONES (rutinas/planes) ====== */
$conexion->query("
  CREATE TABLE IF NOT EXISTS rutinas_vistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    rutina_id INT NOT NULL,
    visto_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cli_rut (cliente_id, rutina_id),
    INDEX idx_cli (cliente_id),
    INDEX idx_rut (rutina_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* Marcar como visto (POST al mismo archivo) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_visto'], $_POST['rutina_id'])) {
  $rid = (int)$_POST['rutina_id'];
  $okRut = false;
  if ($st = $conexion->prepare("SELECT 1 FROM rutinas_clientes WHERE id=? AND cliente_id=? AND gimnasio_id=? LIMIT 1")) {
    $st->bind_param('iii', $rid, $cliente_id, $gimnasio_id);
    $st->execute(); $st->store_result(); $okRut = $st->num_rows > 0; $st->close();
  }
  if ($okRut) {
    if ($st2 = $conexion->prepare("
      INSERT INTO rutinas_vistas (cliente_id, rutina_id) VALUES (?, ?)
      ON DUPLICATE KEY UPDATE visto_en = CURRENT_TIMESTAMP
    ")) {
      $st2->bind_param('ii', $cliente_id, $rid);
      $st2->execute(); $st2->close();
    }
  }
  header("Location: panel_cliente.php");
  exit;
}

/* Traer últimas 10 rutinas/planes para este cliente con flag visto */
$notis = [];
if ($stmtN = $conexion->prepare("
  SELECT r.id, r.nombre_archivo, r.url_archivo, r.extension, r.tamano_bytes, r.creado_en,
         COALESCE(CONCAT(p.apellido, ', ', p.nombre), CONCAT('ID ', r.profesor_id)) AS profesor,
         CASE WHEN v.id IS NULL THEN 0 ELSE 1 END AS visto
  FROM rutinas_clientes r
  LEFT JOIN profesores p ON p.id = r.profesor_id
  LEFT JOIN rutinas_vistas v ON v.rutina_id = r.id AND v.cliente_id = ?
  WHERE r.gimnasio_id = ? AND r.cliente_id = ?
  ORDER BY r.creado_en DESC, r.id DESC
  LIMIT 10
")) {
  $stmtN->bind_param('iii', $cliente_id, $gimnasio_id, $cliente_id);
  $stmtN->execute();
  $notis = $stmtN->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtN->close();
}

/* ===== Menú cliente ===== */
include __DIR__ . '/menu_cliente.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Panel del Cliente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>/* (estilos iguales a tu versión) */</style>
</head>
<body>
  <div class="container">
    <!-- Promos, encabezado, tarjetas, etc... -->
    <!-- (contenido igual al que ya tenías, sin cambios) -->
  </div>

  <script>
  /* Slider de promos y reservas (igual a tu versión) */
  </script>
</body>
</html>
