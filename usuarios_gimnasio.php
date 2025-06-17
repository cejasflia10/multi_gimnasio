<?php
include 'conexion.php';
include 'menu.php';
?>

<div class="container mt-5">
    <h2 class="text-center mb-4" style="color: gold;">Usuarios por Gimnasio</h2>
    <div class="table-responsive">
        <table class="table table-dark table-bordered table-hover text-center">
            <thead>
                <tr>
                    <th>Nombre de Usuario</th>
                    <th>Rol</th>
                    <th>Gimnasio</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $consulta = "SELECT u.id, u.usuario, u.rol, g.nombre AS gimnasio, g.email 
                             FROM usuarios_gimnasio u
                             LEFT JOIN gimnasios g ON u.gimnasio_id = g.id";
                $resultado = $conexion->query($consulta);

                while ($fila = $resultado->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($fila['usuario']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['rol']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['gimnasio']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['email']) . "</td>";
                    echo "<td>
                            <a href='editar_usuario.php?id=" . $fila['id'] . "' class='btn btn-warning btn-sm'>Editar</a>
                            <a href='eliminar_usuario.php?id=" . $fila['id'] . "' class='btn btn-danger btn-sm' onclick="return confirm('¿Estás seguro de eliminar este usuario?')">Eliminar</a>
                          </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
