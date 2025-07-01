<?php
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
?><?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

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
    $peso = $_POST['peso'];
    $altura = $_POST['altura'];
    $remera = $_POST['talle_remera'];
    $pantalon = $_POST['talle_pantalon'];
    $calzado = $_POST['talle_calzado'];
    $observaciones = $_POST['observaciones'];
    $fecha = date('Y-m-d');

    $stmt = $conexion->prepare("INSERT INTO datos_fisicos (profesor_id, cliente_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssss", $profesor_id, $cliente_id, $fecha, $peso, $altura, $remera, $pantalon, $calzado, $observaciones);
    $stmt->execute();
    $mensaje = "✅ Datos registrados correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Datos Físicos</title>
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
        label, input, select, textarea {
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

<h1>🧍 Registrar Datos Físicos Generales</h1>

<form method="POST">
    <label>Alumno:</label>
    <select name="cliente_id" required>
        <option value="">-- Seleccionar alumno --</option>
        <?php while ($a = $alumnos->fetch_assoc()): ?>
            <option value="<?= $a['id'] ?>"><?= $a['apellido'] ?>, <?= $a['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Peso (kg):</label>
    <input type="text" name="peso" required>

    <label>Altura (cm):</label>
    <input type="text" name="altura" required>

    <label>Talle de Remera:</label>
    <input type="text" name="talle_remera" required>

    <label>Talle de Pantalón:</label>
    <input type="text" name="talle_pantalon" required>

    <label>Talle de Calzado:</label>
    <input type="text" name="talle_calzado" required>

    <label>Observaciones:</label>
    <textarea name="observaciones" rows="4"></textarea>

    <button type="submit">Guardar Datos</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>

</body>
</html>
