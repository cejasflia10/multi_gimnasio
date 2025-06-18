<?php
include 'conexion.php';

// Obtener lista de gimnasios
$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $contrasena = trim($_POST["contrasena"]);
    $id_gimnasio = $_POST["id_gimnasio"];
    $rol = "admin";

    // Encriptar contraseña
    $contrasena_hash = password_hash($contrasena, PASSWORD_BCRYPT);

    // Insertar en tabla usuarios
    $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, contrasena, rol, id_gimnasio) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $usuario, $contrasena_hash, $rol, $id_gimnasio);

    if ($stmt->execute()) {
        echo "<script>alert('Administrador creado correctamente'); window.location.href='login.php';</script>";
    } else {
        echo "<script>alert('Error al crear administrador: " . $stmt->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Administrador</title>
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            padding: 30px;
        }
        .formulario {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            margin-top: 15px;
            display: block;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            background-color: #333;
            color: #FFD700;
            border: none;
            border-radius: 5px;
        }
        button {
            margin-top: 20px;
            background-color: #FFD700;
            color: #111;
            border: none;
            padding: 10px;
            width: 100%;
            font-weight: bold;
            border-radius: 5px;
        }
        h2 {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="formulario">
        <h2>Crear Administrador Manualmente</h2>
        <form method="POST">
            <label>Usuario:</label>
            <input type="text" name="usuario" required>

            <label>Contraseña:</label>
            <input type="password" name="contrasena" required>

            <label>Gimnasio:</label>
            <select name="id_gimnasio" required>
                <option value="">-- Seleccionar gimnasio --</option>
                <?php while ($g = $gimnasios->fetch_assoc()) { ?>
                    <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
                <?php } ?>
            </select>

            <button type="submit">Crear Administrador</button>
        </form>
    </div>
</body>
</html>
