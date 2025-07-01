<?php
session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
$foto = $cliente['foto'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['nueva_foto'])) {
    $nombre_archivo = "fotos_clientes/" . uniqid() . "_" . basename($_FILES['nueva_foto']['name']);
    if (move_uploaded_file($_FILES['nueva_foto']['tmp_name'], $nombre_archivo)) {
        $conexion->query("UPDATE clientes SET foto = '$nombre_archivo' WHERE id = $cliente_id");
        $foto = $nombre_archivo;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="manifest" href="manifest_cliente.json">
<link rel="icon" href="icono_cliente.png">
<meta name="theme-color" content="#FFD700">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js');
  }
</script>

    <meta charset="UTF-8">
    <title>Panel Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; }
        .card {
            background: #111;
            border: 1px solid gold;
            border-radius: 10px;
            padding: 20px;
            max-width: 600px;
            margin: auto;
        }
        .foto-perfil {
            display: block;
            margin: 10px auto;
            border: 2px solid gold;
            border-radius: 10px;
            max-width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: left;
        }
        th { background: #222; }
        button {
            background: gold;
            color: black;
            padding: 10px;
            margin-top: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        input[type="file"] {
            margin-top: 10px;
            color: gold;
        }
    </style>
</head>
<body>

<h1>🏋️ Bienvenido/a <?= $cliente['nombre'] ?> <?= $cliente['apellido'] ?></h1>

<div class="card">
    <img src="<?= $foto && file_exists($foto) ? $foto : 'img/foto_default.png' ?>" class="foto-perfil" alt="Foto de perfil">

    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="nueva_foto" accept="image/*" capture="environment" required>
        <button type="submit">Subir / Tomar Foto</button>
    </form>

    <table>
        <tr><th>DNI</th><td><?= $cliente['dni'] ?></td></tr>
        <tr><th>Fecha de nacimiento</th><td><?= $cliente['fecha_nacimiento'] ?></td></tr>
        <tr><th>Edad</th><td><?= $cliente['edad'] ?> años</td></tr>
        <tr><th>Teléfono</th><td><?= $cliente['telefono'] ?></td></tr>
        <tr><th>Email</th><td><?= $cliente['email'] ?></td></tr>
        <tr><th>Domicilio</th><td><?= $cliente['domicilio'] ?></td></tr>
        <tr><th>Disciplina</th><td><?= $cliente['disciplina'] ?></td></tr>
    </table>
</div>

</body>
</html>
