<?php
require_once "conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido'] ?? '');
    $nombre   = trim($_POST['nombre'] ?? '');
    $edad     = (int)($_POST['edad'] ?? 0);
    $peso     = (float)($_POST['peso'] ?? 0);
    $escuela  = trim($_POST['escuela'] ?? '');

    if ($apellido !== '' && $nombre !== '' && $edad > 0 && $peso > 0 && $escuela !== '') {
        $sql = "INSERT INTO competidores (apellido, nombre, edad, peso, escuela)
                VALUES (?, ?, ?, ?, ?)";
        $st = $conexion->prepare($sql);
        if ($st) {
            $st->bind_param("ssids", $apellido, $nombre, $edad, $peso, $escuela);
            if ($st->execute()) {
                echo "✅ Competidor creado con ID: " . $conexion->insert_id;
            } else {
                echo "❌ Error al guardar: " . $st->error;
            }
            $st->close();
        } else {
            echo "❌ Error preparando SQL: " . $conexion->error;
        }
    } else {
        echo "⚠️ Todos los campos son obligatorios.";
    }
}
?>

<h2>➕ Crear competidor mínimo</h2>
<form method="post">
    <label>Apellido:</label><br>
    <input type="text" name="apellido" required><br><br>

    <label>Nombre:</label><br>
    <input type="text" name="nombre" required><br><br>

    <label>Edad:</label><br>
    <input type="number" name="edad" required><br><br>

    <label>Peso (kg):</label><br>
    <input type="number" step="0.1" name="peso" required><br><br>

    <label>Escuela:</label><br>
    <input type="text" name="escuela" required><br><br>

    <button type="submit">✅ Crear competidor</button>
</form>
<p><a href="ver_peleas_evento.php">⬅️ Volver a peleas</a></p>
