<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
$adicionales = $conexion->query("SELECT id, nombre FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes Registrados</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-volver {
            background-color: gold;
            color: #111;
            padding: 10px 20px;
            border: none;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .search-box {
            text-align: right;
            margin-bottom: 15px;
        }
        .search-box input {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid gold;
            background-color: #222;
            color: gold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
        }
        th {
            background-color: #333;
            color: gold;
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        td {
            border: 1px solid gold;
            padding: 8px;
        }
        td.align-left {
            text-align: left;
        }
        td.align-center {
            text-align: center;
        }
        tr:nth-child(even) {
            background-color: #1a1a1a;
        }
        .btn-generar {
            color: gold;
            text-decoration: underline;
        }
        .acciones a {
            margin: 0 5px;
            color: gold;
            text-decoration: none;
            font-size: 18px;
        }
        @media screen and (max-width: 768px) {
            .container {
                padding: 10px;
            }
            table, thead, tbody, th, td, tr {
                font-size: 14px;
            }
            .search-box {
                text-align: center;
            }
            .search-box input {
                width: 100%;
                margin-top: 10px;
            }
        }
    </style>
    <script>
        function filtrarClientes() {
            var input = document.getElementById("busqueda").value.toLowerCase();
            var filas = document.getElementById("tablaClientes").getElementsByTagName("tr");
            for (var i = 1; i < filas.length; i++) {
                var fila = filas[i];
                var textoFila = fila.textContent.toLowerCase();
                fila.style.display = textoFila.includes(input) ? "" : "none";
            }
        }
    </script>
</head>
<body>
<div class="container">
    <h1>Clientes Registrados</h1>
    <a href="index.php" class="btn-volver">← Volver al Menú</a>

    <div class="search-box">
        <input type="text" id="busqueda" placeholder="Buscar por nombre, apellido, DNI o disciplina..." onkeyup="filtrarClientes()">
    </div>

    <table id="tablaClientes">
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
        <?php while ($fila = $clientes->fetch_assoc()) : ?>
            <tr>
                <td class="align-left"><?= htmlspecialchars($fila['apellido'] ?? '') ?></td>
                <td class="align-left"><?= htmlspecialchars($fila['nombre'] ?? '') ?></td>
                <td class="align-center"><?= htmlspecialchars($fila['dni'] ?? '') ?></td>
                <td class="align-center"><?= htmlspecialchars($fila['telefono'] ?? '') ?></td>
                <td class="align-left"><?= htmlspecialchars($fila['email'] ?? '') ?></td>
                <td class="align-left"><?= htmlspecialchars($fila['disciplina'] ?? '') ?></td>
                <td class="align-center">
                    <?php
                    $qr_path = "qr/" . ($fila['dni'] ?? '') . ".png";
                    if (file_exists($qr_path)) {
                        echo "<a class='btn-generar' href='$qr_path' target='_blank'>Ver QR</a>";
                    } else {
                        echo "<a class='btn-generar' href='generar_qr_individual.php?id=" . ($fila['id'] ?? '') . "'>Generar QR</a>";
                    }
                    ?>
                </td>
                <td class="align-left"><?= htmlspecialchars($fila['nombre_gimnasio'] ?? '') ?></td>
                <td class="acciones align-center">
                    <a href="editar_cliente.php?id=<?= $fila['id'] ?>">✏️</a>
                    <a href="eliminar_cliente.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Estás seguro de que deseas eliminar este cliente?');">🗑️</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
