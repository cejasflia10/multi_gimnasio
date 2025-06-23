<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

if ($rol === 'admin') {
    $query = "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio 
              FROM clientes 
              LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id";
} else {
    $query = "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio 
              FROM clientes 
              LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id 
              WHERE gimnasio_id = $gimnasio_id";
}
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes Registrados</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        h2 {
            text-align: center;
            margin: 20px 0;
        }
        .container {
            padding: 20px;
            overflow-x: auto;
        }
        .buscador {
            margin-bottom: 15px;
            text-align: center;
        }
        .buscador input {
            padding: 10px;
            width: 80%;
            max-width: 400px;
            border-radius: 5px;
            border: none;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1c1c1c;
        }
        th, td {
            border: 1px solid #f1c40f;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        a.btn {
            display: inline-block;
            padding: 8px 12px;
            margin: 10px 0;
            background-color: #f1c40f;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        a.btn:hover {
            background-color: #d4ac0d;
        }
        .acciones a {
            margin: 0 4px;
            text-decoration: none;
            font-size: 18px;
        }
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                font-size: 14px;
            }
        }
    </style>
</head>
<body style='margin-left:260px; padding: 20px;'>
<div class="container">
    <h2>Clientes Registrados</h2>
    <a href="index.php" class="btn">← Volver al Menú</a>

    <div class="buscador">
        <input type="text" id="filtro" placeholder="Buscar por nombre, apellido, DNI o disciplina...">
    </div>

    <div style='overflow-x:auto;'><table id="tablaClientes">
        <thead>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Disciplina</th>
            <th>QR</th>
            <th>Gimnasio</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($cliente = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?= htmlspecialchars($cliente['apellido'] ?? '') ?></td>
                <td><?= htmlspecialchars($cliente['nombre'] ?? '') ?></td>
                <td><?= htmlspecialchars($cliente['dni'] ?? '') ?></td>
                <td><?= htmlspecialchars($cliente['telefono'] ?? '') ?></td>
                <td><?= htmlspecialchars($cliente['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($cliente['disciplina'] ?? '') ?></td>
                <td>
                    <?php
$qr_path = "qr/" . $cliente['dni'] . ".png";
if (file_exists($qr_path)) {
    echo "<a href='$qr_path' target='_blank' title='Ver QR'><i class='fas fa-qrcode'></i></a> ";
    echo "<a href='$qr_path' download title='Descargar QR'><i class='fas fa-download'></i></a>";
} else {
    echo "<a href='generar_qr_individual.php?id=" . $cliente['id'] . "' title='Generar QR'><i class='fas fa-qrcode'></i></a>";
}
?>
                </td>
                <td><?= htmlspecialchars($cliente['nombre_gimnasio'] ?? '') ?></td>
                <td class="acciones">
                    <a href="editar_cliente.php?id=<?= $cliente['id'] ?>" title="Editar">✏️</a>
                    <a href="eliminar_cliente.php?id=<?= $cliente['id'] ?>" title="Eliminar"
                       onclick="return confirm('¿Estás seguro de eliminar este cliente?')">🗑️</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table></div>
</div>

<script>
    document.getElementById('filtro').addEventListener('keyup', function () {
        let filtro = this.value.toLowerCase();
        let filas = document.querySelectorAll('#tablaClientes tbody tr');

        filas.forEach(function (fila) {
            let texto = fila.textContent.toLowerCase();
            fila.style.display = texto.includes(filtro) ? '' : 'none';
        });
    });
</script>
</body>
</html>
