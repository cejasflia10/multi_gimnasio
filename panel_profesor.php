<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

// Verificar sesión válida
if (!isset($_SESSION['profesor_id'])) {
    header("Location: login_profesor.php");
    exit;
}

$profesor_id = $_SESSION['profesor_id'];
$profesor_nombre = $_SESSION['profesor_nombre'] ?? 'Profesor';

include 'menu_profesor.php';

$fecha_hoy = date('Y-m-d');

$prof = $conexion->query("SELECT apellido, nombre FROM profesores WHERE id = $profesor_id")->fetch_assoc();

// Obtener alumnos de hoy
$alumnos = $conexion->query("
    SELECT c.apellido, c.nombre
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.id_profesor = $profesor_id AND r.fecha = '$fecha_hoy'
    ORDER BY c.apellido
");

// Calcular saldo mensual
$ingresos = $conexion->query("
    SELECT fecha, hora_ingreso, hora_egreso
    FROM asistencias_profesor
    WHERE profesor_id = $profesor_id AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
");

$total_horas = 0;
while ($fila = $ingresos->fetch_assoc()) {
    if ($fila['hora_egreso'] && $fila['hora_ingreso']) {
        $inicio = strtotime($fila['hora_ingreso']);
        $fin = strtotime($fila['hora_egreso']);
        $total_horas += round(($fin - $inicio) / 3600, 2);
    }
}
$valor_hora = 1500;
$saldo = $total_horas * $valor_hora;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <!-- App Instalable -->
<link rel="manifest" href="manifest_profesor.json">
<link rel="icon" href="icono_profesor.png">
<meta name="theme-color" content="#a00a00">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js');
  }
</script>

    <meta charset="UTF-8">
    <title>Panel Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1, h2 {
            text-align: center;
        }
        .card {
            background-color: #111;
            padding: 20px;
            margin: 20px auto;
            border: 1px solid gold;
            border-radius: 10px;
            max-width: 800px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
    </style>
</head>
<?php
$mes_actual = date('m');
$anio_actual = date('Y');

$pagos_q = $conexion->query("
    SELECT fecha
    FROM asistencias_profesor
    WHERE profesor_id = $profesor_id
      AND MONTH(fecha) = $mes_actual
      AND YEAR(fecha) = $anio_actual
    ORDER BY fecha DESC
");

$pagos_dia = [];

while ($p = $pagos_q->fetch_assoc()) {
    $fecha = $p['fecha'];

    $alumnos_q = $conexion->query("
        SELECT COUNT(*) AS total
        FROM asistencias_clientes
        WHERE profesor_id = $profesor_id AND fecha = '$fecha'
    ");

    $alumnos = $alumnos_q->fetch_assoc()['total'];

    // Calcular pago según cantidad de alumnos
    if ($alumnos >= 5) {
        $monto = 2000;
    } elseif ($alumnos >= 2) {
        $monto = 1500;
    } elseif ($alumnos == 1) {
        $monto = 1000;
    } else {
        $monto = 0;
    }

    $pagos_dia[] = [
        'fecha' => $fecha,
        'alumnos' => $alumnos,
        'monto' => $monto
    ];
}

$total_mes = array_sum(array_column($pagos_dia, 'monto'));
?>

<body>

<h1>👨‍🏫 Bienvenido <?= $prof['apellido'] ?>, <?= $prof['nombre'] ?></h1>

<div class="card">
    <h3>📅 Alumnos del día (<?= $fecha_hoy ?>)</h3>
    <?php if ($alumnos->num_rows > 0): ?>
        <table>
            <thead><tr><th>Apellido</th><th>Nombre</th></tr></thead>
            <tbody>
            <?php while ($a = $alumnos->fetch_assoc()): ?>
                <tr><td><?= $a['apellido'] ?></td><td><?= $a['nombre'] ?></td></tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">No hay alumnos registrados hoy.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>💰 Saldo mensual</h3>
    <p style="text-align: center; font-size: 20px;">
        <strong>$<?= number_format($saldo, 2, ',', '.') ?></strong> por <?= $total_horas ?> horas trabajadas
    </p>
</div>

</body>
</html>
