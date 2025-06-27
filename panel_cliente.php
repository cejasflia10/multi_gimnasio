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

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
$datos = $conexion->query("SELECT * FROM datos_personales_cliente WHERE cliente_id = $cliente_id")->fetch_assoc();
$graduaciones = $conexion->query("SELECT * FROM graduaciones_cliente WHERE cliente_id = $cliente_id ORDER BY fecha_examen DESC");
$competencias = $conexion->query("SELECT * FROM competencias_cliente WHERE cliente_id = $cliente_id ORDER BY fecha DESC");
$asistencias = $conexion->query("SELECT fecha, hora FROM asistencias WHERE cliente_id = $cliente_id ORDER BY fecha DESC, hora DESC LIMIT 10");
$seguimientos = $conexion->query("SELECT * FROM seguimiento_nutricional WHERE cliente_id = $cliente_id ORDER BY fecha DESC");
$ficha_medica = $conexion->query("SELECT * FROM ficha_medica WHERE cliente_id = $cliente_id LIMIT 1")->fetch_assoc();
$reservas = $conexion->query("SELECT * FROM reservas WHERE cliente_id = $cliente_id AND fecha >= CURDATE() ORDER BY fecha ASC, hora ASC");

// Datos para gráfica
$peso_evol = $conexion->query("SELECT fecha, peso FROM seguimiento_nutricional WHERE cliente_id = $cliente_id ORDER BY fecha ASC");
$fechas = [];
$peso = [];
while ($row = $peso_evol->fetch_assoc()) {
    $fechas[] = $row['fecha'];
    $peso[] = $row['peso'];
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
    body { background: #111; color: gold; font-family: Arial; padding: 20px; margin: 0; }
    .container { max-width: 900px; margin: auto; background: #222; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px gold; }
    h2, h3 { text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { border: 1px solid gold; padding: 8px; text-align: center; }
    input, select, textarea { width: 100%; padding: 8px; background: #333; color: gold; border: 1px solid gold; margin: 6px 0; border-radius: 5px; }
    .section { margin-top: 30px; }
    .btn { background: gold; color: #000; font-weight: bold; padding: 10px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 0; }
    img { width: 180px; border: 3px solid gold; border-radius: 10px; display: block; margin: auto; }
  </style>
</head>
<body>
<div class="container">
  <h2>Bienvenido <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

  <img src="<?= $cliente['foto'] ?: 'fotos/default.jpg' ?>" alt="Foto">
  <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
  <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
  <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
  <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
  <p><strong>Obra Social:</strong> <?= $cliente['obra_social'] ?? 'No especificada' ?></p>

  <div class="section">
    <h3>📋 Ficha Física</h3>
    <p><strong>Altura:</strong> <?= $datos['altura_cm'] ?? '—' ?> cm | <strong>Peso:</strong> <?= $datos['peso_kg'] ?? '—' ?> kg | <strong>Peso Ideal:</strong> <?= $datos['peso_ideal'] ?? '—' ?> kg</p>
    <p><strong>Nivel:</strong> <?= $datos['nivel_entrenamiento'] ?? '—' ?></p>
    <p><strong>Objetivo:</strong> <?= $datos['objetivo'] ?? '—' ?></p>
    <canvas id="graficoPeso" height="100"></canvas>
  </div>

  <div class="section">
    <h3>🥋 Ficha de Graduaciones</h3>
    <table>
      <tr><th>Disciplina</th><th>Grado</th><th>Fecha</th></tr>
      <?php while ($g = $graduaciones->fetch_assoc()): ?>
      <tr><td><?= $g['disciplina'] ?></td><td><?= $g['grado'] ?></td><td><?= $g['fecha_examen'] ?></td></tr>
      <?php endwhile; ?>
    </table>
  </div>

  <div class="section">
    <h3>🥊 Ficha de Competencias</h3>
    <table>
      <tr><th>Torneo</th><th>Fecha</th><th>Categoría</th><th>Resultado</th><th>Ciudad</th></tr>
      <?php while ($c = $competencias->fetch_assoc()): ?>
      <tr><td><?= $c['torneo'] ?></td><td><?= $c['fecha'] ?></td><td><?= $c['categoria'] ?></td><td><?= $c['resultado'] ?></td><td><?= $c['ciudad'] ?></td></tr>
      <?php endwhile; ?>
    </table>
  </div>

  <div class="section">
    <h3>🥗 Seguimiento Nutricional</h3>
    <table>
      <tr><th>Fecha</th><th>Peso</th><th>Recomendaciones</th><th>Observaciones</th></tr>
      <?php mysqli_data_seek($seguimientos, 0); while ($s = $seguimientos->fetch_assoc()): ?>
      <tr><td><?= $s['fecha'] ?></td><td><?= $s['peso'] ?> kg</td><td><?= $s['recomendaciones'] ?></td><td><?= $s['observaciones'] ?></td></tr>
      <?php endwhile; ?>
    </table>
  </div>

  <?php if ($ficha_medica): ?>
  <div class="section">
    <h3>🩺 Ficha Médica</h3>
    <p><strong>Alergias:</strong> <?= $ficha_medica['alergias'] ?></p>
    <p><strong>Medicaciones:</strong> <?= $ficha_medica['medicacion'] ?></p>
    <p><strong>Lesiones:</strong> <?= $ficha_medica['lesiones'] ?></p>
    <p><strong>Antecedentes:</strong> <?= $ficha_medica['antecedentes'] ?></p>
  </div>
  <?php endif; ?>

  <div class="section">
    <h3>📅 Turnos Reservados</h3>
    <a class="btn" href="cliente_reservas.php">Reservar nuevo turno</a>
    <table>
      <tr><th>Fecha</th><th>Hora</th><th>Profesor</th></tr>
      <?php while ($r = $reservas->fetch_assoc()): ?>
      <tr><td><?= $r['fecha'] ?></td><td><?= $r['hora'] ?></td><td><?= $r['profesor'] ?></td></tr>
      <?php endwhile; ?>
    </table>
  </div>

  <div class="section">
    <h3>📷 Código QR</h3>
    <?php $qr_path = "qr/" . $cliente['dni'] . ".png"; ?>
    <?php if (file_exists($qr_path)): ?>
      <img src="<?= $qr_path ?>" alt="QR">
    <?php else: ?>
      <a class="btn" href="generar_qr_individual.php?id=<?= $cliente_id ?>">Generar QR</a>
    <?php endif; ?>
  </div>

  <div class="section">
    <h3>🕘 Últimas Asistencias</h3>
    <table><tr><th>Fecha</th><th>Hora</th></tr>
    <?php while ($a = $asistencias->fetch_assoc()): ?>
      <tr><td><?= $a['fecha'] ?></td><td><?= $a['hora'] ?></td></tr>
    <?php endwhile; ?>
    </table>
  </div>
</div>

<script>
const ctx = document.getElementById('graficoPeso')?.getContext('2d');
if (ctx) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($fechas) ?>,
      datasets: [{
        label: 'Peso (kg)',
        data: <?= json_encode($peso) ?>,
        borderColor: 'gold',
        backgroundColor: 'rgba(255, 215, 0, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.3,
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
}
</script>
</body>
</html>
