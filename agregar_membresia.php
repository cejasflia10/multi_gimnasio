<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
include 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Membresía</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="buscador_cliente.css">
</head>
<body>
  <div class="container">
    <h2>Agregar Membresía</h2>

    <!-- Buscador de cliente -->
    <label for="buscar">Buscar Cliente (DNI, Nombre, Apellido):</label>
    <input type="text" id="buscar" placeholder="Escriba para buscar..." autocomplete="off">
    <div id="resultado_busqueda"></div>

    <form id="formulario_membresia" action="guardar_membresia.php" method="POST">
      <input type="hidden" name="cliente_id" id="cliente_id_seleccionado" required>

      <label for="plan">Plan:</label>
      <select name="plan" id="plan">
        <?php
          $planes = $conexion->query("SELECT id, nombre FROM planes WHERE gimnasio_id = $gimnasio_id");
          while($p = $planes->fetch_assoc()){
              echo "<option value='{$p['id']}'>{$p['nombre']}</option>";
          }
        ?>
      </select>

      <label for="plan_adicional">Plan Adicional:</label>
      <select name="plan_adicional" id="plan_adicional">
        <option value="">Ninguno</option>
        <?php
          $adicionales = $conexion->query("SELECT id, nombre FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
          while($a = $adicionales->fetch_assoc()){
              echo "<option value='{$a['id']}'>{$a['nombre']}</option>";
          }
        ?>
      </select>

      <label for="fecha_inicio">Fecha de Inicio:</label>
      <input type="date" name="fecha_inicio" value="<?php echo date('Y-m-d'); ?>" required>

      <label for="metodo_pago">Método de Pago:</label>
      <select name="metodo_pago" required>
        <option value="efectivo">Efectivo</option>
        <option value="transferencia">Transferencia</option>
        <option value="tarjeta">Tarjeta</option>
        <option value="cuenta_corriente">Cuenta Corriente</option>
      </select>

      <label for="otros_pagos">Otros Pagos:</label>
      <input type="number" name="otros_pagos" value="0">

      <label for="total">Total a Pagar:</label>
      <input type="number" name="total" id="total" readonly>

      <button type="submit">Registrar Membresía</button>
    </form>
    <br>
    <a href="index.php" class="boton-volver">Volver al Panel</a>
  </div>

  <script src="buscador_cliente.js"></script>
</body>
</html>
