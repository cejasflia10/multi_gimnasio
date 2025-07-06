<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? null;
if (!$profesor_id) die("Acceso denegado.");

// Guardar plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $disciplina = $_POST['disciplina'];
    $objetivo = $_POST['objetivo'];
    $duracion = $_POST['duracion'];
    $fecha = $_POST['fecha'];
    $contenido = $_POST['contenido'];
    $archivo = '';

    if (!empty($_FILES['archivo']['name'])) {
        $nombre = basename($_FILES['archivo']['name']);
        $ruta = "planes_archivos/$cliente_id/";
        if (!file_exists($ruta)) mkdir($ruta, 0777, true);
        $archivo = $ruta . $nombre;
        move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo);
    }

    $conexion->query("INSERT INTO planes_entrenamiento (cliente_id, profesor_id, disciplina, objetivo, duracion, fecha, archivo, contenido)
    VALUES ($cliente_id, $profesor_id, '$disciplina', '$objetivo', '$duracion', '$fecha', '$archivo', '$contenido')");

    echo "<script>alert('Plan cargado correctamente'); window.location.href='plan_entrenamiento_profesor.php';</script>";
    exit;
}

// Alumnos del profesor
$alumnos = $conexion->query("
    SELECT DISTINCT c.id, c.apellido, c.nombre 
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.profesor_id = $profesor_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Plan de Entrenamiento</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
        
</head>
<body>
    <div class="contenedor">
    <h2 style="text-align:center;">📋 Crear Plan de Entrenamiento</h2>
    <form method="POST" enctype="multipart/form-data">
        <label>Alumno:</label>
        <select name="cliente_id" required>
            <option value="">Seleccionar</option>
            <?php while($a = $alumnos->fetch_assoc()): ?>
                <option value="<?= $a['id'] ?>"><?= $a['apellido'] . ' ' . $a['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Disciplina:</label>
        <input type="text" name="disciplina" required>

        <label>Objetivo:</label>
        <input type="text" name="objetivo">

        <label>Duración:</label>
        <input type="text" name="duracion">

        <label>Fecha:</label>
        <input type="date" name="fecha" required>

        <label>Descripción del Plan:</label>
        <textarea name="contenido" rows="4"></textarea>

        <label>Subir archivo (PDF/JPG):</label>
        <input type="file" name="archivo" accept=".pdf,.jpg,.png,.jpeg">

        <button type="submit">Guardar Plan</button>
    </form>
</div>
</body>
</html>
