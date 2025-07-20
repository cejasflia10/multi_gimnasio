<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$mensaje = "";

// Guardar nuevo plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $duracion = intval($_POST['duracion'] ?? 1);
    $max_clientes = intval($_POST['max_clientes'] ?? 0);

    // Accesos según menú horizontal
    $clientes = isset($_POST['clientes']) ? 1 : 0;
    $asistencias = isset($_POST['asistencias']) ? 1 : 0;
    $competencias = isset($_POST['competencias']) ? 1 : 0;
    $profesores = isset($_POST['profesores']) ? 1 : 0;
    $ventas = isset($_POST['ventas']) ? 1 : 0;
    $panel = isset($_POST['panel']) ? 1 : 0;
    $configuraciones = isset($_POST['configuraciones']) ? 1 : 0;

    $existe = $conexion->query("SELECT id FROM planes_acceso WHERE nombre = '$nombre' AND gimnasio_id = $gimnasio_id")->num_rows;
    if ($existe > 0) {
        $mensaje = "<p style='color:red;'>❌ Ya existe un plan con ese nombre.</p>";
    } else {
        $stmt = $conexion->prepare("INSERT INTO planes_acceso 
            (gimnasio_id, nombre, precio, duracion_meses, clientes, asistencias, competencias, profesores, ventas, panel, configuraciones, max_clientes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("isdiiiiiiiii", $gimnasio_id, $nombre, $precio, $duracion, $clientes, $asistencias, $competencias, $profesores, $ventas, $panel, $configuraciones, $max_clientes);
        $stmt->execute();
        $mensaje = "<p style='color:lime;'>✅ Plan guardado correctamente.</p>";
    }
}

// Obtener planes existentes
$planes = $conexion->query("SELECT * FROM planes_acceso WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes de Acceso</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        .formulario { background: #222; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="number"] {
            width: 100%; padding: 8px; background: #000; color: white; border: 1px solid gold;
        }
        input[type="checkbox"] { transform: scale(1.3); margin-right: 10px; }
        button { background: gold; color: black; padding: 10px 20px; font-weight: bold; margin-top: 20px; cursor: pointer; border: none; }
        table { width: 100%; border-collapse: collapse; background: #222; color: white; margin-top: 30px; }
        th, td { border: 1px solid gold; padding: 10px; text-align: center; }
        a.editar { color: gold; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="formulario">
    <h2>⚙️ Configurar Planes de Acceso</h2>
    <?= $mensaje ?>
    <form method="post">
        <label>Nombre del plan</label>
        <input type="text" name="nombre" required>

        <label>Precio mensual</label>
        <input type="number" step="0.01" name="precio" required>

        <label>Duración del plan (meses)</label>
        <input type="number" name="duracion" min="1" value="1" required>

        <label>Máximo de clientes permitidos</label>
        <input type="number" name="max_clientes" min="0" value="0" required>

        <label>Permisos visibles:</label>
        <label><input type="checkbox" name="clientes"> Clientes</label>
        <label><input type="checkbox" name="asistencias"> Asistencias</label>
        <label><input type="checkbox" name="competencias"> Competencias</label>
        <label><input type="checkbox" name="profesores"> Profesores</label>
        <label><input type="checkbox" name="ventas"> Ventas</label>
        <label><input type="checkbox" name="panel"> Panel Cliente</label>
        <label><input type="checkbox" name="configuraciones"> Configuraciones</label>

        <button type="submit">💾 Guardar Plan</button>
    </form>
</div>

<?php if ($planes && $planes->num_rows > 0): ?>
    <h3 style="text-align:center;">📋 Planes existentes</h3>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Precio</th>
            <th>Duración</th>
            <th>Máx. Clientes</th>
            <th>Clientes</th>
            <th>Asistencias</th>
            <th>Competencias</th>
            <th>Profesores</th>
            <th>Ventas</th>
            <th>Panel</th>
            <th>Configuraciones</th>
            <th>Editar</th>
        </tr>
        <?php while ($plan = $planes->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($plan['nombre']) ?></td>
            <td>$<?= number_format($plan['precio'], 2, ',', '.') ?></td>
            <td><?= $plan['duracion_meses'] ?> mes(es)</td>
            <td><?= $plan['max_clientes'] ?></td>
            <td><?= $plan['clientes'] ? '✔️' : '❌' ?></td>
            <td><?= $plan['asistencias'] ? '✔️' : '❌' ?></td>
            <td><?= $plan['competencias'] ? '✔️' : '❌' ?></td>
            <td><?= $plan['profesores'] ? '✔️' : '❌' ?></td>
            <td><?= $plan['ventas'] ? '✔️' : '❌' ?></td>
            <td><?= $plan['panel'] ? '✔️' : '❌' ?></td>
            <td><?= $plan['configuraciones'] ? '✔️' : '❌' ?></td>
            <td><a class="editar" href="editar_plan_usuario.php?id=<?= $plan['id'] ?>">✏️ Editar</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
<?php endif; ?>

</body>
</html>
