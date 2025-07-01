<?php
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Filtro por tipo
$tipo = $_GET['tipo'] ?? '';
$condicion = "WHERE gimnasio_id = $gimnasio_id";
if (!empty($tipo)) {
    $condicion .= " AND tipo = '$tipo'";
}

// Obtener tipos distintos para el filtro
$tipos = $conexion->query("SELECT DISTINCT tipo FROM suplementos WHERE gimnasio_id = $gimnasio_id");

// Traer suplementos
$suplementos = $conexion->query("SELECT * FROM suplementos $condicion ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Suplementos</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            padding: 30px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            color: #ffc107;
        }

        form {
            text-align: center;
            margin-bottom: 20px;
        }

        select {
            padding: 8px;
            font-size: 16px;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            overflow-x: auto;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #333;
            text-align: center;
        }

        th {
            color: #ffc107;
        }

        .btn {
            padding: 5px 10px;
            margin: 0 3px;
            background: #ffc107;
            color: #111;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        @media screen and (max-width: 768px) {
            .container {
                padding: 15px 10px;
            }

            table, thead, tbody, th, td, tr {
                display: block;
                width: 100%;
            }

            thead tr {
                display: none;
            }

            tr {
                margin-bottom: 15px;
                background-color: #222;
                border-radius: 5px;
                padding: 10px;
            }

            td {
                text-align: left;
                padding: 10px;
                border: none;
                border-bottom: 1px solid #333;
            }

            td:before {
                content: attr(data-label);
                font-weight: bold;
                color: #ffc107;
                display: block;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>

<div class="container">
    <h1>Suplementos</h1>

    <form method="GET">
        <label>Filtrar por tipo:</label>
        <select name="tipo" onchange="this.form.submit()">
            <option value="">-- Todos --</option>
            <?php while ($fila = $tipos->fetch_assoc()): ?>
                <option value="<?= $fila['tipo'] ?>" <?= $tipo == $fila['tipo'] ? 'selected' : '' ?>>
                    <?= ucfirst($fila['tipo']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Compra</th>
                <th>Venta</th>
                <th>Stock</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($s = $suplementos->fetch_assoc()): ?>
            <tr>
                <td data-label="Nombre"><?= $s['nombre'] ?></td>
                <td data-label="Tipo"><?= $s['tipo'] ?></td>
                <td data-label="Compra">$<?= number_format($s['precio_compra'], 2) ?></td>
                <td data-label="Venta">$<?= number_format($s['precio_venta'], 2) ?></td>
                <td data-label="Stock"><?= $s['stock'] ?></td>
                <td data-label="Acciones">
                    <button class="btn" onclick="location.href='editar_suplemento.php?id=<?= $s['id'] ?>'">Editar</button>
                    <button class="btn" onclick="if(confirm('¿Eliminar este suplemento?')) location.href='eliminar_suplemento.php?id=<?= $s['id'] ?>'">Eliminar</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
