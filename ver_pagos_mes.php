<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ✅ Verificar sesión
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    header("Location: login.php");
    exit;
}

// ✅ Validar mes y año
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
if (!preg_match('/^(0[1-9]|1[0-2])$/', $mes)) $mes = date('m');
if (!preg_match('/^\d{4}$/', $anio)) $anio = date('Y');

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
    '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
    '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
    '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// ✅ Consulta preparada para mayor seguridad
$stmt = $conexion->prepare("
    SELECT m.fecha_inicio, m.fecha_vencimiento,
           m.pago_efectivo, m.pago_transferencia, m.pago_debito,
           m.pago_credito, m.pago_cuenta_corriente,
           IFNULL(m.otros_pagos, 0) AS otros_pagos,
           IFNULL(m.total_pagado, 0) AS total,
           c.apellido, c.nombre
    FROM membresias m
    INNER JOIN clientes c ON m.cliente_id = c.id
    WHERE MONTH(m.fecha_inicio) = ? AND YEAR(m.fecha_inicio) = ? AND m.gimnasio_id = ?
    ORDER BY m.fecha_inicio DESC
");
$stmt->bind_param("iii", $mes, $anio, $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();

$pagos = [];
$total_mes = 0;

function obtenerMetodoPago($fila) {
    $metodos = [];
    if ($fila['pago_efectivo'] > 0) $metodos[] = 'Efectivo';
    if ($fila['pago_transferencia'] > 0) $metodos[] = 'Transferencia';
    if ($fila['pago_debito'] > 0) $metodos[] = 'Débito';
    if ($fila['pago_credito'] > 0) $metodos[] = 'Crédito';
    if ($fila['pago_cuenta_corriente'] > 0) $metodos[] = 'Cuenta Corriente';
    return implode(' + ', $metodos);
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
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 8px;
            border: 1px solid #666;
            text-align: center;
        }
        th { background-color: #222; color: gold; }
        td { background-color: #111; color: white; }
        .boton-descarga {
            margin-top: 10px;
            display: inline-block;
            padding: 8px 15px;
            background-color: gold;
            color: black;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2 style="text-align:center;">💳 Pagos de <?= $meses[$mes] ?> <?= $anio ?></h2>

    <form method="get" style="text-align:center; margin-bottom:20px;">
        <label>Mes:</label>
        <select name="mes">
            <?php foreach ($meses as $num => $nombre): ?>
                <option value="<?= $num ?>" <?= $mes == $num ? 'selected' : '' ?>><?= $nombre ?></option>
            <?php endforeach; ?>
        </select>

        <label>Año:</label>
        <select name="anio">
            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $anio == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit">Filtrar</button>

        <!-- ✅ Se pasa el gimnasio_id en la URL para que funcione en APK -->
        <a class="boton-descarga" 
   href="<?= "https://".$_SERVER['HTTP_HOST']."/exportar_pagos_pdf.php?mes=$mes&anio=$anio&gimnasio_id=$gimnasio_id" ?>" 
   target="_blank">
   📄 Descargar PDF
</a>

    </form>

    <table>
        <tr>
            <th>Cliente</th>
            <th>Fecha Inicio</th>
            <th>Vencimiento</th>
            <th>Método Pago</th>
            <th>Otros Pagos</th>
            <th>Total ($)</th>
        </tr>
        <?php if (empty($pagos)): ?>
            <tr><td colspan="6" style="color:red;">❌ No hay pagos registrados.</td></tr>
        <?php else: ?>
            <?php foreach ($pagos as $fila): ?>
                <tr>
                    <td><?= htmlspecialchars($fila['apellido'] . ' ' . $fila['nombre']) ?></td>
                    <td><?= $fila['fecha_inicio'] ?></td>
                    <td><?= $fila['fecha_vencimiento'] ?></td>
                    <td><?= $fila['metodo_pago'] ?: '<span style="color:red;">Sin especificar</span>' ?></td>
                    <td>$<?= number_format($fila['otros_pagos'], 0, ',', '.') ?></td>
                    <td><strong style="color:lime;">$<?= number_format($fila['total'], 0, ',', '.') ?></strong></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <h3 style="text-align:right; margin-top:20px;">
        💰 Total del mes: 
        <span style="color:gold;">$<?= number_format($total_mes, 0, ',', '.') ?></span>
    </h3>
</div>
</body>
</html>
