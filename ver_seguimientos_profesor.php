<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'profesor' && $rol !== 'admin') {
    die("Acceso denegado.");
}

$busqueda = $_GET['buscar'] ?? '';
$condicion = "WHERE clientes.gimnasio_id = $gimnasio_id";
if (!empty($busqueda)) {
    $condicion .= " AND (clientes.nombre LIKE '%$busqueda%' OR clientes.apellido LIKE '%$busqueda%' OR clientes.dni LIKE '%$busqueda%')";
}

$query = "SELECT fichas_seguimiento.*, clientes.nombre, clientes.apellido, clientes.dni
          FROM fichas_seguimiento
          JOIN clientes ON fichas_seguimiento.cliente_id = clientes.id
          $condicion
          ORDER BY fichas_seguimiento.semana DESC";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seguimientos Nutricionales</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">

    <h2>Historial de Seguimientos Nutricionales</h2>

    <form method="GET" style="text-align: center; margin-bottom: 20px;">
        <input type="text" name="buscar" placeholder="Buscar por nombre, apellido o DNI" value="<?= htmlspecialchars($busqueda) ?>" required>
        <button type="submit">Buscar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Semana</th>
                <th>Fecha Inicio</th>
                <th>Peso Inicio</th>
                <th>Peso Fin</th>
                <th>Adherencia</th>
                <th>Satisfacción</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= $fila['nombre'] . ' ' . $fila['apellido'] ?></td>
                <td><?= $fila['dni'] ?></td>
                <td><?= $fila['semana'] ?></td>
                <td><?= $fila['fecha_inicio'] ?></td>
                <td><?= $fila['peso_inicio'] ?> kg</td>
                <td><?= $fila['peso_fin'] ?> kg</td>
                <td><?= $fila['adherencia'] ?></td>
                <td><?= $fila['satisfaccion'] ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>
</body>
</html>
