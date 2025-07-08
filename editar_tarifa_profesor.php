<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$mensaje = '';

// Guardar tarifa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profesor_id = intval($_POST['profesor_id']);
    $valor_hora = floatval($_POST['valor_hora']);

    // Insertar o actualizar tarifa
    $check = $conexion->query("SELECT id FROM tarifas_profesor WHERE profesor_id = $profesor_id");
    if ($check->num_rows > 0) {
        $conexion->query("UPDATE tarifas_profesor SET valor_hora = $valor_hora WHERE profesor_id = $profesor_id");
    } else {
        $conexion->query("INSERT INTO tarifas_profesor (profesor_id, valor_hora) VALUES ($profesor_id, $valor_hora)");
    }

    $mensaje = "✅ Tarifa actualizada correctamente.";
}

// Obtener profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Tarifas Profesores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">

<h2>💰 Asignar Tarifa por Hora a Profesores</h2>

<form method="POST">
    <label>Profesor:</label>
    <select name="profesor_id" required>
        <option value="">-- Seleccionar --</option>
        <?php while ($p = $profesores->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>">
                <?= $p['apellido'] . ' ' . $p['nombre'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Valor por hora ($):</label>
    <input type="number" name="valor_hora" step="0.01" min="0" required>

    <input type="submit" value="Guardar Tarifa">
</form>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= $mensaje ?></div>
<?php endif; ?>
</div>

</body>
</html>
