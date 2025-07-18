<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$resultado = $conexion->query("SELECT * FROM clientes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Clientes</title>
    <link rel="stylesheet" href="estilo_unificado.css">
      
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
<div class="contenedor">
<h2>Listado de Clientes</h2>

<input type="text" id="buscador" class="buscador" placeholder="Buscar por nombre, apellido o DNI" onkeyup="buscarCliente()">

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Disciplina</th>
            <th>QR</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $n = 1;
        while ($fila = $resultado->fetch_assoc()) {
            echo "<tr>";
            echo "<td>$n</td>";
            echo "<td>{$fila['apellido']}</td>";
            echo "<td>{$fila['nombre']}</td>";
            echo "<td>{$fila['dni']}</td>";
            echo "<td>{$fila['disciplina']}</td>";

            $qrPath = "qr/qr_cliente_{$fila['id']}.png";
            if (file_exists($qrPath)) {
                echo "<td><img src='$qrPath' alt='QR' width='40'></td>";
            } else {
                echo "<td><a class='btn-qr' href='generar_qr_individual.php?id={$fila['id']}'>Generar QR</a></td>";
            }

            echo "<td>
                    <a href='editar_cliente.php?id={$fila['id']}' class='btn-qr'>✏️ Editar</a>
                    <a href='eliminar_cliente.php?id={$fila['id']}' class='btn-qr' onclick='return confirm(\"¿Seguro que querés eliminar este cliente?\")'>🗑️ Eliminar</a>
                  </td>";
            echo "</tr>";
            $n++; // ✅ Corrección aquí
        }
        ?>
    </tbody>
</table>
</div>
</body>
</html>
