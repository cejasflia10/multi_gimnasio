<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
require_once 'phpqrcode/qrlib.php'; // Asegúrate de tener esta librería instalada

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
$rol = $_SESSION['rol'] ?? null;

if (!$gimnasio_id && $rol != 'admin') {
    die("Acceso denegado.");
}

// Cargar disciplinas
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas");

// Cargar gimnasios si es administrador
if ($rol == 'admin') {
    $gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Agregar Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
</head>
<script src="fullscreen.js"></script>

<body>
<div class="contenedor">

<a class="volver-btn" href="index.php">← Volver al Menú</a>

<h2>Agregar Cliente</h2>

<form action="guardar_cliente.php" method="POST" onsubmit="return validarDNI()">
    <label for="apellido">Apellido:</label>
    <input type="text" name="apellido" required>

    <label for="nombre">Nombre:</label>
    <input type="text" name="nombre" required>

    <label for="dni">DNI:</label>
    <input type="text" name="dni" id="dni" required oninput="verificarDNI(this.value)">
    <div class="mensaje-error" id="mensajeDNI">Este DNI ya está registrado.</div>

    <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
    <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required onchange="calcularEdad()">

    <label for="edad">Edad:</label>
    <input type="text" name="edad" id="edad" readonly>

    <label for="domicilio">Domicilio:</label>
    <input type="text" name="domicilio">

    <label for="telefono">Teléfono:</label>
    <input type="text" name="telefono">

    <label for="email">Email:</label>
    <input type="email" name="email">

    <label for="disciplina">Disciplina:</label>
    <select name="disciplina">
        <option value="">Seleccionar</option>
        <?php while ($d = $disciplinas->fetch_assoc()): ?>
            <option value="<?= $d['nombre'] ?>"><?= $d['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <?php if ($rol == 'admin'): ?>
        <label for="gimnasio_id">Gimnasio:</label>
        <select name="gimnasio_id" required>
            <option value="">Seleccionar</option>
            <?php while ($g = $gimnasios->fetch_assoc()): ?>
                <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
    <?php else: ?>
        <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">
    <?php endif; ?>

    <input type="submit" value="Guardar Cliente">
</form>
</div>

<script>
function calcularEdad() {
    const fechaNac = document.getElementById('fecha_nacimiento').value;
    if (!fechaNac) return;

    const hoy = new Date();
    const nacimiento = new Date(fechaNac);
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const mes = hoy.getMonth() - nacimiento.getMonth();

    if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
        edad--;
    }

    document.getElementById('edad').value = edad;
}

let dniValido = true;

function verificarDNI(dni) {
    if (dni.length < 5) {
        document.getElementById('mensajeDNI').style.display = 'none';
        dniValido = true;
        return;
    }

    fetch('verificar_dni.php?dni=' + dni)
        .then(response => response.json())
        .then(data => {
            if (data.existe) {
                document.getElementById('mensajeDNI').style.display = 'block';
                dniValido = false;
            } else {
                document.getElementById('mensajeDNI').style.display = 'none';
                dniValido = true;
            }