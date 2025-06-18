<?php
session_start();
if (!isset($_SESSION["gimnasio_id"])) {
    die("⚠️ No has iniciado sesión correctamente.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes del Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #f1c40f;
            margin: 0;
            padding: 0;
        }
        .container {
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: #f1c40f;
        }
        .btn {
            background-color: #f1c40f;
            color: black;
            padding: 10px 15px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #ffd700;
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 600px;
        }
        th, td {
            border: 1px solid #f1c40f;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        a.action {
            color: #f1c40f;
            text-decoration: none;
            margin: 0 5px;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <a href="agregar_cliente.php" class="btn">➕ Agregar Cliente</a>
            <a href="index.php" class="btn">🏠 Volver al Panel</a>
        </div>
        <h2>Clientes del Gimnasio</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Apellido</th>
                        <th>Nombre</th>
                        <th>DNI</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Disciplina</th>
                        <th>Vencimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conexion->prepare("SELECT apellido, nombre, dni, telefono, email, disciplina, fecha_vencimiento, id FROM clientes WHERE gimnasio_id = ?");
                    $stmt->bind_param("i", $gimnasio_id);
                    $stmt->execute();
                    $resultado = $stmt->get_result();

                    while ($row = $resultado->fetch_assoc()) {
                        echo "<tr>
                            <td>{$row['apellido']}</td>
                            <td>{$row['nombre']}</td>
                            <td>{$row['dni']}</td>
                            <td>{$row['telefono']}</td>
                            <td>{$row['email']}</td>
                            <td>{$row['disciplina']}</td>
                            <td>{$row['fecha_vencimiento']}</td>
                            <td>
                                <a class='action' href='editar_cliente.php?id={$row['id']}'>✏️</a>
                                <a class='action' href='eliminar_cliente.php?id={$row['id']}' onclick='return confirm("¿Eliminar este cliente?")'>🗑️</a>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
