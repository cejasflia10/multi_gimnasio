<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "SELECT m.*, c.nombre, c.apellido, p.nombre AS nombre_plan 
          FROM membresias m 
          JOIN clientes c ON m.cliente_id = c.id 
          JOIN planes p ON m.plan_id = p.id 
          WHERE m.gimnasio_id = $gimnasio_id 
          ORDER BY m.fecha_inicio DESC";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <meta charset="UTF-8">
    <title>Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #111;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid gold;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        tr.vencida {
            background-color: #330000;
        }
        .acciones a {
            margin: 0 5px;
            text-decoration: none;
            font-weight: bold;
            padding: 6px 12px;
            border-radius: 4px;
        }
        .editar { background-color: orange; color: black; }
        .eliminar { background-color: crimson; color: white; }
        .renovar { background-color: limegreen; color: black; }

        .boton-volver {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            margin-top: 20px;
            text-decoration: none;
            display: inline-block;
            border-radius: 6px;
            font-weight: bold;
        }
        @media screen and (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                text-align: left;
            }
            td {
                text-align: left;
                border-bottom: 1px solid #444;
            }
            .acciones a {
                display: block;
                margin-bottom: 5px;
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

<h1>Listado de Membresías</h1>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Clases</th>
            <th>Total</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $n = 1;
        while ($fila = $resultado->fetch_assoc()):
            $vencida = (strtotime($fila['fecha_vencimiento']) < strtotime(date("Y-m-d"))) ? 'vencida' : '';
            $id_membresia = $fila['id'];
        ?>
            <tr class="<?= $vencida ?>">
                <td><?= $n ?></td>
                <td><?= $fila['apellido'] . ', ' . $fila['nombre'] ?></td>
                <td><?= $fila['nombre_plan'] ?></td>
                <td><?= $fila['fecha_inicio'] ?></td>
                <td><?= $fila['fecha_vencimiento'] ?></td>
                <td><?= $fila['clases_disponibles'] ?></td>
                <td>$<?= number_format($fila['total'], 2) ?></td>
                <td class="acciones">
                    <a href="editar_membresia.php?id=<?= $id_membresia ?>" class="editar">✏️</a>
                    <a href="eliminar_membresia.php?id=<?= $id_membresia ?>" class="eliminar" onclick="return confirm('¿Eliminar esta membresía?')">❌</a>
                    <a href="renovar_membresia.php?id=<?= $id_membresia ?>" class="renovar">♻️</a>
                </td>
            </tr>
        <?php $n++; endwhile; ?>
    </tbody>
</table>

<a href="index.php" class="boton-volver">Volver al Menú</a>
<a href="ver_historial_membresias.php?cliente_id=<?= $fila['cliente_id'] ?>">📜 Historial</a>

</body>
</html>
