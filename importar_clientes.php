<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';
$gimnasio_id = $_SESSION['gimnasio_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["archivo_csv"])) {
    $archivo = $_FILES["archivo_csv"]["tmp_name"];
    $handle = fopen($archivo, "r");
    $fila = 0;
    $importados = 0;
    $duplicados = 0;

    while (($datos = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if ($fila == 0) { $fila++; continue; } // Saltar encabezado

        list($apellido, $nombre, $dni, $fecha_nac, $domicilio, $telefono, $email, $rfid, $disciplina, $fecha_vto) = $datos;

        // Verificar si el DNI ya existe
        $check = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
        $check->bind_param("s", $dni);
        $check->execute();
        $check->store_result();

        if ($check->num_rows == 0) {
            $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, rfid, disciplina, fecha_vencimiento, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssi", $apellido, $nombre, $dni, $fecha_nac, $domicilio, $telefono, $email, $rfid, $disciplina, $fecha_vto, $gimnasio_id);
            $stmt->execute();
            $importados++;
        } else {
            $duplicados++;
        }
        $fila++;
    }

    fclose($handle);
    echo "<script>alert('Importación finalizada: $importados clientes importados, $duplicados duplicados.'); window.location.href='clientes.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Clientes por CSV</title>
    <style>
        body { background: #000; color: #FFD700; font-family: Arial; padding: 30px; }
        input[type=file], input[type=submit] {
            padding: 10px;
            margin: 10px 0;
            background: #222;
            color: #FFD700;
            border: 1px solid #FFD700;
        }
        .btn {
            background-color: #FFD700;
            color: #000;
            font-weight: bold;
            border: none;
            padding: 10px 20px;
        }
    </style>
</head>
<body>
    <h2>Importar Clientes (Archivo CSV)</h2>
    <form method="post" enctype="multipart/form-data">
        <label>Seleccionar archivo CSV:</label><br>
        <input type="file" name="archivo_csv" accept=".csv" required><br>
        <input type="submit" class="btn" value="Importar Clientes">
    </form>
    <p>Formato requerido: apellido,nombre,dni,fecha_nacimiento,domicilio,telefono,email,rfid,disciplina,fecha_vencimiento</p>
</body>
</html>
