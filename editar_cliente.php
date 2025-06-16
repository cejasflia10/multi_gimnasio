<?php
include 'conexion.php';
if (!isset($_GET['id'])) {
  echo "ID de cliente no proporcionado.";
  exit;
}
$id = $_GET['id'];
$resultado = $conexion->query("SELECT * FROM clientes WHERE id = $id");
$cliente = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Cliente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
    }
    h2 { text-align: center; }
    form {
      max-width: 600px;
      margin: auto;
      background-color: #222;
      padding: 20px;
      border-radius: 10px;
    }
    label { display: block; margin-top: 10px; }
    input {
      width: 100%;
      padding: 10px;
      background: #333;
      border: none;
      color: #fff;
      border-radius: 5px;
    }
    button {
      margin-top: 20px;
      width: 100%;
      padding: 10px;
      background: gold;
      border: none;
      color: #000;
      font-weight: bold;
      border-radius: 5px;
      cursor: pointer;
    }
  </style>
  <script>
    function calcularEdad() {
      const fechaNac = document.getElementById("fecha_nacimiento").value;
      if (fechaNac) {
        const hoy =
