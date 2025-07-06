<?php
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Filtro por tipo
$tipo = $_GET['tipo'] ?? '';
$condicion = "WHERE gimnasio_id = $gimnasio_id";
if (!empty($tipo)) {
    $condicion .= " AND tipo = '$tipo'";
}

// Obtener tipos distintos para el filtro
$tipos = $conexion->query("SELECT DISTINCT tipo FROM suplementos WHERE gimnasio_id = $gimnasio_id");

// Traer suplementos
$suplementos = $conexion->query("SELECT * FROM suplementos $condicion ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Suplementos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">

    <h2>🥤 Suplementos</h2>

    <form method="GET">
        <label>Filtrar por tipo:</label>
        <select name="tipo" onchange="this.form.submit()">
            <option value="">-- Todos --</option>
            <?php while ($fila = $tipos->fetch_assoc()): ?>
                <option value="<?= $fila['tipo'] ?>" <?= $tipo == $fila['tipo'] ? 'selected' : '' ?>>
                    <?= ucfirst($fila['tipo']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Compra</th>
                <th>Venta</th>
                <th>Stock</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($s = $suplementos->fetch_assoc()): ?>
            <tr>
                <td data-label="Nombre"><?= $s['nombre'] ?></td>
                <td data-label="Tipo"><?= $s['tipo'] ?></td>
                <td data-label="Compra">$<?= number_format($s['precio_compra'], 2) ?></td>
                <td data-label="Venta">$<?= number_format($s['precio_venta'], 2) ?></td>
                <td data-label="Stock"><?= $s['stock'] ?></td>
                <td data-label="Acciones">
                    <a class="btn-editar" href="editar_suplemento.php?id=<?= $s['id'] ?>">✏️</a>
                    <a class="btn-eliminar" href="eliminar_suplemento.php?id=<?= $s['id'] ?>" onclick="return confirm('¿Eliminar este suplemento?')">🗑️</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

</div>
</body>
</html>
