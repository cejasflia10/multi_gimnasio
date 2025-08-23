<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/conexion.php';
require __DIR__.'/menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id<=0){ http_response_code(403); die('Acceso denegado'); }

$hoy = new DateTime('now');
$ym  = $_GET['ym'] ?? $hoy->format('Y-m'); // YYYY-MM validación simple
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = $hoy->format('Y-m');

function tableExists(mysqli $cx, $name){
  $name = $cx->real_escape_string($name);
  $res = $cx->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$name}'");
  return $res && $res->num_rows>0;
}

/* ===== Ingresos del mes actual ===== */
$ingresos = 0.0;

/* 1) membresias.total_pagado del mes (referencia: fecha_inicio) */
$q1 = $conexion->query("
  SELECT COALESCE(SUM(total_pagado),0) AS s
  FROM membresias
  WHERE gimnasio_id = {$gimnasio_id}
    AND DATE_FORMAT(fecha_inicio, '%Y-%m') = '{$ym}'
");
if ($q1){ $ingresos += (float)$q1->fetch_assoc()['s']; }

/* 2) cuentas_corrientes: montos positivos (pagos a deuda) del mes */
$q2 = $conexion->query("
  SELECT COALESCE(SUM(monto),0) AS s
  FROM cuentas_corrientes
  WHERE gimnasio_id = {$gimnasio_id}
    AND monto > 0
    AND DATE_FORMAT(fecha, '%Y-%m') = '{$ym}'
");
if ($q2){ $ingresos += (float)$q2->fetch_assoc()['s']; }

/* 3) ventas_pagos (si existe) */
if (tableExists($conexion, 'ventas_pagos')){
  $q3 = $conexion->query("
    SELECT COALESCE(SUM(vp.monto),0) AS s
    FROM ventas_pagos vp
    JOIN ventas v ON v.id = vp.venta_id
    WHERE v.gimnasio_id = {$gimnasio_id}
      AND DATE_FORMAT(v.fecha, '%Y-%m') = '{$ym}'
  ");
  if ($q3){ $ingresos += (float)$q3->fetch_assoc()['s']; }
}

/* 4) membresias_pagos (si existe) */
if (tableExists($conexion, 'membresias_pagos')){
  $q4 = $conexion->query("
    SELECT COALESCE(SUM(mp.monto),0) AS s
    FROM membresias_pagos mp
    JOIN membresias m ON m.id = mp.membresia_id
    WHERE m.gimnasio_id = {$gimnasio_id}
      AND DATE_FORMAT(m.fecha_inicio, '%Y-%m') = '{$ym}'
  ");
  if ($q4){ $ingresos += (float)$q4->fetch_assoc()['s']; }
}

/* ===== Gastos del mes actual ===== */
$qg = $conexion->query("
  SELECT COALESCE(SUM(monto),0) AS s
  FROM gastos
  WHERE gimnasio_id = {$gimnasio_id}
    AND DATE_FORMAT(fecha, '%Y-%m') = '{$ym}'
");
$gastos_mes = $qg ? (float)$qg->fetch_assoc()['s'] : 0.0;

$saldo_mes = $ingresos - $gastos_mes;

/* Tipos de gasto */
$tipos = $conexion->query("SELECT id, nombre FROM gastos_tipos WHERE gimnasio_id = {$gimnasio_id} ORDER BY nombre");

/* Listado de gastos del mes */
$listado = $conexion->query("
  SELECT g.id, g.fecha, g.descripcion, g.monto, t.nombre AS tipo
  FROM gastos g
  JOIN gastos_tipos t ON t.id = g.tipo_id
  WHERE g.gimnasio_id = {$gimnasio_id}
    AND DATE_FORMAT(g.fecha, '%Y-%m') = '{$ym}'
  ORDER BY g.fecha DESC, g.id DESC
");
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Gastos del Mes</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{background:#000;color:gold;font-family:system-ui,Arial,sans-serif}
    .contenedor{max-width:1100px;margin:0 auto;padding:16px}
    h1{margin:0 0 12px}
    .tarjetas{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:14px 0}
    .card{background:#111;border:1px solid #444;border-radius:10px;padding:12px}
    .num{font-size:22px;font-weight:800}
    .ok{color:#22c55e}.bad{color:#ef4444}.muted{color:#9aa}
    form{background:#111;border:1px solid #444;border-radius:10px;padding:12px;margin:14px 0}
    label{display:block;margin:8px 0 5px}
    input,select,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #555;background:#0c0c0c;color:#ddd}
    table{width:100%;border-collapse:collapse;background:#111;margin-top:16px}
    th,td{border:1px solid #444;padding:8px;text-align:center;white-space:nowrap}
    th{background:#1b1b1b}
    .acciones a{color:#fff;text-decoration:none;padding:4px 8px;border-radius:6px}
    .btn-eliminar{background:#ef4444}
    .rowflex{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .btn{background:#0ea5e9;color:#fff;text-decoration:none;border-radius:8px;padding:8px 12px;display:inline-block}
  </style>
</head>
<body>
<div class="contenedor">
  <h1>Gastos del Mes (<?= htmlspecialchars($ym) ?>)</h1>

  <div class="tarjetas">
    <div class="card">
      <div class="muted">Ingresos mes</div>
      <div class="num ok">$<?= number_format($ingresos,2,',','.') ?></div>
    </div>
    <div class="card">
      <div class="muted">Gastos mes</div>
      <div class="num bad">$<?= number_format($gastos_mes,2,',','.') ?></div>
    </div>
    <div class="card">
      <div class="muted">Saldo</div>
      <div class="num <?= $saldo_mes>=0?'ok':'bad' ?>">$<?= number_format($saldo_mes,2,',','.') ?></div>
    </div>
  </div>

  <div class="rowflex" style="justify-content:space-between">
    <form method="get" class="rowflex">
      <label>Mes:</label>
      <input type="month" name="ym" value="<?= htmlspecialchars($ym) ?>" />
      <button type="submit">Filtrar</button>
    </form>

    <a href="gastos_pdf.php?ym=<?= urlencode($ym) ?>" class="btn">⬇️ Descargar PDF</a>
  </div>

  <form action="guardar_gasto.php" method="POST" onsubmit="return confirmarNegativo()">
    <input type="hidden" name="gimnasio_id" value="<?= (int)$gimnasio_id ?>">
    <input type="hidden" id="saldo_actual" value="<?= htmlspecialchars($saldo_mes) ?>">

    <h3>➕ Cargar gasto</h3>
    <div class="rowflex">
      <div style="flex:1 1 220px">
        <label>Fecha</label>
        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div style="flex:2 1 260px">
        <label>Tipo</label>
        <div class="rowflex">
          <select name="tipo_id" id="tipo_id" required style="flex:1 1 auto">
            <option value="">Seleccionar…</option>
            <?php while($t = $tipos->fetch_assoc()): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
            <?php endwhile; ?>
          </select>
          <input type="text" id="nuevo_tipo" placeholder="Nuevo tipo…">
          <button type="button" onclick="crearTipo()">Crear</button>
        </div>
      </div>
      <div style="flex:1 1 180px">
        <label>Monto</label>
        <input type="number" step="0.01" min="0" name="monto" id="monto" required>
      </div>
    </div>
    <label>Descripción</label>
    <textarea name="descripcion" rows="2" placeholder="Opcional"></textarea>
    <div class="rowflex" style="justify-content:flex-end;margin-top:10px">
      <button type="submit">💾 Guardar gasto</button>
    </div>
  </form>

  <h3>📄 Gastos del mes</h3>
  <div style="overflow-x:auto">
    <table>
      <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Monto</th><th>Acción</th></tr></thead>
      <tbody>
        <?php while($g = $listado->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($g['fecha']) ?></td>
            <td><?= htmlspecialchars($g['tipo']) ?></td>
            <td><?= htmlspecialchars($g['descripcion']) ?></td>
            <td>$<?= number_format((float)$g['monto'],2,',','.') ?></td>
            <td class="acciones">
              <a class="btn-eliminar" href="eliminar_gasto.php?id=<?= (int)$g['id'] ?>" onclick="return confirm('¿Eliminar gasto?')">Eliminar</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Confirma si el saldo (ingresos - gastos) quedará negativo
function confirmarNegativo(){
  const saldo = parseFloat(document.getElementById('saldo_actual').value) || 0;
  const monto = parseFloat(document.getElementById('monto').value) || 0;
  if ((saldo - monto) < -0.009) {
    return confirm("⚠️ El saldo del mes quedará NEGATIVO.\nSaldo actual: $"+saldo.toFixed(2)+"\nGasto: $"+monto.toFixed(2)+"\n¿Desea continuar?");
  }
  return true;
}

// Alta rápida de tipo
async function crearTipo(){
  const nombre = (document.getElementById('nuevo_tipo').value || '').trim();
  if (!nombre) return alert('Escribí un nombre');
  const body = new URLSearchParams({ nombre: nombre, gimnasio_id: "<?= (int)$gimnasio_id ?>" });
  const res = await fetch('guardar_tipo_gasto.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
  const data = await res.json().catch(()=>({ok:false}));
  if (!data.ok) return alert('No se pudo crear el tipo');
  const sel = document.getElementById('tipo_id');
  const opt = document.createElement('option');
  opt.value = data.id; opt.textContent = nombre;
  sel.appendChild(opt); sel.value = data.id;
  document.getElementById('nuevo_tipo').value = '';
}
</script>
</body>
</html>
