<?php
session_start();
if (!isset($_SESSION["gimnasio_id"])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';
include 'menu_horizontal.php';

$query = "SELECT id, apellido, nombre, dni, telefono, email, fecha_nacimiento, domicilio, disciplina, rfid_uid, fecha_vencimiento FROM clientes WHERE gimnasio_id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>

<html lang="es">
    
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
</head>

<body>
<div class="contenedor">
        <h1>Clientes del Gimnasio</h1>
        <a href="agregar_cliente.php" class="boton">➕ Agregar Cliente</a>
        <a href="index.php" class="boton">🏠 Volver al Panel</a>
        <div class="tabla-responsive">
        <table>
            <thead>
                <tr>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Nacimiento</th>
                    <th>Domicilio</th>
                    <th>Disciplina</th>
                    <th>RFID</th>
                    <th>Vencimiento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row["apellido"] ?></td>
                    <td><?= $row["nombre"] ?></td>
                    <td><?= $row["dni"] ?></td>
                    <td><?= $row["telefono"] ?></td>
                    <td><?= $row["email"] ?></td>
                    <td><?= $row["fecha_nacimiento"] ?></td>
                    <td><?= $row["domicilio"] ?></td>
                    <td><?= $row["disciplina"] ?></td>
                    <td><?= $row["rfid_uid"] ?></td>
                    <td><?= $row["fecha_vencimiento"] ?></td>
                    <td class="acciones">
                        <a href="editar_cliente.php?id=<?= $row['id'] ?>">✏️</a>
                        <a href="eliminar_cliente.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este cliente?')">🗑️</a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
</div>

    </div>
</body>
</html>
