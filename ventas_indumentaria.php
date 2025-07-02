<?php
include 'conexion.php';
include 'menu_horizontal.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$clientes = $conexion->query("SELECT id, dni, apellido, nombre FROM clientes WHERE gimnasio_id = $gimnasio_id");
$productos = $conexion->query("SELECT id, nombre, precio_venta, stock FROM productos_indumentaria WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Venta de Indumentaria</title>
    <style>
        body { background-color: #000; color: gold; font-family: sans-serif; padding: 20px; }
        input, select, button { padding: 8px; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #444; text-align: center; }
    </style>
</head>
<body>
<h2>👕 Venta de Indumentaria</h2>

<form method="POST" action="formas_pago.php" onsubmit="return calcularTotal();">
    <label>Buscar Cliente:</label><br>
    <input list="clientes" name="cliente_nombre" required>
    <datalist id="clientes">
        <?php while($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['apellido'] . ', ' . $c['nombre'] . ' - DNI: ' . $c['dni'] ?>">
        <?php endwhile; ?>
    </datalist>
    <label><input type="checkbox" name="cliente_temporal" value="1"> Cliente temporal</label>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Cantidad</th>
                <th>Quitar</th>
            </tr>
        </thead>
        <tbody id="tabla-productos">
            <?php while($p = $productos->fetch_assoc()): ?>
            <tr>
                <td><?= $p['nombre'] ?>
                    <input type="hidden" name="producto_id[]" value="<?= $p['id'] ?>">
                </td>
                <td>$<?= number_format($p['precio_venta'], 2) ?>
                    <input type="hidden" name="precio[]" value="<?= $p['precio_venta'] ?>" class="precio" data-precio="<?= $p['precio_venta'] ?>">
                </td>
                <td><?= $p['stock'] ?></td>
                <td><input type="number" name="cantidad[]" value="1" min="1" max="<?= $p['stock'] ?>" class="cantidad" required></td>
                <td><button type="button" onclick="this.closest('tr').remove()">❌</button></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <input type="hidden" name="total" id="total_hidden">
    <br><button type="submit">Siguiente → Formas de Pago</button>
</form>

<script>
function calcularTotal() {
    let total = 0;
    document.querySelectorAll('.precio').forEach((el, i) => {
        const precio = parseFloat(el.dataset.precio);
        const cantidad = parseInt(document.querySelectorAll('.cantidad')[i].value);
        total += precio * cantidad;
    });
    document.getElementById('total_hidden').value = total.toFixed(2);
    return true;
}
</script>
</body>
</html>
