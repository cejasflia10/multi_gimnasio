<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? null;
if (!$profesor_id) {
    die("Acceso denegado.");
}

// Obtener datos del profesor
$profesor_q = $conexion->query("SELECT * FROM profesores WHERE id = $profesor_id");
$profesor = $profesor_q->fetch_assoc();
$nombre_completo = $profesor['nombre'] . ' ' . $profesor['apellido'];

// QR generado (con DNI)
$qr_path = "qr_profesores/{$profesor['dni']}.png";
if (!file_exists($qr_path)) {
    include 'phpqrcode/qrlib.php';
    if (!is_dir('qr_profesores')) mkdir('qr_profesores');
    QRcode::png($profesor['dni'], $qr_path, QR_ECLEVEL_L, 4);
}

// Obtener alumnos anotados hoy
$hoy = date('N'); // día de la semana 1-7
$alumnos_q = $conexion->query("
    SELECT c.nombre, c.apellido, d.nombre AS disciplina, h.hora_inicio
    FROM reservas r
    JOIN clientes c ON r.cliente_id = c.id
    JOIN turnos t ON r.turno_id = t.id
    JOIN dias d ON t.dia_id = d.id
    JOIN horarios h ON t.horario_id = h.id
    WHERE t.profesor_id = $profesor_id AND d.id = $hoy
");

// Obtener asistencias para calcular horas trabajadas
$asistencias = $conexion->query("
    SELECT fecha, COUNT(*) AS alumnos
    FROM asistencias
    WHERE profesor_id = $profesor_id
    GROUP BY fecha
");

$saldo_total = 0;
while ($fila = $asistencias->fetch_assoc()) {
    $alumnos = $fila['alumnos'];
    if ($alumnos == 1) $monto = 1000;
    elseif ($alumnos <= 4) $monto = 2000;
    elseif ($alumnos > 10) $monto = 4000;
    else $monto = 3000;

    $saldo_total += $monto;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .card {
            background-color: #222;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(255,215,0,0.1);
        }

        .qr-img {
            display: block;
            margin: 0 auto;
            width: 150px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid gold;
        }

        th {
            background-color: #333;
        }

        .btn {
            padding: 10px;
            background: gold;
            color: black;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
            width: 100%;
        }

        @media (max-width: 600px) {
            .card { padding: 10px; }
            table, thead, tbody, th, td, tr { font-size: 14px; }
        }
    </style>
</head>
<body>

<h2>Bienvenido Prof. <?= htmlspecialchars($nombre_completo) ?></h2>

<div class="card">
    <h3>🔳 QR Personal</h3>
    <img src="<?= $qr_path ?>" alt="QR Profesor" class="qr-img">
</div>

<div class="card">
    <h3>📋 Alumnos de Hoy</h3>
    <?php if ($alumnos_q->num_rows > 0): ?>
        <table>
            <tr>
                <th>Nombre</th>
                <th>Disciplina</th>
                <th>Hora</th>
            </tr>
            <?php while ($alumno = $alumnos_q->fetch_assoc()): ?>
                <tr>
                    <td><?= $alumno['nombre'] . ' ' . $alumno['apellido'] ?></td>
                    <td><?= $alumno['disciplina'] ?></td>
                    <td><?= $alumno['hora_inicio'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No hay alumnos anotados para hoy.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>💰 Saldo por horas trabajadas</h3>
    <p><strong>Total acumulado:</strong> $<?= number_format($saldo_total, 0, ',', '.') ?></p>
</div>

<!-- Aquí irá la carga de rutinas, archivos y datos físicos -->

</body>
</html>
