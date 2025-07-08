<?php
session_start();
include 'conexion.php';

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
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 30px;
        }
        h2 {
            color: white;
            text-align: center;
        }
        form {
            max-width: 500px;
            margin: auto;
            background: #111;
            padding: 20px;
            border: 2px solid #555;
            border-radius: 10px;
        }
        label, select, input {
            display: block;
            width: 100%;
            margin-bottom: 15px;
            font-size: 16px;
        }
        input[type="number"] {
            padding: 5px;
        }
        input[type="submit"] {
            background: gold;
            color: black;
            padding: 10px;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .mensaje {
            text-align: center;
            color: lime;
            margin-top: 20px;
        }
    </style>
</head>
<body>

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

</body>
</html>
