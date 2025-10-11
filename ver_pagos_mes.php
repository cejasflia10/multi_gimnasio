<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ✅ Verificar sesión
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) {
    header("Location: login.php");
    exit;
}

// ✅ Validar mes y año
$mes  = $_GET['mes']  ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
if (!preg_match('/^(0[1-9]|1[0-2])$/', $mes))  $mes  = date('m');
if (!preg_match('/^\d{4}$/', $anio))           $anio = date('Y');
$mes_int  = (int)$mes;   // <-- bind como int
$anio_int = (int)$anio;

$meses = [
    '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
    '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
];

/* ================== Datos ================== */
$stmt = $conexion->prepare("
    SELECT m.fecha_inicio, m.fecha_vencimiento,
           IFNULL(m.pago_efectivo,0)          AS pago_efectivo,
           IFNULL(m.pago_transferencia,0)     AS pago_transferencia,
           IFNULL(m.pago_debito,0)            AS pago_debito,
           IFNULL(m.pago_credito,0)           AS pago_credito,
           IFNULL(m.pago_cuenta_corriente,0)  AS pago_cuenta_corriente,
           IFNULL(m.otros_pagos,0)            AS otros_pagos,
           IFNULL(m.total_pagado,0)           AS total,
           c.apellido, c.nombre
    FROM membresias m
    INNER JOIN clientes c ON m.cliente_id = c.id
    WHERE MONTH(m.fecha_inicio) = ? AND YEAR(m.fecha_inicio) = ? AND m.gimnasio_id = ?
    ORDER BY m.fecha_inicio DESC
");
$stmt->bind_param("iii", $mes_int, $anio_int, $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();

function obtenerMetodoPago(array $f): string {
    $metodos = [];
    if ($f['pago_efectivo']          > 0) $metodos[] = 'Efectivo';
    if ($f['pago_transferencia']     > 0) $metodos[] = 'Transferencia';
    if ($f['pago_debito']            > 0) $metodos[] = 'Débito';
    if ($f['pago_credito']           > 0) $metodos[] = 'Crédito';
    if ($f['pago_cuenta_corriente']  > 0) $metodos[] = 'Cuenta Corriente';
    return implode(' + ', $metodos);
}

$pagos = [];
$total_mes = 0.0;
while ($fila = $resultado->fetch_assoc()) {
    $fila['metodo_pago'] = obtenerMetodoPago($fila);
    $pagos[] = $fila;
    $total_mes += (float)$fila['total'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pagos del Mes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tema unificado igual al index -->
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    /* ===== Estructura alineada al index ===== */
    .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }
    .page-card{
      background:var(--card); border:1px solid var(--stroke);
      border-radius:18px; box-shadow:var(--shadow); padding:16px;
    }
    .page-title{
      margin:0 0 12px 0; text-align:center; font-weight:900; letter-spacing:.4px;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }

    /* Filtros (mes/año) con estilos del sistema */
    .toolbar{
      display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;
    }
    .toolbar select, .toolbar button, .toolbar a{
      padding:10px 12px; border-radius:12px; border:1px solid var(--stroke);
      background:linear-gradient(180deg,#fff,#f7fafc); color:var(--ink); font-weight:700; cursor:pointer;
      text-decoration:none; display:inline-block;
    }
    .toolbar button:hover, .toolbar a:hover{
      box-shadow:0 6px 16px rgba(2,6,23,.06);
    }

    /* ===== Tabla “tipo index” ===== */
    .table-wrap{ width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table.tabla{ width:100%; min-width:900px; border-collapse:collapse; background:#fff; }
    .tabla thead th{
      position:sticky; top:0; z-index:1; background:#f7fafc; color:#0f172a;
      border-bottom:1px solid var(--stroke);
    }
    .tabla th, .tabla td{
      padding:10px 12px; border-bottom:1px solid var(--stroke); text-align:center; white-space:nowrap;
    }
    .tabla tbody tr:hover{ background:#f9fafb; }

    .muted{ color:#64748b; }
    .amount-strong{ font-weight:800; color:#0f172a; }
    .amount-total{ color:var(--brand); font-weight:900; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="page-card">
      <h2 class="page-title">💳 Pagos de <?= htmlspecialchars($meses[$mes]) ?> <?= (int)$anio ?></h2>

      <form method="get" class="toolbar" role="search">
        <label for="mes" class="mut">Mes:</label>
        <select id="mes" name="mes">
          <?php foreach ($meses as $num => $nombre): ?>
            <option value="<?= $num ?>" <?= ($mes === $num ? 'selected' : '') ?>><?= htmlspecialchars($nombre) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="anio" class="mut">Año:</label>
        <select id="anio" name="anio">
          <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
            <option value="<?= $y ?>" <?= ((int)$anio === $y ? 'selected' : '') ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>

        <button type="submit">Filtrar</button>

        <!-- PDF con gimnasio_id en la URL (APK compat) -->
        <a
          href="<?= 'https://'.$_SERVER['HTTP_HOST'].'/exportar_pagos_pdf.php?mes='.$mes.'&anio='.$anio.'&gimnasio_id='.$gimnasio_id ?>"
          target="_blank" rel="noopener"
          >📄 Descargar PDF</a>
      </form>

      <div class="table-wrap">
        <table class="tabla" aria-label="Pagos del mes">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Fecha Inicio</th>
              <th>Vencimiento</th>
              <th>Método Pago</th>
              <th>Otros Pagos</th>
              <th>Total ($)</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($pagos)): ?>
            <tr><td colspan="6" class="muted">❌ No hay pagos registrados.</td></tr>
          <?php else: ?>
            <?php foreach ($pagos as $f): ?>
              <tr>
                <td><?= htmlspecialchars(($f['apellido'] ?? '').' '.($f['nombre'] ?? '')) ?></td>
                <td><?= htmlspecialchars($f['fecha_inicio']) ?></td>
                <td><?= htmlspecialchars($f['fecha_vencimiento']) ?></td>
                <td>
                  <?php if ($f['metodo_pago']): ?>
                    <?= htmlspecialchars($f['metodo_pago']) ?>
                  <?php else: ?>
                    <span class="muted">Sin especificar</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right">$<?= number_format((float)$f['otros_pagos'], 0, ',', '.') ?></td>
                <td style="text-align:right"><span class="amount-strong">$<?= number_format((float)$f['total'], 0, ',', '.') ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <h3 style="text-align:right; margin-top:16px;">
        💰 Total del mes: <span class="amount-total">$<?= number_format((float)$total_mes, 0, ',', '.') ?></span>
      </h3>
    </div>
  </div>
</body>
</html>
