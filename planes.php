<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

$mensaje = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST["nombre"] ?? '';
    $precio = $_POST["precio"] ?? '';
    $dias = $_POST["dias_disponibles"] ?? '';
    $duracion = $_POST["duracion_meses"] ?? '';
    $gimnasio_id = $_SESSION['gimnasio_id'];

    $stmt = $conexion->prepare("INSERT INTO planes (nombre, precio, dias_disponibles, duracion_meses, gimnasio_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdsii", $nombre, $precio, $dias, $duracion, $gimnasio_id);
    if ($stmt->execute()) {
        $mensaje = "Plan agregado correctamente.";
    } else {
        $mensaje = "Error al agregar plan: " . $stmt->error;
    }
    $stmt->close();
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$resultado = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h1 { text-align: center; }
        .formulario, .tabla {
            max-width: 600px;
            margin: auto;
        }
        input, select, button {
            width: 100%;
            margin: 5px 0;
            padding: 10px;
            border: 1px solid #FFD700;
            background-color: #222;
            color: #FFD700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #FFD700;
            padding: 8px;
            text-align: center;
        }
        .acciones button {
            padding: 5px 10px;
            background-color: #FFD700;
            color: #111;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Planes</h1>
    <div class="formulario">
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre del plan" required>
            <input type="number" step="0.01" name="precio" placeholder="Precio" required>
            <input type="text" name="dias_disponibles" placeholder="Días disponibles" required>
            <input type="number" name="duracion_meses" placeholder="Duración (meses)" required>
            <button type="submit">Agregar Plan</button>
        </form>
        <?php if ($mensaje) echo "<p>$mensaje</p>"; ?>
    </div>
    <div class="tabla">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Precio</th>
                    <th>Días disponibles</th>
                    <th>Duración (meses)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                        <td>$<?= number_format($row['precio'], 2) ?></td>
                        <td><?= htmlspecialchars($row['dias_disponibles']) ?></td>
                        <td><?= htmlspecialchars($row['duracion_meses']) ?></td>
                        <td class="acciones"><button onclick="location.href='eliminar_plan.php?id=<?= $row['id'] ?>'">Eliminar</button></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
