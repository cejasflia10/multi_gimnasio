<?php
include 'conexion.php';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST["nombre"];
    $direccion = $_POST["direccion"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $duracion = $_POST["duracion_plan"];
    $limite = $_POST["limite_clientes"];
    $panel = isset($_POST["acceso_panel"]) ? 1 : 0;
    $ventas = isset($_POST["acceso_ventas"]) ? 1 : 0;
    $asistencias = isset($_POST["acceso_asistencias"]) ? 1 : 0;

    $stmt = $conexion->prepare("INSERT INTO gimnasios (nombre, direccion, telefono, email, duracion_plan, limite_clientes, acceso_panel, acceso_ventas, acceso_asistencias) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiIIII", $nombre, $direccion, $telefono, $email, $duracion, $limite, $panel, $ventas, $asistencias);
    $stmt->execute();
    header("Location: gimnasios.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Gimnasio</title>
  <style>
    body { background-color: #111; color: #f1f1f1; font-family: Arial; padding: 20px; }
    form { background-color: #222; padding: 20px; border-radius: 10px; max-width: 600px; }
    input, label { display: block; width: 100%; margin-top: 10px; }
    input[type=checkbox] { width: auto; }
    button { margin-top: 15px; padding: 10px; background: gold; color: black; border: none; border-radius: 5px; font-weight: bold; }
  </style>
</head>
<body>
  <h2>Agregar Gimnasio</h2>
  <form method="post">
    <label>Nombre: <input type="text" name="nombre" required></label>
    <label>Dirección: <input type="text" name="direccion" required></label>
    <label>Teléfono: <input type="text" name="telefono" required></label>
    <label>Email: <input type="email" name="email" required></label>
    <label>Duración del plan (días): <input type="number" name="duracion_plan" value="30"></label>
    <label>Límite de clientes: <input type="number" name="limite_clientes" value="100"></label>
    <label><input type="checkbox" name="acceso_panel" value="1" checked> Acceso al panel</label>
    <label><input type="checkbox" name="acceso_ventas" value="1" checked> Acceso a ventas</label>
    <label><input type="checkbox" name="acceso_asistencias" value="1" checked> Acceso a asistencias</label>
    <button type="submit">Guardar</button>
  </form>
</body>
</html>
