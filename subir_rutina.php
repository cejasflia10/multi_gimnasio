<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

// Obtener lista de alumnos del profesor
$alumnos = $conexion->query("
    SELECT id, apellido, nombre FROM clientes WHERE gimnasio_id = $gimnasio_id
    ORDER BY c.apellido
");

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $tipo = $_POST['tipo'];
    $fecha = date('Y-m-d H:i:s');
    $archivo = $_FILES['archivo']['name'];
    $tmp = $_FILES['archivo']['tmp_name'];
    $destino = "archivos_profesor/" . uniqid() . "_" . basename($archivo);

    if (move_uploaded_file($tmp, $destino)) {
        $stmt = $conexion->prepare("INSERT INTO archivos_profesor (profesor_id, cliente_id, tipo, archivo, fecha) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $profesor_id, $cliente_id, $tipo, $destino, $fecha);
        $stmt->execute();
        $mensaje = "✅ Archivo cargado correctamente.";
    } else {
        $mensaje = "❌ Error al subir el archivo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Rutina o Archivo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        form {
            max-width: 600px;
            margin: auto;
            background: #111;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid gold;
        }
        label, select, input {
            display: block;
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            border: none;
        }
        button {
            margin-top: 20px;
            background: gold;
            color: black;
            font-weight: bold;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
        }
        .mensaje {
            text-align: center;
            margin-top: 15px;
            color: lightgreen;
        }
    </style>
</head>
<body>

<h1 style="text-align:center;">📤 Subir Rutina / Archivo</h1>

<form method="POST" enctype="multipart/form-data">
    <label>Seleccionar Alumno:</label>
    <select name="cliente_id" required>
        <option value="">-- Elegir alumno --</option>
        <?php while ($a = $alumnos->fetch_assoc()): ?>
            <option value="<?= $a['id'] ?>"><?= $a['apellido'] ?>, <?= $a['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Tipo de archivo:</label>
    <select name="tipo" required>
        <option value="Rutina">Rutina</option>
        <option value="Nutrición">Nutrición</option>
        <option value="Otro">Otro</option>
    </select>

    <label>Archivo (PDF, JPG, PNG):</label>
    <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required>

    <button type="submit">Subir Archivo</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>

</body>
</html>
