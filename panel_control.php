<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}
include 'conexion.php';

$consulta = "SELECT u.id, u.usuario, u.email, u.rol, g.nombre AS gimnasio, 
                    u.permiso_clientes, u.permiso_membresias, u.perm_profesores, 
                    u.perm_ventas, u.puede_ver_panel, u.puede_ver_asistencias
             FROM usuarios u 
             LEFT JOIN gimnasios g ON u.id_gimnasio = g.id";
$resultado = $conexion->query($consulta);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial; margin: 0; padding: 20px; }
        h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid gold; padding: 10px; text-align: center; }
        th { background-color: #222; }
        td { background-color: #000; }
        a.button { padding: 6px 12px; background-color: gold; color: black; text-decoration: none; border-radius: 5px; }
        .acciones { display: flex; gap: 10px; justify-content: center; }
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            td { text-align: left; position: relative; padding-left: 50%; }
            td::before { position: absolute; left: 10px; color: #ccc; }
        }
    </style>
</head>
<body>
<h2>Listado de Usuarios</h2>
<table>
    <thead>
        <tr>
            <th>Usuario</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Gimnasio</th>
            <th>Permisos</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($fila['usuario']) ?></td>
                <td><?= htmlspecialchars($fila['email']) ?></td>
                <td><?= htmlspecialchars($fila['rol']) ?></td>
                <td><?= htmlspecialchars($fila['gimnasio']) ?></td>
                <td>
                    <?= $fila['permiso_clientes'] ? 'Clientes | ' : '' ?>
                    <?= $fila['permiso_membresias'] ? 'Membresías | ' : '' ?>
                    <?= $fila['perm_profesores'] ? 'Profesores | ' : '' ?>
                    <?= $fila['perm_ventas'] ? 'Ventas | ' : '' ?>
                    <?= $fila['puede_ver_panel'] ? 'Panel | ' : '' ?>
                    <?= $fila['puede_ver_asistencias'] ? 'Asistencias' : '' ?>
                </td>
                <td class="acciones">
                    <a class="button" href="editar_usuario.php?id=<?= $fila['id'] ?>">Editar</a>
                    <a class="button" href="eliminar_usuario.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Estás seguro?')">Eliminar</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</body>
</html>
