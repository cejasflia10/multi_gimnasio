<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = $_POST["apellido"];
    $nombre = $_POST["nombre"];
    $dni = $_POST["dni"];
    $fecha_nacimiento = $_POST["fecha_nacimiento"];
    $domicilio = $_POST["domicilio"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $rfid = $_POST["rfid"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $disciplina = $_POST["disciplina"];
    $gimnasio_id = 1; // Fijo para gimnasio 1

    $edad = date_diff(date_create($fecha_nacimiento), date_create('today'))->y;

    $verificar = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
    $verificar->bind_param("s", $dni);
    $verificar->execute();
    $verificar->store_result();

    if ($verificar->num_rows > 0) {
        echo "<script>alert('Ya existe un cliente con ese DNI.'); window.location.href='registro_gimnasio1.php';</script>";
    } else {
        $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid, fecha_vencimiento, disciplina, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssisssssii", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid, $fecha_vencimiento, $disciplina, $gimnasio_id);
        if ($stmt->execute()) {
            echo "<script>alert('Registro exitoso'); window.location.href='registro_gimnasio1.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    $verificar->close();
}
?>

<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Registro Fight Academy</title>
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            background: #222;
            padding: 20px;
            border-radius: 10px;
            max-width: 500px;
            margin: auto;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 6px 0;
            background: #333;
            color: white;
            border: 1px solid #555;
            border-radius: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: gold;
            color: black;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h2>Registro de Cliente - Fight Academy</h2>
    <form method='POST'>
        <input type='text' name='apellido' placeholder='Apellido' required>
        <input type='text' name='nombre' placeholder='Nombre' required>
        <input type='text' name='dni' placeholder='DNI' required>
        <input type='date' name='fecha_nacimiento' required>
        <input type='text' name='domicilio' placeholder='Domicilio' required>
        <input type='text' name='telefono' placeholder='Teléfono' required>
        <input type='email' name='email' placeholder='Email'>
        <input type='text' name='rfid' placeholder='Código RFID' required>
        <input type='date' name='fecha_vencimiento' required>
        <select name='disciplina' required>
            <option value='' disabled selected>Seleccionar disciplina</option>
            <option value='Boxeo'>Boxeo</option>
            <option value='Kickboxing'>Kickboxing</option>
            <option value='MMA'>MMA</option>
        </select>
        <button type='submit'>Registrar</button>
    </form>
</body>
</html>
