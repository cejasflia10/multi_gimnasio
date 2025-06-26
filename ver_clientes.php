<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

// VERIFICAR QUE ESTÁS LOGUEADO
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol'])) {
    die("Acceso denegado. No ha iniciado sesión correctamente.");
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// OPCIONAL: Debug del rol
// echo "ROL: $rol";

// MOSTRAR EL MENÚ CORRESPONDIENTE
if ($rol === 'admin') {
    include 'menu_horizontal_admin.php'; // Asegurate de tener este archivo si el admin tiene menú diferente
} else {
    include 'menu_horizontal.php';
}

// CONSULTA DIFERENCIADA POR ROL
if ($rol === 'admin') {
    $query = "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio 
              FROM clientes 
              LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id";
} else {
    $query = "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio 
              FROM clientes 
              LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id
              WHERE clientes.gimnasio_id = $gimnasio_id";
}

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
            margin: 0;
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
        }

        .container {
            margin-left: 260px;
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
            overflow-x: auto;
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

        .btn-volver {
            background-color: gold;
            color: #111;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
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

        .search-box {
            margin-bottom: 15px;
            text-align: right;
        }

        .search-box input {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        @media screen and (max-width: 768px) {
            .container {
                margin-left: 0;
                padding: 10px;
            }

            table, thead, tbody, th, td, tr {
                font-size: 14px;
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
        <?php while ($fila = $resultado->fetch_assoc()) : ?>
            <tr>
                <td><?= htmlspecialchars($fila['apellido'] ?? '') ?></td>
                <td><?= htmlspecialchars($fila['nombre'] ?? '') ?></td>
                <td><?= htmlspecialchars($fila['dni'] ?? '') ?></td>
                <td><?= htmlspecialchars($fila['telefono'] ?? '') ?></td>
                <td><?= htmlspecialchars($fila['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($fila['disciplina'] ?? '') ?></td>
                <td>
                    <?php
                    $qr_path = "qr/" . ($fila['dni'] ?? '') . ".png";
                    if (file_exists($qr_path)) {
                        echo "<a class='btn-generar' href='$qr_path' target='_blank'>Ver QR</a>";
                    } else {
                        echo "<a class='btn-generar' href='generar_qr_individual.php?id=" . ($fila['id'] ?? '') . "'>Generar QR</a>";
                    }
                    ?>
                </td>
                <td><?= htmlspecialchars($fila['nombre_gimnasio'] ?? '') ?></td>
                <td class="acciones">
                    <a href="editar_cliente.php?id=<?= $fila['id'] ?>"><i class="fas fa-edit"></i>✎</a>
                    <a href="eliminar_cliente.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Estás seguro de que deseas eliminar este cliente?');">🗑️</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
