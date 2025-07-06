<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias Registradas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<script src="fullscreen.js"></script>
<body>

<div class="contenedor">
    <h1>📋 Asistencias Registradas</h1>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Fecha</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT c.nombre, c.apellido, c.dni, a.fecha, a.hora
                      FROM asistencias a
                      JOIN clientes c ON a.cliente_id = c.id
                      WHERE c.gimnasio_id = $gimnasio_id
                      ORDER BY a.fecha DESC, a.hora DESC
                      LIMIT 100";
            $resultado = $conexion->query($query);
            if ($resultado && $resultado->num_rows > 0) {
                while ($fila = $resultado->fetch_assoc()) {
                    echo "<tr>
                            <td data-label='Nombre'>{$fila['nombre']} {$fila['apellido']}</td>
                            <td data-label='DNI'>{$fila['dni']}</td>
                            <td data-label='Fecha'>{$fila['fecha']}</td>
                            <td data-label='Hora'>{$fila['hora']}</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='4'>No se encontraron asistencias registradas.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

</body>
</html>
