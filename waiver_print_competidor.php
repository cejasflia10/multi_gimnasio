<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_fecha_es(?string $ymd): string {
  if (!$ymd || $ymd === '0000-00-00') return '';
  $ts = strtotime($ymd);
  return $ts ? date('d/m/Y',$ts) : '';
}

$evento_id = (int)($_GET['evento_id'] ?? 0);
$comp_id   = (int)($_GET['id'] ?? 0);
if ($evento_id<=0 || $comp_id<=0) exit('❌ Falta id.');

$st = $conexion->prepare("
  SELECT id,apellido,nombre,dni,edad,escuela_nombre
  FROM competidores_evento
  WHERE id=? AND evento_id=?
  LIMIT 1
");
$st->bind_param('ii',$comp_id,$evento_id);
$st->execute(); $rs=$st->get_result();
$comp=$rs?$rs->fetch_assoc():null;
$st->close();
if(!$comp) exit('❌ Competidor no encontrado.');

$evento = ["titulo"=>"Evento #$evento_id","fecha"=>"","lugar"=>""];
$q=$conexion->prepare("SELECT titulo,fecha,lugar FROM eventos_deportivos WHERE id=?");
$q->bind_param('i',$evento_id);
$q->execute(); $r=$q->get_result();
if($r&&$r->num_rows){
  $ev=$r->fetch_assoc();
  $evento["titulo"]=$ev["titulo"] ?: $evento["titulo"];
  $evento["fecha"]=fmt_fecha_es($ev["fecha"]);
  $evento["lugar"]=$ev["lugar"];
}
$q->close();

$nom = trim($comp["apellido"]." ".$comp["nombre"]);
$dni = $comp["dni"];
$edad= $comp["edad"];
$esc = $comp["escuela_nombre"];

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Deslinde — <?=h($evento["titulo"])?></title>

<style>
@page { size: A4; margin: 12mm; }

body{
  margin:0;
  font-family:"Times New Roman",serif;
  font-size:11.2pt;
  line-height:1.38;
}

.contenido{
  max-width:190mm;
  margin:auto;
}

h1{
  text-align:center;
  font-size:18pt;
  margin:0 0 4mm 0;
  text-transform:uppercase;
}

.tagbox{ text-align:center; margin-bottom:5mm; }
.tag{
  border:1px solid #ccc;
  padding:2px 8px;
  border-radius:50px;
  font-size:10pt;
  margin:0 3px;
}

table.meta{
  width:100%;
  border:1px solid #ccc;
  border-collapse:collapse;
  margin-bottom:6mm;
  font-size:10.5pt;
}
table.meta td{
  padding:3px 5px;
  border:1px solid #ddd;
}

.bloque{
  margin-bottom:6mm;
  text-align:justify;
  text-justify:inter-word;
}

.bloque h2{
  font-size:13pt;
  text-align:center;
  margin:0 0 2mm 0;
}

.bloque ol{
  margin:0 0 0 17px;
  padding:0;
}
.bloque li{
  margin:2mm 0;
}

.firmas{
  width:100%;
  margin-top:10mm;
  font-size:10pt;
}
.firmas td{
  width:33%;
  text-align:center;
  vertical-align:bottom;
  padding-top:14mm;
}
.firmas .linea{
  border-top:1px solid #000;
  margin-bottom:2px;
}
</style>
</head>

<body>
<div class="contenido">

<h1>Deslinde de Responsabilidad</h1>

<div class="tagbox">
  <span class="tag">Evento: <?=h($evento["titulo"])?></span>
  <span class="tag">Competidor #<?=$comp_id?></span>
</div>

<table class="meta">
<tr><td><b>Fecha:</b></td><td><?=h($evento["fecha"])?></td></tr>
<tr><td><b>Lugar:</b></td><td><?=h($evento["lugar"])?></td></tr>
<tr><td><b>Nombre y Apellido:</b></td><td><?=h($nom)?></td></tr>
<tr><td><b>DNI:</b></td><td><?=h($dni)?></td></tr>
<tr><td><b>Edad:</b></td><td><?=h($edad)?></td></tr>
<tr><td><b>Escuela/Gimnasio:</b></td><td><?=h($esc)?></td></tr>
</table>

<div class="bloque">
<h2>DECLARACIÓN</h2>

<ol>
<li>Declaro que participo voluntariamente en la actividad/competencia y conozco los riesgos inherentes a la práctica de deportes de contacto.</li>
<li>Afirmo que me encuentro en condiciones físicas aptas y, de ser necesario, presentaré apto médico correspondiente.</li>
<li>Asumo personalmente todos los riesgos de lesiones, daños o pérdidas que pudieran ocurrir durante el evento.</li>
<li>Libero de toda responsabilidad a la organización, promotores, jueces, árbitros, colaboradores, sponsors y al lugar del evento por cualquier contingencia derivada de la actividad.</li>
<li>Me comprometo a respetar el reglamento vigente y las indicaciones del staff y oficiales durante el desarrollo del evento.</li>
<li>Autorizo la utilización de mi imagen en fotografías y material audiovisual del evento con fines informativos y promocionales.</li>
<li>Si soy menor de edad, declaro contar con la autorización de mi padre/madre/tutor responsable, quien firma también este deslinde.</li>
</ol>

<p style="font-size:10pt; margin-top:3mm;">
Nota: en caso de emergencia, la organización gestionará la asistencia correspondiente y se comunicará con el contacto indicado.
</p>
</div>

<table class="firmas">
<tr>
  <td>
    <div class="linea"></div>
    Firma del Competidor<br><span style="font-size:9pt;">Aclaración y DNI</span>
  </td>

  <td>
    <div class="linea"></div>
    Firma del Responsable (si corresponde)<br><span style="font-size:9pt;">Aclaración y DNI</span>
  </td>

  <td>
    <div class="linea"></div>
    Firma del Organizador<br><span style="font-size:9pt;">Aclaración</span>
  </td>
</tr>
</table>

</div>
</body>
</html>
