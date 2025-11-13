<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// === Parámetros ===
$comp_id   = (int)($_GET['id']        ?? 0);
$evento_id = (int)($_GET['evento_id'] ?? 0);

if ($comp_id <= 0 || $evento_id <= 0) {
  http_response_code(400);
  exit('❌ Falta id de competidor o evento.');
}

// === Traer datos del competidor + evento ===
$sql = "SELECT 
          ce.*,
          ev.titulo       AS evento_titulo,
          ev.fecha        AS evento_fecha,
          ev.hora         AS evento_hora,
          ev.lugar        AS evento_lugar
        FROM competidores_evento ce
        INNER JOIN eventos_deportivos ev ON ev.id = ce.evento_id
        WHERE ce.id = ? AND ce.evento_id = ?
        LIMIT 1";

$st = $conexion->prepare($sql);
if (!$st) {
  http_response_code(500);
  exit('❌ Error SQL: '.$conexion->error);
}
$st->bind_param('ii', $comp_id, $evento_id);
$st->execute();
$res = $st->get_result();
$comp = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$comp) {
  http_response_code(404);
  exit('❌ No se encontró el competidor para este evento.');
}

$nombreCompleto = trim(($comp['apellido'] ?? '').' '.($comp['nombre'] ?? ''));
$edad           = $comp['edad'] ?? '';
$dni            = $comp['dni'] ?? '';
$domicilio      = $comp['domicilio'] ?? '';
$localidad      = $comp['localidad'] ?? '';
$telefono       = $comp['telefono'] ?? '';
$escuela        = $comp['escuela_nombre'] ?? '';
$disciplina_id  = $comp['disciplina_id'] ?? null;
$modalidad_id   = $comp['modalidad_id'] ?? null;

// Opcional: leer el nombre de la disciplina y modalidad si querés que figuren
$disciplina = '';
if (!empty($disciplina_id)) {
  $rs = $conexion->query("SELECT nombre FROM disciplinas_evento WHERE id=".(int)$disciplina_id." LIMIT 1");
  if ($rs && $row = $rs->fetch_assoc()) $disciplina = $row['nombre'];
}
$modalidad = '';
if (!empty($modalidad_id)) {
  $rs = $conexion->query("SELECT nombre FROM modalidades_evento WHERE id=".(int)$modalidad_id." LIMIT 1");
  if ($rs && $row = $rs->fetch_assoc()) $modalidad = $row['nombre'];
}

// Formatear fecha evento
$fecha_evento = '';
if (!empty($comp['evento_fecha'])) {
  $t = strtotime($comp['evento_fecha']);
  if ($t) {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $d = (int)date('d',$t);
    $m = $meses[(int)date('m',$t)-1] ?? date('m',$t);
    $y = date('Y',$t);
    $fecha_evento = "$d de $m de $y";
  }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Deslinde de responsabilidad - <?= h($nombreCompleto) ?></title>
  <style>
    /* ==============================
       Estilos de impresión A4
       ============================== */
    @page {
      size: A4;
      margin: 2.2cm 2cm 2cm 2cm; /* márgenes ajustados para entrar en 1 hoja */
    }
    html, body {
      padding: 0;
      margin: 0;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
      font-size: 11pt;
      line-height: 1.35;
      color: #111827;
    }
    .page {
      max-width: 800px;
      margin: 0 auto;
    }
    .header {
      text-align: center;
      margin-bottom: 10px;
    }
    .header h1 {
      font-size: 18pt;
      margin: 0 0 4px 0;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .header h2 {
      font-size: 13pt;
      margin: 0 0 2px 0;
      font-weight: 600;
    }
    .header .sub {
      font-size: 10pt;
      color: #374151;
      margin-bottom: 4px;
    }
    .divider {
      border-top: 1px solid #9ca3af;
      margin: 6px 0 10px 0;
    }

    .section-title {
      font-weight: 700;
      font-size: 11.5pt;
      margin: 6px 0 3px 0;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .datos-grid {
      display: grid;
      grid-template-columns: 1.2fr 1.2fr;
      column-gap: 16px;
      row-gap: 2px;
      margin-bottom: 6px;
      font-size: 10.5pt;
    }
    .datos-grid div strong {
      font-weight: 600;
    }

    p {
      text-align: justify;
      margin: 4px 0;
    }
    ol {
      margin: 4px 0 6px 0;
      padding-left: 18px;
    }
    ol li {
      margin-bottom: 3px;
      text-align: justify;
    }

    .firmas {
      display: grid;
      grid-template-columns: 1.1fr 1.1fr;
      column-gap: 26px;
      margin-top: 14px;
      font-size: 10pt;
    }
    .firma-block {
      text-align: center;
      margin-top: 10px;
    }
    .firma-line {
      border-top: 1px solid #111827;
      margin: 18px 0 2px 0;
    }
    .firma-label {
      font-size: 9pt;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .nota {
      font-size: 9pt;
      color: #4b5563;
      margin-top: 8px;
      text-align: justify;
    }

    /* Evitar saltos feos */
    .bloque-completo {
      page-break-inside: avoid;
    }
  </style>
</head>
<body>
<div class="page">
  <div class="header">
    <h1>Deslinde de Responsabilidad</h1>
    <h2><?= h($comp['evento_titulo'] ?? 'Evento deportivo') ?></h2>
    <?php if ($fecha_evento || !empty($comp['evento_lugar'])): ?>
      <div class="sub">
        <?php if ($fecha_evento): ?>
          Fecha: <?= h($fecha_evento) ?>
        <?php endif; ?>
        <?php if ($fecha_evento && !empty($comp['evento_lugar'])): ?> · <?php endif; ?>
        <?php if (!empty($comp['evento_lugar'])): ?>
          Lugar: <?= h($comp['evento_lugar']) ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="divider"></div>

  <div class="bloque-completo">
    <div class="section-title">Datos del/la competidor/a</div>
    <div class="datos-grid">
      <div><strong>Apellido y Nombre:</strong> <?= h($nombreCompleto) ?></div>
      <div><strong>DNI:</strong> <?= h($dni) ?></div>
      <div><strong>Edad:</strong> <?= h($edad) ?></div>
      <div><strong>Teléfono:</strong> <?= h($telefono) ?></div>
      <div><strong>Domicilio:</strong> <?= h($domicilio) ?></div>
      <div><strong>Localidad:</strong> <?= h($localidad) ?></div>
      <div><strong>Escuela / Gimnasio:</strong> <?= h($escuela) ?></div>
      <?php if ($disciplina): ?>
        <div><strong>Disciplina:</strong> <?= h($disciplina) ?></div>
      <?php endif; ?>
      <?php if ($modalidad): ?>
        <div><strong>Modalidad:</strong> <?= h($modalidad) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="bloque-completo">
    <div class="section-title">Declaración</div>
    <p>
      Por la presente, quien suscribe declara que participa de manera voluntaria en el evento deportivo
      arriba mencionado, asumiendo que la práctica de deportes de contacto implica riesgos físicos propios
      de la actividad, tales como golpes, caídas, lesiones musculares, articulares y óseas, entre otros.
    </p>
    <p>
      Declaro que me encuentro en condiciones físicas y de salud aptas para realizar esta actividad, y que
      he informado a los organizadores acerca de cualquier antecedente médico relevante. Asimismo
