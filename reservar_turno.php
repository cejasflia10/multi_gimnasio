
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) {
    die("Acceso denegado.");
}

$hoy = date('Y-m-d');
$membresia = $conexion->query("
    SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id 
      AND fecha_vencimiento >= '$hoy'
      AND clases_disponibles > 0
    ORDER BY fecha_vencimiento DESC LIMIT 1
")->fetch_assoc();

if (!$membresia) {
    die("<h2>No tenés una membresía activa o no tenés clases disponibles.</h2>");
}

$turnos = $conexion->query("
    SELECT t.id, d.nombre AS dia, h.hora_inicio, h.hora_fin, p.apellido AS profesor, t.cupo_maximo,
        (SELECT COUNT(*) FROM reservas WHERE turno_id = t.id AND cliente_id = $cliente_id AND fecha = CURDATE()) AS ya_reservado
    FROM turnos t
    JOIN dias d ON t.dia_id = d.id
    JOIN horarios h ON t.horario_id = h.id
    JOIN profesores p ON t.profesor_id = p.id
    ORDER BY t.dia_id, h.hora_inicio
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reservar Turno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 { text-align: center; }
        .turno {
            border: 1px solid gold;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
        }
        .btn {
            background-color: gold;
            color: black;
            font-weight: bold;
            padding: 10px;
            border: none;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn:disabled {
            background: gray;
            cursor: not-allowed;
        }
        @media (max-width: 600px) {
            .turno { font-size: 16px; }
        }
    </style>
</head>
<body>
    <h1>Reservar Turno</h1>

    <?php while ($t = $turnos->fetch_assoc()): ?>
        <div class="turno">
            <strong><?= $t['dia'] ?> - <?= $t['hora_inicio'] ?> a <?= $t['hora_fin'] ?></strong><br>
            Profesor: <?= $t['profesor'] ?><br>
            Cupo máximo: <?= $t['cupo_maximo'] ?><br>

            <?php if ($t['ya_reservado'] > 0): ?>
                <button class="btn" disabled>Ya reservaste este turno</button>
            <?php else: ?>
                <form action="guardar_reserva.php" method="POST">
                    <input type="hidden" name="turno_id" value="<?= $t['id'] ?>">
                    <button class="btn" type="submit">Reservar</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
</body>
</html>
