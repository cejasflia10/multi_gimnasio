<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener el usuario logueado
$usuario = $_SESSION['usuario'];

// Obtener ID del gimnasio del usuario desde la base de datos
$sql = "SELECT id_gimnasio FROM usuarios WHERE nombre_usuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $fila = $resultado->fetch_assoc();
    $id_gimnasio = $fila['id_gimnasio'];
} else {
    die("No se pudo obtener el gimnasio del usuario.");
}

// Obtener los clientes asociados al gimnasio
$sql_clientes = "SELECT * FROM clientes WHERE id_gimnasio = ?";
$stmt = $conexion->prepare($sql_clientes);
$stmt->bind_param("i", $id_gimnasio);
$stmt->execute();
$resultado_clientes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes</title>
</head>
<body>
    <h1>Clientes del gimnasio</h1>
    <table border="1">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Teléfono</th>
                <!-- otros campos si querés -->
            </tr>
        </thead>
        <tbody>
            <?php while ($cliente = $resultado_clientes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $cliente['nombre']; ?></td>
                    <td><?php echo $cliente['dni']; ?></td>
                    <td><?php echo $cliente['telefono']; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
