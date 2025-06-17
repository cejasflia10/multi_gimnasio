
<?php
include 'menu.php';
include 'conexion.php';

echo '<div style="margin-left: 250px; padding: 20px;">';
echo '<h2 style="color: gold;">Usuarios por Gimnasio</h2>';

$consulta = "SELECT u.id, u.nombre_usuario, u.rol, g.nombre AS gimnasio, u.email 
             FROM usuarios u
             LEFT JOIN gimnasios g ON u.id_gimnasio = g.id";

$resultado = $conexion->query($consulta);

echo '<table class="table" style="color: white;">';
echo '<thead><tr>
        <th>Usuario</th>
        <th>Rol</th>
        <th>Gimnasio</th>
        <th>Email</th>
        <th>Acciones</th>
      </tr></thead>';
echo '<tbody>';

while ($fila = $resultado->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($fila['nombre_usuario'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($fila['rol'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($fila['gimnasio'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($fila['email'] ?? '') . '</td>';
    echo "<td>
            <a href='editar_usuario.php?id=" . $fila['id'] . "' class='btn btn-warning btn-sm'>Editar</a>
            <a href='eliminar_usuario.php?id=" . $fila['id'] . "' class='btn btn-danger btn-sm' onclick=\"return confirm('¿Estás seguro de eliminar este usuario?')\">Eliminar</a>
          </td>";
    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
?>
