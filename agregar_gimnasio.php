<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('agregar_gimnasio')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

// Validar si se ha enviado el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST["nombre"];
    $direccion = $_POST["direccion"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];

    $stmt = $conexion->prepare("INSERT INTO gimnasios (nombre, direccion, telefono, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nombre, $direccion, $telefono, $email);
    $stmt->execute();
    $stmt->close();
}

// Eliminar gimnasio si se pasa un ID por GET
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $conexion->query("DELETE FROM gimnasios WHERE id = $id");
}

// Obtener gimnasios existentes
$resultado = $conexion->query("SELECT * FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Gimnasio</title>
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            padding: 30px;
        }
        h2 {
            color: #FFD700;
        }
        form input, form button {
            padding: 10px;
            margin: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            color: #fff;
        }
        th, td {
            padding: 12px;
            border: 1px solid #444;
            text-align: center;
        }
        th {
            background-color: #333;
            color: #FFD700;
        }
        a.btn {
            padding: 6px 12px;
            text-decoration: none;
            color: black;
            background-color: #FFD700;
            border-radius: 5px;
        }
        .volver {
            margin-top: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>

    <h2>Agregar Gimnasio</h2>

    <form method="POST">
        <input type="text" name="nombre" placeholder="Nombre" required>
        <input type="text" name="direccion" placeholder="Dirección" required>
        <input type="text" name="telefono" placeholder="Teléfono" required>
        <input type="email" name="email" placeholder="Email" required>
        <button type="submit">Agregar Gimnasio</button>
    </form>

    <h2>Listado de Gimnasios</h2>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?= $fila["nombre"] ?></td>
                    <td><?= $fila["direccion"] ?></td>
                    <td><?= $fila["telefono"] ?></td>
                    <td><?= $fila["email"] ?></td>
                    <td>
                        <a class="btn" href="editar_gimnasio.php?id=<?= $fila['id'] ?>">Editar</a>
                        <a class="btn" href="agregar_gimnasio.php?eliminar=<?= $fila['id'] ?>" onclick="return confirm('¿Eliminar este gimnasio?')">Eliminar</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <a href="index.php" class="btn volver">Volver al Menú</a>

</body>
</html>
