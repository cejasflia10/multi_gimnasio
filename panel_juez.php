<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['__JUEZ_MODE__'] = 1; // BYPASS guard organizador

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$juez_id = (int)($_SESSION['juez_id'] ?? 0);
if ($juez_id <= 0) {
  header('Location: login_juez.php?err='.urlencode('Iniciá sesión primero.')); exit;
}

function has_col(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c' LIMIT 1";
  if ($r = $db->query($sql)) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

// Traer juez por PK id (NO juez_id)
$sqlJ = "SELECT id, dni, nombre, apellido"
       . (has_col($conexion,'jueces_evento','telefono') ? ", telefono" : "")
       . (has_col($conexion,'jueces_evento','email')    ? ", email"    : "")
       . (has_col($conexion,'jueces_evento','rol')      ? ", rol"      : "")
       . (has_col($conexion,'jueces_evento','mesa')     ? ", mesa"     : "")
       . (has_col($conexion,'jueces_evento','escuela')  ? ", escuela"  : "")
       . " FROM jueces_evento WHERE id = ? LIMIT 1";

$st = $conexion->prepare($sqlJ);
if (!$st) { exit('❌ Error interno (prep juez).'); }
$st->bind_param('i', $juez_id);
$st->execute();
$res = $st->get_result();
$juez = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$juez) { exit('Juez no encontrado.'); }

// (Opcional) Traer tarjeta si existe tabla tarjetas_jueces
$tarjeta = null;
if ($conexion->query("SHOW TABLES LIKE 'tarjetas_jueces'")->num_rows) {
  $sqlT = "SELECT id, juez_id"
        . (has_col($conexion,'tarjetas_jueces','qr_url')        ? ", qr_url"        : "")
        . (has_col($conexion,'tarjetas_jueces','credencial_url')? ", credencial_url": "")
        . " FROM tarjetas_jueces WHERE juez_id = ? ORDER BY id DESC LIMIT 1";
  $st2 = $conexion->prepare($sqlT);
  if ($st2) {
    $st2->bind_param('i', $juez_id);
    $st2->execute();
    $r2 = $st2->get_result();
    $tarjeta = $r2 ? $r2->fetch_assoc() : null;
    $st2->close();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Juez - <?= htmlspecialchars(($juez['nombre'] ?? '').' '.($juez['apellido'] ?? '')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;background:#0b1115;color:#e6eef4}
    .wrap{max-width:900px;margin:32px auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:18px}
    h1{margin:0 0 10px 0;font-size:22px}
    .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
    .pill{display:inline-block;padding:4px 10px;border:1px solid #2b3c4f;border-radius:999px;background:#13202c}
    a.btn{display:inline-block;margin-top:12px;padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none}
    .muted{color:#8bb3d9}
    .row{margin:6px 0}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Bienvenido/a, <?= htmlspecialchars(($juez['nombre'] ?? '').' '.($juez['apellido'] ?? '')) ?></h1>
      <div class="grid" style="margin-top:12px">
        <div class="row"><b>DNI:</b> <span class="pill"><?= htmlspecialchars((string)($juez['dni'] ?? '')) ?></span></div>
        <div class="row"><b>Rol:</b> <span class="pill"><?= htmlspecialchars((string)($juez['rol'] ?? '-')) ?></span></div>
        <div class="row"><b>Mesa/Tatami:</b> <span class="pill"><?= htmlspecialchars((string)($juez['mesa'] ?? '-')) ?></span></div>
        <div class="row"><b>Escuela:</b> <span class="pill"><?= htmlspecialchars((string)($juez['escuela'] ?? '-')) ?></span></div>
        <div class="row"><b>Teléfono:</b> <span class="pill"><?= htmlspecialchars((string)($juez['telefono'] ?? '-')) ?></span></div>
        <div class="row"><b>Email:</b> <span class="pill"><?= htmlspecialchars((string)($juez['email'] ?? '-')) ?></span></div>
      </div>

      <a class="btn" href="tarjeta_juez.php">Ver mi tarjeta</a>
      <a class="btn" style="background:#1b2836;border-color:#2b3c4f" href="logout_juez.php">Cerrar sesión</a>
    </div>

    <div class="card" style="margin-top:16px">
      <h1>Tarjeta</h1>
      <?php if ($tarjeta): ?>
        <?php if (!empty($tarjeta['qr_url'])): ?>
          <div class="row"><b>QR:</b> <a class="btn" href="<?= htmlspecialchars($tarjeta['qr_url']) ?>" target="_blank">Abrir QR</a></div>
        <?php endif; ?>
        <?php if (!empty($tarjeta['credencial_url'])): ?>
          <div class="row"><b>Credencial:</b> <a class="btn" href="<?= htmlspecialchars($tarjeta['credencial_url']) ?>" target="_blank">Ver credencial</a></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="muted">No hay tarjeta generada para este juez.</div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
