<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$productos = $conexion->query("
    SELECT id, nombre, venta AS precio_venta, stock, categoria FROM productos WHERE gimnasio_id = $gimnasio_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Venta de Productos</title>
    <style>
        body { background-color: #000; color: gold; font-family: sans-serif; padding: 20px; }
        input, select, button { padding: 6px; margin: 4px; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #555; text-align: center; }
    </style>
</head>
<body>
<h2>🛒 Venta de Productos</h2>

<form method="POST" action="formas_pago.php" onsubmit="return prepararTotal()">
    <label>Cliente:</label>
    <input type="text" name="cliente_nombre" placeholder="Apellido o DNI" required>
    <label><input type="checkbox" name="cliente_temporal" value="1"> Cliente temporal</label>
    <br><br>

    <label>Producto:</label>
    <select id="selector-producto">
        <?php while($p = $productos->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" data-nombre="<?= $p['nombre'] ?>" data-precio="<?= $p['precio_venta'] ?>" data-stock="<?= $p['stock'] ?>">
                <?= $p['categoria'] ?> - <?= $p['nombre'] ?> ($<?= $p['precio_venta'] ?> | Stock: <?= $p['stock'] ?>)
            </option>
        <?php endwhile; ?>
    </select>
    <input type="number" id="cantidad" value="1" min="1">
    <button type="button" onclick="agregarProducto()">➕ Agregar</button>

    <table id="tabla-productos">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Precio</th>
                <th>Cantidad</th>
                <th>Subtotal</th>
                <th>Quitar</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <input type="hidden" name="total" id="total_hidden">
    <br><br>
    <button type="submit">Siguiente → Formas de Pago</button>
</form>

<script>
function agregarProducto() {
    const selector = document.getElementById("selector-producto");
    const selected = selector.options[selector.selectedIndex];
    const nombre = selected.dataset.nombre;
    const precio = parseFloat(selected.dataset.precio);
    const stock = parseInt(selected.dataset.stock);
    const cantidad = parseInt(document.getElementById("cantidad").value);

    if (cantidad > stock) {
        alert("Stock insuficiente.");
        return;
    }

    const tbody = document.querySelector("#tabla-productos tbody");
    const tr = document.createElement("tr");
    tr.innerHTML = `
        <td>${nombre}<input type="hidden" name="producto_nombre[]" value="${nombre}"></td>
        <td>$${precio.toFixed(2)}<input type="hidden" name="precio[]" value="${precio}"></td>
        <td><input type="hidden" name="cantidad[]" value="${cantidad}">${cantidad}</td>
        <td>$${(precio * cantidad).toFixed(2)}</td>
        <td><button type="button" onclick="this.closest('tr').remove();actualizarTotal()">❌</button></td>
    `;
    tbody.appendChild(tr);
    actualizarTotal();
}

function actualizarTotal() {
    let total = 0;
    document.querySelectorAll("tbody tr").forEach(row => {
        const precio = parseFloat(row.querySelector('input[name="precio[]"]').value);
        const cantidad = parseInt(row.querySelector('input[name="cantidad[]"]').value);
        total += precio * cantidad;
    });
    document.getElementById("total_hidden").value = total.toFixed(2);
}

function prepararTotal() {
    actualizarTotal();
    return true;
}
</script>
</body>
</html>
