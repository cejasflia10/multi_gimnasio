<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['evento_usuario_id'])) {
    header("Location: login_evento.php");
    exit;
}

$evento_id = $_GET['id'] ?? 0;
$evento_id = intval($evento_id);
$mensaje = '';

$consulta = $conexion->query("SELECT * FROM eventos_deportivos WHERE id = $evento_id");
$evento = $consulta->fetch_assoc();

if (!$evento) {
    echo "Evento no encontrado.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $lugar = trim($_POST['lugar'] ?? '');
    $video = trim($_POST['video'] ?? '');
    $flyer = $_FILES['flyer']['name'] ?? '';
    $ruta_flyer = $evento['flyer'];

    if ($titulo && $fecha && $hora && $lugar) {
        // Si se sube una nueva imagen, reemplazamos
        if (!empty($flyer)) {
            $ruta_flyer = 'flyers_eventos/' . basename($flyer);
            move_uploaded_file($_FILES['flyer']['tmp_name'], $ruta_flyer);
        }

        $stmt = $conexion->prepare("UPDATE eventos_deportivos SET titulo=?, descripcion=?, fecha=?, hora=?, lugar=?, flyer=?, video=? WHERE id=?");
        $stmt->bind_param("sssssssi", $titulo, $descripcion, $fecha, $hora, $lugar, $ruta_flyer, $video, $evento_id);

        if ($stmt->execute()) {
            $mensaje = "✅ Evento actualizado correctamente.";
        } else {
            $mensaje = "❌ Error al actualizar.";
        }
    } else {
        $mensaje = "⚠️ Todos los campos requeridos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Evento</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>✏️ Editar Evento</h2>
    <?php if ($mensaje) echo "<p style='color: gold;'>$mensaje</p>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Título:</label>
        <input type="text" name="titulo" value="<?= htmlspecialchars($evento['titulo']) ?>" required>

        <label>Descripción:</label>
        <textarea name="descripcion" rows="4"><?= htmlspecialchars($evento['descripcion']) ?></textarea>

        <label>Fecha:</label>
        <input type="date" name="fecha" value="<?= $evento['fecha'] ?>" required>

        <label>Hora:</label>
        <input type="time" name="hora" value="<?= $evento['hora'] ?>" required>

        <label>Lugar:</label>
        <input type="text" name="lugar" value="<?= htmlspecialchars($evento['lugar']) ?>" required>

        <label>Video (link):</label>
        <input type="text" name="video" value="<?= htmlspecialchars($evento['video']) ?>">

        <label>Flyer nuevo (opcional):</label>
        <input type="file" name="flyer" accept="image/*">

        <button type="submit">💾 Guardar Cambios</button>
        <a href="panel_eventos.php" class="boton-volver">⬅ Volver</a>
    </form>
</div>
</body>
</html>
