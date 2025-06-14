<?php
include 'conexion.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo']['tmp_name'];

    if (($handle = fopen($archivo, "r")) !== FALSE) {
        $fila = 0;
        while (($datos = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($fila === 0) {
                $fila++;
                continue; // saltar encabezado
            }

            list($apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $email, $rfid, $gimnasio_id) = $datos;

            $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, email, rfid, gimnasio_id)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $email, $rfid, $gimnasio_id);
            $stmt->execute();
        }
        fclose($handle);
        $mensaje = "✅ Importación realizada con éxito.";
    } else {
        $mensaje = "❌ Error al leer el archivo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Clientes</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        input[type="file"] {
            margin-top: 10px;
        }
        .btn {
            margin-top: 15px; padding: 10px 20px;
            background: #ffc107; color: #111;
            border: none; border-radius: 5px;
            cursor: pointer;
        }
        .btn:hover { background: #e0a800; }
        .mensaje { margin-top: 15px; color: #ffc107; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Importar Clientes (CSV)</h1>
    <p>El archivo debe tener las siguientes columnas:</p>
    <pre style="color:#ccc;background:#222;padding:10px;">apellido,nombre,dni,fecha_nacimiento,domicilio,email,rfid,gimnasio_id</pre>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="archivo" required>
        <br>
        <button type="submit" class="btn">📥 Importar</button>
    </form>
</div>
</body>
</html>
