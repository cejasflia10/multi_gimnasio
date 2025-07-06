<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$gimnasio_id || !isset($_GET['id'])) {
    die("Acceso denegado.");
}

$id = intval($_GET['id']);
$query = "SELECT * FROM disciplinas WHERE id = $id AND gimnasio_id = $gimnasio_id";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Disciplina no encontrada o no pertenece a este gimnasio.");
}

$disciplina = $resultado->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);

    $stmt = $conexion->prepare("UPDATE disciplinas SET nombre = ?, descripcion = ? WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("ssii", $nombre, $descripcion, $id, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Disciplina actualizada.'); window.location.href='disciplinas.php';</script>";
    } else {
        echo "<script>alert('Error al actualizar.');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Disciplina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>✏️ Editar Disciplina</h2>
    <form method="POST">
        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" id="nombre" value="<?= htmlspecialchars($disciplina['nombre']) ?>" required>

        <label for="descripcion">Descripción:</label>
        <textarea name="descripcion" id="descripcion" rows="4"><?= htmlspecialchars($disciplina['descripcion']) ?></textarea>

        <button type="submit">Guardar Cambios</button>
        <br><br>
        <a href="disciplinas.php" class="btn-volver">⬅ Volver al listado</a>
    </form>
</div>
</body>
</html>
