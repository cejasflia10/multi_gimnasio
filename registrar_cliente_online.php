<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_GET['gimnasio'] ?? 0;

// Obtener logo y nombre del gimnasio
$info = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$nombre_gimnasio = $info['nombre'] ?? 'Gimnasio';
$logo_gimnasio = $info['logo'] ?? 'logo.png';

// Obtener disciplinas del gimnasio
$disciplinas = [];
if ($gimnasio_id) {
    $resultado = $conexion->query("SELECT id, nombre FROM disciplinas WHERE gimnasio_id = $gimnasio_id");
    while ($fila = $resultado->fetch_assoc()) {
        $disciplinas[] = $fila;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Cliente Online</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .contenedor {
            max-width: 500px;
            margin: auto;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: gold;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        .logo {
            display: block;
            margin: 0 auto 20px auto;
            max-width: 150px;
        }
        .btn {
            background-color: gold;
            color: black;
            font-weight: bold;
            border: none;
            padding: 10px;
            margin-top: 20px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <?php if ($logo_gimnasio): ?>
            <img src="<?= htmlspecialchars($logo_gimnasio) ?>" alt="Logo Gimnasio" class="logo">
        <?php endif; ?>
        <h2><?= htmlspecialchars($nombre_gimnasio) ?></h2>
        <h3>Registro de Cliente Online</h3>

        <form action="guardar_cliente_online.php" method="post" onsubmit="return redirigirDespues()">
            <input type="hidden" name="gimnasio_id" value="<?= htmlspecialchars($gimnasio_id) ?>">

            <label>Apellido:</label>
            <input type="text" name="apellido" required>

            <label>Nombre:</label>
            <input type="text" name="nombre" required>

            <label>DNI:</label>
            <input type="number" name="dni" required>

            <label>Fecha de nacimiento:</label>
            <input type="date" name="fecha_nacimiento" required>

            <label>Domicilio:</label>
            <input type="text" name="domicilio" required>

            <label>Teléfono:</label>
            <input type="text" name="telefono" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Disciplina:</label>
            <select name="disciplina" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($disciplinas as $disciplina): ?>
                    <option value="<?= htmlspecialchars($disciplina['nombre']) ?>">
                        <?= htmlspecialchars($disciplina['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="submit" class="btn" value="Registrar Cliente">
        </form>
    </div>

    <script>
        function redirigirDespues() {
            setTimeout(function () {
                window.location.href = "cliente_acceso.php";
            }, 1000); // espera 1 segundo
            return true; // permitir que se envíe el formulario
        }
    </script>
</body>
</html>
