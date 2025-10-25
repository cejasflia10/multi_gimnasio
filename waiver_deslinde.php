<?php
session_start();
require_once __DIR__.'/conexion.php';

date_default_timezone_set('America/Argentina/San_Luis');

$gimnasio_id = isset($_GET['gimnasio']) ? (int)$_GET['gimnasio'] : (int)($_SESSION['gimnasio_id'] ?? 0);
$cliente_id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($gimnasio_id <= 0 || $cliente_id <= 0) {
  http_response_code(404);
  echo "No se pudo abrir el deslinde (faltan parámetros).";
  exit;
}

/* Gym info */
$gym = ['nombre'=>'Gimnasio','logo'=>'logo.png'];
$st = $conexion->prepare("SELECT nombre, logo FROM gimnasios WHERE id = ? LIMIT 1");
$st->bind_param("i", $gimnasio_id);
$st->execute();
$r = $st->get_result()->fetch_assoc();
$st->close();
if ($r) {
  if (!empty($r['nombre'])) $gym['nombre'] = $r['nombre'];
  if (!empty($r['logo']))   $gym['logo']   = $r['logo'];
}

/* Cliente */
$st = $conexion->prepare("SELECT dni, apellido, nombre, telefono, email, fecha_nacimiento, domicilio
                          FROM clientes WHERE gimnasio_id = ? AND id = ? LIMIT 1");
$st->bind_param("ii", $gimnasio_id, $cliente_id);
$st->execute();
$cl = $st->get_result()->fetch_assoc();
$st->close();

if (!$cl) {
  http_response_code(404);
  echo "Cliente no encontrado.";
  exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$fecha = date('Y-m-d'); $hora = date('H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Deslinde - <?= h($cl['apellido'].' '.$cl['nombre']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    @page { size: A4; margin: 24mm 18mm; }
    @media print { .no-print { display: none !important; } }
    body { font-family: Arial, Helvetica, sans-serif; color:#111; }
    .toolbar { margin: 10px 0; }
    .btn { padding: .6rem .9rem; border-radius: 10px; border:1px solid #ccc; background:#f5f5f5; cursor:pointer; }
    .head { display: flex; gap:12px; align-items: center; }
    .logo { height: 70px; object-fit: contain; border:1px solid #eee; padding:6px; border-radius:8px; }
    h1 { margin:0 0 4px; font-size: 22px; }
    .muted { color:#555; }
    .hr { height:1px; background:#ddd; margin:10px 0 14px; }
    .dl { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .dl td { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
    .sec h2 { font-size: 16px; margin: 8px 0 6px; }
    .sec p  { margin: 6px 0; line-height: 1.5; font-size: 14px; }
    .firma-grid { display: table; width:100%; margin-top: 28px; }
    .firma-col { display: table-cell; width: 50%; vertical-align: bottom; padding-right: 12px; }
    .line { margin-top: 48px; border-top:1px solid #000; padding-top:4px; }
    .small { font-size: 12px; color:#444; }
  </style>
</head>
<body>
  <div class="no-print toolbar">
    <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
  </div>

  <div class="head">
    <?php if (!empty($gym['logo'])): ?>
      <img class="logo" src="<?= h($gym['logo']) ?>" alt="logo">
    <?php endif; ?>
    <div>
      <h1>Deslinde de responsabilidad</h1>
      <div class="muted"><?= h($gym['nombre']) ?></div>
    </div>
  </div>
  <div class="hr"></div>

  <table class="dl">
    <tr><td><strong>Cliente</strong></td><td><?= h($cl['apellido'].' '.$cl['nombre']) ?></td></tr>
    <tr><td><strong>DNI</strong></td><td><?= h($cl['dni']) ?></td></tr>
    <tr><td><strong>Fecha</strong></td><td><?= h($fecha.' '.$hora) ?></td></tr>
    <tr><td><strong>Teléfono</strong></td><td><?= h($cl['telefono'] ?? '') ?></td></tr>
    <tr><td><strong>Email</strong></td><td><?= h($cl['email'] ?? '') ?></td></tr>
    <tr><td><strong>Domicilio</strong></td><td><?= h($cl['domicilio'] ?? '') ?></td></tr>
    <tr><td><strong>Fecha de nacimiento</strong></td><td><?= h($cl['fecha_nacimiento'] ?? '') ?></td></tr>
  </table>

  <div class="sec">
    <h2>Declaración y aceptación</h2>
    <p>Declaro bajo juramento que me encuentro en <strong>buen estado de salud</strong> y que, en caso de corresponder, cuento con los <strong>controles y apto médico</strong> necesarios para realizar actividad física.</p>
    <p>Reconozco y acepto que la práctica de ejercicios y actividades deportivas implica <strong>riesgos inherentes</strong> de lesiones y/o accidentes. Asumo voluntariamente dichos riesgos y libero a <strong><?= h($gym['nombre']) ?></strong>, sus dueños, directivos, empleados y contratados de cualquier <strong>responsabilidad civil y/o penal</strong> por lesiones, daños o pérdidas que puedan derivarse directa o indirectamente de mi participación.</p>
    <p>Autorizo a que, en caso de emergencia, se me brinde la <strong>asistencia médica</strong> que corresponda, asumiendo los costos que pudieran derivarse.</p>
    <p>Autorizo el <strong>tratamiento de mis datos personales</strong> con fines de gestión de membresías, control de acceso y seguridad, conforme la normativa vigente. Podré ejercer mis derechos de acceso, rectificación y supresión según la ley aplicable.</p>
    <p>He leído y comprendo el presente deslinde, y manifiesto mi <strong>conformidad</strong>.</p>
  </div>

  <div class="firma-grid">
    <div class="firma-col">
      <div class="line">Firma del cliente</div>
      <div class="small">Aclaración: <?= h($cl['apellido'].' '.$cl['nombre']) ?></div>
      <div class="small">DNI: <?= h($cl['dni']) ?></div>
    </div>
    <div class="firma-col">
      <div class="line">Firma y sello del gimnasio</div>
      <div class="small"><?= h($gym['nombre']) ?></div>
    </div>
  </div>
</body>
</html>
