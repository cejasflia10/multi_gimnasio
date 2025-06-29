<?php
include 'conexion.php';
session_start();

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id']);
        $conexion->query("DELETE FROM precio_hora WHERE id = $id AND gimnasio_id = $gimnasio_id");
    } elseif (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
        $min = intval($_POST['nuevo_min']);
        $max = intval($_POST['nuevo_max']);
        $precio = floatval($_POST['nuevo_precio']);
        $conexion->query("INSERT INTO precio_hora (gimnasio_id, rango_min, rango_max, precio) VALUES ($gimnasio_id, $min, $max, $precio)");
    } else {
        foreach ($_POST['rango_min'] as $index => $min) {
            $min = intval($min);
            $max = intval($_POST['rango_max'][$index]);
            $precio = floatval($_POST['precio'][$index]);
            $id = intval($_POST['id'][$index]);

            if ($id > 0) {
                $conexion->query("UPDATE precio_hora SET rango_min = $min, rango_max = $max, precio = $precio WHERE id = $id AND gimnasio_id = $gimnasio_id");
            }
        }
    }
    header("Location: configurar_precios_hora.php");
    exit;
}

$precios = $conexion->query("SELECT * FROM precio_hora WHERE gimnasio_id = $gimnasio_id ORDER BY rango_min ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Precio por Hora</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        th {
            background: #222;
        }
        input[type="number"] {
            width: 80px;
        }
        input[type="submit"], button {
            margin-top: 15px;
            padding: 8px 16px;
            font-size: 14px;
            background-color: gold;
            border: none;
            cursor: pointer;
        }
        .eliminar {
            background-color: red;
            color: white;
        }
    </style>
</head>
<body>
    <h1>Configurar Precios por Hora</h1>
    <form method="POST">
        <table>
            <tr>
                <th>Desde</th>
                <th>Hasta</th>
                <th>Precio por Hora ($)</th>
                <th>Eliminar</th>
            </tr>
            <?php while ($row = $precios->fetch_assoc()): ?>
                <tr>
                    <td>
                        <input type="hidden" name="id[]" value="<?= $row['id'] ?>">
                        <input type="number" name="rango_min[]" value="<?= $row['rango_min'] ?>">
                    </td>
                    <td><input type="number" name="rango_max[]" value="<?= $row['rango_max'] ?>"></td>
                    <td><input type="number" step="0.01" name="precio[]" value="<?= $row['precio'] ?>"></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit" class="eliminar">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
        <input type="submit" value="Guardar Cambios">
    </form>

    <h2>Agregar Nuevo Rango</h2>
    <form method="POST">
        <input type="hidden" name="accion" value="agregar">
        Desde: <input type="number" name="nuevo_min" required>
        Hasta: <input type="number" name="nuevo_max" required>
        Precio: <input type="number" step="0.01" name="nuevo_precio" required>
        <button type="submit">Agregar</button>
    </form>
</body>
</html>
