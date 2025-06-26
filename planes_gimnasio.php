<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';
include 'permisos.php';

if (!tiene_permiso('configuraciones')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

if (!isset($_SESSION['gimnasio_id'])) {
    die("<h2 style='color:red;'>⛔ Gimnasio no identificado.</h2>");
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$mensaje = "";

// Agregar plan
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $clases = intval($_POST['clases']);

    if (!empty($nombre) && $precio > 0 && $clases > 0) {
        $stmt = $conexion->prepare("INSERT INTO planes_gimnasio (nombre, precio, clases, gimnasio_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdii", $nombre, $precio, $clases, $gimnasio_id);

        if ($stmt->execute()) {
            $mensaje = "✅ Plan agregado correctamente.";
        } else {
            $mensaje = "❌ Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
    }
}

// Obtener planes del gimnasio
$planes = $conexion->query("SELECT * FROM planes_gimnasio WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Planes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 { text-align: center; margin-bottom: 20px; }
        form {
            max-width: 500px;
            margin: 0 auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid gold;
        }
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            background-color: #000;
            border: 1px solid gold;
            color: gold;
        }
        input[type="submit"] {
            margin-top: 15px;
            padding: 10px;
            background-color: gold;
            border: none;
            color: #111;
            font-weight: bold;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #ffd700;
        }
        .mensaje {
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
        }
        .volver {
            display: block;
            text-align: center;
            margin-top: 20px;
        }
        .volver a {
            color: gold;
            text-decoration: underline;
        }
        table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            border: 1px solid gold;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        tr:nth-child(even) {
            background-color: #1a1a1a;
        }
        a.boton {
            padding: 5px 10px;
            background-color: gold;
            color: #111;
            text-decoration: none;
            font-weight: bold;
            border-radius: 4px;
        }
        a.boton:hover {
            background-color: #ffd700;
        }
    </style>
</head>
<body>

    <h1>Configurar Planes del Gimnasio</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="nombre">Nombre del Plan:</label>
        <input type="text" name="nombre" required>

        <label for="precio">Precio:</label>
        <input type="number" name="precio" step="0.01" required>

        <label for="clases">Clases disponibles:</label>
        <input type="number" name="clases" required>

        <input type="submit" value="Guardar Plan">
    </form>

    <h2 style="text-align:center; margin-top:40px;">Planes Actuales</h2>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Precio</th>
            <th>Clases</th>
            <th>Acciones</th>
        </tr>
        <?php while($plan = $planes->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($plan['nombre']) ?></td>
            <td>$<?= number_format($plan['precio'], 2) ?></td>
            <td><?= $plan['clases'] ?></td>
            <td>
                <a class="boton" href="editar_plan.php?id=<?= $plan['id'] ?>">Editar</a>
                <a class="boton" href="eliminar_plan.php?id=<?= $plan['id'] ?>" onclick="return confirm('¿Eliminar este plan?')">Eliminar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <div class="volver">
        <a href="index.php">← Volver al panel</a>
    </div>

</body>
</html>
