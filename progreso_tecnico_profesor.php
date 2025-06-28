<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? null;
if (!$profesor_id) die("Acceso denegado.");

$disciplina = '';
if (isset($_POST['cliente_id'])) {
    $cid = intval($_POST['cliente_id']);
    $disciplina_q = $conexion->query("SELECT d.nombre FROM clientes c JOIN disciplinas d ON c.disciplina_id = d.id WHERE c.id = $cid");
    $disciplina = $disciplina_q->fetch_assoc()['nombre'] ?? '';
}

// Guardar progreso
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = $_POST['cliente_id'];
    $fecha = $_POST['fecha'];
    $tecnica = $_POST['tecnica'];
    $fuerza = $_POST['fuerza'];
    $resistencia = $_POST['resistencia'];
    $coordinacion = $_POST['coordinacion'];
    $velocidad = $_POST['velocidad'];
    $obs = $_POST['observaciones'];

    $conexion->query("INSERT INTO progreso_tecnico 
    (cliente_id, profesor_id, fecha, tecnica, fuerza, resistencia, coordinacion, velocidad, observaciones)
    VALUES ($cid, $profesor_id, '$fecha', $tecnica, $fuerza, $resistencia, $coordinacion, $velocidad, '$obs')");

    echo "<script>alert('Progreso técnico guardado'); window.location.href='progreso_tecnico_profesor.php';</script>";
    exit;
}

$alumnos = $conexion->query("
    SELECT DISTINCT c.id, c.apellido, c.nombre, d.nombre AS disciplina
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    LEFT JOIN disciplinas d ON c.disciplina_id = d.id
    WHERE t.profesor_id = $profesor_id
");

?>
<?php if (!empty($disciplina)): ?>
    <label>Disciplina:</label>
    <input type="text" value="<?= htmlspecialchars($disciplina) ?>" disabled style="background:#000; color:gold;">
<?php endif; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Progreso Técnico</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #111; color: gold; font-family: Arial; padding: 20px; }
        form {
            background: #222; padding: 20px; max-width: 700px;
            margin: auto; border-radius: 10px;
        }
        label { display: block; margin-top: 10px; }
        input, textarea, select {
            width: 100%; background: #000; color: gold;
            padding: 8px; border: 1px solid gold; border-radius: 5px;
        }
        button {
            margin-top: 20px; width: 100%; padding: 10px;
            background: gold; color: black; font-weight: bold;
            border: none; border-radius: 5px; cursor: pointer;
        }
    </style>
</head>
<body>
    <h2 style="text-align:center;">📋 Evaluar Progreso Técnico</h2>
    <form method="POST">
        <label>Alumno:</label>
        <select name="cliente_id" required>
            <option value="">Seleccionar</option>
            <?php while($a = $alumnos->fetch_assoc()): ?>
                <option value="<?= $a['id'] ?>"><?= $a['apellido'] . ' ' . $a['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Fecha:</label>
        <input type="date" name="fecha" required>

        <label>Técnica (1-10):</label>
        <input type="number" name="tecnica" min="1" max="10" required>

        <label>Fuerza (1-10):</label>
        <input type="number" name="fuerza" min="1" max="10" required>

        <label>Resistencia (1-10):</label>
        <input type="number" name="resistencia" min="1" max="10" required>

        <label>Coordinación (1-10):</label>
        <input type="number" name="coordinacion" min="1" max="10" required>

        <label>Velocidad (1-10):</label>
        <input type="number" name="velocidad" min="1" max="10" required>

        <label>Observaciones:</label>
        <textarea name="observaciones" rows="3"></textarea>

        <button type="submit">Guardar Evaluación</button>
    </form>
</body>
</html>
