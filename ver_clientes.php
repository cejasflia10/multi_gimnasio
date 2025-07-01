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
            margin: 2px;
            display: inline-block;
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
<script>
// Reactivar pantalla completa con el primer clic
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones como F12, Ctrl+Shift+I
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
</script>

<body>

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
            $n;
        }
        ?>
    </tbody>
</table>

</body>
</html>
