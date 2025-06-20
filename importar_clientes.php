<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'];
$importados = 0;
$omitidos = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo_tmp = $_FILES['archivo_csv']['tmp_name'];
    if (($handle = fopen($archivo_tmp, "r")) !== false) {
        $primera = true;
        while (($datos = fgetcsv($handle, 1000, ",")) !== false) {
            if ($primera) { $primera = false; continue; }

            list($apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $rfid, $fecha_vencimiento, $disciplina) = $datos;

            if (empty($dni)) continue;

            $check = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
            $check->bind_param("si", $dni, $gimnasio_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows == 0) {
                $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, rfid, fecha_vencimiento, disciplina, gimnasio_id)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $rfid, $fecha_vencimiento, $disciplina, $gimnasio_id);
                if ($stmt->execute()) {
                    $importados++;
                }
                $stmt->close();
            } else {
                $omitidos++;
            }
            $check->close();
        }
        fclose($handle);
    }
    echo "<p style='color:yellow;'>Importados: $importados | Omitidos (duplicados): $omitidos</p>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Clientes</title>
    <style>
        body { background-color: #111; color: #FFD700; font-family: Arial; text-align: center; padding-top: 50px; }
        input, button { padding: 10px; margin: 10px; border-radius: 5px; border: 1px solid #FFD700; background-color: #222; color: #FFD700; }
    </style>
</head>
<body>
    <h2>Importar Clientes desde CSV</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="archivo_csv" accept=".csv" required>
        <br>
        <button type="submit">Importar</button>
    </form>
</body>
</html>
