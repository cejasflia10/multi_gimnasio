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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- PWA: Progressive Web App -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#000000">
<link rel="icon" href="icono192.png" type="image/png">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js');
  }
</script>

</head>
<body>
<div class="container">
  <h2>Bienvenido <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

<?php
$foto_path = (!empty($cliente['foto']) && file_exists($cliente['foto'])) ? $cliente['foto'] : 'fotos/default.jpg';
?>
<div class="foto" style="text-align:center; margin: 20px 0;">
    <img src="<?= $foto_path ?>" alt="Foto de perfil">
    
    <?php if ($rol === 'cliente'): ?>
    <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
        <label style="display:block; color: gold; font-weight: bold;">📷 Subir nueva foto:</label>
        <input type="file" name="foto" accept="image/*" style="color: gold;" required>
        <button class="btn" type="submit" style="margin-top: 10px;">Actualizar Foto</button>
    </form>
    <?php endif; ?>
</div>
  <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
  <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
  <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
  <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
  <p><strong>Obra Social:</strong> <?= $cliente['obra_social'] ?? 'No especificada' ?></p>

<?php
// Obtener controles físicos del cliente
$controles = $conexion->query("
    SELECT * FROM controles_fisicos 
    WHERE cliente_id = $cliente_id 
    ORDER BY fecha DESC
");
?>
<?php
$planes = $conexion->query("
    SELECT * FROM planes_entrenamiento 
    WHERE cliente_id = $cliente_id 
    ORDER BY fecha DESC
");
?>
<?php
$progresos = $conexion->query("
    SELECT * FROM progreso_tecnico 
    WHERE cliente_id = $cliente_id 
    ORDER BY fecha DESC
");
?>
<div class="card">
    <h3>📘 Plan de Entrenamiento</h3>
    <?php
    $planes = $conexion->query("
        SELECT * FROM planes_entrenamiento 
        WHERE cliente_id = $cliente_id 
        ORDER BY fecha DESC
        LIMIT 1
    ");
    ?>

    <?php if ($planes->num_rows > 0): ?>
        <?php $plan = $planes->fetch_assoc(); ?>
        <p><strong>Fecha:</strong> <?= $plan['fecha'] ?></p>
        <p><strong>Disciplina:</strong> <?= $plan['disciplina'] ?></p>
        <p><strong>Contenido:</strong><br><?= nl2br($plan['contenido']) ?></p>

        <?php if (!empty($plan['archivo']) && file_exists($plan['archivo'])): ?>
            <p><strong>Archivo:</strong> 
                <a href="<?= $plan['archivo'] ?>" target="_blank" style="color: lightblue;">📎 Ver archivo</a>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p>No hay planes cargados aún.</p>
    <?php endif; ?>
</div>
<div class="card">
    <h3>🧍 Evaluaciones Físicas</h3>
    <?php
    $evaluaciones = $conexion->query("
        SELECT * FROM evaluaciones_fisicas 
        WHERE cliente_id = $cliente_id 
        ORDER BY fecha DESC
    ");
    ?>

    <?php if ($evaluaciones->num_rows > 0): ?>
        <?php $ultima = $evaluaciones->fetch_assoc(); ?>
        <p><strong>📅 Fecha:</strong> <?= $ultima['fecha'] ?></p>
        <p><strong>Peso:</strong> <?= $ultima['peso'] ?> kg</p>
        <p><strong>Altura:</strong> <?= $ultima['altura'] ?> cm</p>
        <p><strong>Edad:</strong> <?= $ultima['edad'] ?> años</p>
        <p><strong>IMC:</strong> <?= $ultima['imc'] ?></p>
        <p><strong>Tipo de control:</strong> <?= ucfirst($ultima['tipo_control']) ?></p>
        <p><strong>Observaciones:</strong><br><?= nl2br($ultima['observaciones']) ?></p>

        <hr style="border-top: 1px solid gold; margin: 20px 0;">

        <h4>📋 Historial de evaluaciones:</h4>
        <table style="width:100%; border-collapse: collapse;">
            <tr style="background:#222;">
                <th style="color:gold;">Fecha</th>
                <th style="color:gold;">Peso</th>
                <th style="color:gold;">Altura</th>
                <th style="color:gold;">IMC</th>
                <th style="color:gold;">Tipo</th>
            </tr>
            <tr>
                <td><?= $ultima['fecha'] ?></td>
                <td><?= $ultima['peso'] ?> kg</td>
                <td><?= $ultima['altura'] ?> cm</td>
                <td><?= $ultima['imc'] ?></td>
                <td><?= $ultima['tipo_control'] ?></td>
            </tr>
            <?php while ($e = $evaluaciones->fetch_assoc()): ?>
                <tr>
                    <td><?= $e['fecha'] ?></td>
                    <td><?= $e['peso'] ?> kg</td>
                    <td><?= $e['altura'] ?> cm</td>
                    <td><?= $e['imc'] ?></td>
                    <td><?= $e['tipo_control'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No hay evaluaciones físicas registradas aún.</p>
    <?php endif; ?>
</div>
<div class="card">
    <h3>📷 Fotos de Evolución Física</h3>
    <?php
    $fotos = $conexion->query("
        SELECT * FROM fotos_evolucion 
        WHERE cliente_id = $cliente_id 
        ORDER BY fecha DESC
    ");
    ?>

    <?php if ($fotos->num_rows > 0): ?>
        <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; margin-top: 15px;">
            <?php while ($f = $fotos->fetch_assoc()): ?>
                <div style="background: #222; padding: 10px; border-radius: 10px; text-align: center; max-width: 180px;">
                    <img src="<?= $f['archivo'] ?>" style="max-width: 100%; border-radius: 8px;">
                    <p style="margin: 5px 0;"><strong><?= $f['etapa'] ?></strong></p>
                    <small><?= $f['fecha'] ?></small>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No hay fotos de evolución registradas aún.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>📈 Progreso Técnico</h3>
    <?php if ($progresos->num_rows > 0): ?>
        <table style="width:100%; border-collapse: collapse;">
            <tr>
                <th style="color:gold;">Fecha</th>
                <th style="color:gold;">Técnica</th>
                <th style="color:gold;">Fuerza</th>
                <th style="color:gold;">Resistencia</th>
                <th style="color:gold;">Coordinación</th>
                <th style="color:gold;">Velocidad</th>
            </tr>
            <?php while ($p = $progresos->fetch_assoc()): ?>
                <tr>
                    <td><?= $p['fecha'] ?></td>
                    <td><?= $p['tecnica'] ?></td>
                    <td><?= $p['fuerza'] ?></td>
                    <td><?= $p['resistencia'] ?></td>
                    <td><?= $p['coordinacion'] ?></td>
                    <td><?= $p['velocidad'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No hay evaluaciones cargadas aún.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>📘 Planes de Entrenamiento</h3>
    <?php if ($planes->num_rows > 0): ?>
        <?php while($p = $planes->fetch_assoc()): ?>
            <div style="margin-bottom:15px; border-bottom:1px solid gold; padding-bottom:10px;">
                <p><strong>Disciplina:</strong> <?= $p['disciplina'] ?></p>
                <p><strong>Objetivo:</strong> <?= $p['objetivo'] ?></p>
                <p><strong>Duración:</strong> <?= $p['duracion'] ?></p>
                <p><strong>Fecha:</strong> <?= $p['fecha'] ?></p>
                <?php if (!empty($p['contenido'])): ?>
                    <p><?= nl2br($p['contenido']) ?></p>
                <?php endif; ?>
                <?php if (!empty($p['archivo']) && file_exists($p['archivo'])): ?>
                    <a href="<?= $p['archivo'] ?>" target="_blank" style="color:lightblue;">📎 Ver archivo</a>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No hay planes de entrenamiento cargados aún.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>📊 Ficha Física</h3>
    <?php if ($controles->num_rows > 0): ?>
        <?php $primero = $controles->fetch_assoc(); ?>
        <p><strong>Altura:</strong> <?= $primero['altura'] ?> cm</p>
        <p><strong>Peso actual:</strong> <?= $primero['peso'] ?> kg</p>
        <p><strong>Edad:</strong> <?= $primero['edad'] ?> años</p>
        <p><strong>IMC:</strong> <?= $primero['imc'] ?></p>
        <p><strong>Nivel:</strong> <?= $primero['nivel'] ?></p>
        <p><strong>Objetivo:</strong> <?= $primero['objetivo'] ?></p>
        <p><strong>Observaciones:</strong> <?= $primero['observaciones'] ?></p>

        <h4>📅 Evolución (últimos controles):</h4>
        <ul>
            <li><strong><?= $primero['fecha'] ?>:</strong> <?= $primero['peso'] ?> kg</li>
            <?php while ($c = $controles->fetch_assoc()): ?>
                <li><strong><?= $c['fecha'] ?>:</strong> <?= $c['peso'] ?> kg</li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No se ha registrado información física aún.</p>
    <?php endif; ?>
</div>
<?php
// Volvemos a obtener los datos de evolución en orden ASC
$peso_q = $conexion->query("
    SELECT fecha, peso FROM controles_fisicos
    WHERE cliente_id = $cliente_id
    ORDER BY fecha ASC
");

$fechas = [];
$pesos = [];
while ($row = $peso_q->fetch_assoc()) {
    $fechas[] = $row['fecha'];
    $pesos[] = $row['peso'];
}
?>
<div class="card">
    <h4>📈 Evolución de Peso</h4>
    <canvas id="graficoPeso" width="100%" height="60"></canvas>
</div>

<script>
    const ctx = document.getElementById('graficoPeso').getContext('2d');
    const graficoPeso = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($fechas) ?>,
            datasets: [{
                label: 'Peso (kg)',
                data: <?= json_encode($pesos) ?>,
                borderColor: 'gold',
                backgroundColor: 'rgba(255, 215, 0, 0.2)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        color: 'gold'
                    }
                },
                x: {
                    ticks: {
                        color: 'gold'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: 'gold'
                    }
                }
            }
        }
    });
</script>

  <div class="card">
    <h3>🥋 Graduación Técnica</h3>
    <?php
    $grad = $conexion->query("
        SELECT * FROM graduaciones 
        WHERE cliente_id = $cliente_id 
        ORDER BY fecha DESC LIMIT 1
    ");
    ?>

    <?php if ($grad->num_rows > 0): ?>
        <?php $g = $grad->fetch_assoc(); ?>
        <p><strong>Disciplina:</strong> <?= $g['disciplina'] ?></p>
        <p><strong>Nivel:</strong> <?= $g['nivel'] ?></p>
        <p><strong>Fecha:</strong> <?= $g['fecha'] ?></p>
        <p><strong>Observaciones:</strong><br><?= nl2br($g['observaciones']) ?></p>
    <?php else: ?>
        <p>No se ha registrado ninguna graduación aún.</p>
    <?php endif; ?>
</div>

  <div class="card">
    <h3>🏆 Competencias</h3>
    <?php
    $comp = $conexion->query("
        SELECT * FROM competencias 
        WHERE cliente_id = $cliente_id 
        ORDER BY fecha DESC
    ");
    ?>
<div class="card">
  <h3>📅 Reservar Turno</h3>
  <p>Accedé a tus turnos disponibles y reservá tu lugar semanal.</p>
  <a href="cliente_turnos.php" style="background:gold;color:black;padding:10px 15px;border-radius:5px;font-weight:bold;text-decoration:none;">Ver Turnos</a>
</div>

    <?php if ($comp->num_rows > 0): ?>
        <ul style="list-style: none; padding: 0;">
            <?php while ($c = $comp->fetch_assoc()): ?>
                <li style="margin-bottom: 15px; border-bottom: 1px solid gold; padding-bottom: 10px;">
                    <strong><?= $c['fecha'] ?>:</strong> <?= $c['nombre_competencia'] ?> <br>
                    <em>Lugar:</em> <?= $c['lugar'] ?> <br>
                    <em>Resultado:</em> <?= $c['resultado'] ?> <br>
                    <em>Observaciones:</em> <?= nl2br($c['observaciones']) ?>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No hay competencias registradas aún.</p>
    <?php endif; ?>
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
