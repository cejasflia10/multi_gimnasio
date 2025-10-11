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
  SELECT COALESCE(SUMA,0) AS s FROM (
    SELECT SUM(total_pagado) AS SUMA
    FROM membresias
    WHERE gimnasio_id = {$gimnasio_id}
      AND DATE_FORMAT(fecha_inicio, '%Y-%m') = '{$ym}'
  ) t
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
    /* ===== Maqueta alineada al index ===== */
    .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }

    .page-card{
      background:var(--card); border:1px solid var(--stroke);
      border-radius:18px; box-shadow:var(--shadow); padding:16px;
    }
    .page-title{
      margin:0 0 12px 0; font-weight:900; letter-spacing:.4px; text-align:left;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }

    /* KPIs (Ingresos/Gastos/Saldo) */
    .tarjetas{ display:grid; grid-template-columns:repeat(12,1fr); gap:12px; margin:14px 0; }
    .kpi{ grid-column:span 4; background:linear-gradient(180deg,#fff,#f8fafc); border:1px solid var(--stroke);
          border-radius:16px; padding:14px; box-shadow:var(--shadow); }
    .kpi .mut{ color:var(--mut); font-size:.9rem; }
    .kpi .num{ font-size:22px; font-weight:900; }
    .ok{ color:#16a34a; } .bad{ color:#b91c1c; }

    @media (max-width:900px){
      .kpi{ grid-column:1 / -1; text-align:center; }
    }

    /* Filtros + PDF */
    .rowflex{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .toolbar{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin:10px 0 16px; }
    .toolbar form{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

    input[type="month"], select, input[type="date"], input[type="text"], input[type="number"], textarea, button, .btn{
      padding:10px 12px; border-radius:12px; border:1px solid var(--stroke);
      background:linear-gradient(180deg,#fff,#f7fafc); color:var(--ink); font-weight:700;
    }
    button, .btn{ cursor:pointer; text-decoration:none; display:inline-block; }
    button:hover, .btn:hover{ box-shadow:0 6px 16px rgba(2,6,23,.06); }

    /* Formulario de carga (card) */
    .form-card{
      background:#fff; border:1px solid var(--stroke); border-radius:18px;
      box-shadow:var(--shadow); padding:16px; margin:14px 0;
    }
    .form-card h3{ margin:0 0 10px 0; color:var(--brand); }

    /* Tabla clara “tipo index” */
    .table-wrap{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table.tabla{ width:100%; min-width:820px; border-collapse:collapse; background:#fff; }
    .tabla thead th{
      position:sticky; top:0; z-index:1; background:#f7fafc; color:#0f172a;
      border-bottom:1px solid var(--stroke); text-align:center;
    }
    .tabla th, .tabla td{
      padding:10px 12px; border-bottom:1px solid var(--stroke); text-align:center; white-space:nowrap;
    }
    .tabla tbody tr:hover{ background:#f9fafb; }

    .acciones a.btn-danger{
      border-color:rgba(239,68,68,.35);
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="page-card">
      <h1 class="page-title">Gastos del Mes (<?= htmlspecialchars($ym) ?>)</h1>

      <!-- KPIs -->
      <div class="tarjetas">
        <div class="kpi">
          <div class="mut">Ingresos mes</div>
          <div class="num ok">$<?= number_format($ingresos,2,',','.') ?></div>
        </div>
        <div class="kpi">
          <div class="mut">Gastos mes</div>
          <div class="num bad">$<?= number_format($gastos_mes,2,',','.') ?></div>
        </div>
        <div class="kpi">
          <div class="mut">Saldo</div>
          <div class="num <?= $saldo_mes>=0?'ok':'bad' ?>">$<?= number_format($saldo_mes,2,',','.') ?></div>
        </div>
      </div>

      <!-- Filtros + PDF -->
      <div class="toolbar">
        <form method="get" class="rowflex">
          <label for="ym" class="mut">Mes:</label>
          <input id="ym" type="month" name="ym" value="<?= htmlspecialchars($ym) ?>" />
          <button type="submit">Filtrar</button>
        </form>

        <a href="gastos_pdf.php?ym=<?= urlencode($ym) ?>" class="btn">⬇️ Descargar PDF</a>
      </div>

      <!-- Cargar gasto -->
      <form class="form-card" action="guardar_gasto.php" method="POST" onsubmit="return confirmarNegativo()">
        <input type="hidden" name="gimnasio_id" value="<?= (int)$gimnasio_id ?>">
        <input type="hidden" id="saldo_actual" value="<?= htmlspecialchars($saldo_mes) ?>">

        <h3>➕ Cargar gasto</h3>
        <div class="rowflex">
          <div style="flex:1 1 220px">
            <label class="mut" for="fecha">Fecha</label>
            <input id="fecha" type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div style="flex:2 1 260px">
            <label class="mut" for="tipo_id">Tipo</label>
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
            <label class="mut" for="monto">Monto</label>
            <input id="monto" type="number" step="0.01" min="0" name="monto" required>
          </div>
        </div>
        <label class="mut" for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="2" placeholder="Opcional"></textarea>
        <div class="rowflex" style="justify-content:flex-end;margin-top:10px">
          <button type="submit">💾 Guardar gasto</button>
        </div>
      </form>

      <!-- Tabla -->
      <h3 style="margin:10px 0">📄 Gastos del mes</h3>
      <div class="table-wrap">
        <table class="tabla" aria-label="Gastos del mes">
          <thead>
            <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Monto</th><th>Acción</th></tr>
          </thead>
          <tbody>
            <?php while($g = $listado->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($g['fecha']) ?></td>
                <td><?= htmlspecialchars($g['tipo']) ?></td>
                <td><?= htmlspecialchars($g['descripcion']) ?></td>
                <td style="text-align:right">$<?= number_format((float)$g['monto'],2,',','.') ?></td>
                <td class="acciones">
                  <a class="btn btn-danger" href="eliminar_gasto.php?id=<?= (int)$g['id'] ?>" onclick="return confirm('¿Eliminar gasto?')">Eliminar</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>

<script>
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
