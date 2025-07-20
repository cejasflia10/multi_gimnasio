<?php
session_start();
include 'conexion.php';

$mensaje = '';
$eventos = $conexion->query("SELECT id, titulo FROM eventos_deportivos ORDER BY fecha ASC");
$tipos = $conexion->query("SELECT * FROM tipos_entrada_evento");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evento_id = intval($_POST['evento_id']);
    $tipo_id = intval($_POST['tipo_id']);
    $inicio = intval($_POST['rango_inicio']);
    $fin = intval($_POST['rango_fin']);

    if ($evento_id && $tipo_id && $inicio > 0 && $fin >= $inicio) {
        for ($i = $inicio; $i <= $fin; $i++) {
            $conexion->query("INSERT INTO butacas_evento (evento_id, tipo_id, numero) VALUES ($evento_id, $tipo_id, $i)");
        }
        $mensaje = "✅ Butacas asignadas correctamente.";
    } else {
        $mensaje = "❌ Datos inválidos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Butacas</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body style="background:black; color:gold;">
<div class="contenedor">
    <h2>🎯 Asignar Butacas por Tipo</h2>
    <?php if ($mensaje) echo "<p style='color: gold;'>$mensaje</p>"; ?>
    <form method="POST">
        <label>Evento:</label>
        <select name="evento_id" required>
            <option value="">-- Seleccionar Evento --</option>
            <?php while ($e = $eventos->fetch_assoc()): ?>
                <option value="<?= $e['id'] ?>"><?= $e['titulo'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Tipo de Entrada:</label>
        <select name="tipo_id" required>
            <option value="">-- Seleccionar Tipo --</option>
            <?php while ($t = $tipos->fetch_assoc()): ?>
                <option value="<?= $t['id'] ?>"><?= $t['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Butaca desde:</label>
        <input type="number" name="rango_inicio" required>

        <label>Hasta:</label>
        <input type="number" name="rango_fin" required>

        <button type="submit">Asignar Rango</button>
        <a href="menu_eventos.php" class="boton-volver">⬅ Volver</a>
    </form>
</div>
</body>
</html>
