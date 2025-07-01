<?php
include 'conexion.php';
include 'menu_horizontal.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener clientes
$clientes = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE gimnasio_id = $gimnasio_id ORDER BY apellido");

// Obtener suplementos
$productos = $conexion->query("SELECT id, nombre, precio_venta FROM productos_suplemento WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrar Venta de Suplemento</title>
  <style>
    body {
      background: #000;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 600px;
      margin: 30px auto;
      background-color: #111;
      padding: 20px;
      border-radius: 10px;
    }

    h2 {
      text-align: center;
      color: #ffc107;
    }

    label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
    }

    select, input {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border-radius: 5px;
      border: none;
    }

    .btn {
      margin-top: 20px;
      width: 100%;
      padding: 10px;
      background: #ffc107;
      color: #000;
      border: none;
      border-radius: 5px;
      font-weight: bold;
      cursor: pointer;
    }
  </style>
  <script>
    function actualizarTotal() {
      const precio = parseFloat(document.getElementById('precio').value) || 0;
      const cantidad = parseInt(document.getElementById('cantidad').value) || 0;
      document.getElementById('total').value = (precio * cantidad).toFixed(2);
    }

    function cargarPrecio() {
      const selector = document.getElementById('producto');
      const precio = selector.options[selector.selectedIndex].dataset.precio;
      document.getElementById('precio').value = precio;
      actualizarTotal();
    }
  </script>
</head>
<script src="fullscreen.js"></script>

<body>

<div class="container">
  <h2>Registrar Venta de Suplemento</h2>

  <form action="guardar_venta_suplemento.php" method="POST">
    <label for="cliente">Cliente:</label>
    <select name="cliente_id" id="cliente" required>
      <option value="">Seleccionar cliente</option>
      <?php while ($c = $clientes->fetch_assoc()): ?>
        <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ' ' . $c['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label for="producto">Suplemento:</label>
    <select name="producto_id" id="producto" onchange="cargarPrecio()" required>
      <option value="">Seleccionar suplemento</option>
      <?php while ($p = $productos->fetch_assoc()): ?>
        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio_venta'] ?>"><?= $p['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label for="precio">Precio Unitario:</label>
    <input type="number" id="precio" name="precio" step="0.01" readonly>

    <label for="cantidad">Cantidad:</label>
    <input type="number" id="cantidad" name="cantidad" min="1" onchange="actualizarTotal()" onkeyup="actualizarTotal()" required>

    <label for="metodo">Método de Pago:</label>
    <select name="metodo_pago" id="metodo" required>
      <option value="">Seleccionar método</option>
      <option value="Efectivo">Efectivo</option>
      <option value="Transferencia">Transferencia</option>
      <option value="Tarjeta Débito">Tarjeta Débito</option>
      <option value="Tarjeta Crédito">Tarjeta Crédito</option>
      <option value="Cuenta Corriente">Cuenta Corriente</option>
    </select>

    <label for="total">Total:</label>
    <input type="text" id="total" name="total" readonly>

    <button type="submit" class="btn">Registrar Venta</button>
  </form>
</div>

</body>
</html>
