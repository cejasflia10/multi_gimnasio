<?php
session_start();
include 'conexion.php';
include 'menu_eventos.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre != '') {
        $conexion->query("INSERT INTO disciplinas_competencia (nombre) VALUES ('$nombre')");
    }
}
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM disciplinas_competencia WHERE id = $id");
}

$disciplinas = $conexion->query("SELECT * FROM disciplinas_competencia");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Disciplinas de Competencia</title>
    <style>
        body { background: #000; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #444; text-align: center; }
        input, button { padding: 8px; margin-top: 10px; background: #111; color: gold; border: 1px solid gold; }
        h2 { text-align: center; color: white; }
    </style>
</head>
<body>
    <h2>🏷️ Disciplinas de Competencia</h2>

    <form method="POST" style="text-align:center;">
        <input type="text" name="nombre" placeholder="Nueva disciplina" required>
        <button type="submit">➕ Agregar</button>
    </form>

    <table>
        <tr><th>#</th><th>Nombre</th><th>Acción</th></tr>
        <?php while($d = $disciplinas->fetch_assoc()): ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td><?= $d['nombre'] ?></td>
                <td><a href="?eliminar=<?= $d['id'] ?>" onclick="return confirm('¿Eliminar esta disciplina?')" style="color:red;">❌ Eliminar</a></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
