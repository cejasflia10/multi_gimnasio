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
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
        <h1>🧮 Configurar Precios por Hora</h1>

        <!-- FORM PRINCIPAL: EDITAR y GUARDAR -->
        <form method="post">
            <input type="hidden" name="accion" value="guardar">

            <table border="1">
                <tr>
                    <th>Mín. alumnos</th>
                    <th>Máx. alumnos</th>
                    <th>Precio por turno ($)</th>
                    <th>Eliminar</th>
                </tr>
                <?php if ($precios && $precios->num_rows > 0): ?>
                    <?php while ($p = $precios->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <input type="number" name="rango_min[]" value="<?= $p['rango_min'] ?>" required>
                                <input type="hidden" name="id[]" value="<?= $p['id'] ?>">
                            </td>
                            <td><input type="number" name="rango_max[]" value="<?= $p['rango_max'] ?>" required></td>
                            <td><input type="number" step="0.01" name="precio[]" value="<?= $p['precio'] ?>" required></td>
                            <td>
                                <form method="post" onsubmit="return confirm('¿Eliminar este precio?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </table>

            <br>
            <button type="submit">💾 Guardar Cambios</button>
        </form>

        <h2>➕ Agregar Nuevo Rango</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="agregar">
            Desde: <input type="number" name="nuevo_min" required>
            Hasta: <input type="number" name="nuevo_max" required>
            Precio: <input type="number" step="0.01" name="nuevo_precio" required>
            <button type="submit">Agregar</button>
        </form>
    </div>
</body>
</html>
