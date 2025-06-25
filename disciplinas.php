<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    header('Location: login.php');
    exit;
}

include 'conexion.php';
$gimnasio_id = intval($_SESSION['gimnasio_id']);

$resultado = $conexion->query("SELECT id, nombre FROM disciplinas WHERE id_gimnasio = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disciplinas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; margin: 0; }
        h2 { text-align: center; margin-bottom: 20px; }
        .nuevo { display: inline-block; padding: 10px 20px; background-color: gold; color: black; text-decoration: none; font-weight: bold; border-radius: 5px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background-color: #222; }
        th, td { border: 1px solid gold; padding: 10px; text-align: center; }
        th { background-color: #333; }
        a { color: gold; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
        @media (max-width: 600px) {
            body { padding: 10px; }
            .nuevo { display: block; width: 100%; text-align: center; margin-bottom: 15px; }
            table, thead, tbody, th, td, tr { display: block; width: 100%; }
            thead tr { display: none; }
            tr { margin-bottom: 15px; border-bottom: 2px solid gold; }
            td { text-align: right; padding-left: 50%; position: relative; }
            td::before { content: attr(data-label); position: absolute; left: 10px; width: 45%; white-space: nowrap; font-weight: bold; text-align: left; }
        }
    </style>
</head>
<body>
    <h2>Disciplinas</h2>
    <a class="nuevo" href="crear_disciplina.php">+ Nueva Disciplina</a>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado->fetch_assoc()) : ?>
                <tr>
                    <td data-label="Nombre"><?= htmlspecialchars($fila['nombre']) ?></td>
                    <td data-label="Acciones">
                        <a href="editar_disciplina.php?id=<?= $fila['id'] ?>">Editar</a> |
                        <a href="eliminar_disciplina.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Eliminar esta disciplina?')">Eliminar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
