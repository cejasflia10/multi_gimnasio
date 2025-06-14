<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso no autorizado.');
}

$id_gimnasio = $_SESSION['id_gimnasio'];

$sql = "SELECT * FROM clientes WHERE id_gimnasio = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_gimnasio);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes</title>
</head>
<body>
    <h2>Clientes del gimnasio</h2>
    <table border="1">
        <tr><th>ID</th><th>Apellido</th><th>Nombre</th><th>DNI</th></tr>
        <?php while ($row = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo $row['apellido']; ?></td>
                <td><?php echo $row['nombre']; ?></td>
                <td><?php echo $row['dni']; ?></td>
            </tr>
        <?php } ?>
    </table>
</body>
</html>
