<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$resultado = $conexion->query("SELECT * FROM clientes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Clientes</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            color: gold;
        }
        th, td {
            padding: 10px;
            border: 1px solid gold;
            text-align: center;
        }
        .btn-qr {
            padding: 6px 10px;
            background: #222;
            color: gold;
            border: 1px solid gold;
            cursor: pointer;
            border-radius: 5px;
        }
        .buscador {
            margin: 15px;
            padding: 10px;
            font-size: 16px;
            width: 300px;
        }
    </style>
    <script>
        function buscarCliente() {
            var input = document.getElementById("buscador").value.toLowerCase();
            var filas = document.querySelectorAll("tbody tr");

            filas.forEach(fila => {
                let texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(input) ? "" : "none";
            });
        }
    </script>
</head>
<body>

<h2>Listado de Clientes</h2>

<input type="text" id="buscador" class="buscador" placeholder="Buscar por nombre, apellido o DNI" onkeyup="buscarCliente()">

<table>
    <thead>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Disciplina</th>
            <th>QR</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $resultado->fetch_assoc()) {
            $id = $row['id'];
            $dni = $row['dni'];
            $archivo_qr = "qr_cliente_" . $id . ".png";
            $ruta_qr = "qr/" . $archivo_qr;
            $qr_generado = file_exists($ruta_qr);
            ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['dni'] ?></td>
                <td><?= $row['disciplina'] ?></td>
                <td>
                    <?php if ($qr_generado): ?>
                        <img src="<?= $ruta_qr ?>" alt="QR" width="60">
                    <?php else: ?>
                        <a class="btn-qr" href="generar_qr_individual.php?id=<?= $id ?>">Generar QR</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

</body>
</html>
