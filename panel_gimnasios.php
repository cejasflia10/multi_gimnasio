<?php
session_start();
include 'conexion.php';

// Consulta de gimnasios
$resultado = $conexion->query("SELECT * FROM gimnasios ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Gimnasios</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #444; text-align: left; }
        th { background: #222; color: gold; }
        tr:nth-child(even) { background: #111; }
        .boton {
            background: gold; color: black; padding: 6px 12px;
            border-radius: 6px; text-decoration: none; font-weight: bold;
            margin: 2px;
            display: inline-block;
        }
        .boton-rojo { background: red; color: white; }
    </style>
</head>
<body>

<h2>🏢 Panel de Administración de Gimnasios</h2>
<a href="agregar_gimnasio.php" class="boton">➕ Agregar Gimnasio</a>

<table>
    <tr>
        <th>Nombre</th>
        <th>CUIT</th>
        <th>Email</th>
        <th>Teléfono</th>
        <th>Vencimiento</th>
        <th>Activo</th>
        <th>Acciones</th>
    </tr>
    <?php while ($gim = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($gim['nombre'] ?? '') ?></td>
            <td><?= htmlspecialchars($gim['cuit'] ?? '') ?></td>
            <td><?= htmlspecialchars($gim['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($gim['telefono'] ?? '') ?></td>
            <td><?= !empty($gim['fecha_vencimiento']) ? date('d/m/Y', strtotime($gim['fecha_vencimiento'])) : '-' ?></td>
            <td><?= isset($gim['activo']) && $gim['activo'] == 1 ? 'Sí' : 'No' ?></td>
            <td>
                <a href="editar_gimnasio.php?id=<?= $gim['id'] ?>" class="boton">✏️ Editar</a>
                <a href="panel_configuracion.php?id=<?= $gim['id'] ?>" class="boton">🔧 Configurar</a>
                <?php if (!empty($gim['activo'])): ?>
                    <a href="suspender_gimnasio.php?id=<?= $gim['id'] ?>" class="boton-rojo" onclick="return confirm('¿Suspender acceso al gimnasio?')">🚫 Suspender</a>
                <?php else: ?>
                    <a href="activar_gimnasio.php?id=<?= $gim['id'] ?>" class="boton" onclick="return confirm('¿Reactivar acceso al gimnasio?')">✅ Activar</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
