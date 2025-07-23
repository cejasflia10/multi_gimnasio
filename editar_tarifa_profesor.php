<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$mensaje = '';

// Guardar tarifa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profesor_id = intval($_POST['profesor_id']);
    $valor_hora = floatval($_POST['valor_hora']);
    $modo_pago = $_POST['modo_pago'] ?? 'fijo';
    $porcentaje1 = floatval($_POST['porcentaje_1'] ?? 0);
    $porcentaje2 = floatval($_POST['porcentaje_2'] ?? 0);
    $porcentaje3 = floatval($_POST['porcentaje_3'] ?? 100);

    $check = $conexion->query("SELECT id FROM tarifas_profesor WHERE profesor_id = $profesor_id");
    if ($check->num_rows > 0) {
        $conexion->query("UPDATE tarifas_profesor SET valor_hora = $valor_hora, modo_pago = '$modo_pago', porcentaje_1 = $porcentaje1, porcentaje_2 = $porcentaje2, porcentaje_3 = $porcentaje3 WHERE profesor_id = $profesor_id");
    } else {
        $conexion->query("INSERT INTO tarifas_profesor (profesor_id, valor_hora, modo_pago, porcentaje_1, porcentaje_2, porcentaje_3) VALUES ($profesor_id, $valor_hora, '$modo_pago', $porcentaje1, $porcentaje2, $porcentaje3)");
    }

    $mensaje = "✅ Tarifa y porcentajes actualizados correctamente.";
}

// Obtener profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Tarifas Profesores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background-color: #111; color: gold; font-family: Arial; }
        .contenedor {
            max-width: 700px;
            margin: auto;
            padding: 20px;
            background: #222;
            border-radius: 10px;
        }
        label { display: block; margin-top: 10px; }
        select, input[type="number"], input[type="submit"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            background: #000;
            color: gold;
            border: 1px solid #555;
            border-radius: 5px;
        }
        table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 8px;
            border: 1px solid #444;
            text-align: center;
        }
        .mensaje {
            margin-top: 15px;
            padding: 10px;
            background-color: #003300;
            color: #0f0;
            border-radius: 5px;
        }
    </style>
    <script>
        function mostrarTabla() {
            const modo = document.getElementById('modo_pago').value;
            document.getElementById('tabla_porcentajes').style.display = (modo === 'asistencia') ? 'block' : 'none';
        }
    </script>
</head>
<body>

<div class="contenedor">
    <h2>💰 Asignar Tarifa por Hora a Profesores</h2>

    <form method="POST">
        <label>Profesor:</label>
        <select name="profesor_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($p = $profesores->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>">
                    <?= $p['apellido'] . ' ' . $p['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Valor por hora ($):</label>
        <input type="number" name="valor_hora" step="0.01" min="0" required>

        <label>Modo de pago:</label>
        <select name="modo_pago" id="modo_pago" onchange="mostrarTabla()">
            <option value="fijo">💵 Fijo (por hora)</option>
            <option value="asistencia">📊 Según alumnos por turno</option>
        </select>

        <div id="tabla_porcentajes" style="display: none;">
            <h3>📐 Porcentajes por cantidad de alumnos</h3>
            <table>
                <tr>
                    <th>1 Alumno</th>
                    <th>2 Alumnos</th>
                    <th>3 o más</th>
                </tr>
                <tr>
                    <td><input type="number" name="porcentaje_1" min="0" max="100" step="1" value="50" required> %</td>
                    <td><input type="number" name="porcentaje_2" min="0" max="100" step="1" value="75" required> %</td>
                    <td><input type="number" name="porcentaje_3" min="0" max="100" step="1" value="100" required> %</td>
                </tr>
            </table>
        </div>

        <input type="submit" value="Guardar Tarifa">
    </form>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>
</div>

<script>
    // Ejecutar al cargar
    mostrarTabla();
</script>

</body>
</html>
