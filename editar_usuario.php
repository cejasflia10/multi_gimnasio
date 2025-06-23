<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

if (!isset($_GET['id'])) {
    die("ID de usuario no especificado.");
}

$id = intval($_GET['id']);
$query = "SELECT * FROM usuarios WHERE id = $id";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Usuario no encontrado.");
}

$usuario = $resultado->fetch_assoc();

// Obtener gimnasios disponibles
$gimnasios_resultado = $conexion->query("SELECT id, nombre FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #121212;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: gold;
        }
        form {
            max-width: 500px;
            margin: auto;
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            background-color: #2c2c2c;
            color: white;
            border: 1px solid gold;
            border-radius: 5px;
        }
        input[type="submit"] {
            background-color: gold;
            color: black;
            cursor: pointer;
            margin-top: 20px;
            font-weight: bold;
        }
        input[type="submit"]:hover {
            background-color: #e0c200;
        }

        @media screen and (max-width: 600px) {
            form {
                width: 100%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <h1>Editar Usuario</h1>
    <form action="guardar_edicion_usuario.php" method="POST">
        <input type="hidden" name="id" value="<?= htmlspecialchars($usuario['id']) ?>">

        <label>Usuario:</label>
        <input type="text" name="usuario" value="<?= htmlspecialchars($usuario['usuario']) ?>" required>

        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>">

        <label>Rol:</label>
        <select name="rol" required>
            <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="cliente_gym" <?= $usuario['rol'] === 'cliente_gym' ? 'selected' : '' ?>>Cliente Gym</option>
            <option value="profesor" <?= $usuario['rol'] === 'profesor' ? 'selected' : '' ?>>Profesor</option>
        </select>

        <label>Gimnasio:</label>
        <select name="id_gimnasio" required>
            <?php while ($gim = $gimnasios_resultado->fetch_assoc()): ?>
                <option value="<?= $gim['id'] ?>" <?= $usuario['id_gimnasio'] == $gim['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($gim['nombre']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="submit" value="Guardar Cambios">
    </form>
</body>
</html>
