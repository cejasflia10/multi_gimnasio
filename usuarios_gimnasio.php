<?php
include 'conexion.php';
include 'menu.php';

$resultado = $conexion->query("SELECT u.id, u.usuario, u.rol, g.nombre AS gimnasio
    FROM usuarios u
    LEFT JOIN gimnasios g ON u.gimnasio_id = g.id
    ORDER BY g.nombre ASC, u.usuario ASC");
?>

<div class="container" style="margin-top: 80px; color: #fff;">
    <h2 style="color: gold;">Usuarios del Sistema</h2>
    <table class="table table-dark table-striped">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Gimnasio</th>
            </tr>
        </thead>
        <tbody>
            <?php while($fila = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($fila['usuario']) ?></td>
                    <td><?= ucfirst($fila['rol']) ?></td>
                    <td><?= $fila['gimnasio'] ?? 'Sin gimnasio' ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
