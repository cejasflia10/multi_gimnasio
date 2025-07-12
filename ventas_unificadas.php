<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $producto = trim($_POST['producto'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $cliente = trim($_POST['cliente'] ?? '');
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    $precio = floatval($_POST['precio'] ?? 0);

    if ($producto && $categoria && $metodo_pago && $precio > 0) {
        $stmt = $conexion->prepare("INSERT INTO ventas_productos (gimnasio_id, fecha, producto, categoria, cliente, metodo_pago, precio) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssd", $gimnasio_id, $fecha, $producto, $categoria, $cliente, $metodo_pago, $precio);
        if ($stmt->execute()) {
            $mensaje = "✅ Venta registrada correctamente.";
        } else {
            $mensaje = "❌ Error al registrar la venta.";
        }
    } else {
        $mensaje = "❌ Todos los campos son obligatorios.";
    }
}

// Obtener ventas del día
$hoy = date('Y-m-d');
$ventas_q = $conexion->query("SELECT * FROM ventas_productos WHERE gimnasio_id = $gimnasio_id AND fecha = '$hoy' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas de Productos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .formulario, .tabla { max-width: 800px; margin: auto; }
        label, input, select { display: block; margin-top: 10px; width: 100%; }
        input[type="submit"] { background: gold; font-weight: bold; cursor: pointer; margin-top: 20px; }
        table { width: 100%; margin-top: 30px; }
        th, td { padding: 8px; border: 1px solid #444; text-align: center; }
    </style>
</head>
<body>
<div class="contenedor">

<h2 style="text-align:center;">🛒 Registrar Venta de Productos</h2>

<?php if ($mensaje): ?>
    <p style="text-align:center; color:lime;"><?= $mensaje ?></p>
<?php endif; ?>

<form method="POST" class="formulario">
    <label>Fecha:</label>
    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>">

    <label>Producto:</label>
    <input type="text" name="producto" required>

    <label>Categoría:</label>
    <select name="categoria" required>
        <option value="">-- Seleccionar --</option>
        <option value="Indumentaria">Indumentaria</option>
        <option value="Suplemento">Suplemento</option>
        <option value="Otro">Otro</option>
    </select>

    <label>Cliente (opcional):</label>
    <input type="text" name="cliente" placeholder="Nombre o DNI">

    <label>Método de Pago:</label>
    <select name="metodo_pago" required>
        <option value="Efectivo">Efectivo</option>
        <option value="Transferencia">Transferencia</option>
        <option value="Tarjeta Débito">Tarjeta Débito</option>
        <option value="Tarjeta Crédito">Tarjeta Crédito</option>
        <option value="Cuenta Corriente">Cuenta Corriente</option>
    </select>

    <label>Precio:</label>
    <input type="number" name="precio" step="0.01" required>

    <input type="submit" value="Registrar Venta">
</form>

<h3 style="text-align:center;">🧾 Ventas de Hoy</h3>
<table class="tabla">
    <tr>
        <th>Fecha</th>
        <th>Producto</th>
        <th>Categoría</th>
        <th>Cliente</th>
        <th>Método Pago</th>
        <th>Precio</th>
    </tr>
    <?php while ($venta = $ventas_q->fetch_assoc()): ?>
        <tr>
            <td><?= $venta['fecha'] ?></td>
            <td><?= ucfirst($venta['producto']) ?></td>
            <td><?= ucfirst($venta['categoria']) ?></td>
            <td><?= $venta['cliente'] ?></td>
            <td><?= $venta['metodo_pago'] ?></td>
            <td>$<?= number_format($venta['precio'], 0, ',', '.') ?></td>
        </tr>
    <?php endwhile; ?>
</table>

</div>
</body>
</html>
