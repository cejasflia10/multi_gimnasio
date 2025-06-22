<?php
session_start();
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

if ($rol === 'admin') {
    $query = "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio FROM clientes 
              LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id";
} else {
    $query = "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio FROM clientes 
              LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id
              WHERE gimnasio_id = $gimnasio_id";
}
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: #111;
            color: #f1f1f1;
        }
        .contenido {
            margin-left: 260px;
            padding: 20px;
        }
        h1 {
            color: #f7d774;
            margin-bottom: 10px;
        }
        .volver-btn {
            display: inline-block;
            background-color: #f7d774;
            color: #111;
            padding: 10px 20px;
            margin-bottom: 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .volver-btn:hover {
            background-color: #e5c100;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1a1a1a;
            margin-top: 10px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #333;
            text-align: left;
        }
        th {
            background-color: #222;
            color: #f7d774;
        }
        tr:nth-child(even) {
            background-color: #1f1f1f;
        }
        .action {
            color: #f7d774;
            margin-right: 10px;
            text-decoration: none;
            font-size: 1.2em;
        }
        .action:hover {
            color: #fff;
        }

        @media (max-width: 768px) {
            .contenido {
                margin-left: 0;
                padding: 10px;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead {
                display: none;
            }
            td {
                padding: 10px;
                border: none;
                border-bottom: 1px solid #333;
                position: relative;
                padding-left: 50%;
            }
            td:before {
                position: absolute;
                top: 10px;
                left: 10px;
                width: 45%;
                white-space: nowrap;
                font-weight: bold;
                color: #f7d774;
            }
            td:nth-child(1):before { content: "Apellido"; }
            td:nth-child(2):before { content: "Nombre"; }
            td:nth-child(3):before { content: "DNI"; }
            td:nth-child(4):before { content: "Teléfono"; }
            td:nth-child(5):before { content: "Email"; }
            td:nth-child(6):before { content: "Disciplina"; }
            td:nth-child(7):before { content: "Vencimiento"; }
            td:nth-child(8):before { content: "QR"; }
            <?php if ($rol === 'admin'): ?>
            td:nth-child(9):before { content: "Gimnasio"; }
            td:nth-child(10):before { content: "Acciones"; }
            <?php else: ?>
            td:nth-child(9):before { content: "Acciones"; }
            <?php endif; ?>
        }
    </style>
</head>
<body>
<div class="contenido">
    <h1>Clientes Registrados</h1>
    <a class="volver-btn" href="index.php">← Volver al Menú</a>
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
                <th>QR</th>
                <?php if ($rol === 'admin'): ?>
                    <th>Gimnasio</th>
                <?php endif; ?>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['dni'] ?></td>
                <td><?= $row['telefono'] ?></td>
                <td><?= $row['email'] ?></td>
                <td><?= $row['disciplina'] ?></td>
                <td><?= $row['fecha_vencimiento'] ?></td>
                <td>
                    <?php
                    $qr_file = "qr_clientes/" . $row['apellido'] . "_" . $row['nombre'] . "_" . $row['dni'] . ".png";
                    if (file_exists($qr_file)) {
                        echo "<a class='action' href='$qr_file' target='_blank' title='Ver QR'>📷</a>";
                        echo "<a class='action' href='$qr_file' download title='Descargar QR'>⬇️</a>";
                    } else {
                        echo "❌";
                    }
                    ?>
                </td>
                <?php if ($rol === 'admin'): ?>
                    <td><?= $row['nombre_gimnasio'] ?></td>
                <?php endif; ?>
                <td>
                    <a class="action" href="editar_cliente.php?id=<?= $row['id'] ?>" title="Editar">✏️</a>
                    <a class="action" href="eliminar_cliente.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este cliente?')" title="Eliminar">🗑️</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
