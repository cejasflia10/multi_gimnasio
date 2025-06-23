<?php
include 'conexion.php';
$gimnasio_id = isset($_GET['gimnasio_id']) ? intval($_GET['gimnasio_id']) : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = $_POST["apellido"];
    $nombre = $_POST["nombre"];
    $dni = $_POST["dni"];
    $fecha_nac = $_POST["fecha_nacimiento"];
    $domicilio = $_POST["domicilio"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $disciplina = $_POST["disciplina"];
    $gimnasio_id = $_POST["gimnasio_id"];

    $verificar = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
    if ($verificar->num_rows > 0) {
        echo "<script>alert('El DNI ya está registrado.'); window.history.back();</script>";
        exit;
    }

    $conexion->query("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id) 
    VALUES ('$apellido', '$nombre', '$dni', '$fecha_nac', '$domicilio', '$telefono', '$email', '$disciplina', $gimnasio_id)");

    echo "<script>alert('Registro exitoso.'); window.location.href='index.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: gold;
        }
        form {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            max-width: 500px;
            margin: auto;
        }
        label {
            display: block;
            margin-top: 10px;
            color: gold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            margin-top: 5px;
        }
        button {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background-color: gold;
            color: black;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        @media (max-width: 600px) {
            form {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<h2>FIGHT ACADEMY SCORPIONS</h2>

<form method="POST">
    <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">
    
    <label>Apellido:</label>
    <input type="text" name="apellido" required>

    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>DNI:</label>
    <input type="text" name="dni" required>

    <label>Fecha de nacimiento:</label>
    <input type="date" name="fecha_nacimiento" required>

    <label>Domicilio:</label>
    <input type="text" name="domicilio">

    <label>Teléfono:</label>
    <input type="text" name="telefono">

    <label>Email:</label>
    <input type="email" name="email">

    <label>Disciplina:</label>
    <select name="disciplina" required>
        <option value="">Seleccionar disciplina</option>
        <option value="Boxeo">Boxeo</option>
        <option value="Kickboxing">Kickboxing</option>
        <option value="MMA">MMA</option>
        <option value="Funcional">Funcional</option>
    </select>

    <button type="submit">Registrar</button>
</form>

</body>
</html>
