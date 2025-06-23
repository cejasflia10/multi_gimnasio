<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

if (!isset($_SESSION['usuario'])) {
    die("Acceso denegado.");
}

$query = ($rol === 'admin') 
    ? "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio FROM clientes LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id"
    : "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio FROM clientes LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id WHERE gimnasio_id = $gimnasio_id";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes Registrados</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }

        h1 {
            text-align: center;
            font-size: 24px;
            color: gold;
            margin-top: 80px;
        }

        .volver {
            margin-bottom: 10px;
            display: inline-block;
            background-color: gold;
            color: black;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
        }

        .search-bar {
            margin-bottom: 10px;
            width: 100%;
            max-width: 400px;
            padding: 8px;
            font-size: 16px;
            border-radius: 4px;
            border: none;
        }

        .tabla-container {
            overflow-x: auto;
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

        .btn-generar {
            background-color: #222;
            color: gold;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-generar:hover {
            background-color: gold;
            color: #000;
        }

        @media screen and (max-width: 768px) {
            th, td {
                font-size: 13px;
                padding: 6px;
            }

            img {
                width: 60px;
                height: 60px;
            }

            .btn-generar {
                font-size: 12px;
                padding: 3px 6px;
            }

            h1 {
                font-size: 20px;
            }

            .volver {
                font-size: 14px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>

    <h1>Clientes Registrados</h1>
    <a class="volver" href="index.php">← Volver al Menú</a><br><br>

    <input type="text" class="search-bar" id="buscador" placeholder="Buscar por nombre, apellido, DNI o disciplina...">

    <div class="tabla-container">
        <table id="tabla-clientes">
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
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['apellido']) ?></td>
                        <td><?= htmlspecialchars($fila['nombre']) ?></td>
                        <td><?= htmlspecialchars($fila['dni']) ?></td>
                        <td><?= htmlspecialchars($fila['telefono']) ?></td>
                        <td><?= htmlspecialchars($fila['email']) ?></td>
                        <td><?= htmlspecialchars($fila['disciplina']) ?></td>
                        <td>
                            <?php
                            $qr_path = 'qr/cliente_' . $fila['id'] . '.png';
                            if (file_exists($qr_path)) {
                                echo "<img src='$qr_path' alt='QR' style='width: 80px; height: 80px; display:block; margin:auto;'><br>";
                                echo "<a href='$qr_path' download style='color: gold;'>Descargar</a>";
                            } else {
                                echo "<a href='generar_qr_individual.php?id={$fila['id']}' class='btn-generar'>Generar QR</a>";
                            }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($fila['nombre_gimnasio']) ?></td>
                        <td>
                            <a href="editar_cliente.php?id=<?= $fila['id'] ?>" style="color: gold;">✎</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.getElementById("buscador").addEventListener("input", function () {
            let filtro = this.value.toLowerCase();
            let filas = document.querySelectorAll("#tabla-clientes tbody tr");
            filas.forEach(function (fila) {
                let texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(filtro) ? "" : "none";
            });
        });
    </script>
</body>
</html>
