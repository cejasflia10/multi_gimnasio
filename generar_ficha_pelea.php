<?php
/* ============================================================
   generar_ficha_pelea.php — FICHA MÉDICA DOBLE (2 por hoja)
   - Usa plantilla "ficha competidor-doble-2022" (2 fichas verticales)
   - Arriba: competidor 1 (esquina roja de la pelea)
   - Abajo: competidor 2 (esquina azul)
   - Datos que completa en cada ficha:
       MODALIDAD
       DNI
       Apellido y Nombre
       Fecha de Nacimiento
       Sexo / Edad
       Localidad / Provincia
       Institución / Club o Gimnasio
       Profesor o Coach
       Peso (en cuadro de control médico)
   - Extra médico (apto, etc.) se edita opcional desde ?edit=1
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/* ========= Helpers ========= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function colmap(mysqli $db, string $t){
  $m=[];
  if($r=$db->query("SHOW COLUMNS FROM ".bt($t))){
    while($x=$r->fetch_assoc()){
      $m[strtolower($x['Field'])]=$x['Field'];
    }
  }
  return $m;
}
function pick($cands,$pool){
  foreach((array)$cands as $c){
    $lc=strtolower($c);
    if(isset($pool[$lc])) return $pool[$lc];
  }
  return null;
}
function fetch1(mysqli $db,$sql,$p=[]){
  if(!$st=$db->prepare($sql)) return null;
  if($p){
    $t=str_repeat('s',count($p));
    $st->bind_param($t,...$p);
  }
  if(!$st->execute()) return null;
  $res=$st->get_result();
  return $res?$res->fetch_assoc():null;
}
function safe_date($s){
  $t=strtotime((string)$s);
  return $t?date('Y-m-d',$t):null;
}
function edad($f){
  if(!$f) return null;
  try{
    $b=new DateTime($f);
    return $b->diff(new DateTime())->y;
  }catch(Throwable $e){
    return null;
  }
}
function fmt_dmy(?string $ymd): string {
  if(!$ymd || $ymd==='0000-00-00') return '';
  $ts = strtotime($ymd);
  if(!$ts) return '';
  return date('d/m/Y', $ts);
}
function build_web_url($relPath){
  $base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
  return $base . '/' . ltrim(str_replace('\\','/',$relPath), '/');
}

/* ========= Tablas/columnas ========= */
$T_P='peleas_evento';
$T_C='competidores_evento';
$T_E='eventos_deportivos';
$T_X='ficha_pelea_extra';

$cp=colmap($conexion,$T_P);
$cc=colmap($conexion,$T_C);
$ce=colmap($conexion,$T_E);

$col_pelea_id  = pick(['id','id_pelea','pelea_id'],$cp);
$col_evento_fk = pick(['evento_id','id_evento'],$cp);
$col_orden     = pick(['orden','numero','nro_pelea','orden_pelea'],$cp);
$col_rojo_fk   = pick(['rojo_id','competidor_rojo_id','id_rojo'],$cp);
$col_azul_fk   = pick(['azul_id','competidor_azul_id','id_azul'],$cp);
$col_modalidad = pick(['modalidad','disciplina','reglamento'],$cp);
$col_categoria = pick(['categoria','categoria_peso','cat_peso'],$cp);
$col_fechahora = pick(['fecha_hora','f_hora','datetime','hora','fecha'],$cp);

$col_evento_id = pick(['id','evento_id'],$ce);
$col_evento_nom= pick(['titulo','nombre','evento'],$ce);

$colc_id       = pick(['id','id_competidor','competidor_id'],$cc);

if(!$col_pelea_id||!$col_evento_fk||!$col_rojo_fk||!$col_azul_fk||!$col_evento_id||!$col_evento_nom||!$colc_id){
  http_response_code(500);
  exit('❌ Esquema no reconocido (faltan columnas esperadas).');
}

/* ========= Parámetros ========= */
$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

/* ========= Resolver pelea_id ========= */
function resolve_pid(mysqli $db,$cp,$T_P){
  $col_pelea_id = pick(['id','id_pelea','pelea_id'],$cp);
  $col_evento_fk= pick(['evento_id','id_evento'],$cp);
  $col_orden    = pick(['orden','numero','nro_pelea','orden_pelea'],$cp);
  $pid = 0;

  if(isset($_GET['pelea_id']))      $pid=(int)$_GET['pelea_id'];
  elseif(isset($_GET['id']))        $pid=(int)$_GET['id'];

  if($pid>0) return $pid;

  $eid = isset($_GET['evento_id'])?(int)$_GET['evento_id']:0;
  $ord = isset($_GET['orden'])?trim($_GET['orden']):'';

  if($eid>0 && $col_evento_fk && $col_orden && $ord!==''){
    $row=fetch1($db,"SELECT ".bt($col_pelea_id)." pid FROM ".bt($T_P)." WHERE ".bt($col_evento_fk)."=? AND ".bt($col_orden)."=? LIMIT 1",[$eid,$ord]);
    if($row) return (int)$row['pid'];
  }

  return !empty($_SESSION['last_pelea_id'])?(int)$_SESSION['last_pelea_id']:0;
}
$pid=resolve_pid($conexion,$cp,$T_P);

if($pid<=0){
  // Selector evento→pelea
  header('Content-Type:text/html; charset=utf-8');
  $evs=[];
  if($col_evento_id && $col_evento_nom){
    $q="SELECT ".bt($col_evento_id)." id, ".bt($col_evento_nom)." nom FROM ".bt($T_E)." ORDER BY 1 DESC";
    if($r=$conexion->query($q)){
      while($row=$r->fetch_assoc()) $evs[]=$row;
    }
  }
  $evento_id=isset($_GET['evento_id'])?(int)$_GET['evento_id']:0;
  $peleas=[];
  if($evento_id>0){
    $q="SELECT * FROM ".bt($T_P)." WHERE ".bt($col_evento_fk)."=".(int)$evento_id." ORDER BY ".($col_orden?bt($col_orden):bt($col_pelea_id));
    if($r=$conexion->query($q)){
      while($row=$r->fetch_assoc()) $peleas[]=$row;
    }
  } ?>
  <!doctype html>
  <html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Elegir pelea</title>
    <style>
      body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px}
      select,button{padding:8px;font-size:16px}
    </style>
  </head>
  <body>
    <h2>Generar ficha médica (doble)</h2>
    <form method="get">
      <label>Evento:
        <select name="evento_id" onchange="this.form.submit()">
          <option value="">-- Elegí evento --</option>
          <?php foreach($evs as $ev){ ?>
            <option value="<?=$ev['id']?>" <?=$evento_id==$ev['id']?'selected':''?>><?=h($ev['nom'])?></option>
          <?php } ?>
        </select>
      </label>
      <?php if($evento_id>0){ ?><br><br>
        <label>Pelea:
          <select name="pelea_id">
            <?php foreach($peleas as $p){
              $ord=$col_orden?($p[$col_orden]??''):$p[$col_pelea_id];
            ?>
              <option value="<?=$p[$col_pelea_id]?>">#<?=$p[$col_pelea_id]?><?= $ord!==''?" · Orden ".$ord:'' ?></option>
            <?php } ?>
          </select>
        </label>
        <br><br>
        <button type="submit">Continuar</button>
      <?php } ?>
    </form>
  </body>
  </html>
  <?php
  exit;
}
$_SESSION['last_pelea_id']=$pid;

/* ========= Cargar pelea+evento ========= */
$sql="SELECT p.*, e.".bt($col_evento_nom)." AS ev_titulo
      FROM ".bt($T_P)." p
      LEFT JOIN ".bt($T_E)." e ON e.".bt($col_evento_id)."=p.".bt($col_evento_fk)."
      WHERE p.".bt($col_pelea_id)."=?";
$pelea=fetch1($conexion,$sql,[$pid]);
if(!$pelea){
  http_response_code(404);
  exit('❌ Pelea no encontrada');
}
$ev_titulo=$pelea['ev_titulo'] ?? 'Evento';
$orden = $col_orden?($pelea[$col_orden]??null):null;
$modal = $col_modalidad?($pelea[$col_modalidad]??null):null;
$categ = $col_categoria?($pelea[$col_categoria]??null):null;
$fecha = $col_fechahora? safe_date($pelea[$col_fechahora]??null): null;

/* ========= Competidores ========= */
$rid=(int)($pelea[$col_rojo_fk]??0);
$aid=(int)($pelea[$col_azul_fk]??0);

function get_comp(mysqli $db,$T_C,$cc,$id){
  if($id<=0) return null;
  $cid=pick(['id','id_competidor','competidor_id'],$cc);
  $row=fetch1($db,"SELECT * FROM ".bt($T_C)." WHERE ".bt($cid)."=?",[$id]);
  if(!$row) return null;

  $val=function($cands)use($row,$cc){
    $k=pick((array)$cands,$cc);
    return $k?($row[$k]??null):null;
  };

  $fn_raw = $val(['fecha_nac','fecha_nacimiento','nacimiento','fec_nac']);
  $fn     = safe_date($fn_raw);
  $ed     = edad($fn);

  // Localidad / Provincia (usar lo que haya)
  $loc = $val(['localidad','provincia','localidad_provincia','ciudad','poblacion']);

  // Profesor / Coach si existe
  $prof = $val(['profesor','coach','entrenador']);

  return [
    'apellido'  => ($val(['apellido','apellidos']) ?? ''),
    'nombre'    => ($val(['nombre','nombres']) ?? ''),
    'escuela'   => ($val(['escuela','academia','team','gimnasio']) ?? ''),
    'dni'       => ($val(['dni','documento','doc']) ?? ''),
    'sexo'      => ($val(['sexo','genero']) ?? ''),
    'nac'       => $fn,
    'edad'      => $ed,
    'peso'      => ($val(['peso','peso_kg','peso_oficial']) ?? ''),
    'catpeso'   => ($val(['categoria_peso','cat_peso','categoria']) ?? ''),
    'localidad' => $loc,
    'profesor'  => $prof,
  ];
}

$R=get_comp($conexion,$T_C,$cc,$rid);
$A=get_comp($conexion,$T_C,$cc,$aid);

$norm=function(&$c){
  if(!$c){
    $c=[
      'apellido'=>'-','nombre'=>'-','escuela'=>'-','dni'=>'-','sexo'=>'-',
      'nac'=>'-','edad'=>'-','peso'=>'-','catpeso'=>'-','localidad'=>'-','profesor'=>'-'
    ];
    return;
  }
  foreach($c as $k=>$v){
    if($v===null || $v==='') $c[$k]='-';
  }
};
$norm($R);
$norm($A);

/* ========= Extras (tabla JSON) ========= */
function x_load(mysqli $db,$T_X,$pid){
  $r=fetch1($db,"SELECT extras_json FROM ".bt($T_X)." WHERE pelea_id=?",[$pid]);
  $d=$r?json_decode($r['extras_json'],true):[];
  return is_array($d)?$d:[];
}
function x_save(mysqli $db,$T_X,$pid,$data){
  $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $sql="INSERT INTO ".bt($T_X)." (pelea_id,extras_json) VALUES (?,?) ON DUPLICATE KEY UPDATE extras_json=VALUES(extras_json)";
  if(!$st=$db->prepare($sql)) return false;
  $st->bind_param('is',$pid,$json);
  return $st->execute();
}

$X=x_load($conexion,$T_X,$pid);
$DEF=[
  'general'=>[
    'modalidad'=>$modal??'',
    'categoria'=>$categ??'',
    'fecha'=>$fecha??'',
  ],
  'r'=>[
    'apto'=>'','apto_fecha'=>'',
    'obs'=>'',
  ],
  'a'=>[
    'apto'=>'','apto_fecha'=>'',
    'obs'=>'',
  ],
];
$X=array_replace_recursive($DEF,$X);

/* ========= Editor simple (apto / obs) ========= */
if($edit===1){
  if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__save'])){
    $X['general']['modalidad']=trim($_POST['g_modalidad']??$X['general']['modalidad']);
    $X['general']['categoria']=trim($_POST['g_categoria']??$X['general']['categoria']);
    $X['general']['fecha']    =trim($_POST['g_fecha']??$X['general']['fecha']);

    $pull=function($prefix,$fields)use(&$X){
      foreach($fields as $f){
        $k=$prefix.'_'.$f;
        if(isset($_POST[$k])) $X[$prefix][$f]=trim($_POST[$k]);
      }
    };
    $pull('r',['apto','apto_fecha','obs']);
    $pull('a',['apto','apto_fecha','obs']);

    if(!x_save($conexion,$T_X,$pid,$X)){
      http_response_code(500);
      exit('❌ No se pudo guardar');
    }

    if(!headers_sent()){
      header("Location: generar_ficha_pelea.php?pelea_id=".$pid."&edit=1");
      exit;
    }
    echo "<script>location.href='generar_ficha_pelea.php?pelea_id=".$pid."&edit=1'</script>";
    exit;
  }

  header('Content-Type: text/html; charset=utf-8'); ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Editar datos médicos — #<?=h($pid)?></title>
    <style>
      body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px}
      fieldset{border:1px solid #000;padding:12px;margin:12px 0}
      legend{font-weight:700}
      .g{display:grid;gap:8px}
      .g2{grid-template-columns:1fr 1fr}
      label{font-size:13px;color:#333}
      input,textarea{font-size:15px;padding:8px;border:1px solid #bbb;border-radius:6px}
      .row{display:flex;flex-direction:column}
      .act{margin-top:12px;display:flex;gap:8px}
      .btn{padding:10px 14px;border:1px solid #000;background:#111;color:#fff;border-radius:8px;text-decoration:none}
      @media (max-width:900px){ .g2{grid-template-columns:1fr} }
    </style>
  </head>
  <body>
    <h2>Editar datos médicos — <?=h($ev_titulo)?> (Orden: <?=h($orden??'-')?>)</h2>
    <p>Competidor 1: <b><?=h($R['apellido'].' '.$R['nombre'])?></b><br>
       Competidor 2: <b><?=h($A['apellido'].' '.$A['nombre'])?></b></p>

    <form method="post">
      <fieldset>
        <legend>Generales del evento</legend>
        <div class="g g2">
          <div class="row">
            <label>Modalidad</label>
            <input name="g_modalidad" value="<?=h($X['general']['modalidad'])?>" placeholder="<?=h($modal??'')?>">
          </div>
          <div class="row">
            <label>Categoría</label>
            <input name="g_categoria" value="<?=h($X['general']['categoria'])?>" placeholder="<?=h($categ??'')?>">
          </div>
          <div class="row">
            <label>Fecha</label>
            <input name="g_fecha" type="date" value="<?=h($X['general']['fecha'])?>">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Competidor 1</legend>
        <p><b><?=h($R['apellido'].' '.$R['nombre'])?></b> — DNI: <?=h($R['dni'])?></p>
        <div class="g g2">
          <div class="row">
            <label>Apto</label>
            <input name="r_apto" value="<?=h($X['r']['apto'])?>">
          </div>
          <div class="row">
            <label>Fecha apto</label>
            <input name="r_apto_fecha" value="<?=h($X['r']['apto_fecha'])?>">
          </div>
          <div class="row" style="grid-column:1/-1">
            <label>Observación</label>
            <input name="r_obs" value="<?=h($X['r']['obs'])?>">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Competidor 2</legend>
        <p><b><?=h($A['apellido'].' '.$A['nombre'])?></b> — DNI: <?=h($A['dni'])?></p>
        <div class="g g2">
          <div class="row">
            <label>Apto</label>
            <input name="a_apto" value="<?=h($X['a']['apto'])?>">
          </div>
          <div class="row">
            <label>Fecha apto</label>
            <input name="a_apto_fecha" value="<?=h($X['a']['apto_fecha'])?>">
          </div>
          <div class="row" style="grid-column:1/-1">
            <label>Observación</label>
            <input name="a_obs" value="<?=h($X['a']['obs'])?>">
          </div>
        </div>
      </fieldset>

      <div class="act">
        <button class="btn" type="submit" name="__save" value="1">💾 Guardar</button>
        <a class="btn" target="_blank" href="generar_ficha_pelea.php?pelea_id=<?=h($pid)?>">🖨️ Ver ficha / imprimir</a>
      </div>
    </form>
  </body>
  </html>
  <?php
  exit;
}

/* ========= Dibujo sobre la plantilla (2 fichas) ========= */
function render_print_overlay($bg_url,$ev_titulo,$orden,$modal,$categ,$fecha,$R,$A,$X,$pid){
  $modal = $X['general']['modalidad'] ?: $modal;
  $fecha = $X['general']['fecha']     ?: $fecha;

  // Parámetros de calibración
  $escala = isset($_GET['escala']) ? max(80, min(120, (float)$_GET['escala'])) : 100;
  $dx     = isset($_GET['dx']) ? (float)$_GET['dx'] : 0;
  $dy     = isset($_GET['dy']) ? (float)$_GET['dy'] : 0;
  $fs     = isset($_GET['fs']) ? max(2.5, min(5.5,(float)$_GET['fs'])) : 3.6;
  $debug  = !empty($_GET['debug']);

  header('Content-Type:text/html; charset=utf-8'); ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ficha médica #<?=h($pid)?></title>
    <style>
      @page { size: A4 portrait; margin: 0; }
      @media print { .no-print { display:none } }
      html, body { margin:0; padding:0; background:#fff; }

      .sheet {
        position: relative;
        width: 210mm; height: 297mm;
        overflow: hidden;
        background-image: url('<?=h($bg_url)?>');
        background-repeat: no-repeat;
        background-position: 0 0;
        background-size: 210mm 297mm;
      }
      .overlay {
        position:absolute; left:0; top:0;
        width:210mm; height:297mm;
        transform-origin: 0 0;
        transform: translate(<?= $dx ?>mm, <?= $dy ?>mm) scale(<?= $escala/100 ?>);
      }
      .txt { position:absolute; font-family: Arial, Helvetica, sans-serif; font-size: <?= $fs ?>mm; line-height:1.2; color:#000; white-space:nowrap; }
      .wrap{ white-space:normal; max-width:90mm; }

      .btnbar{
        padding:10px;
        background:#f6f6f6;
        border-bottom:1px solid #ddd;
        font-family:system-ui,Segoe UI,Roboto,Arial
      }
      .btn{
        display:inline-block;
        padding:8px 12px;
        border:1px solid #333;
        border-radius:6px;
        text-decoration:none;
        color:#000;
        margin-right:8px;
        background:#fff
      }

      <?php if($debug){ ?>
      .grid::before {
        content:""; position:absolute; inset:0; pointer-events:none;
        background-image:
          linear-gradient(to right, rgba(0,0,255,.12) 1px, transparent 1px),
          linear-gradient(to bottom, rgba(0,0,255,.12) 1px, transparent 1px);
        background-size: 5mm 5mm, 5mm 5mm;
      }
      .rules > div {
        position:absolute;
        font: 9px/1.1 monospace;
        color:#d00;
        background:rgba(255,255,255,.6);
        padding:1px 3px;
      }
      <?php } ?>
    </style>
  </head>
  <body>
    <div class="no-print btnbar">
      <a class="btn" href="generar_ficha_pelea.php?pelea_id=<?=h($pid)?>&edit=1" target="_blank">✏️ Editar datos médicos</a>
      <a class="btn" href="#" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</a>
      <span>Escala: <?=h($escala)?>% · dx: <?=h($dx)?>mm · dy: <?=h($dy)?>mm · fuente: <?=h($fs)?>mm</span>
      <div style="margin-top:6px">
        <small>Tips: <code>?escala=98</code>, <code>?dx=2&dy=1</code>, <code>?fs=3.4</code>, <code>?debug=1</code>.</small>
      </div>
    </div>

    <div class="sheet grid">
      <div class="overlay">
        <?php
        $put = function($x_mm,$y_mm,$txt) {
          echo '<div class="txt" style="left:'.$x_mm.'mm; top:'.$y_mm.'mm">'.h($txt).'</div>';
        };

        // --- función que dibuja UNA ficha (arriba o abajo) ---
        $drawFicha = function($offsetY, $C, $modal_use, $X_side) use ($put){
          // MODALIDAD (campo grande arriba a la izquierda)
          $put(33, $offsetY + 18, $modal_use?:'-');

          // Datos personales (alineados con la planilla)
          $put(33, $offsetY + 32, $C['dni']);                                     // DNI
          $put(60, $offsetY + 38, $C['apellido'].' '.$C['nombre']);              // Apellido y Nombre
          $put(75, $offsetY + 44, fmt_dmy($C['nac']));                           // Fecha de Nacimiento
          $put(33, $offsetY + 50, strtoupper(substr($C['sexo'],0,1)));           // Sexo M/F
          $put(80, $offsetY + 50, ($C['edad']!==null && $C['edad']!=='-')?$C['edad']:''); // Edad
          $put(65, $offsetY + 56, $C['localidad']?:'-');                         // Localidad / Provincia
          $put(80, $offsetY + 62, $C['escuela']?:'-');                           // Institución / Club o Gimnasio
          $put(70, $offsetY + 68, $C['profesor']?:'-');                          // Profesor o Coach

          // Control médico: APTO y PESO (el resto lo completa el médico)
          if(!empty($X_side['apto'])){
            $put(145, $offsetY + 32, $X_side['apto']);                           // cerca de APTO
          }
          if(!empty($X_side['apto_fecha'])){
            $put(145, $offsetY + 38, $X_side['apto_fecha']);                     // si querés usar
          }
          if(!empty($X_side['obs'])){
            $put(145, $offsetY + 80, $X_side['obs']);                            // obs. médica texto corto
          }

          // Peso del competidor en el cuadro derecho (PESO)
          if($C['peso'] && $C['peso']!=='-'){
            $put(170, $offsetY + 72, $C['peso'].' kg');                          // campo PESO
          }
        };

        // Ficha 1 (arriba) → competidor ROJO
        $drawFicha(0,   $R, $modal, $X['r']);

        // Ficha 2 (abajo) → competidor AZUL (desplazo ~145 mm hacia abajo)
        $drawFicha(145, $A, $modal, $X['a']);

        if($debug){ ?>
          <div class="rules">
            <div style="left:0;top:0">0,0</div>
            <div style="left:50mm;top:0">50mm</div>
            <div style="left:100mm;top:0">100mm</div>
            <div style="left:150mm;top:0">150mm</div>
            <div style="left:0;top:100mm">100mm</div>
            <div style="left:0;top:200mm">200mm</div>
          </div>
        <?php } ?>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* ========= Detección/generación de imagen de fondo ========= */
$bg_candidates = [
  'assets/img/ficha_competidor_doble_2022.png',
  'assets/img/ficha competidor-doble-2022.png',
  'assets/img/ficha_competidor_doble_2022.jpg',
  'assets/img/ficha competidor-doble-2022.jpg',
];
$pdf_candidates = [
  'assets/pdf/ficha_competidor_doble_2022.pdf',
  'assets/pdf/ficha competidor-doble-2022.pdf',
  'assets/pdf/ficha competidor-doble-2022 (1).pdf',
  'assets/pdf/ficha competidor-doble-2022 (1) (1).pdf',
];

$bg_found = null;
$probed   = [];

foreach ($bg_candidates as $rel) {
  $fs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/','\\'], DIRECTORY_SEPARATOR, $rel);
  $ok = is_file($fs);
  $probed[] = ['IMG', $rel, $fs, $ok];
  if ($ok) { $bg_found = $rel; break; }
}

/* Si no hay imagen, intentar generarla desde PDF */
$gen_errors = [];
if (!$bg_found) {
  $pdf_path = null;
  foreach ($pdf_candidates as $rel) {
    $fs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/','\\'], DIRECTORY_SEPARATOR, $rel);
    $ok = is_file($fs);
    $probed[] = ['PDF', $rel, $fs, $ok];
    if ($ok) { $pdf_path = $fs; break; }
  }
  if ($pdf_path) {
    $out_rel = 'assets/img/ficha_competidor_doble_2022.png';
    $out_fs  = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/','\\'], DIRECTORY_SEPARATOR, $out_rel);

    if (extension_loaded('imagick')) {
      try {
        $im = new Imagick();
        $im->setResolution(200, 200);
        $im->readImage($pdf_path."[0]");
        $im->setImageFormat('png');
        $im->setImageBackgroundColor('white');
        $im = $im->flattenImages();
        @mkdir(dirname($out_fs), 0777, true);
        $im->writeImage($out_fs);
        $im->clear(); $im->destroy();
        if (is_file($out_fs)) { $bg_found = $out_rel; }
      } catch (Throwable $e) {
        $gen_errors[] = "Imagick error: ".$e->getMessage();
      }
    }

    if (!$bg_found) {
      $cmds = [
        '"C:\Program Files\ImageMagick-7.1.1-Q16-HDRI\magick.exe" -density 200 "%IN%"[0] -quality 100 -alpha remove -alpha off "%OUT%"',
        '"C:\Program Files\ImageMagick-7.1.1-Q16\magick.exe" -density 200 "%IN%"[0] -quality 100 -alpha remove -alpha off "%OUT%"',
        '"C:\Program Files\ImageMagick-6.9.3-Q16\convert.exe" -density 200 "%IN%"[0] -quality 100 -alpha remove -alpha off "%OUT%"',
        'magick -density 200 "%IN%"[0] -quality 100 -alpha remove -alpha off "%OUT%"',
      ];
      @mkdir(dirname($out_fs), 0777, true);
      foreach ($cmds as $raw) {
        $cmd = str_replace(['%IN%','%OUT%'], [$pdf_path,$out_fs], $raw);
        $out = @shell_exec($cmd.' 2>&1');
        if (is_file($out_fs) && filesize($out_fs) > 0) { $bg_found = $out_rel; break; }
        $gen_errors[] = "CMD tried: $cmd\n".trim((string)$out);
      }
    }
  }
}

/* Si ya tenemos imagen, render normal */
if ($bg_found) {
  $bg_url = build_web_url($bg_found);
  render_print_overlay($bg_url,$ev_titulo,$orden,$modal,$categ,$fecha,$R,$A,$X,$pid);
  exit;
}

/* === Sin fondo: versión simple (por si falta el PDF) === */
header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Falta imagen de ficha</title>
  <style>
    body{font:14px/1.45 system-ui,Segoe UI,Roboto,Arial;margin:24px}
    pre{background:#fff8f8;border:1px solid #e7b6b6;padding:10px;border-radius:8px;white-space:pre-wrap}
    code{background:#f1f1f1;padding:2px 6px;border-radius:4px}
    a.btn{display:inline-block;padding:8px 12px;border:1px solid #333;border-radius:6px;color:#000;text-decoration:none;background:#fff;margin-right:8px}
  </style>
</head>
<body>
  <h2>⚠️ No se pudo obtener la imagen de la ficha</h2>
  <p>Poné el PDF de la planilla en <code>assets/pdf/</code> (por ejemplo: <code>assets/pdf/ficha competidor-doble-2022.pdf</code>) y actualizá la página.</p>
  <pre><?php
  foreach($probed as $row){
    [$type,$rel,$fs,$ok] = $row;
    echo sprintf("[%s] %s => %s  %s\n", $type, $rel, $fs, $ok?'[OK]':'[NO]');
  }
  if ($gen_errors) {
    echo "\n-- Generación desde PDF --\n";
    echo implode("\n\n", $gen_errors);
  }
  ?></pre>
  <a class="btn" href="generar_ficha_pelea.php?pelea_id=<?=h($pid)?>">🔁 Reintentar</a>
</body>
</html>
<?php
exit;
