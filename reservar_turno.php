<?php
session_start();
include("conexion.php");

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener todos los turnos disponibles (puedes filtrar por disciplina si querés)
$turnos = $conexion->query("
    SELECT t.*, p.apellido AS profesor 
    FROM turnos t 
    JOIN profesores p ON t.profesor_id = p.id 
    WHERE t.gimnasio_id = $gimnasio_id
    ORDER BY FIELD(t.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'), t.hora_inicio
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
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .turno {
            background-color: #222;
            border: 1px solid #444;
            border-radius: 10px;
            margin: 10px 0;
            padding: 15px;
            box-shadow: 0 0 10px #000;
        }
        .turno strong {
            color: #fff;
        }
        .btn-reservar {
            background-color: gold;
            color: #000;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            width: 100%;
        }
        .btn-reservar:hover {
            background-color: #ffc107;
        }
        @media screen and (max-width: 600px) {
            .turno {
                padding: 12px;
                font-size: 14px;
            }
            .btn-reservar {
                font-size: 14px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>

<h2>Reservar Turno</h2>

<?php while ($turno = $turnos->fetch_assoc()): ?>
    <form method="POST" action="reservar_turno.php">
        <div class="turno">
            <div><strong>Día:</strong> <?= $turno['dia'] ?></div>
            <div><strong>Hora:</strong> <?= substr($turno['hora_inicio'], 0, 5) ?> a <?= substr($turno['hora_fin'], 0, 5) ?></div>
            <div><strong>Profesor:</strong> <?= $turno['profesor'] ?></div>
            <div><strong>Cupos:</strong> <?= $turno['cupos'] ?></div>
            <input type="hidden" name="turno_id" value="<?= $turno['id'] ?>">
            <button type="submit" class="btn-reservar">Reservar</button>
        </div>
    </form>
<?php endwhile; ?>

</body>
</html>
