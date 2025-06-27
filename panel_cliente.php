<?php
session_start();
include 'conexion.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['cliente','admin', 'profesor'])) {
    die("Acceso denegado.");
}

$cliente_id = $_SESSION['cliente_id'] ?? null;
if ($rol === 'profesor') {
    $cliente_id = $_GET['id'] ?? null;
}
if (!$cliente_id) {
    die("ID de cliente no especificado.");
}

$query = "SELECT * FROM clientes WHERE id = $cliente_id";
$resultado = $conexion->query($query);
if ($resultado->num_rows === 0) {
    die("Cliente no encontrado.");
}
$cliente = $resultado->fetch_assoc();

// Guardar foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $foto = $_FILES['foto'];
    $nombre_archivo = "fotos/cliente_" . $cliente['id'] . ".jpg";
    if (move_uploaded_file($foto['tmp_name'], $nombre_archivo)) {
        $conexion->query("UPDATE clientes SET foto = '$nombre_archivo' WHERE id = " . $cliente['id']);
        $cliente['foto'] = $nombre_archivo;
    }
}

// Guardar ficha física
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['altura'])) {
    $altura = $_POST['altura'];
    $peso = $_POST['peso'];
    $peso_ideal = $_POST['peso_ideal'];
    $nivel = $_POST['nivel'];
    $objetivo = $_POST['objetivo'];
    $imc = ($altura > 0) ? round($peso / pow($altura / 100, 2), 2) : 0;

    $existe = $conexion->query("SELECT id FROM datos_personales_cliente WHERE cliente_id = $cliente_id")->num_rows;
    if ($existe) {
        $conexion->query("UPDATE datos_personales_cliente SET altura_cm = $altura, peso_kg = $peso, peso_ideal = $peso_ideal, nivel_entrenamiento = '$nivel', objetivo = '$objetivo' WHERE cliente_id = $cliente_id");
    } else {
        $conexion->query("INSERT INTO datos_personales_cliente (cliente_id, altura_cm, peso_kg, peso_ideal, nivel_entrenamiento, objetivo) VALUES ($cliente_id, $altura, $peso, $peso_ideal, '$nivel', '$objetivo')");
    }
}

// Consultar datos físicos, graduaciones y competencias
$datos = $conexion->query("SELECT * FROM datos_personales_cliente WHERE cliente_id = $cliente_id")->fetch_assoc();
$graduaciones = $conexion->query("SELECT * FROM graduaciones_cliente WHERE cliente_id = $cliente_id ORDER BY fecha_examen DESC");
$competencias = $conexion->query("SELECT * FROM competencias_cliente WHERE cliente_id = $cliente_id ORDER BY fecha DESC");
$asistencias = $conexion->query("SELECT fecha, hora FROM asistencias WHERE cliente_id = $cliente_id ORDER BY fecha DESC, hora DESC LIMIT 10");

$seguimientos = $conexion->query("SELECT fecha, peso FROM seguimiento_nutricional WHERE cliente_id = $cliente_id ORDER BY fecha ASC");
$fechas = [];
$peso = [];
while ($s = $seguimientos->fetch_assoc()) {
    $fechas[] = $s['fecha'];
    $peso[] = $s['peso'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #111; color: gold; font-family: Arial; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #222; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px gold; }
        h2, h3 { text-align: center; }
        img { width: 180px; border: 3px solid gold; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid gold; padding: 6px; text-align: center; }
        input, select, textarea { width: 100%; padding: 8px; background: #333; color: gold; border: 1px solid gold; margin: 6px 0; border-radius: 5px; }
        .section { margin-top: 30px; }
        .btn { background: gold; color: #111; padding: 10px; border: none; font-weight: bold; cursor: pointer; border-radius: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Bienvenido <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

    <div style="text-align:center">
        <img src="<?= $cliente['foto'] ?: 'fotos/default.jpg' ?>">
        <?php if ($rol === 'cliente'): ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="foto" accept="image/*" required>
            <button class="btn" type="submit">Actualizar Foto</button>
        </form>
        <?php endif; ?>
    </div>

    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
    <p><strong>Obra Social:</strong> <?= $cliente['obra_social'] ?? 'No especificada' ?></p>

    <div class="section">
        <h3>📋 Ficha Física</h3>
        <form method="POST">
            <label>Altura (cm):</label><input type="number" name="altura" value="<?= $datos['altura_cm'] ?? '' ?>">
            <label>Peso (kg):</label><input type="number" step="0.01" name="peso" value="<?= $datos['peso_kg'] ?? '' ?>">
            <label>Peso ideal:</label><input type="number" step="0.01" name="peso_ideal" value="<?= $datos['peso_ideal'] ?? '' ?>">
            <label>Nivel de entrenamiento:</label>
            <select name="nivel">
                <option <?= ($datos['nivel_entrenamiento'] ?? '') == 'Principiante' ? 'selected' : '' ?>>Principiante</option>
                <option <?= ($datos['nivel_entrenamiento'] ?? '') == 'Intermedio' ? 'selected' : '' ?>>Intermedio</option>
                <option <?= ($datos['nivel_entrenamiento'] ?? '') == 'Avanzado' ? 'selected' : '' ?>>Avanzado</option>
            </select>
            <label>Objetivo:</label><textarea name="objetivo"><?= $datos['objetivo'] ?? '' ?></textarea>
            <button class="btn" type="submit">Guardar datos físicos</button>
        </form>
        <canvas id="graficoPeso" height="100"></canvas>
    </div>

    <div class="section">
        <h3>🥋 Ficha de Graduaciones</h3>
        <table>
            <tr><th>Disciplina</th><th>Grado</th><th>Fecha Examen</th></tr>
            <?php while ($g = $graduaciones->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($g['disciplina']) ?></td>
                    <td><?= htmlspecialchars($g['grado']) ?></td>
                    <td><?= htmlspecialchars($g['fecha_examen']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="section">
        <h3>🥊 Ficha de Competencias</h3>
        <table>
            <tr><th>Torneo</th><th>Fecha</th><th>Categoría</th><th>Resultado</th><th>Ciudad</th></tr>
            <?php while ($c = $competencias->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($c['torneo']) ?></td>
                    <td><?= htmlspecialchars($c['fecha']) ?></td>
                    <td><?= htmlspecialchars($c['categoria']) ?></td>
                    <td><?= htmlspecialchars($c['resultado']) ?></td>
                    <td><?= htmlspecialchars($c['ciudad']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="section">
        <h3>📅 Últimas Asistencias</h3>
        <table><tr><th>Fecha</th><th>Hora</th></tr>
            <?php while ($a = $asistencias->fetch_assoc()) { echo "<tr><td>{$a['fecha']}</td><td>{$a['hora']}</td></tr>"; } ?>
        </table>
    </div>

    <div class="section">
        <h3>📷 Código QR</h3>
        <div style="text-align:center">
            <?php
            $qr_path = "qr/" . $cliente['dni'] . ".png";
            if (file_exists($qr_path)) {
                echo "<img src='$qr_path' alt='QR del cliente'>";
            } else {
                echo "<p>Tu QR aún no ha sido generado.</p>";
                echo "<a href='generar_qr_individual.php?id=" . $cliente['id'] . "' class='btn'>Generar QR</a>";
            }
            ?>
        </div>
    </div>
</div>
<script>
const ctx = document.getElementById('graficoPeso').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas) ?>,
        datasets: [{
            label: 'Peso (kg)',
            data: <?= json_encode($peso) ?>,
            borderColor: 'gold',
            backgroundColor: 'rgba(255, 215, 0, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: 'gold'
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: false,
                ticks: { color: 'gold' },
                grid: { color: '#444' }
            },
            x: {
                ticks: { color: 'gold' },
                grid: { color: '#333' }
            }
        },
        plugins: {
            legend: { labels: { color: 'gold' } }
        }
    }
});
</script>
</body>
</html>
