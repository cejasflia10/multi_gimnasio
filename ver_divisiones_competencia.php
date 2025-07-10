<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre != '') {
        $conexion->query("INSERT INTO divisiones_competencia (nombre) VALUES ('$nombre')");
    }
}
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM divisiones_competencia WHERE id = $id");
}

$divisiones = $conexion->query("SELECT * FROM divisiones_competencia");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Divisiones de Competencia</title>
    <style>
        body { background: #000; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #444; text-align: center; }
        input, button { padding: 8px; margin-top: 10px; background: #111; color: gold; border: 1px solid gold; }
        h2 { text-align: center; color: white; }
    </style>
</head>
<body>
    <h2>🧩 Divisiones de Competencia</h2>

    <form method="POST" style="text-align:center;">
        <input type="text" name="nombre" placeholder="Nueva división" required>
        <button type="submit">➕ Agregar</button>
    </form>

    <table>
        <tr><th>#</th><th>Nombre</th><th>Acción</th></tr>
        <?php while($d = $divisiones->fetch_assoc()): ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td><?= $d['nombre'] ?></td>
                <td><a href="?eliminar=<?= $d['id'] ?>" onclick="return confirm('¿Eliminar esta división?')" style="color:red;">❌ Eliminar</a></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
