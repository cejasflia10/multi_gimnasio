<?php
session_start();
include 'conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);
    $consulta = $conexion->prepare("SELECT c.id, c.nombre, c.apellido, c.dni, c.disciplina, m.fecha_vencimiento 
                                     FROM clientes c 
                                     LEFT JOIN membresias m ON c.id = m.cliente_id 
                                     WHERE c.dni = ? AND m.activa = 1 AND m.fecha_vencimiento >= CURDATE()
                                     ORDER BY m.fecha_vencimiento DESC LIMIT 1");
    $consulta->bind_param("s", $dni);
    $consulta->execute();
    $resultado = $consulta->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_nombre'] = $cliente['nombre'] . ' ' . $cliente['apellido'];
        header("Location: panel_cliente.php");
        exit;
    } else {
        $mensaje = "DNI inválido o membresía no activa.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Acceso del Cliente</title>
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 60px;
    }

    .formulario {
      background-color: #222;
      padding: 20px;
      border-radius: 10px;
      width: 90%;
      max-width: 400px;
      box-shadow: 0 0 10px gold;
    }

    h2 {
      text-align: center;
      color: gold;
    }

    label {
      display: block;
      margin: 10px 0 5px;
    }

    input {
      width: 100%;
      padding: 10px;
      border: none;
      border-radius: 5px;
      margin-bottom: 15px;
      font-size: 16px;
    }

    button {
      width: 100%;
      background-color: gold;
      color: #000;
      font-weight: bold;
      padding: 12px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .error {
      color: red;
      text-align: center;
      margin-top: 10px;
    }

    .logo {
      max-width: 150px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <img src="logo.png" alt="Logo Gimnasio" class="logo" />
  <div class="formulario">
    <h2>Ingreso de Clientes</h2>
    <form method="POST">
      <label for="dni">DNI:</label>
      <input type="text" name="dni" id="dni" required />
      <button type="submit">Ingresar</button>
    </form>
    <?php if ($mensaje): ?>
      <p class="error"><?= $mensaje ?></p>
    <?php endif; ?>
  </div>
</body>
</html>
