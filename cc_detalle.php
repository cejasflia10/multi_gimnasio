<?php
/* cc_detalle.php */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/conexion.php';
require __DIR__ . '/menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$cliente_id  = (int)($_GET['cliente_id'] ?? 0);
if ($gimnasio_id <= 0 || $cliente_id <= 0) {
  http_response_code(403);
  exit('Acceso denegado');
}

@ $conexion->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$' . number_format((float)$n, 2, ',', '.'); }
function db_has_col(mysqli $db, string $table, string $col): bool {
  $table = $db->real_escape_string($table);
  $col   = $db->real_escape_string($col);
  $q = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
  return ($q && $q->num_rows > 0);
}

/* ---------- Detectar columna de comprobante ---------- */
$comprobante_col = null;
foreach (['comprobante_url','comprobante','archivo_comprobante','comprobante_path'] as $cand) {
  if (db_has_col($conexion, 'cc_movimientos', $cand)) { $comprobante_col = $cand; break; }
}

/* ---------- Cargar datos del cliente ---------- */
$cli = ['nombre'=>'','apellido'=>'','dni'=>'','telefono'=>''];
$qc = $conexion->prepare("SELECT nombre, apellido, dni, telefono FROM clientes WHERE id=? LIMIT 1");
$qc->bind_param('i', $cliente_id);
$qc->execute();
$rcli = $qc->get_result();
if ($rcli && $rcli->num_rows) { $cli = $rcli->fetch_assoc(); }
$qc->close();

/* ---------- Registrar pago (POST) ---------- */
$mensaje = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'registrar_pago') {
  $monto   = (float)str_replace(',', '.', $_POST['monto'] ?? '0');
  $fecha   = ($_POST['fecha'] ?? date('Y-m-d'));
  $concepto= trim($_POST['concepto'] ?? 'Pago CC');

  // Validación básica
  if ($monto <= 0) {
    $mensaje = 'El monto debe ser mayor a 0.';
  } else {
    // Subida de comprobante (opcional)
    $ruta_guardada = '';
    if (!empty($_FILES['comprobante']['name']) && is_uploaded_file($_FILES['comprobante']['tmp_name'])) {
      $dir = __DIR__ . '/uploads/cc';
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
      $base = 'comp_'.$gimnasio_id.'_'.$cliente_id.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
      $fname = $base.($ext ? '.'.$ext : '');
      $dest_fs = $dir.'/'.$fname;
      if (@move_uploaded_file($_FILES['comprobante']['tmp_name'], $dest_fs)) {
        // guardar ruta relativa para abrir desde el navegador
        $ruta_guardada = 'uploads/cc/'.$fname;
      }
    }

    // Insertar movimiento: haber = pago, debe = 0
    if ($comprobante_col) {
      $sql = "INSERT INTO cc_movimientos (gimnasio_id, cliente_id, fecha, concepto, debe, haber, `$comprobante_col`) 
              VALUES (?, ?, ?, ?, 0, ?, ?)";
      $st  = $conexion->prepare($sql);
      $st->bind_param('iissds', $gimnasio_id, $cliente_id, $fecha, $concepto, $monto, $ruta_guardada);
    } else {
      $sql = "INSERT INTO cc_movimientos (gimnasio_id, cliente_id, fecha, concepto, debe, haber) 
              VALUES (?, ?, ?, ?, 0, ?)";
      $st  = $conexion->prepare($sql);
      $st->bind_param('iissd', $gimnasio_id, $cliente_id, $fecha, $concepto, $monto);
    }

    if ($st && $st->execute()) {
      $mensaje = '✅ Pago registrado correctamente.';
      // Redirigir para evitar reenvío
      header("Location: cc_detalle.php?cliente_id={$cliente_id}&ok=1#pago");
      exit;
    } else {
      $mensaje = '❌ Error al registrar: '.$conexion->error;
    }
  }
}

/* ---------- Traer movimientos ---------- */
$cols = "id, fecha, concepto, debe, haber";
if ($comprobante_col) { $cols .= ", `$comprobante_col` AS comprobante"; }

$qm = $conexion->prepare("
  SELECT $cols
  FROM cc_movimientos
  WHERE gimnasio_id = ? AND cliente_id = ?
  ORDER BY fecha DESC, id DESC
");
$qm->bind_param('ii', $gimnasio_id, $cliente_id);
$qm->execute();
$movs = $qm->get_result();

$total_debe = 0.0; $total_haber = 0.0;
$rows = [];
if ($movs) {
  while($r = $movs->fetch_assoc()){
    $total_debe  += (float)$r['debe'];
    $total_haber += (float)$r['haber'];
    $rows[] = $r;
  }
}
$saldo = $total_debe - $total_haber;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cuenta Corriente — <?= h(trim(($cli['apellido'] ?? '').' '.($cli['nombre'] ?? ''))) ?></title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body { background:#000; color:gold; font-family:Arial, sans-serif; }
    .wrap { max-width:1100px; margin:auto; padding:20px; }
    .card { background:#111; border:1px solid gold; border-radius:8px; padding:16px; margin-bottom:18px; }
    h2, h3 { margin:0 0 10px 0; }
    table { width:100%; border-collapse:collapse; background:#0c0c0c; }
    th, td { border:1px solid gold; padding:8px; text-align:center; }
    th { background:#222; }
    .num { text-align:right; white-space:nowrap; }
    .muted { opacity:.75; }
    .badge { padding:3px 8px; border:1px solid gold; border-radius:999px; }
    .btn { padding:6px 12px; font-weight:700; border:none; border-radius:5px; cursor:pointer; text-decoration:none; margin:2px; display:inline-block; }
    .btn-back { background:#333; color:gold; }
    .btn-primary { background:green; color:#fff; }
    input, select { background:#000; color:gold; border:1px solid gold; border-radius:6px; padding:8px; }
    input[type="date"]{ padding:6px; }
    .row { display:flex; gap:10px; flex-wrap:wrap; }
    .row > div { flex:1 1 220px; }
  </style>
</head>
<body>
<div class="wrap">

  <a class="btn btn-back" href="ver_cuentas_corrientes.php">← Volver</a>

  <div class="card">
    <h2>Cuenta Corriente de <?= h(trim(($cli['apellido'] ?? '').' '.($cli['nombre'] ?? ''))) ?></h2>
    <div class="muted">DNI: <?= h($cli['dni'] ?? '—') ?> · Tel: <?= h($cli['telefono'] ?? '—') ?></div>
    <div style="margin-top:8px">
      <span class="badge">Debe: <?= money($total_debe) ?></span>
      <span class="badge">Haber: <?= money($total_haber) ?></span>
      <span class="badge">Saldo: <?= money($saldo) ?></span>
      <a class="btn btn-back" href="cc_comprobante.php?cliente_id=<?= (int)$cliente_id ?>" target="_blank" rel="noopener">PDF</a>
    </div>
  </div>

  <div class="card">
    <h3>Movimientos</h3>
    <table>
      <tr>
        <th>Fecha</th>
        <th>Concepto</th>
        <th class="num">Debe</th>
        <th class="num">Haber</th>
        <?php if ($comprobante_col): ?><th>Comprobante</th><?php endif; ?>
      </tr>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= $comprobante_col ? 5 : 4 ?>" class="muted">Sin movimientos.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['fecha'] ?? '') ?></td>
          <td><?= h($r['concepto'] ?? '') ?></td>
          <td class="num"><?= money($r['debe'] ?? 0) ?></td>
          <td class="num"><?= money($r['haber'] ?? 0) ?></td>
          <?php if ($comprobante_col): ?>
            <td>
              <?php
                $url = trim((string)($r['comprobante'] ?? ''));
                if ($url !== '') {
                  $href = $url; // puede ser relativa o absoluta
                  echo '<a class="btn btn-back" href="'.h($href).'" target="_blank" rel="noopener">Ver</a>';
                } else {
                  echo '<span class="muted">—</span>';
                }
              ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>

  <div class="card" id="pago">
    <h3>Registrar Pago</h3>
    <?php if (isset($_GET['ok'])): ?>
      <div class="muted">✅ Pago guardado.</div>
    <?php elseif ($mensaje): ?>
      <div class="muted"><?= h($mensaje) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="registrar_pago">
      <div class="row" style="margin-top:8px">
        <div>
          <label>Monto</label><br>
          <input type="number" name="monto" step="0.01" min="0" required>
        </div>
        <div>
          <label>Fecha</label><br>
          <input type="date" name="fecha" value="<?= h(date('Y-m-d')) ?>">
        </div>
        <div>
          <label>Concepto</label><br>
          <input type="text" name="concepto" value="Pago CC">
        </div>
        <div>
          <label>Comprobante (opcional)</label><br>
          <input type="file" name="comprobante" accept="image/*,application/pdf">
        </div>
      </div>
      <div style="margin-top:10px">
        <button class="btn btn-primary" type="submit">Guardar pago</button>
      </div>
    </form>
  </div>

</div>
</body>
</html>
