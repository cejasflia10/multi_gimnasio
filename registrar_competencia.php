<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

// Obtener alumnos del profesor
$alumnos = $conexion->query("
    SELECT DISTINCT c.id, c.apellido, c.nombre
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.id_profesor = $profesor_id
    ORDER BY c.apellido
");

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $fecha = $_POST['fecha'];
    $evento = $_POST['evento'];
    $resultado = $_POST['resultado'];
    $obs = $_POST['observaciones'];

    $stmt = $conexion->prepare("INSERT INTO competencias (profesor_id, cliente_id, fecha, evento, resultado, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $profesor_id, $cliente_id, $fecha, $evento, $resultado, $obs);
    $stmt->execute();
    $mensaje = "✅ Competencia registrada correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Competencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; }
        form {
            max-width: 600px;
            margin: auto;
            background: #111;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid gold;
        }
        label, input, textarea, select {
            display: block;
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            border: none;
        }
        button {
            margin-top: 20px;
            background: gold;
            color: black;
            font-weight: bold;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
        }
        .mensaje {
            text-align: center;
            margin-top: 15px;
            color: lightgreen;
        }
    </style>
</head>
<body>

<h1>🏆 Registrar Competencia</h1>

<form method="POST">
    <label>Alumno:</label>
    <select name="cliente_id" required>
        <option value="">-- Seleccionar alumno --</option>
        <?php while ($a = $alumnos->fetch_assoc()): ?>
            <option value="<?= $a['id'] ?>"><?= $a['apellido'] ?>, <?= $a['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Fecha:</label>
    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>

    <label>Evento / Torneo:</label>
    <input type="text" name="evento" required>

    <label>Resultado / Medalla:</label>
    <input type="text" name="resultado" required>

    <label>Observaciones:</label>
    <textarea name="observaciones" rows="4"></textarea>

    <button type="submit">Guardar Competencia</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>

</body>
</html>
