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
                <?php while ($fila = $resultado->fetch_assoc()) {
                    $qr_path = "qr/" . $fila['dni'] . ".png";
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($fila['apellido']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['nombre']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['dni']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['telefono']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['disciplina']) . "</td>";
                    echo "<td>";
                    if (file_exists($qr_path)) {
                        echo "<a class='btn-generar' href='$qr_path' target='_blank'>Ver QR</a>";
                    } else {
                        echo "<a class='btn-generar' href='generar_qr_individual.php?id=" . $fila['id'] . "'>Generar QR</a>";
                    }
                    echo "</td>";
                    echo "<td>" . htmlspecialchars($fila['nombre_gimnasio']) . "</td>";
                    echo "<td><a href='editar_cliente.php?id=" . $fila['id'] . "' style='color: gold;'>➤</a></td>";
                    echo "</tr>";
                } ?>
            </tbody>
        </table>
    </div>

    <script>
        const buscador = document.getElementById("buscador");
        buscador.addEventListener("input", function () {
            const filtro = buscador.value.toLowerCase();
            const filas = document.querySelectorAll("#tabla-clientes tbody tr");

            filas.forEach(fila => {
                const texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(filtro) ? "" : "none";
            });
        });
    </script>

</body>
</html>
