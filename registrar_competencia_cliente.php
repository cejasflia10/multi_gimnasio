<?php
session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$cliente_id || !$gimnasio_id) {
    echo "<p style='color:red;'>Acceso denegado.</p>";
    exit;
}

// Datos del cliente
$cliente = $conexion->query("SELECT apellido, nombre, dni, fecha_nacimiento, domicilio, email, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad 
    FROM clientes WHERE id = $cliente_id")->fetch_assoc();

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $disciplina = $_POST['disciplina'] ?? '';
    $division = $_POST['division'] ?? '';
    $peso = $_POST['peso'] ?? '';
    $peleas = intval($_POST['peleas'] ?? 0);
    $observaciones = $conexion->real_escape_string($_POST['observaciones'] ?? '');

    $conexion->query("INSERT INTO competidores 
        (cliente_id, gimnasio_id, disciplina, division, peso, peleas, observaciones, fecha_registro)
        VALUES ($cliente_id, $gimnasio_id, '$disciplina', '$division', '$peso', $peleas, '$observaciones', NOW())");

    $mensaje = "<p style='color:lime;'>✅ Te has registrado correctamente para la competencia.</p>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inscripción a Competencia</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 style="text-align:center;">🥊 Inscripción a Competencia</h2>

    <?= $mensaje ?>

    <form method="POST">
        <label>Apellido y Nombre</label>
        <input type="text" value="<?= $cliente['apellido'] . ' ' . $cliente['nombre'] ?>" disabled>

        <label>DNI</label>
        <input type="text" value="<?= $cliente['dni'] ?>" disabled>

        <label>Edad</label>
        <input type="text" value="<?= $cliente['edad'] ?>" disabled>

        <label>Fecha de Nacimiento</label>
        <input type="text" value="<?= $cliente['fecha_nacimiento'] ?>" disabled>

        <label>Domicilio</label>
        <input type="text" value="<?= $cliente['domicilio'] ?>" disabled>

        <label>Email</label>
        <input type="text" value="<?= $cliente['email'] ?>" disabled>

        <label>Disciplina</label>
        <select name="disciplina" required>
            <option value="">Seleccionar</option>
            <option value="Boxeo">Boxeo</option>
            <option value="Kickboxing">Kickboxing</option>
            <option value="K1">K1</option>
        </select>

        <label>División</label>
        <select name="division" required>
            <option value="">Seleccionar</option>
            <option value="Exhibición">Exhibición</option>
            <option value="Amateur">Amateur</option>
            <option value="ProAm">ProAm</option>
            <option value="Profesional">Profesional</option>
        </select>

        <label>Peso (kg)</label>
        <input type="text" name="peso" required>

        <label>Cantidad de peleas</label>
        <input type="number" name="peleas" min="0" required>

        <label>Observaciones</label>
        <textarea name="observaciones"></textarea>

        <button type="submit">📥 Registrar inscripción</button>
    </form>
</div>

</body>
</html>
