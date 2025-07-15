<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($gimnasio_id == 0) {
    exit("❌ Acceso denegado.");
}

$mensaje = '';

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $cuit = trim($_POST['cuit'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Actualizar datos
    $stmt = $conexion->prepare("UPDATE gimnasios SET nombre=?, direccion=?, cuit=?, telefono=?, email=? WHERE id=?");
    $stmt->bind_param("sssssi", $nombre, $direccion, $cuit, $telefono, $email, $gimnasio_id);
    $stmt->execute();

    // Subir logo si corresponde
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['logo']['tmp_name'];
        $nombre_archivo = 'logos/logo_gimnasio_' . $gimnasio_id . '_' . basename($_FILES['logo']['name']);
        if (!is_dir('logos')) mkdir('logos', 0777, true);
        move_uploaded_file($tmp, $nombre_archivo);
        $conexion->query("UPDATE gimnasios SET logo = '$nombre_archivo' WHERE id = $gimnasio_id");
    }

    $mensaje = "✅ Cambios guardados correctamente.";
}

// Obtener datos actuales
$gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Datos del Gimnasio</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background-color: #000; color: gold; font-family: Arial; padding: 30px; }
        form { max-width: 600px; margin: auto; background: #111; padding: 20px; border-radius: 10px; }
        label { display: block; margin-top: 15px; }
        input[type="text"], input[type="email"], input[type="file"] {
            width: 100%; padding: 8px; margin-top: 5px; border-radius: 6px; border: 1px solid #555; background: #222; color: gold;
        }
        .boton { margin-top: 20px; background: gold; color: black; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .mensaje { color: lightgreen; font-weight: bold; margin-top: 20px; text-align: center; }
        .logo-prev { margin-top: 10px; max-height: 80px; background: white; border-radius: 6px; padding: 4px; }
    </style>
</head>
<body>

<h2 style="text-align:center;">✏️ Editar Datos del Gimnasio</h2>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= $mensaje ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <label>Nombre:</label>
    <input type="text" name="nombre" value="<?= htmlspecialchars($gimnasio['nombre'] ?? '') ?>" required>

    <label>Dirección:</label>
    <input type="text" name="direccion" value="<?= htmlspecialchars($gimnasio['direccion'] ?? '') ?>" required>

    <label>CUIT:</label>
    <input type="text" name="cuit" value="<?= htmlspecialchars($gimnasio['cuit'] ?? '') ?>">

    <label>Teléfono:</label>
    <input type="text" name="telefono" value="<?= htmlspecialchars($gimnasio['telefono'] ?? '') ?>">

    <label>Email:</label>
    <input type="email" name="email" value="<?= htmlspecialchars($gimnasio['email'] ?? '') ?>">

    <label id="logo">Logo (opcional):</label>
    <input type="file" name="logo" accept="image/*">
    <?php if (!empty($gimnasio['logo']) && file_exists($gimnasio['logo'])): ?>
        <img src="<?= $gimnasio['logo'] ?>" class="logo-prev">
    <?php endif; ?>

    <button type="submit" class="boton">💾 Guardar Cambios</button>
    <br><br>
    <a href="panel_configuracion.php" class="boton">↩️ Volver al Panel</a>
</form>

</body>
</html>
