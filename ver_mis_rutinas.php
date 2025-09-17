<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

// (Opcional) menú del cliente si lo tenés
if (file_exists(__DIR__ . '/menu_cliente.php')) {
  require_once __DIR__ . '/menu_cliente.php';
}

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ====== Guards de sesión (lado cliente) ====== */
$cliente_id  = $_SESSION['cliente_id']  ?? 0;   // ajustá si usás otro nombre de variable
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($cliente_id <= 0 || $gimnasio_id <= 0) {
  http_response_code(403);
  exit('❌ Sesión inválida. Iniciá sesión como alumno.');
}

/* ====== Helpers ====== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fmt_bytes($b){
  $b=(float)$b; $u=['B','KB','MB','GB','TB']; $i=0;
  while($b>=1024 && $i<count($u)-1){ $b/=1024; $i++; }
  return number_format($b, ($i>1?2:0), ',', '.') . ' ' . $u[$i];
}
function is_img($ext){ return in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'], true); }
function is_pdf($ext){ return strtolower($ext) === 'pdf'; }

/* ====== Filtros (búsqueda simple/rango de fechas) ====== */
$q = trim($_GET['q'] ?? '');
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');

$params = [$gimnasio_id, $cliente_id];
$wheres = ['r.gimnasio_id = ?', 'r.cliente_id = ?'];
$types  = 'ii';

if ($q !== '') {
  $wheres[] = '(r.nombre_archivo LIKE CONCAT("%",?,"%") OR r.extension LIKE CONCAT("%",?,"%"))';
  $params[] = $q; $params[] = $q; $types .= 'ss';
}
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
  $wheres[] = 'DATE(r.creado_en) >= ?';
  $params[] = $desde; $types .= 's';
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
  $wheres[] = 'DATE(r.creado_en) <= ?';
  $params[] = $hasta; $types .= 's';
}

/* ====== Query principal ====== */
$sql = "
  SELECT r.id, r.nombre_archivo, r.url_archivo, r.tamano_bytes, r.extension, r.creado_en,
         r.profesor_id, COALESCE(CONCAT(p.apellido, ', ', p.nombre), CONCAT('ID ', r.profesor_id)) AS profesor
  FROM rutinas_clientes r
  LEFT JOIN profesores p ON p.id = r.profesor_id
  WHERE " . implode(' AND ', $wheres) . "
  ORDER BY r.creado_en DESC, r.id DESC
";
$stmt = $conexion->prepare($sql);
if (!$stmt) { http_response_code(500); exit('❌ Error interno (prep lista).'); }
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

/* ====== Vista previa (si ?ver=ID) ====== */
$preview = null;
if (isset($_GET['ver']) && ctype_digit($_GET['ver'])) {
  $vid = (int)$_GET['ver'];
  $stmt = $conexion->prepare("
    SELECT id, nombre_archivo, url_archivo, tamano_bytes, extension, creado_en
    FROM rutinas_clientes
    WHERE id=? AND gimnasio_id=? AND cliente_id=?
    LIMIT 1
  ");
  if ($stmt) {
    $stmt->bind_param('iii', $vid, $gimnasio_id, $cliente_id);
    $stmt->execute();
    $r = $stmt->get_result();
    $preview = $r ? $r->fetch_assoc() : null;
    $stmt->close();
  }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mis Rutinas / Alimentación</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#0f172a;--card:#111827;--muted:#94a3b8;--text:#e5e7eb;--accent:#22c55e}
    body{background:#0b1220;color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial}
    a{color:#a5b4fc;text-decoration:none}
    .wrap{max-width:1000px;margin:20px auto;padding:12px}
    .title{font-size:1.35rem;margin:0 0 10px}
    .filters{display:flex;gap:8px;flex-wrap:wrap;background:rgba(255,255,255,.04);padding:10px;border-radius:10px;margin-bottom:12px}
    .filters input{background:#0f172a;border:1px solid #1f2937;border-radius:8px;padding:8px;color:var(--text)}
    .filters button{background:var(--accent);color:#062; border:0;padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:600}
    .card{background:linear-gradient(180deg,#0b1220,#0a0f1a);border:1px solid #1f2937;border-radius:14px;padding:12px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #1f2937;text-align:left;font-size:.95rem}
    th{color:#cbd5e1;font-weight:600}
    .badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:.8rem;border:1px solid #334155;color:#e2e8f0;background:#0f172a}
    .muted{color:var(--muted)}
    .actions a, .actions button{display:inline-block;padding:6px 10px;border-radius:8px;border:1px solid #334155;background:#0f172a}
    .viewer{margin-top:14px}
    .viewer .pane{background:#0b1220;border:1px solid #1f2937;border-radius:12px;padding:10px}
    .empty{padding:16px;text-align:center;color:#94a3b8}
    .back{display:inline-block;margin:6px 0 12px}
    .chips{display:flex;gap:6px;flex-wrap:wrap}
    .chip{border:1px solid #334155;background:#0f172a;border-radius:999px;padding:2px 8px;font-size:.82rem}
  </style>
</head>
<body>
<div class="wrap">
  <h2 class="title">🗂️ Mis Rutinas y Planes de Alimentación</h2>

  <form class="filters" method="get">
    <input type="text" name="q" placeholder="Buscar por nombre o extensión (ej: pdf, jpg)" value="<?= h($q) ?>">
    <input type="date" name="desde" value="<?= h($desde) ?>">
    <input type="date" name="hasta" value="<?= h($hasta) ?>">
    <button type="submit">Filtrar</button>
    <?php if ($q || $desde || $hasta): ?>
      <a class="actions" href="ver_mis_rutinas.php" style="border:none;padding:8px 10px;background:#1f2937;color:#e5e7eb;border-radius:8px">Limpiar</a>
    <?php endif; ?>
  </form>

  <?php if ($preview): ?>
    <a class="back" href="ver_mis_rutinas.php">← Volver al listado</a>
    <div class="card viewer">
      <div class="pane">
        <div class="chips">
          <span class="chip">Archivo: <?= h($preview['nombre_archivo']) ?></span>
          <span class="chip">Ext: <?= h(strtoupper($preview['extension'])) ?></span>
          <span class="chip">Tamaño: <?= h(fmt_bytes($preview['tamano_bytes'])) ?></span>
          <span class="chip">Fecha: <?= h($preview['creado_en']) ?></span>
        </div>
        <div style="margin:10px 0">
          <a href="<?= h($preview['url_archivo']) ?>" target="_blank" rel="noopener">⬇️ Descargar / abrir en nueva pestaña</a>
        </div>
        <?php if (is_pdf($preview['extension'])): ?>
          <iframe src="<?= h($preview['url_archivo']) ?>" width="100%" height="700" style="border:1px solid #334155;border-radius:12px"></iframe>
        <?php elseif (is_img($preview['extension'])): ?>
          <img src="<?= h($preview['url_archivo']) ?>" alt="Vista previa" style="max-width:100%;border-radius:12px;border:1px solid #334155">
        <?php else: ?>
          <p class="muted">👀 Vista previa no disponible para este tipo de archivo. Usá el enlace para descargarlo.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-top:12px">
    <?php if (empty($items)): ?>
      <div class="empty">No tenés archivos cargados por tus profesores todavía.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Archivo</th>
            <th>Tipo</th>
            <th>Tamaño</th>
            <th>Profesor</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td class="muted"><?= h($it['creado_en']) ?></td>
            <td><?= h($it['nombre_archivo']) ?></td>
            <td><span class="badge"><?= h(strtoupper
