<?php
include 'conexion.php';
include 'menu.php';

$consulta = "SELECT * FROM clientes";
$resultado = mysqli_query($conexion, $consulta);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes registrados</title>
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .contenido {
            margin-left: 240px;
            padding: 20px;
        }
        h2 {
            color: #f1c40f;
        }
        input[type="text"] {
            width: 300px;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #f1c40f;
            background-color: #1a1a1a;
            color: #fff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1a1a1a;
            color: #fff;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #f1c40f;
        }
        th {
            background-color: #222;
            color: #f1c40f;
        }
        a.btn-editar {
            background-color: #f1c40f;
            color: #111;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
        }
        a.btn-editar:hover {
            background-color: #d4ac0d;
        }
    </style>
    <script>
        function filtrarClientes() {
            let input = document.getElementById("buscador").value.toLowerCase();
            let filas = document.querySelectorAll("#tabla-clientes tbody tr");
            filas.forEach(fila => {
                let texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(input) ? "" : "none";
            });
        }
    </script>
</head>
<body>
<div class="contenido">
    <h2>Clientes registrados</h2>
    <input type="text" id="buscador" onkeyup="filtrarClientes()" placeholder="Buscar por nombre, apellido, DNI, email...">

    <table id="tabla-clientes">
        <thead>
            <tr>
                <th>DNI</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Fecha Nac.</th>
                <th>Edad</th>
                <th>Domicilio</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>RFID</th>
                <th>Gimnasio</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($cliente = mysqli_fetch_assoc($resultado)) {
                $fecha_nac = new DateTime($cliente['fecha_nacimiento']);
                $hoy = new DateTime();
                $edad = $hoy->diff($fecha_nac)->y;
                echo "<tr>
                    <td>{$cliente['dni']}</td>
                    <td>{$cliente['nombre']}</td>
                    <td>{$cliente['apellido']}</td>
                    <td>{$cliente['fecha_nacimiento']}</td>
                    <td>$edad</td>
                    <td>{$cliente['domicilio']}</td>
                    <td>{$cliente['telefono']}</td>
                    <td>{$cliente['email']}</td>
                    <td>{$cliente['rfid_uid']}</td>
                    <td>{$cliente['gimnasio']}</td>
                    <td><a class='btn-editar' href='editar_cliente.php?id={$cliente['id']}'>Editar</a></td>
                </tr>";
            } ?>
        </tbody>
    </table>
</div>
</body>
</html>
