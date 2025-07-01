<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

// Obtener lista de alumnos del profesor
$alumnos = $conexion->query("
    SELECT id, apellido, nombre FROM clientes WHERE gimnasio_id = \$_SESSION['gimnasio_id']
    ORDER BY c.apellido
");

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $peso = $_POST['peso'];
    $altura = $_POST['altura'];
    $observaciones = $_POST['observaciones'];
    $fecha = date('Y-m-d');

    $stmt = $conexion->prepare("INSERT INTO progreso_fisico (profesor_id, cliente_id, fecha, peso, altura, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $profesor_id, $cliente_id, $fecha, $peso, $altura, $observaciones);
    $stmt->execute();

    $mensaje = "✅ Progreso registrado correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Progreso Físico</title>
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
        textarea {
            resize: vertical;
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

<h1>⚖️ Registrar Progreso Físico</h1>

<form method="POST">
    <label>Seleccionar Alumno:</label>
    <select name="cliente_id" required>
        <option value="">-- Elegir alumno --</option>
        <?php while ($a = $alumnos->fetch_assoc()): ?>
            <option value="<?= $a['id'] ?>"><?= $a['apellido'] ?>, <?= $a['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Peso (kg):</label>
    <input type="text" name="peso" required>

    <label>Altura (cm):</label>
    <input type="text" name="altura" required>

    <label>Observaciones:</label>
    <textarea name="observaciones" rows="4"></textarea>

    <button type="submit">Registrar Progreso</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>

</body>
</html>
