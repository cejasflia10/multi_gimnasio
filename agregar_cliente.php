<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $dni = $_POST['dni'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $rfid = $_POST['rfid'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $disciplina_id = $_POST['disciplina_id'];
    $gimnasio_id = $_SESSION['gimnasio_id'];

    $fecha_actual = date('Y-m-d');
    $edad = date_diff(date_create($fecha_nacimiento), date_create($fecha_actual))->y;

    // Validación de DNI duplicado
    $verificar = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $verificar->bind_param("si", $dni, $gimnasio_id);
    $verificar->execute();
    $verificar->store_result();

    if ($verificar->num_rows > 0) {
        echo "<script>alert('El DNI ingresado ya está registrado.'); window.location.href='agregar_cliente.php';</script>";
        exit();
    }

    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, telefono, email, rfid, fecha_vencimiento, disciplina_id, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissssii", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $telefono, $email, $rfid, $fecha_vencimiento, $disciplina_id, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Cliente registrado exitosamente'); window.location.href='clientes.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $verificar->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Cliente</title>
    <style>
        body {
            background-color: #000;
            color: #FFD700;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        form {
            max-width: 600px;
            margin: auto;
            background-color: #111;
            padding: 20px;
            border-radius: 10px;
        }

        label {
            display: block;
            margin-top: 10px;
        }

        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            background-color: #222;
            color: #fff;
            border: 1px solid #555;
            border-radius: 5px;
        }

        button {
            margin-top: 15px;
            background-color: #FFD700;
            color: #000;
            padding: 10px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
        }

        h2 {
            text-align: center;
            color: #FFD700;
        }
    </style>
</head>
<body>
    <h2>Registrar Nuevo Cliente</h2>
    <form method="POST">
        <label>Apellido:</label>
        <input type="text" name="apellido" required>

        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>DNI:</label>
        <input type="text" name="dni" required>

        <label>Fecha de Nacimiento:</label>
        <input type="date" name="fecha_nacimiento" required>

        <label>Teléfono:</label>
        <input type="text" name="telefono">

        <label>Email:</label>
        <input type="email" name="email">

        <label>RFID:</label>
        <input type="text" name="rfid" required>

        <label>Fecha de Vencimiento:</label>
        <input type="date" name="fecha_vencimiento" required>

        <label>Disciplina:</label>
        <select name="disciplina_id" required>
            <option value="">Seleccionar Disciplina</option>
            <?php
            $gimnasio_id = $_SESSION['gimnasio_id'];
            $query = "SELECT id, nombre FROM disciplinas WHERE gimnasio_id = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("i", $gimnasio_id);
            $stmt->execute();
            $stmt->bind_result($id_disciplina, $nombre_disciplina);

            while ($stmt->fetch()) {
                echo '<option value="' . $id_disciplina . '">' . $nombre_disciplina . '</option>';
            }

            $stmt->close();
            ?>
        </select>

        <button type="submit">Guardar Cliente</button>
    </form>
</body>
</html>
