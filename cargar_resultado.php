<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$evento_id = $_SESSION['evento_id'] ?? 0;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $combate_id = intval($_POST['combate_id']);
    $resultado = trim($_POST['resultado']);
    $metodo = trim($_POST['metodo']);

    $conexion->query("UPDATE peleas_evento SET resultado = '$resultado', metodo = '$metodo' WHERE id = $combate_id AND evento_id = $evento_id");

    echo "<script>alert('✅ Resultado guardado correctamente.'); window.location.href='cargar_resultado.php';</script>";
    exit;
}

// Obtener lista de combates
$combates = $conexion->query("
    SELECT c.id, 
           r.apellido AS rojo_apellido, r.nombre AS rojo_nombre,
           a.apellido AS azul_apellido, a.nombre AS azul_nombre
    FROM peleas_evento c
    LEFT JOIN competidores_evento r ON c.competidor_rojo_id = r.id
    LEFT JOIN competidores_evento a ON c.competidor_azul_id = a.id
    WHERE c.evento_id = $evento_id
    ORDER BY c.id DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar Resultado de Combate</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>📥 Cargar Resultado de Combate</h2>

    <form method="POST">
        <label>Seleccionar Combate:</label><br>
        <select name="combate_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($c = $combates->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>">
                    Rojo: <?= $c['rojo_apellido'] . ' ' . $c['rojo_nombre'] ?> vs Azul: <?= $c['azul_apellido'] . ' ' . $c['azul_nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select><br><br>

        <label>Ganador (nombre completo o 'Empate'):</label><br>
        <input type="text" name="resultado" required><br><br>

        <label>Método de Victoria (KO, Decisión, etc.):</label><br>
        <input type="text" name="metodo"><br><br>

        <button type="submit">Guardar Resultado</button>
    </form>
</div>
</body>
</html>
