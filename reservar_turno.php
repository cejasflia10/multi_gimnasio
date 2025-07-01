<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");

$hoy = date('Y-m-d');

$fechas_semana = [
    'Lunes' => '2025-06-30',
    'Martes' => '2025-07-01',
    'Miércoles' => '2025-07-02',
    'Jueves' => '2025-07-03',
    'Viernes' => '2025-07-04',
    'Sábado' => '2025-07-05',
];

$membresia = $conexion->query("
    SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id 
      AND fecha_vencimiento >= '$hoy'
      AND clases_disponibles > 0
    ORDER BY fecha_vencimiento DESC LIMIT 1
")->fetch_assoc();

if (!$membresia) {
    die("<h2 style='color: gold; text-align: center;'>No tenés una membresía activa o no tenés clases disponibles.</h2>");
}

$turnos = $conexion->query("
    SELECT 
        t.id,
        t.dia,
        h.hora_inicio,
        h.hora_fin,
        p.apellido AS profesor,
        t.cupo_maximo
    FROM turnos t
    JOIN horarios h ON t.id_horario = h.id
    JOIN profesores p ON t.id_profesor = p.id
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📅 Reservar Turno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; }
        .turno {
            background-color: #111;
            border: 1px solid gold;
            border-radius: 10px;
            padding: 20px;
            margin: 15px auto;
            max-width: 600px;
            box-shadow: 0 0 10px rgba(255,215,0,0.2);
        }
        .btn {
            background-color: gold;
            color: black;
            font-weight: bold;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn:disabled {
            background: gray;
            cursor: not-allowed;
        }
        @media (max-width: 600px) {
            .btn { width: 100%; }
        }
    </style>
</head>
<body>

<h1>📅 Reservar Turno</h1>

<?php while ($t = $turnos->fetch_assoc()): 
    $dia_normalizado = ucfirst(strtolower(trim($t['dia'])));
    $fecha_turno = $fechas_semana[$dia_normalizado] ?? 'Fecha no asignada';

    $ya = $conexion->query("SELECT 1 FROM reservas WHERE cliente_id = $cliente_id AND turno_id = {$t['id']} AND fecha = '$fecha_turno'");
    $ya_reservado = $ya->num_rows > 0;
?>
    <div class="turno">
        <strong><?= $dia_normalizado ?> (<?= $fecha_turno ?>) - <?= $t['hora_inicio'] ?> a <?= $t['hora_fin'] ?></strong><br>
        Profesor: <b><?= $t['profesor'] ?></b><br>
        Cupo máximo: <?= $t['cupo_maximo'] ?><br>

        <?php if ($ya_reservado): ?>
            <button class="btn" disabled>Ya reservaste este turno</button>
        <?php else: ?>
            <form action="guardar_reserva.php" method="POST">
                <input type="hidden" name="turno_id" value="<?= $t['id'] ?>">
                <input type="hidden" name="fecha" value="<?= $fecha_turno ?>">
                <button class="btn" type="submit">Reservar</button>
            </form>
        <?php endif; ?>
    </div>
<?php endwhile; ?>

</body>
</html>
