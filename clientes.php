
<?php
session_start();
if (!isset($_SESSION["gimnasio_id"])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';

$query = "SELECT id, apellido, nombre, dni, telefono, email, fecha_nacimiento, domicilio, disciplina, rfid_uid, fecha_vencimiento FROM clientes WHERE gimnasio_id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #FFD700;
            margin: 0;
            padding-top: 60px;
        }
        .contenido {
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #FFD700;
        }
        .boton {
            display: inline-block;
            margin: 10px 5px;
            padding: 10px 20px;
            background-color: #FFD700;
            color: #111;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .tabla-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            color: #FFD700;
        }
        th, td {
            padding: 10px;
            border: 1px solid #FFD700;
            text-align: center;
        }
        th {
            background-color: #333;
        }
        @media (max-width: 768px) {
            th, td {
                font-size: 12px;
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="contenido">
        <h1>Clientes del Gimnasio</h1>
        <a href="agregar_cliente.php" class="boton">Agregar Cliente</a>
        <a href="index.php" class="boton">Volver al Panel</a>
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
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
