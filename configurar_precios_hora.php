<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$precios = $conexion->query("SELECT * FROM precio_hora WHERE gimnasio_id = $gimnasio_id ORDER BY rango_min ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Precios por Hora</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h1>🧮 Configurar Precios por Hora</h1>

    <!-- FORM PARA EDITAR Y ELIMINAR -->
    <form method="POST" action="guardar_y_eliminar_precios.php">
        <table border="1">
            <tr>
                <th>Mín. alumnos</th>
                <th>Máx. alumnos</th>
                <th>Precio por turno ($)</th>
                <th>Eliminar</th>
            </tr>
            <?php while ($p = $precios->fetch_assoc()): ?>
                <tr>
                    <td>
                        <input type="number" name="rango_min[]" value="<?= $p['rango_min'] ?>" required>
                        <input type="hidden" name="id[]" value="<?= $p['id'] ?>">
                    </td>
                    <td><input type="number" name="rango_max[]" value="<?= $p['rango_max'] ?>" required></td>
                    <td><input type="number" step="0.01" name="precio[]" value="<?= $p['precio'] ?>" required></td>
                    <td>
                        <input type="checkbox" name="eliminar_ids[]" value="<?= $p['id'] ?>">
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
        <br>
        <button type="submit" name="accion" value="guardar">💾 Guardar Cambios</button>
        <button type="submit" name="accion" value="eliminar" onclick="return confirm('¿Estás seguro de eliminar los seleccionados?')">🗑️ Eliminar Seleccionados</button>
    </form>

    <h2>➕ Agregar Nuevo Rango</h2>
    <form method="POST" action="agregar_precio.php">
        Desde: <input type="number" name="nuevo_min" required>
        Hasta: <input type="number" name="nuevo_max" required>
        Precio: <input type="number" step="0.01" name="nuevo_precio" required>
        <button type="submit">Agregar</button>
    </form>
</div>
</body>
</html>
