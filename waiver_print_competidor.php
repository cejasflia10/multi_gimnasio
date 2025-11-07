<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_fecha_es(?string $ymd): string {
  if (!$ymd || $ymd === '0000-00-00') return '';
  $ts = strtotime($ymd);
  if ($ts === false) return '';
  return date('d/m/Y', $ts);
}

/* ===== Parámetros ===== */
$evento_id = (int)($_GET['evento_id'] ?? 0);
$comp_id   = (int)($_GET['id'] ?? 0);
if ($evento_id <= 0 || $comp_id <= 0) { http_response_code(400); exit('❌ Falta evento_id o id.'); }

/* ===== Competidor: valida pertenencia y datos ===== */
$st = $conexion->prepare("
  SELECT id, apellido, nombre, dni, edad, escuela_nombre
  FROM competidores_evento
  WHERE id=? AND evento_id=?
  LIMIT 1
");
if (!$st) { http_response_code(500); exit('❌ Error SQL competidor: '.$conexion->error); }
$st->bind_param('ii', $comp_id, $evento_id);
$st->execute(); $rs = $st->get_result(); $comp = $rs ? $rs->fetch_assoc() : null;
$st->close();
if (!$comp) { http_response_code(404); exit('❌ Competidor no encontrado en este evento.'); }

/* ===== Evento: titulo, fecha, lugar desde eventos_deportivos ===== */
$evento_titulo = "Evento #{$evento_id}";
$evento_fecha  = '';
$evento_lugar  = '';

$qe = $conexion->prepare("SELECT titulo, fecha, lugar FROM eventos_deportivos WHERE id=? LIMIT 1");
if ($qe) {
  $qe->bind_param('i', $evento_id);
  $qe->execute(); $re = $qe->get_result();
  if ($re && $re->num_rows) {
    $row = $re->fetch_assoc();
    if (!empty($row['titulo'])) $evento_titulo = (string)$row['titulo'];
    $evento_fecha = fmt_fecha_es($row['fecha'] ?? null);
    $evento_lugar = (string)($row['lugar'] ?? '');
  }
  $qe->close();
}

/* ===== Valores impresos ===== */
$comp_apenom = trim(($comp['apellido'] ?? '').' '.($comp['nombre'] ?? ''));
$comp_dni    = trim((string)($comp['dni'] ?? ''));
$comp_edad   = trim((string)($comp['edad'] ?? ''));
$comp_esc    = trim((string)($comp['escuela_nombre'] ?? ''));

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Deslinde de Responsabilidad — <?= h($evento_titulo) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{ --negro:#111827; --gris:#6b7280; --borde:#e5e7eb; }
  *{ box-sizing: border-box; }
  html,body{ margin:0; padding:0; }
  body{ font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--negro); background:#fff; }
  .sheet{ max-width:210mm; margin:0 auto; padding:16mm 16mm 18mm; background:#fff; }
  .hdr{ margin-bottom:10mm; }
  .hdr h1{ font-size:20pt; margin:0 0 4px 0; line-height:1.2; }
  .sub{ color:var(--gris); font-size:11.5pt; display:flex; flex-wrap:wrap; gap:8px; }
  .tag{ display:inline-block; padding:2px 10px; border:1px solid var(--borde); border-radius:999px; background:#fbfbfb; }
  .meta{ width:100%; border:1px solid var(--borde); border-radius:8px; padding:10px; margin-bottom:8mm; }
  .row{ display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; margin-bottom:8px; }
  .lbl{ font-size:10pt; color:var(--gris); margin-bottom:4px; }
  .line{ border-bottom:1px solid var(--borde); min-height:22px; position:relative; padding:2px 0; }
  .fill{ position:relative; top:0; left:0; font-size:11.5pt; }
  .full{ grid-column:1 / -1; }
  .box{ border:1px solid var(--borde); border-radius:8px; padding:10px 12px; margin-bottom:8mm; }
  .box h3{ margin:0 0 6px 0; font-size:12.5pt; }
  ol{ margin:6px 0 6px 18px; padding:0; }
  li{ margin:6px 0; }
  .sig-grid{ display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:10mm; }
  .sig{ border-top:1px solid var(--borde); padding-top:6px; text-align:center; min-height:36px; }
  .small{ font-size:10pt; color:var(--gris); }
  .print-bar{ position:sticky; top:0; background:#fff; border-bottom:1px solid var(--borde); padding:8px 12px; display:flex; justify-content:flex-end; }
  .btn{ padding:8px 12px; border:1px solid var(--borde); border-radius:8px; background:#f9fafb; cursor:pointer; }
  @media print{
    .print-bar{ display:none; }
    .sheet{ padding:12mm 12mm 14mm; }
  }
</style>
</head>
<body>
<div class="print-bar">
  <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
</div>

<div class="sheet">
  <div class="hdr">
    <h1>Deslinde de Responsabilidad</h1>
    <div class="sub">
      <span class="tag">Evento: <?= h($evento_titulo) ?></span>
      <span class="tag">Competidor #<?= (int)$comp_id ?></span>
    </div>
  </div>

  <!-- DATOS (se imprimen si hay valor; si no, queda la línea vacía) -->
  <div class="meta">
    <div class="row">
      <div>
        <div class="lbl">Fecha</div>
        <div class="line">
          <?php if ($evento_fecha !== ''): ?><span class="fill"><?= h($evento_fecha) ?></span><?php endif; ?>
        </div>
      </div>
      <div>
        <div class="lbl">Lugar</div>
        <div class="line">
          <?php if ($evento_lugar !== ''): ?><span class="fill"><?= h($evento_lugar) ?></span><?php endif; ?>
        </div>
      </div>

      <div>
        <div class="lbl">Nombre y Apellido</div>
        <div class="line">
          <?php if ($comp_apenom !== ''): ?><span class="fill"><?= h($comp_apenom) ?></span><?php endif; ?>
        </div>
      </div>
      <div>
        <div class="lbl">DNI / Pasaporte</div>
        <div class="line">
          <?php if ($comp_dni !== ''): ?><span class="fill"><?= h($comp_dni) ?></span><?php endif; ?>
        </div>
      </div>

      <div class="full">
        <div class="lbl">Escuela / Gimnasio</div>
        <div class="line">
          <?php if ($comp_esc !== ''): ?><span class="fill"><?= h($comp_esc) ?></span><?php endif; ?>
        </div>
      </div>

      <div>
        <div class="lbl">Teléfono</div>
        <div class="line"></div>
      </div>
      <div>
        <div class="lbl">Edad</div>
        <div class="line">
          <?php if ($comp_edad !== ''): ?><span class="fill"><?= h($comp_edad) ?></span><?php endif; ?>
        </div>
      </div>

      <div class="full">
        <div class="lbl">Domicilio</div>
        <div class="line"></div>
      </div>
      <div class="full">
        <div class="lbl">Localidad / Provincia</div>
        <div class="line"></div>
      </div>
      <div class="full">
        <div class="lbl">Responsable / Tutor (si es menor)</div>
        <div class="line"></div>
      </div>
      <div class="full">
        <div class="lbl">Datos médicos relevantes / Alergias</div>
        <div class="line" style="min-height:36px;"></div>
      </div>
    </div>
  </div>

  <div class="box">
    <h3>Declaración</h3>
    <ol>
      <li>Declaro que participo voluntariamente en la actividad/competencia y conozco los riesgos inherentes a la práctica de deportes de contacto.</li>
      <li>Afirmo que me encuentro en condiciones físicas aptas y, de ser necesario, presentaré apto médico correspondiente.</li>
      <li>Asumo personalmente todos los riesgos de lesiones, daños o pérdidas que pudieran ocurrir durante el evento.</li>
      <li>Libero de toda responsabilidad a la organización, promotores, jueces, árbitros, colaboradores, sponsors y al lugar del evento por cualquier contingencia derivada de la actividad.</li>
      <li>Me comprometo a respetar el reglamento vigente y las indicaciones del staff y oficiales durante el desarrollo del evento.</li>
      <li>Autorizo la utilización de mi imagen en fotografías y/o material audiovisual del evento con fines informativos y promocionales.</li>
      <li>Si soy menor de edad, declaro contar con la autorización de mi padre/madre/tutor responsable, quien firma también este deslinde.</li>
    </ol>
    <p class="small">Nota: en caso de emergencia, la organización gestionará la asistencia correspondiente y se comunicará con el contacto indicado.</p>
  </div>

  <div class="sig-grid">
    <div>
      <div class="sig">Firma del Competidor</div>
      <div class="small">Aclaración y DNI</div>
    </div>
    <div>
      <div class="sig">Firma del Responsable (si corresponde)</div>
      <div class="small">Aclaración y DNI</div>
    </div>
    <div class="full">
      <div class="sig">Firma del Organizador</div>
      <div class="small">Aclaración</div>
    </div>
  </div>
</div>
</body>
</html>
