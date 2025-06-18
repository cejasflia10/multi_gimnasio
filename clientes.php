<?php
include 'conexion.php';
session_start();

if (!isset($_SESSION['gimnasio_id'])) {
    echo "<script>alert('Debe iniciar sesión.'); window.location.href='login.php';</script>";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$resultado = $conexion->query("SELECT * FROM clientes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes</title>
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: gold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        tr:nth-child(even) {
            background-color: #1a1a1a;
        }
        .btn {
            display: inline-block;
            margin: 10px 5px;
            padding: 10px;
            background-color: gold;
            color: black;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        @media (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            td {
                text-align: right;
                padding-left: 50%;
                position: relative;
            }
            td::before {
                position: absolute;
                left: 10px;
                width: 45%;
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                color: #ccc;
            }
            td:nth-of-type(1)::before { content: "Apellido"; }
            td:nth-of-type(2)::before { content: "Nombre"; }
            td:nth-of-type(3)::before { content: "DNI"; }
            td:nth-of-type(4)::before { content: "Teléfono"; }
            td:nth-of-type(5)::before { content: "Disciplina"; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Clientes del Gimnasio</h1>
        <a class="btn" href="agregar_cliente.php">Agregar Cliente</a>
        <table>
            <thead>
                <tr>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Teléfono</th>
                    <th>Disciplina</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($fila['apellido']) ?></td>
                    <td><?= htmlspecialchars($fila['nombre']) ?></td>
                    <td><?= htmlspecialchars($fila['dni']) ?></td>
                    <td><?= htmlspecialchars($fila['telefono']) ?></td>
                    <td><?= htmlspecialchars($fila['disciplina']) ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>
