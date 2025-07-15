<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener clientes
$clientes = $conexion->query("SELECT id, apellido, nombre, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");

// Obtener productos SOLO de suplementos
$productos = $conexion->query("
    SELECT id, nombre, precio_venta AS venta, stock 
    FROM productos 
    WHERE gimnasio_id = $gimnasio_id AND categoria = 'suplemento'
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Venta de Suplementos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
<h2>🥤 Venta de Suplementos</h2>

<form method="POST" action="formas_pago.php" onsubmit="return prepararTotal()">

    <label>Buscar Cliente:</label>
    <input type="text" id="filtro_cliente" placeholder="Filtrar por apellido o DNI" onkeyup="filtrarClientes()">

    <label>Seleccionar Cliente:</label>
    <select name="cliente_id" id="selector_cliente" required>
        <option value="">-- Seleccionar cliente --</option>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>">
                <?= $c['apellido'] . ' ' . $c['nombre'] ?> (<?= $c['dni'] ?>)
            </option>
        <?php endwhile; ?>
    </select>

    <label><input type="checkbox" name="cliente_temporal" value="1"> Cliente temporal</label>
    <br><br>

    <label>Suplemento:</label>
    <select id="selector-producto">
        <?php while($p = $productos->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" data-nombre="<?= $p['nombre'] ?>" data-precio="<?= $p['venta'] ?>" data-stock="<?= $p['stock'] ?>">
                <?= $p['nombre'] ?> ($<?= $p['venta'] ?> | Stock: <?= $p['stock'] ?>)
            </option>
        <?php endwhile; ?>
    </select>
    <input type="number" id="cantidad" value="1" min="1">
    <button type="button" onclick="agregarProducto()">➕ Agregar</button>

    <table id="tabla-productos">
        <thead>
            <tr>
                <th>Suplemento</th>
                <th>Precio</th>
                <th>Cantidad</th>
                <th>Subtotal</th>
                <th>Quitar</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <input type="hidden" name="tipo_venta" value="suplementos">
    <input type="hidden" name="total" id="total_hidden">
    <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">
    <br><br>
    <button type="submit">Siguiente → Formas de Pago</button>
</form>
</div>

<script>
function filtrarClientes() {
    let filtro = document.getElementById("filtro_cliente").value.toLowerCase();
    let opciones = document.getElementById("selector_cliente").options;
    for (let i = 0; i < opciones.length; i++) {
        let texto = opciones[i].text.toLowerCase();
        opciones[i].style.display = texto.includes(filtro) ? '' : 'none';
    }
}

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
