<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    header("Location: login.php");
    exit;
}

$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

if (!preg_match('/^(0[1-9]|1[0-2])$/', $mes)) $mes = date('m');
if (!preg_match('/^\d{4}$/', $anio)) $anio = date('Y');

$meses = [
    '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
    '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
    '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
];

$stmt = $conexion->prepare("
    SELECT m.fecha_inicio, m.fecha_vencimiento,
           m.pago_efectivo, m.pago_transferencia, m.pago_debito, m.pago_credito, m.pago_cuenta_corriente,
           IFNULL(m.otros_pagos, 0) AS otros_pagos,
           IFNULL(m.total_pagado, 0) AS total,
           c.apellido, c.nombre
    FROM membresias m
    INNER JOIN clientes c ON m.cliente_id = c.id
    WHERE MONTH(m.fecha_inicio) = ? AND YEAR(m.fecha_inicio) = ?
      AND m.gimnasio_id = ?
    ORDER BY m.fecha_inicio DESC
");
$stmt->bind_param("iii", $mes, $anio, $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();

$pagos = [];
$total_mes = 0;

function obtenerMetodoPago($f) {
    $m = [];
    if ($f['pago_efectivo'] > 0) $m[] = 'Efectivo';
    if ($f['pago_transferencia'] > 0) $m[] = 'Transferencia';
    if ($f['pago_debito'] > 0) $m[] = 'Débito';
    if ($f['pago_credito'] > 0) $m[] = 'Crédito';
    if ($f['pago_cuenta_corriente'] > 0) $m[] = 'Cuenta Corriente';
    return implode(' + ', $m);
}

while ($fila = $resultado->fetch_assoc()) {
    $fila['metodo_pago'] = obtenerMetodoPago($fila);
    $pagos[] = $fila;
    $total_mes += floatval($fila['total']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos del Mes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background:#111; color:white; font-family:Arial; }
        table { width:100%; border-collapse:collapse; margin-top:15px; font-size:14px; }
        th,td { border:1px solid #666; padding:8px; text-align:center; }
        th { background:#222; color:gold; }
        td { background:#181818; }
        .boton-descarga {
            display:inline-block; padding:8px 15px;
            background:gold; color:black; border-radius:5px;
            text-decoration:none; margin-left:10px;
        }
        @media(max-width:768px){
            table,thead,tbody,tr,td,th{display:block;width:100%;}
            tr{margin-bottom:10px; border:1px solid #333;}
            td{text-align:right; padding-left:50%; position:relative;}
            td::before{
                position:absolute; left:10px; top:8px;
                color:gold; font-weight:bold;
                white-space:nowrap;
            }
            td:nth-child(1)::before{content:"Cliente";}
            td:nth-child(2)::before{content:"Inicio";}
            td:nth-child(3)::before{content:"Vencimiento";}
            td:nth-child(4)::before{content:"Método";}
            td:nth-child(5)::before{content:"Otros";}
            td:nth-child(6)::before{content:"Total";}
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2 style="text-align:center;">💳 Pagos de <?= $meses[$mes] ?> <?= $anio ?></h2>

    <form method="get" style="text-align:center; margin-bottom:15px;">
        <label>Mes:</label>
        <select name="mes">
            <?php foreach($meses as $num=>$nom): ?>
                <option value="<?= $num ?>" <?= $mes==$num?'selected':'' ?>><?= $nom ?></option>
            <?php endforeach; ?>
        </select>

        <label>Año:</label>
        <select name="anio">
            <?php for($y=date('Y');$y>=2020;$y--): ?>
                <option value="<?= $y ?>" <?= $anio==$y?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit">Filtrar</button>
        <a class="boton-descarga" href="<?= "https://".$_SERVER['HTTP_HOST']."/exportar_pagos_pdf.php?mes=$mes&anio=$anio" ?>">📄 Descargar PDF</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Cliente</th><th>Inicio</th><th>Vencimiento</th>
                <th>Método Pago</th><th>Otros</th><th>Total ($)</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($pagos)): ?>
            <tr><td colspan="6" style="color:red;">❌ No hay pagos registrados.</td></tr>
        <?php else: ?>
            <?php foreach($pagos as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['apellido']." ".$f['nombre']) ?></td>
                <td><?= $f['fecha_inicio'] ?></td>
                <td><?= $f['fecha_vencimiento'] ?></td>
                <td><?= $f['metodo_pago'] ?: '<span style="color:red;">Sin especificar</span>' ?></td>
                <td>$<?= number_format($f['otros_pagos'],0,',','.') ?></td>
                <td><strong style="color:lime;">$<?= number_format($f['total'],0,',','.') ?></strong></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <h3 style="text-align:right; margin-top:15px;">💰 Total del mes:
        <span style="color:gold;">$<?= number_format($total_mes,0,',','.') ?></span>
    </h3>
</div>
</body>
</html>
