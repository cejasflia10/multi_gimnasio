<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias Registradas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid gold;
        }
        th {
            background-color: #333;
            color: gold;
        }
        tr:hover {
            background-color: #444;
        }
        @media (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                display: none;
            }
            td {
                border: none;
                padding: 10px;
                position: relative;
            }
            td::before {
                content: attr(data-label);
                font-weight: bold;
                color: gold;
                display: block;
            }
        }
    </style>
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
    <h1>Asistencias Registradas</h1>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Fecha</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT c.nombre, c.apellido, c.dni, a.fecha, a.hora
                      FROM asistencias a
                      JOIN clientes c ON a.cliente_id = c.id
                      WHERE c.gimnasio_id = $gimnasio_id
                      ORDER BY a.fecha DESC, a.hora DESC
                      LIMIT 100";
            $resultado = $conexion->query($query);
            if ($resultado && $resultado->num_rows > 0) {
                while ($fila = $resultado->fetch_assoc()) {
                    echo "<tr>
                            <td data-label='Nombre'>{$fila['nombre']} {$fila['apellido']}</td>
                            <td data-label='DNI'>{$fila['dni']}</td>
                            <td data-label='Fecha'>{$fila['fecha']}</td>
                            <td data-label='Hora'>{$fila['hora']}</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='4'>No se encontraron asistencias registradas.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</body>
</html>
