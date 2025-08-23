<?php
// gastos_pdf.php
if (session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id<=0) { http_response_code(403); die('Acceso denegado'); }

$ym = $_GET['ym'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) { $ym = date('Y-m'); }

// ====== Info del gimnasio ======
$gym = ['nombre'=>'Mi Gimnasio','direccion'=>'—','cuit'=>'—','logo'=>''];
$q = $conexion->query("SELECT nombre, direccion, cuit, logo FROM gimnasios WHERE id = {$gimnasio_id} LIMIT 1");
if ($q && $row = $q->fetch_assoc()) {
  $gym['nombre']    = $row['nombre']    ?: $gym['nombre'];
  $gym['direccion'] = $row['direccion'] ?: $gym['direccion'];
  $gym['cuit']      = $row['cuit']      ?: $gym['cuit'];
  $gym['logo']      = trim((string)$row['logo']);
}

// Normalizar logo: si no es URL absoluta y no está vacío, asumimos /uploads
if ($gym['logo'] !== '') {
  $is_url = preg_match('#^https?://#i', $gym['logo']);
  if (!$is_url) {
    // Opción A (URL local via Apache/XAMPP):
    $gym['logo'] = 'http://localhost/multi_gimnasio/uploads/' . ltrim($gym['logo'], '/\\');

    // Opción B (archivo local absoluto):
    // $gym['logo'] = __DIR__ . '/uploads/' . ltrim($gym['logo'], '/\\');
  }
}

// ====== Helpers ======
function tableExists(mysqli $cx, $name){
  $name = $cx->real_escape_string($name);
  $res = $cx->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$name}'");
  return $res && $res->num_rows>0;
}

// ====== Ingresos / Gastos / Saldo del mes ======
$ingresos = 0.0;

// 1) membresias.total_pagado (mes por fecha_inicio)
$q1 = $conexion->query("
  SELECT COALESCE(SUM(total_pagado),0) AS s
  FROM membresias
  WHERE gimnasio_id = {$gimnasio_id}
    AND DATE_FORMAT(fecha_inicio, '%Y-%m') = '{$ym}'
");
if ($q1) { $ingresos += (float)$q1->fetch_assoc()['s']; }

// 2) cuentas_corrientes: montos positivos (pagos de deuda)
$q2 = $conexion->query("
  SELECT COALESCE(SUM(monto),0) AS s
  FROM cuentas_corrientes
  WHERE gimnasio_id = {$gimnasio_id}
    AND monto > 0
    AND DATE_FORMAT(fecha, '%Y-%m') = '{$ym}'
");
if ($q2) { $ingresos += (float)$q2->fetch_assoc()['s']; }

// 3) ventas_pagos (si existe)
if (tableExists($conexion,'ventas_pagos')) {
  $q3 = $conexion->query("
    SELECT COALESCE(SUM(vp.monto),0) AS s
    FROM ventas_pagos vp
    JOIN ventas v ON v.id = vp.venta_id
    WHERE v.gimnasio_id = {$gimnasio_id}
      AND DATE_FORMAT(v.fecha, '%Y-%m') = '{$ym}'
  ");
  if ($q3) { $ingresos += (float)$q3->fetch_assoc()['s']; }
}

// 4) membresias_pagos (si existe)
if (tableExists($conexion,'membresias_pagos')) {
  $q4 = $conexion->query("
    SELECT COALESCE(SUM(mp.monto),0) AS s
    FROM membresias_pagos mp
    JOIN membresias m ON m.id = mp.membresia_id
    WHERE m.gimnasio_id = {$gimnasio_id}
      AND DATE_FORMAT(m.fecha_inicio, '%Y-%m') = '{$ym}'
  ");
  if ($q4) { $ingresos += (float)$q4->fetch_assoc()['s']; }
}

// Gastos
$qg = $conexion->query("
  SELECT COALESCE(SUM(monto),0) AS s
  FROM gastos
  WHERE gimnasio_id = {$gimnasio_id}
    AND DATE_FORMAT(fecha, '%Y-%m') = '{$ym}'
");
$gastos_mes = $qg ? (float)$qg->fetch_assoc()['s'] : 0.0;
$saldo_mes  = $ingresos - $gastos_mes;

// Listado de gastos
$listado = $conexion->query("
  SELECT g.fecha, t.nombre AS tipo, g.descripcion, g.monto
  FROM gastos g
  JOIN gastos_tipos t ON t.id = g.tipo_id
  WHERE g.gimnasio_id = {$gimnasio_id}
    AND DATE_FORMAT(g.fecha, '%Y-%m') = '{$ym}'
  ORDER BY g.fecha ASC, g.id ASC
");

// ====== HTML ======
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 22mm 15mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color:#111; }
  .header { display:flex; align-items:center; gap:12px; }
  .logo { width:90px; height:90px; object-fit:contain; border:1px solid #ddd; border-radius:8px; }
  .h-title { font-size:20px; font-weight:700; margin:0; }
  .muted { color:#555; }
  .grid { display:flex; gap:20px; margin:10px 0 16px; }
  .card { border:1px solid #ccc; border-radius:8px; padding:8px 12px; }
  .k { font-size:11px; color:#666; }
  .v { font-size:15px; font-weight:700; }
  table { width:100%; border-collapse:collapse; margin-top:8px; }
  th, td { border:1px solid #ccc; padding:6px 8px; }
  th { background:#f5f5f5; }
  .right { text-align:right; }
  .total-row td { font-weight:700; }
  .footer { margin-top:12px; font-size:12px; }
</style>
</head>
<body>
  <div class="header">
    <?php if (!empty($gym['logo'])): ?>
      <img class="logo" src="<?= htmlspecialchars($gym['logo']) ?>" alt="logo">
    <?php endif; ?>
    <div>
      <p class="h-title"><?= htmlspecialchars($gym['nombre']) ?></p>
      <div class="muted">
        Dirección: <?= htmlspecialchars($gym['direccion']) ?> · CUIT: <?= htmlspecialchars($gym['cuit']) ?><br>
        Informe de Gastos — Mes: <?= htmlspecialchars($ym) ?>
      </div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="k">Ingresos del mes</div>
      <div class="v">$<?= number_format($ingresos,2,',','.') ?></div>
    </div>
    <div class="card">
      <div class="k">Gastos del mes</div>
      <div class="v">$<?= number_format($gastos_mes,2,',','.') ?></div>
    </div>
    <div class="card">
      <div class="k">Saldo</div>
      <div class="v">$<?= number_format($saldo_mes,2,',','.') ?></div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:18%">Fecha</th>
        <th style="width:18%">Tipo</th>
        <th>Descripción</th>
        <th style="width:18%" class="right">Monto</th>
      </tr>
    </thead>
    <tbody>
      <?php $totalTabla = 0.0; ?>
      <?php if ($listado): while($g = $listado->fetch_assoc()): $totalTabla += (float)$g['monto']; ?>
      <tr>
        <td><?= htmlspecialchars($g['fecha']) ?></td>
        <td><?= htmlspecialchars($g['tipo']) ?></td>
        <td><?= htmlspecialchars($g['descripcion']) ?></td>
        <td class="right">$<?= number_format((float)$g['monto'],2,',','.') ?></td>
      </tr>
      <?php endwhile; endif; ?>
      <tr class="total-row">
        <td colspan="3" class="right">TOTAL GASTADO</td>
        <td class="right">$<?= number_format($totalTabla,2,',','.') ?></td>
      </tr>
    </tbody>
  </table>

  <div class="footer muted">
    Generado el <?= date('Y-m-d H:i') ?> — Sistema de Gestión
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

// ====== Dompdf (ZIP sin Composer) ======
require __DIR__ . '/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true); // permite logos por URL
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("gastos_{$ym}.pdf", ['Attachment' => 1]);
exit;
