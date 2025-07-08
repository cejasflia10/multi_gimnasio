<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$mensaje = '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$es_admin = ($_SESSION['rol'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gimnasio_seleccionado = $es_admin ? intval($_POST['gimnasio_id']) : $gimnasio_id;

    if ($apellido && $nombre && $dni) {
        $stmt = $conexion->prepare("INSERT INTO profesores (apellido, nombre, dni, telefono, email, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $apellido, $nombre, $dni, $telefono, $email, $gimnasio_seleccionado);
        if ($stmt->execute()) {
            $mensaje = "✅ Profesor registrado correctamente.";
        } else {
            $mensaje = "❌ Error al registrar: " . $stmt->error;
        }
    } else {
        $mensaje = "⚠️ Completá todos los campos obligatorios.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Profesor</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial;
            padding: 20px;
        }
        .formulario {
            max-width: 500px;
            margin: auto;
            background-color: #111;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #444;
        }
        input, select, button {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            font-size: 16px;
            background-color: #222;
            color: gold;
            border: 1px solid #555;
        }
        button {
            background-color: #333;
            cursor: pointer;
        }
        .mensaje {
            text-align: center;
            margin-bottom: 15px;
            color: lime;
        }
        h2 {
            text-align: center;
            color: white;
        }
    </style>
</head>
<body>

<div class="formulario">
    <h2>➕ Registrar Profesor</h2>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="apellido" placeholder="Apellido" required>
        <input type="text" name="nombre" placeholder="Nombre" required>
        <input type="text" name="dni" placeholder="DNI" required>
        <input type="text" name="telefono" placeholder="Teléfono">
        <input type="email" name="email" placeholder="Email">

        <?php if ($es_admin): ?>
            <select name="gimnasio_id" required>
                <option value="">Seleccione gimnasio</option>
                <?php
                $gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
                while ($g = $gimnasios->fetch_assoc()):
                ?>
                    <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
                <?php endwhile; ?>
            </select>
        <?php endif; ?>

        <button type="submit">Guardar Profesor</button>
    </form>
</div>

</body>
</html>
