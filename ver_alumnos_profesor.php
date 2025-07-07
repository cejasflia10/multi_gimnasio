<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($profesor_id == 0 || $gimnasio_id == 0) {
    echo "Acceso denegado.";
    exit;
}

// Procesar selección
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $seleccionados = $_POST['alumnos'] ?? [];

    // Eliminar anteriores asignaciones del profesor
    $conexion->query("DELETE FROM alumnos_asignados_profesor WHERE profesor_id = $profesor_id AND gimnasio_id = $gimnasio_id");

    // Insertar nuevos
    foreach ($seleccionados as $cliente_id) {
        $cliente_id = intval($cliente_id);
        $conexion->query("INSERT INTO alumnos_asignados_profesor (profesor_id, cliente_id, gimnasio_id) 
                          VALUES ($profesor_id, $cliente_id, $gimnasio_id)");
    }

    echo "<div style='background:black; color:lime; padding:10px;'>✅ Asignaciones actualizadas correctamente.</div>";
}

// Obtener todos los alumnos del gimnasio
$clientes = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = $gimnasio_id ORDER BY apellido, nombre");

// Obtener los ya asignados
$asignados_q = $conexion->query("SELECT cliente_id FROM alumnos_asignados_profesor WHERE profesor_id = $profesor_id AND gimnasio_id = $gimnasio_id");
$asignados = [];
while ($fila = $asignados_q->fetch_assoc()) {
    $asignados[] = $fila['cliente_id'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Alumnos</title>
    <style>
        body { background: black; color: gold; font-family: Arial; padding: 20px; }
        h2 { color: orange; }
        .alumno { margin: 5px 0; }
        input[type="submit"] {
            padding: 10px 20px;
            background: gold;
            color: black;
            font-weight: bold;
            border: none;
            cursor: pointer;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<h2>👥 Asignar alumnos al profesor</h2>

<form method="POST">
    <?php while ($c = $clientes->fetch_assoc()): ?>
        <div class="alumno">
            <label>
                <input type="checkbox" name="alumnos[]" value="<?= $c['id'] ?>"
                    <?= in_array($c['id'], $asignados) ? 'checked' : '' ?>>
                <?= $c['apellido'] . ', ' . $c['nombre'] ?>
            </label>
        </div>
    <?php endwhile; ?>

    <input type="submit" value="💾 Guardar asignaciones">
</form>

</body>
</html>
