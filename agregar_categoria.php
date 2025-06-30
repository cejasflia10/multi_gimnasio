<?php include 'verificar_sesion.php'; ?>
<?php
include 'conexion.php';
include 'menu_horizontal.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    if ($nombre != '') {
        $stmt = $conexion->prepare("INSERT INTO categorias (nombre) VALUES (?)");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $mensaje = "Categoría agregada correctamente.";
    } else {
        $mensaje = "El nombre no puede estar vacío.";
    }
}

$categorias = $conexion->query("SELECT * FROM categorias ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Categoría</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        label, input { display: block; margin-top: 10px; width: 100%; padding: 8px; }
        .btn { margin-top: 15px; padding: 10px 20px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #e0a800; }
        .mensaje { margin-top: 20px; color: #0f0; }
        table { margin-top: 20px; width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #333; }
        th { color: #ffc107; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Agregar Nueva Categoría</h1>
    <form method="POST">
        <label>Nombre de la categoría:</label>
        <input type="text" name="nombre" required>
        <button type="submit" class="btn">Agregar</button>
    </form>

    <?php if (isset($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

    <h2>Categorías existentes</h2>
    <table>
        <tr><th>Nombre</th></tr>
        <?php while ($cat = $categorias->fetch_assoc()): ?>
            <tr><td><?= $cat['nombre'] ?></td></tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
