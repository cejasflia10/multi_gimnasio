<?php
include 'conexion.php';

$query = "SELECT c.id, c.apellido, c.nombre, c.dni, c.email, c.rfid, g.nombre AS gimnasio 
          FROM clientes c
          LEFT JOIN gimnasios g ON c.gimnasio_id = g.id
          ORDER BY c.apellido ASC";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Clientes</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #333; }
        th { background-color: #222; color: #ffc107; }
        tr:hover { background-color: #222; }
        a.btn { padding: 5px 10px; background: #ffc107; color: #111; text-decoration: none; border-radius: 5px; }
        a.btn:hover { background: #e0a800; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Clientes registrados</h1>
    <a href="agregar_cliente.php" class="btn">➕ Agregar Cliente</a>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Email</th>
            <th>RFID</th>
            <th>Gimnasio</th>
        </tr>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $row['apellido'] ?></td>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['dni'] ?></td>
            <td><?= $row['email'] ?></td>
            <td><?= $row['rfid'] ?></td>
            <td><?= $row['gimnasio'] ?? 'No asignado' ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
