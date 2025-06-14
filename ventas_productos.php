<?php
include 'conexion.php';
include 'menu.php';

// Obtener lista de productos
$productos = $conexion->query("SELECT id, nombre, categoria, precio_venta FROM productos_proteccion
    UNION
    SELECT id, nombre, categoria, precio_venta FROM productos_indumentaria
    UNION
    SELECT id, nombre, categoria, precio_venta FROM productos_suplemento") or die($conexion->error);

// Obtener lista de clientes
$clientes = $conexion->query("SELECT id, apellido, nombre FROM clientes ORDER BY apellido ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ventas de Productos</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h2 {
      color: #ffc107;
      text-align: center;
    }
    form {
      max-width: 600px;
      margin: auto;
      background: #222;
      padding: 20px;
      border-radius: 10px;
    }
    label {
      display: block;
      margin-top: 10px;
    }
    input, select {
      width: 100%;
      padding: 8px;
      margin-top: 4px;
      border: none;
      border-radius: 5px;
    }
    button {
      margin-top: 15px;
      background-color: #ffc107;
      border: none;
      padding: 10px;
      color: #111;
      font-weight: bold;
      cursor: pointer;
      border-radius: 5px;
    }
  </style>
</head>
<body>

<h2>Registrar Venta de Producto</h2>

<form action="guardar_venta_producto.php" method="POST">
  <label for="cliente_id">Cliente:</label>
  <select name="cliente_id" required>
    <option value="">Seleccionar cliente</option>
    <?php while ($c = $clientes->fetch_assoc()) { ?>
      <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ", " . $c['nombre'] ?></option>
    <?php } ?>
  </select>

  <label for="producto_id">Producto:</label>
  <select name="producto_id" required>
    <option value="">Seleccionar producto</option>
    <?php while ($p = $productos->fetch_assoc()) { ?>
      <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?> (<?= $p['categoria'] ?>) - $<?= $p['precio_venta'] ?></option>
    <?php } ?>
  </select>

  <label for="cantidad">Cantidad:</label>
  <input type="number" name="cantidad" min="1" required>

  <label for="metodo_pago">Método de pago:</label>
  <select name="metodo_pago" required>
    <option value="efectivo">Efectivo</option>
    <option value="transferencia">Transferencia</option>
    <option value="tarjeta_debito">Tarjeta Débito</option>
    <option value="tarjeta_credito">Tarjeta Crédito</option>
    <option value="cuenta_corriente">Cuenta Corriente</option>
  </select>

  <button type="submit">Registrar Venta</button>
</form>

</body>
</html>
