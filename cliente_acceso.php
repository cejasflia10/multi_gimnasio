<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST['dni']);

    $sql = "SELECT c.*, m.fecha_vencimiento 
            FROM clientes c
            LEFT JOIN membresias m ON m.cliente_id = c.id AND m.activa = 1
            WHERE c.dni = ? 
            ORDER BY m.fecha_vencimiento DESC LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $hoy = date('Y-m-d');

        if ($cliente['fecha_vencimiento'] >= $hoy) {
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['cliente_nombre'] = $cliente['nombre'];
            $_SESSION['cliente_apellido'] = $cliente['apellido'];
            header("Location: panel_cliente.php");
            exit();
        } else {
            $error = "Tu membresía está vencida.";
        }
    } else {
        $error = "DNI no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso Cliente</title>
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .formulario {
      background-color: #222;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 10px gold;
      width: 100%;
      max-width: 400px;
    }
    h2 {
      margin-bottom: 20px;
      text-align: center;
    }
    input[type="text"] {
      width: 100%;
      padding: 12px;
      margin-top: 10px;
      border: none;
      border-radius: 5px;
      font-size: 16px;
    }
    button {
      width: 100%;
      margin-top: 15px;
      padding: 12px;
      background-color: gold;
      border: none;
      color: #000;
      font-weight: bold;
      font-size: 16px;
      border-radius: 5px;
      cursor: pointer;
    }
    .error {
      margin-top: 10px;
      color: red;
      text-align: center;
    }
    .logo {
      width: 80px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <div class="formulario">
    <div style="text-align: center;">
      <img src="logo.png" alt="Logo" class="logo">
      <h2>Acceso Cliente</h2>
    </div>
    <form method="POST">
      <label for="dni">Ingrese su DNI:</label>
      <input type="text" name="dni" id="dni" required>
      <button type="submit">Ingresar</button>
    </form>
    <?php if (isset($error)): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
