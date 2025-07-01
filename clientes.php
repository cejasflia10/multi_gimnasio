<?php
session_start();
if (!isset($_SESSION["gimnasio_id"])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';
include 'menu_horizontal.php';

$query = "SELECT id, apellido, nombre, dni, telefono, email, fecha_nacimiento, domicilio, disciplina, rfid_uid, fecha_vencimiento FROM clientes WHERE gimnasio_id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>

<html lang="es">
    
<head>
    <meta charset="UTF-8">
    <title>Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #FFD700;
            margin: 0;
            padding-top: 60px;
        }
        .contenido {
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #FFD700;
        }
        .boton {
            display: inline-block;
            margin: 10px 5px;
            padding: 10px 20px;
            background-color: #FFD700;
            color: #111;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .tabla-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            color: #FFD700;
        }
        th, td {
            padding: 10px;
            border: 1px solid #FFD700;
            text-align: center;
        }
        th {
            background-color: #333;
        }
        .acciones a {
            margin: 0 5px;
            color: #FFD700;
            text-decoration: none;
            font-weight: bold;
        }
        .acciones a:hover {
            color: #fff;
        }
        @media (max-width: 768px) {
            th, td {
                font-size: 12px;
                padding: 6px;
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
    <div class="contenido">
        <h1>Clientes del Gimnasio</h1>
        <a href="agregar_cliente.php" class="boton">➕ Agregar Cliente</a>
        <a href="index.php" class="boton">🏠 Volver al Panel</a>
        <div class="tabla-responsive">
        <table>
            <thead>
                <tr>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Nacimiento</th>
                    <th>Domicilio</th>
                    <th>Disciplina</th>
                    <th>RFID</th>
                    <th>Vencimiento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row["apellido"] ?></td>
                    <td><?= $row["nombre"] ?></td>
                    <td><?= $row["dni"] ?></td>
                    <td><?= $row["telefono"] ?></td>
                    <td><?= $row["email"] ?></td>
                    <td><?= $row["fecha_nacimiento"] ?></td>
                    <td><?= $row["domicilio"] ?></td>
                    <td><?= $row["disciplina"] ?></td>
                    <td><?= $row["rfid_uid"] ?></td>
                    <td><?= $row["fecha_vencimiento"] ?></td>
                    <td class="acciones">
                        <a href="editar_cliente.php?id=<?= $row['id'] ?>">✏️</a>
                        <a href="eliminar_cliente.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este cliente?')">🗑️</a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
