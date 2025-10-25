<?php
// ficha_form_html_medico.php — Doble talonario: Competidor (60%) + Control Médico (40%)
// • Arriba ROJO / Abajo AZUL, línea punteada para cortar
// • Cada dato en FILA: etiqueta izquierda + input derecha (compacto)
// • Autorrellena desde la BD (igual que ver_peleas_evento.php)
// • “Apellido y Nombre” con auto-fit de fuente para que entre completo

if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- CONFIG ---------- */
$BG_IMG = 'assets/img/ficha_competidor_doble_2022.png';  // opcional; ?fondo=1 si existe la imagen
$showBg = isset($_GET['fondo']) ? (int)$_GET['fondo'] : 0;
$escala = isset($_GET['escala']) ? max(80, min(120, (float)$_GET['escala'])) : 100;

/* ---------- PARAMS ---------- */
$pelea_id = isset($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;

/* ---------- BD (igual que ver_peleas_evento.php) ---------- */
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
@$conexion->set_charset('utf8mb4');

function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function colmap(mysqli $db, string $t){ $m=[]; if($r=$db->query("SHOW COLUMNS FROM ".bt($t))){ while($x=$r->fetch_assoc()){ $m[strtolower($x['Field'])]=$x['Field']; } } return $m; }
function pick($cands,$pool){ foreach((array)$cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; }
function fetch1(mysqli $db,$sql,$p=[]){ if(!$st=$db->prepare($sql)) return null; if($p){ $t=str_repeat('s',count($p)); $st->bind_param($t,...$p); } if(!$st->execute()) return null; $res=$st->get_result(); return $res?$res->fetch_assoc():null; }

$T_P='peleas_evento'; $T_C='competidores_evento'; $T_E='eventos_deportivos';
$cp=colmap($conexion,$T_P); $cc=colmap($conexion,$T_C); $ce=colmap($conexion,$T_E);

$col_pelea_id  = pick(['id','id_pelea','pelea_id'],$cp);
$col_evento_fk = pick(['evento_id','id_evento'],$cp);
$col_orden     = pick(['orden','nro_pelea','orden_pelea','numero'],$cp);
$col_rojo_fk   = pick(['rojo_id','competidor_rojo_id','id_rojo'],$cp);
$col_azul_fk   = pick(['azul_id','competidor_azul_id','id_azul'],$cp);
$col_modalidad = pick(['modalidad','disciplina','reglamento'],$cp);
$col_categoria = pick(['categoria','categoria_peso','cat_peso'],$cp);
$col_fechahora = pick(['fecha_hora','fecha','hora','datetime'],$cp);
$col_evento_id = pick(['id','evento_id'],$ce);
$col_evento_nom= pick(['titulo','nombre','evento'],$ce);

$DATA = [
  'evento'   => '',
  'orden'    => '',
  'modalidad'=> '',
  'categoria'=> '',
  'fecha'    => '',
  'rojo'     => ['nombre'=>'','dni'=>'','sexo'=>'','escuela'=>'','entrenador'=>'','contacto'=>''],
  'azul'     => ['nombre'=>'','dni'=>'','sexo'=>'','escuela'=>'','entrenador'=>'','contacto'=>''],
];

if ($pelea_id>0 && $col_pelea_id && $col_rojo_fk && $col_azul_fk) {
  $sql = "SELECT p.*, e.".bt($col_evento_nom)." ev
          FROM ".bt($T_P)." p
          LEFT JOIN ".bt($T_E)." e ON e.".bt($col_evento_id)."=p.".bt($col_evento_fk)."
          WHERE p.".bt($col_pelea_id)."=? LIMIT 1";
  if ($row = fetch1($conexion,$sql,[$pelea_id])) {
    $DATA['evento']    = $row['ev'] ?? '';
    $DATA['orden']     = $col_orden? ($row[$col_orden]??'') : '';
    $DATA['modalidad'] = $col_modalidad? ($row[$col_modalidad]??'') : '';
    $DATA['categoria'] = $col_categoria? ($row[$col_categoria]??'') : '';
    $DATA['fecha']     = !empty($row[$col_fechahora]) ? date('Y-m-d', strtotime($row[$col_fechahora])) : '';

    $rid = (int)($row[$col_rojo_fk]??0);
    $aid = (int)($row[$col_azul_fk]??0);

    $cid  = pick(['id','id_competidor','competidor_id'],$cc);
    $c_ap = pick(['apellido','apellidos'],$cc);
    $c_no = pick(['nombre','nombres'],$cc);
    $c_dni= pick(['dni','documento','doc'],$cc);
    $c_sx = pick(['sexo','genero'],$cc);
    $c_esc= pick(['escuela','academia','team','gimnasio'],$cc);
    $c_ent= pick(['entrenador','coach'],$cc);
    $c_cto= pick(['contacto','telefono','celular','email'],$cc);

    $getC = function($id) use ($conexion,$T_C,$cid,$c_ap,$c_no,$c_dni,$c_sx,$c_esc,$c_ent,$c_cto){
      $r=['nombre'=>'','dni'=>'','sexo'=>'','escuela'=>'','entrenador'=>'','contacto'=>'']; if($id<=0 || !$cid) return $r;
      $x = fetch1($conexion,"SELECT * FROM ".bt($T_C)." WHERE ".bt($cid)."=? LIMIT 1",[$id]);
      if ($x){
        $ap = $c_ap?($x[$c_ap]??''):'';
        $no = $c_no?($x[$c_no]??''):'';
        $r['nombre']=trim($ap.' '.$no);
        $r['dni']   =$c_dni?($x[$c_dni]??''):'';
        $r['sexo']  =$c_sx?($x[$c_sx]??''):'';
        $r['escuela']=$c_esc?($x[$c_esc]??''):'';
        $r['entrenador']=$c_ent?($x[$c_ent]??''):'';
        $r['contacto']=$c_cto?($x[$c_cto]??''):'';
      }
      return $r;
    };
    $DATA['rojo'] = $getC($rid);
    $DATA['azul'] = $getC($aid);
  }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ficha Competidor + Control Médico (Doble)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  @page { size: A4 portrait; margin: 0; }
  :root{ --scale: <?= $escala/100 ?>; }
  *{ box-sizing:border-box }
  body{ margin:0; font-family: Arial, Helvetica, sans-serif; background:#f4f4f4; }
  .topbar{ position:sticky; top:0; z-index:10; display:flex; gap:8px; align-items:center; padding:10px 12px; background:#fff; border-bottom:1px solid #ddd; font:14px/1.2 system-ui,Segoe UI,Roboto,Arial; }
  .btn{ padding:8px 12px; border:1px solid #111; background:#111; color:#fff; border-radius:8px; text-decoration:none; display:inline-block }
  .btn.secondary{ background:#fff; color:#111; }
  .num{ padding:6px 8px; width:80px }
  .hint{ color:#444; font-size:12px }
  .wrap{ display:flex; justify-content:center; padding:16px }
  .sheet{
    position:relative; width:210mm; height:297mm;
    transform: scale(var(--scale)); transform-origin: top center;
    background:#fff; overflow:hidden;
  }
  .sheet.bg::before{
    content:""; position:absolute; inset:0;
    background: url('<?= h($BG_IMG) ?>') no-repeat 0 0 / 210mm 297mm;
    opacity:.14; pointer-events:none;
  }
  .half{ position:relative; width:210mm; height:148.5mm; padding:8mm 10mm; }
  .cutline{ position:absolute; left:0; right:0; top:50%; border-top:1px dashed #000; opacity:.75; }

  /* Cabecera */
  .header{ display:grid; grid-template-columns:1fr 25mm 36mm 32mm; gap:3.5mm; align-items:end; margin-bottom:4mm; }
  .labTop{ font-weight:bold; font-size:9.8pt; text-transform:uppercase; letter-spacing:.2px }
  .field{ border:1px solid #000; padding:1.6mm 2.4mm; min-height:6.4mm; font-size:10.6pt; display:flex; align-items:center }
  input[type="text"], input[type="date"], input[type="time"], input[type="number"], select{
    width:100%; border:none; outline:none; font: inherit; background:transparent;
  }

  /* 60% / 40% */
  .two-cols{ display:grid; grid-template-columns:1.5fr 1fr; gap:6mm; }

  .card{ border:1px solid #000; padding:4mm; }
  .title{ font-weight:bold; font-size:10.8pt; margin:0 0 3mm 0; text-transform:uppercase }

  /* Competidor en filas (etiqueta izq, input der) — etiqueta más angosta para dar más ancho al input */
  .rows{ display:grid; grid-template-columns:24mm 1fr; gap:2.8mm 3.5mm; align-items:center; } /* antes 28mm */
  .rows .lbl{ font-size:9.6pt; color:#111; text-transform:none }
  .rows .box{ border:1px solid #000; min-height:6.4mm; padding:1.4mm 2.2mm; font-size:10.6pt; display:flex; align-items:center }
  .fit-input{ font-size:10.6pt; } /* clase para auto-fit */

  /* Control médico compacto (dos columnas de pares) */
  .med-grid{ display:grid; grid-template-columns:28mm 1fr; gap:2.6mm 3mm; align-items:center; }
  .med-grid .label{ font-size:9.6pt }
  .med-grid .field{ min-height:6.4mm; }

  .med-cols{ display:grid; grid-template-columns:1fr 1fr; gap:3mm 6mm; }
  .footer{ display:grid; grid-template-columns:1fr 26mm 1fr 24mm; gap:3mm; align-items:center; margin-top:3mm; }
  .signs{ display:grid; grid-template-columns:1fr 1fr; gap:5mm; margin-top:3mm; }
  .signbox{ border:1px solid #000; height:12mm; }
  .small{ font-size:9.2pt; color:#333 }

  @media print{
    body{ background:#fff }
    .topbar{ display:none !important }
    .sheet{ transform:none !important }
    .sheet.bg::before{ opacity:1 }
  }
</style>
</head>
<body>

<div class="topbar">
  <a class="btn" href="#" onclick="window.print()">🖨️ Imprimir / PDF</a>
  <a class="btn secondary" href="?pelea_id=<?= (int)$pelea_id ?>&escala=<?= (int)$escala ?>&fondo=<?= $showBg?0:1 ?>">
    <?= $showBg? 'Ocultar fondo' : 'Mostrar fondo' ?>
  </a>
  <span class="hint">Escala:</span><input class="num" id="scaleInput" type="number" value="<?= (int)$escala ?>" min="80" max="120">%
  <?php if($pelea_id): ?><span class="hint">· pelea_id: <b><?= (int)$pelea_id ?></b></span><?php endif; ?>
  <span style="margin-left:auto" class="hint">ROJO arriba / AZUL abajo · cortar por la línea punteada</span>
</div>

<div class="wrap">
  <div class="sheet <?= ($showBg && is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace(['/','\\'], DIRECTORY_SEPARATOR, $BG_IMG))) ? 'bg':'' ?>">
    <div class="cutline"></div>

    <!-- ======= MITAD SUPERIOR: ROJO ======= -->
    <div class="half">
      <div class="header">
        <div><div class="labTop">Evento</div><div class="field"><input type="text" value="<?= h($DATA['evento']) ?>"></div></div>
        <div><div class="labTop">Orden</div><div class="field"><input type="text" value="<?= h($DATA['orden']) ?>"></div></div>
        <div><div class="labTop">Modalidad</div><div class="field"><input type="text" value="<?= h($DATA['modalidad']) ?>"></div></div>
        <div><div class="labTop">Fecha</div><div class="field"><input type="date" value="<?= h($DATA['fecha']) ?>"></div></div>
      </div>

      <div class="two-cols">
        <!-- Competidor ROJO (60%) -->
        <div class="card">
          <div class="title">Esquina ROJA</div>
          <div class="rows">
            <div class="lbl">Apellido y Nombre</div>
            <div class="box"><input id="nom_rojo" class="fit-input" type="text" value="<?= h($DATA['rojo']['nombre']) ?>"></div>

            <div class="lbl">DNI</div>
            <div class="box"><input type="text" value="<?= h($DATA['rojo']['dni']) ?>"></div>

            <div class="lbl">Sexo</div>
            <div class="box"><input type="text" value="<?= h($DATA['rojo']['sexo']) ?>" placeholder="F/M"></div>

            <div class="lbl">Escuela</div>
            <div class="box"><input type="text" value="<?= h($DATA['rojo']['escuela']) ?>"></div>

            <div class="lbl">Entrenador</div>
            <div class="box"><input type="text" value="<?= h($DATA['rojo']['entrenador']) ?>"></div>

            <div class="lbl">Contacto</div>
            <div class="box"><input type="text" value="<?= h($DATA['rojo']['contacto']) ?>"></div>
          </div>
        </div>

        <!-- Control médico ROJO (40%) -->
        <div class="card">
          <div class="title">Control Médico — ROJO</div>

          <div class="med-cols">
            <div class="med-grid">
              <div class="label">Presión</div><div class="field"><input id="pr_rojo" type="text" placeholder="120/80 mmHg"></div>
              <div class="label">Frec. Card.</div><div class="field"><input id="fc_rojo" type="number" step="1" placeholder="bpm"></div>
              <div class="label">SpO₂</div><div class="field"><input id="spo2_rojo" type="number" step="1" placeholder="%"></div>
              <div class="label">Temp.</div><div class="field"><input id="temp_rojo" type="number" step="0.1" placeholder="°C"></div>
              <div class="label">Glucemia</div><div class="field"><input id="glu_rojo" type="number" step="1" placeholder="mg/dL"></div>
            </div>
            <div class="med-grid">
              <div class="label">Peso</div><div class="field"><input id="peso_rojo" type="number" step="0.1" placeholder="kg"></div>
              <div class="label">Altura</div><div class="field"><input id="alt_rojo" type="number" step="0.1" placeholder="cm"></div>
              <div class="label">IMC</div><div class="field"><input id="imc_rojo" type="text" placeholder="kg/m²" readonly></div>
              <div class="label">Apto</div>
              <div class="field">
                <select id="apto_rojo"><option value="">—</option><option>Apto</option><option>No Apto</option></select>
              </div>
            </div>
          </div>

          <div class="footer">
            <div class="labTop">Médico</div><div class="field"><input id="med_rojo" type="text" placeholder="Nombre y Apellido"></div>
            <div class="labTop">Matrícula</div><div class="field"><input id="mat_rojo" type="text"></div>
            <div class="labTop">Fecha</div><div class="field"><input id="fch_rojo" type="date" value="<?= h($DATA['fecha']) ?>"></div>
            <div class="labTop">Hora</div><div class="field"><input id="hor_rojo" type="time"></div>
          </div>

          <div class="signs">
            <div><div class="small">Firma Competidor ROJO</div><div class="signbox"></div></div>
            <div><div class="small">Firma Médico</div><div class="signbox"></div></div>
          </div>
        </div>
      </div>
    </div><!-- /half ROJO -->

    <!-- ======= MITAD INFERIOR: AZUL ======= -->
    <div class="half" style="top:148.5mm; position:absolute;">
      <div class="header">
        <div><div class="labTop">Evento</div><div class="field"><input type="text" value="<?= h($DATA['evento']) ?>"></div></div>
        <div><div class="labTop">Orden</div><div class="field"><input type="text" value="<?= h($DATA['orden']) ?>"></div></div>
        <div><div class="labTop">Modalidad</div><div class="field"><input type="text" value="<?= h($DATA['modalidad']) ?>"></div></div>
        <div><div class="labTop">Fecha</div><div class="field"><input type="date" value="<?= h($DATA['fecha']) ?>"></div></div>
      </div>

      <div class="two-cols">
        <!-- Competidor AZUL (60%) -->
        <div class="card">
          <div class="title">Esquina AZUL</div>
          <div class="rows">
            <div class="lbl">Apellido y Nombre</div>
            <div class="box"><input id="nom_azul" class="fit-input" type="text" value="<?= h($DATA['azul']['nombre']) ?>"></div>

            <div class="lbl">DNI</div>
            <div class="box"><input type="text" value="<?= h($DATA['azul']['dni']) ?>"></div>

            <div class="lbl">Sexo</div>
            <div class="box"><input type="text" value="<?= h($DATA['azul']['sexo']) ?>" placeholder="F/M"></div>

            <div class="lbl">Escuela</div>
            <div class="box"><input type="text" value="<?= h($DATA['azul']['escuela']) ?>"></div>

            <div class="lbl">Entrenador</div>
            <div class="box"><input type="text" value="<?= h($DATA['azul']['entrenador']) ?>"></div>

            <div class="lbl">Contacto</div>
            <div class="box"><input type="text" value="<?= h($DATA['azul']['contacto']) ?>"></div>
          </div>
        </div>

        <!-- Control médico AZUL (40%) -->
        <div class="card">
          <div class="title">Control Médico — AZUL</div>

          <div class="med-cols">
            <div class="med-grid">
              <div class="label">Presión</div><div class="field"><input id="pr_azul" type="text" placeholder="120/80 mmHg"></div>
              <div class="label">Frec. Card.</div><div class="field"><input id="fc_azul" type="number" step="1" placeholder="bpm"></div>
              <div class="label">SpO₂</div><div class="field"><input id="spo2_azul" type="number" step="1" placeholder="%"></div>
              <div class="label">Temp.</div><div class="field"><input id="temp_azul" type="number" step="0.1" placeholder="°C"></div>
              <div class="label">Glucemia</div><div class="field"><input id="glu_azul" type="number" step="1" placeholder="mg/dL"></div>
            </div>
            <div class="med-grid">
              <div class="label">Peso</div><div class="field"><input id="peso_azul" type="number" step="0.1" placeholder="kg"></div>
              <div class="label">Altura</div><div class="field"><input id="alt_azul" type="number" step="0.1" placeholder="cm"></div>
              <div class="label">IMC</div><div class="field"><input id="imc_azul" type="text" placeholder="kg/m²" readonly></div>
              <div class="label">Apto</div>
              <div class="field">
                <select id="apto_azul"><option value="">—</option><option>Apto</option><option>No Apto</option></select>
              </div>
            </div>
          </div>

          <div class="footer">
            <div class="labTop">Médico</div><div class="field"><input id="med_azul" type="text" placeholder="Nombre y Apellido"></div>
            <div class="labTop">Matrícula</div><div class="field"><input id="mat_azul" type="text"></div>
            <div class="labTop">Fecha</div><div class="field"><input id="fch_azul" type="date" value="<?= h($DATA['fecha']) ?>"></div>
            <div class="labTop">Hora</div><div class="field"><input id="hor_azul" type="time"></div>
          </div>

          <div class="signs">
            <div><div class="small">Firma Competidor AZUL</div><div class="signbox"></div></div>
            <div><div class="small">Firma Médico</div><div class="signbox"></div></div>
          </div>
        </div>
      </div>
    </div><!-- /half AZUL -->
  </div>
</div>

<script>
  // Ajuste visual de escala
  document.getElementById('scaleInput')?.addEventListener('change', ()=>{
    const v = Math.max(80, Math.min(120, parseFloat(document.getElementById('scaleInput').value)||100));
    document.documentElement.style.setProperty('--scale', v/100);
  });

  // IMC automático = peso(kg) / (altura(m))^2
  function bindIMC(pesoId, altId, imcId){
    function calc(){
      const kg = parseFloat((document.getElementById(pesoId).value||'').replace(',','.'))||0;
      const cm = parseFloat((document.getElementById(altId).value||'').replace(',','.'))||0;
      if (kg>0 && cm>0){
        const m  = cm/100;
        const imc = kg/(m*m);
        document.getElementById(imcId).value = imc.toFixed(1);
      }else{
        document.getElementById(imcId).value = '';
      }
    }
    document.getElementById(pesoId).addEventListener('input', calc);
    document.getElementById(altId).addEventListener('input', calc);
  }
  bindIMC('peso_rojo','alt_rojo','imc_rojo');
  bindIMC('peso_azul','alt_azul','imc_azul');

  // ===== Auto-fit de Apellido y Nombre (reduce fuente hasta que entre) =====
  function autofitInput(el, minPt=8, maxPt=13) {
    const box = el.parentElement; // .box
    let size = maxPt;
    el.style.fontSize = size + 'pt';
    const needs = () => el.scrollWidth > box.clientWidth - 4; // margen interno
    let guard = 100;
    while (size > minPt && needs() && guard-- > 0) {
      size -= 0.3; // paso fino
      el.style.fontSize = size + 'pt';
    }
  }
  function initAutofit() {
    const rojo = document.getElementById('nom_rojo');
    const azul = document.getElementById('nom_azul');
    if (rojo) { autofitInput(rojo); rojo.addEventListener('input', ()=>autofitInput(rojo)); }
    if (azul) { autofitInput(azul); azul.addEventListener('input', ()=>autofitInput(azul)); }
  }
  window.addEventListener('load', initAutofit);
</script>

</body>
</html>
