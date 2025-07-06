<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso no autorizado.");
}

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $conexion->real_escape_string(trim($_POST['nombre']));
    $gimnasio_id = $_SESSION['gimnasio_id'];

    if (!empty($nombre)) {
        $conexion->query("INSERT INTO disciplinas (nombre, gimnasio_id) VALUES ('$nombre', $gimnasio_id)");
        $mensaje = "✅ Disciplina agregada correctamente.";
    } else {
        $mensaje = "❌ El nombre de la disciplina no puede estar vacío.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Agregar Disciplina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<script src="fullscreen.js"></script>

<body>
    <div class="contenedor">

        <h2>➕ Agregar Nueva Disciplina</h2>

        <?php if ($mensaje): ?>
            <div class="mensaje"><?= $mensaje ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre de la disciplina:</label>
            <input type="text" name="nombre" placeholder="Ej. Kickboxing, MMA..." required>
            <button type="submit"><i class="fas fa-save"></i> Guardar</button>
        </form>
    </div>
        </div>

</body>
</html>
