<?php
/* ============================================================
   generar_ficha_pelea.php — SIN LIBRERÍAS (HTML + fondo imagen)
   - Busca imagen de plantilla (varios nombres). Si no existe:
     * Genera PNG automáticamente desde el PDF (Imagick o ImageMagick)
   - Escribe sobre la ficha y permite imprimir/guardar a PDF
   - Editor de extras (médico/escuela/entrenador, etc.) en JSON
   - Resolver por ?pelea_id= o ?evento_id+orden
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
function colmap(mysqli $db, string $t){ $m=[]; if($r=$db->query("SHOW COLUMNS FROM ".bt($t))){ while($x=$r->fetch_assoc()){ $m[strtolower($x['Field'])]=$x['Field']; } } return $m; }
function pick($cands,$pool){ foreach((array)$cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; }
function fetch1(mysqli $db,$sql,$p=[]){ if(!$st=$db->prepare($sql)) return null; if($p){ $t=str_repeat('s',count($p)); $st->bind_param($t,...$p); } if(!$st->execute()) return null; $res=$st->get_result(); return $res?$res->fetch_assoc():null; }
function safe_date($s){ $t=strtotime((string)$s); return $t?date('Y-m-d',$t):null; }
function edad($f){ if(!$f) return null; $b=new DateTime($f); return $b->diff(new DateTime())->y; }
function build_web_url($relPath){
  // arma URL web relativa desde este script (soporta subcarpeta)
  $base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
  return $base . '/' . ltrim(str_replace('\\','/',$relPath), '/');
}

/* ========= Tablas/columnas ========= */
$T_P='peleas_evento'; $T_C='competidores_evento'; $T_E='eventos_deportivos'; $T_X='ficha_pelea_extra';
$cp=colmap($conexion,$T_P); $cc=colmap($conexion,$T_C); $ce=colmap($conexion,$T_E);

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
  http_response_code(500); exit('❌ Esquema no reconocido (faltan columnas esperadas).');
}

/* ========= Parámetros ========= */
$edit    = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

/* ========= Resolver pelea_id ========= */
function resolve_pid(mysqli $db,$cp,$T_P){
  $col_pelea_id=pick(['id','id_pelea','pelea_id'],$cp);
  $col_evento_fk=pick(['evento_id','id_evento'],$cp);
  $col_orden=pick(['orden','numero','nro_pelea','orden_pelea'],$cp);
  $pid=0;
  if(isset($_GET['pelea_id'])) $pid=(int)$_GET['pelea_id']; elseif(isset($_GET['id'])) $pid=(int)$_GET['id'];
  if($pid>0) return $pid;
  $eid=isset($_GET['evento_id'])?(int)$_GET['evento_id']:0; $ord=isset($_GET['orden'])?trim($_GET['orden']):'';
  if($eid>0 && $col_evento_fk && $col_orden && $ord!==''){
    $row=fetch1($db,"SELECT ".bt($col_pelea_id)." pid FROM ".bt($T_P)." WHERE ".bt($col_evento_fk)."=? AND ".bt($col_orden)."=? LIMIT 1",[$eid,$ord]);
    if($row) return (int)$row['pid'];
  }
  return !empty($_SESSION['last_pelea_id'])?(int)$_SESSION['last_pelea_id']:0;
}
$pid=resolve_pid($conexion,$cp,$T_P);
if($pid<=0){
  // Selector simple evento→pelea
  header('Content-Type:text/html; charset=utf-8');
  $evs=[]; if($col_evento_id && $col_evento_nom){
    $q="SELECT ".bt($col_evento_id)." id, ".bt($col_evento_nom)." nom FROM ".bt($T_E)." ORDER BY 1 DESC";
    if($r=$conexion->query($q)){ while($row=$r->fetch_assoc()) $evs[]=$row; }
  }
  $evento_id=isset($_GET['evento_id'])?(int)$_GET['evento_id']:0; $peleas=[];
  if($evento_id>0){
    $q="SELECT * FROM ".bt($T_P)." WHERE ".bt($col_evento_fk)."=".(int)$evento_id." ORDER BY ".($col_orden?bt($col_orden):bt($col_pelea_id));
    if($r=$conexion->query($q)){ while($row=$r->fetch_assoc()) $peleas[]=$row; }
  } ?>
  <!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Elegir pelea</title>
  <style>body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px} select,button{padding:8px;font-size:16px}</style></head><body>
  <h2>Generar ficha (sin librerías)</h2>
  <form method="get">
    <label>Evento:
      <select name="evento_id" onchange="this.form.submit()">
        <option value="">-- Elegí evento --</option>
        <?php foreach($evs as $ev){ ?><option value="<?=$ev['id']?>" <?=$evento_id==$ev['id']?'selected':''?>><?=h($ev['nom'])?></option><?php } ?>
      </select>
    </label>
    <?php if($evento_id>0){ ?><br><br>
      <label>Pelea:
        <select name="pelea_id">
          <?php foreach($peleas as $p){ $ord=$col_orden?($p[$col_orden]??''):$p[$col_pelea_id]; ?>
            <option value="<?=$p[$col_pelea_id]?>">#<?=$p[$col_pelea_id]?><?= $ord!==''?" · Orden ".$ord:'' ?></option>
          <?php } ?>
        </select>
      </label>
      <br><br><button type="submit">Continuar</button>
    <?php } ?>
  </form></body></html><?php
  exit;
}
$_SESSION['last_pelea_id']=$pid;

/* ========= Cargar pelea+evento ========= */
$sql="SELECT p.*, e.".bt($col_evento_nom)." AS ev_titulo
      FROM ".bt($T_P)." p
      LEFT JOIN ".bt($T_E)." e ON e.".bt($col_evento_id)."=p.".bt($col_evento_fk)."
      WHERE p.".bt($col_pelea_id)."=?";
$pelea=fetch1($conexion,$sql,[$pid]); if(!$pelea){ http_response_code(404); exit('❌ Pelea no encontrada'); }
$ev_titulo=$pelea['ev_titulo'] ?? 'Evento';
$orden = $col_orden?($pelea[$col_orden]??null):null;
$modal = $col_modalidad?($pelea[$col_modalidad]??null):null;
$categ = $col_categoria?($pelea[$col_categoria]??null):null;
$fecha = $col_fechahora? safe_date($pelea[$col_fechahora]??null): null;

/* ========= Competidores ========= */
$rid=(int)($pelea[$col_rojo_fk]??0); $aid=(int)($pelea[$col_azul_fk]??0);
function get_comp(mysqli $db,$T_C,$cc,$id){
  if($id<=0) return null; $cid=pick(['id','id_competidor','competidor_id'],$cc);
  $row=fetch1($db,"SELECT * FROM ".bt($T_C)." WHERE ".bt($cid)."=?",[$id]); if(!$row) return null;
  $val=function($cands)use($row,$cc){ $k=pick((array)$cands,$cc); return $k?($row[$k]??null):null; };
  $fn=$val(['fecha_nac','fecha_nacimiento','nacimiento']); $ed=edad(safe_date($fn));
  return [
    'apellido' => ($val(['apellido','apellidos']) ?? ''),
    'nombre'   => ($val(['nombre','nombres']) ?? ''),
    'escuela'  => ($val(['escuela','academia','team','gimnasio']) ?? ''),
    'dni'      => ($val(['dni','documento','doc']) ?? ''),
    'sexo'     => ($val(['sexo','genero']) ?? ''),
    'nac'      => $fn, 'edad'=>$ed,
    'peso'     => ($val(['peso','peso_kg']) ?? ''),
    'altura'   => ($val(['altura','estatura','talla']) ?? ''),
    'catpeso'  => ($val(['categoria_peso','cat_peso']) ?? ''),
  ];
}
$R=get_comp($conexion,$T_C,$cc,$rid);
$A=get_comp($conexion,$T_C,$cc,$aid);
$norm=function(&$c){ if(!$c){$c=['apellido'=>'-','nombre'=>'-','escuela'=>'-','dni'=>'-','sexo'=>'-','nac'=>'-','edad'=>'-','peso'=>'-','altura'=>'-','catpeso'=>'-']; return;}
  foreach($c as $k=>$v){ if($v===null||$v==='') $c[$k]='-'; } };
$norm($R); $norm($A);

/* ========= Extras (tabla JSON) =========
CREATE TABLE IF NOT EXISTS ficha_pelea_extra (
  pelea_id INT NOT NULL PRIMARY KEY,
  extras_json LONGTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
function x_load(mysqli $db,$T_X,$pid){ $r=fetch1($db,"SELECT extras_json FROM ".bt($T_X)." WHERE pelea_id=?",[$pid]); $d=$r?json_decode($r['extras_json'],true):[]; return is_array($d)?$d:[]; }
function x_save(mysqli $db,$T_X,$pid,$data){ $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $sql="INSERT INTO ".bt($T_X)." (pelea_id,extras_json) VALUES (?,?) ON DUPLICATE KEY UPDATE extras_json=VALUES(extras_json)";
  if(!$st=$db->prepare($sql)) return false; $st->bind_param('is',$pid,$json); return $st->execute(); }
$X=x_load($conexion,$T_X,$pid);
/* defaults + merge */
$DEF=['general'=>['modalidad'=>$modal??'','categoria'=>$categ??'','fecha'=>$fecha??''],
      'r'=>['escuela'=>$R['escuela'],'entrenador'=>'','contacto'=>'','seguro'=>'','seguro_nro'=>'','apto'=>'','apto_fecha'=>'',
            'antecedentes'=>'','alergias'=>'','lesiones'=>'','medicacion'=>'','medico'=>'','matricula'=>'','obs'=>''],
      'a'=>['escuela'=>$A['escuela'],'entrenador'=>'','contacto'=>'','seguro'=>'','seguro_nro'=>'','apto'=>'','apto_fecha'=>'',
            'antecedentes'=>'','alergias'=>'','lesiones'=>'','medicacion'=>'','medico'=>'','matricula'=>'','obs'=>''],];
$X=array_replace_recursive($DEF,$X);

/* ========= Editor ========= */
if( $edit===1 ){
  if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__save'])){
    $X['general']['modalidad']=trim($_POST['g_modalidad']??$X['general']['modalidad']);
    $X['general']['categoria']=trim($_POST['g_categoria']??$X['general']['categoria']);
    $X['general']['fecha']    =trim($_POST['g_fecha']??$X['general']['fecha']);
    $pull=function($prefix,$fields)use(&$X){ foreach($fields as $f){ $k=$prefix.'_'.$f; if(isset($_POST[$k])) $X[$prefix][$f]=trim($_POST[$k]); } };
    $pull('r',['escuela','entrenador','contacto','seguro','seguro_nro','apto','apto_fecha','antecedentes','alergias','lesiones','medicacion','medico','matricula','obs']);
    $pull('a',['escuela','entrenador','contacto','seguro','seguro_nro','apto','apto_fecha','antecedentes','alergias','lesiones','medicacion','medico','matricula','obs']);
    if(!x_save($conexion,$T_X,$pid,$X)){ http_response_code(500); exit('❌ No se pudo guardar'); }
    if(!headers_sent()){ header("Location: generar_ficha_pelea.php?pelea_id=".$pid."&edit=1"); exit; }
    echo "<script>location.href='generar_ficha_pelea.php?pelea_id=".$pid."&edit=1'</script>"; exit;
  }
  header('Content-Type: text/html; charset=utf-8'); ?>
  <!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Editar ficha — #<?=h($pid)?></title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px}
    fieldset{border:1px solid #000;padding:12px;margin:12px 0} legend{font-weight:700}
    .g{display:grid;gap:8px} .g2{grid-template-columns:1fr 1fr} .g3{grid-template-columns:repeat(3,1fr)}
    label{font-size:13px;color:#333} input,textarea{font-size:15px;padding:8px;border:1px solid #bbb;border-radius:6px}
    textarea{min-height:60px} .row{display:flex;flex-direction:column}
    .act{margin-top:12px;display:flex;gap:8px} .btn{padding:10px 14px;border:1px solid #000;background:#111;color:#fff;border-radius:8px;text-decoration:none}
    @media (max-width:900px){ .g2,.g3{grid-template-columns:1fr} }
  </style></head><body>
  <h2>Editar ficha — <?=h($ev_titulo)?> (Orden: <?=h($orden??'-')?>)</h2>
  <form method="post">
    <fieldset><legend>Generales</legend>
      <div class="g g3">
        <div class="row"><label>Modalidad</label><input name="g_modalidad" value="<?=h($X['general']['modalidad'])?>" placeholder="<?=h($modal??'')?>"></div>
        <div class="row"><label>Categoría</label><input name="g_categoria" value="<?=h($X['general']['categoria'])?>" placeholder="<?=h($categ??'')?>"></div>
        <div class="row"><label>Fecha</label><input name="g_fecha" type="date" value="<?=h($X['general']['fecha'])?>"></div>
      </div>
    </fieldset>
    <fieldset><legend>Esquina ROJA</legend>
      <div class="g g2">
        <?php $F=['escuela','entrenador','contacto','seguro','seguro_nro','apto','apto_fecha','medico','matricula'];
          foreach($F as $f){ ?><div class="row"><label><?=h(ucwords(str_replace('_',' ',$f)))?></label><input name="r_<?=$f?>" value="<?=h($X['r'][$f])?>"></div><?php } ?>
        <?php $T=['antecedentes','alergias','lesiones','medicacion','obs'];
          foreach($T as $f){ ?><div class="row" style="grid-column:1/-1"><label><?=h(ucwords($f))?></label><textarea name="r_<?=$f?>"><?=h($X['r'][$f])?></textarea></div><?php } ?>
      </div>
    </fieldset>
    <fieldset><legend>Esquina AZUL</legend>
      <div class="g g2">
        <?php $F=['escuela','entrenador','contacto','seguro','seguro_nro','apto','apto_fecha','medico','matricula'];
          foreach($F as $f){ ?><div class="row"><label><?=h(ucwords(str_replace('_',' ',$f)))?></label><input name="a_<?=$f?>" value="<?=h($X['a'][$f])?>"></div><?php } ?>
        <?php $T=['antecedentes','alergias','lesiones','medicacion','obs'];
          foreach($T as $f){ ?><div class="row" style="grid-column:1/-1"><label><?=h(ucwords($f))?></label><textarea name="a_<?=$f?>"><?=h($X['a'][$f])?></textarea></div><?php } ?>
      </div>
    </fieldset>
    <div class="act">
      <button class="btn" type="submit" name="__save" value="1">💾 Guardar</button>
      <a class="btn" target="_blank" href="generar_ficha_pelea.php?pelea_id=<?=h($pid)?>">🖨️ Ver ficha/imprimir</a>
    </div>
  </form></body></html><?php
  exit;
}

/* ========= Overlay con calibración (escala/dx/dy/fs) ========= */
function render_print_overlay($bg_url,$ev_titulo,$orden,$modal,$categ,$fecha,$R,$A,$X,$pid){
  // Overrides desde extras
  $modal = $X['general']['modalidad'] ?: $modal;
  $categ = $X['general']['categoria'] ?: $categ;
  $fecha = $X['general']['fecha']     ?: $fecha;

  // Parámetros de calibración (mm y %)
  $escala = isset($_GET['escala']) ? max(80, min(120, (float)$_GET['escala'])) : 100; // 80–120%
  $dx     = isset($_GET['dx']) ? (float)$_GET['dx'] : 0;   // desplazamiento X mm
  $dy     = isset($_GET['dy']) ? (float)$_GET['dy'] : 0;   // desplazamiento Y mm
  $fs     = isset($_GET['fs']) ? max(2.5, min(5.5,(float)$_GET['fs'])) : 3.6; // tamaño fuente mm
  $debug  = !empty($_GET['debug']);

  header('Content-Type:text/html; charset=utf-8'); ?>
  <!doctype html><html lang="es"><head><meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ficha pelea #<?=h($pid)?></title>
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
    .bold{ font-weight:700; }
    .wrap{ white-space:normal; max-width:85mm; }

    .btnbar{ padding:10px; background:#f6f6f6; border-bottom:1px solid #ddd; font-family:system-ui,Segoe UI,Roboto,Arial }
    .btn{ display:inline-block; padding:8px 12px; border:1px solid #333; border-radius:6px; text-decoration:none; color:#000; margin-right:8px; background:#fff }

    <?php if($debug){ ?>
    .grid::before {
      content:""; position:absolute; inset:0; pointer-events:none;
      background-image:
        linear-gradient(to right, rgba(0,0,255,.12) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(0,0,255,.12) 1px, transparent 1px);
      background-size: 5mm 5mm, 5mm 5mm;
    }
    .rules > div { position:absolute; font: 9px/1.1 monospace; color:#d00; background:rgba(255,255,255,.6); padding:1px 3px; }
    <?php } ?>
  </style></head><body>
  <div class="no-print btnbar">
    <a class="btn" href="generar_ficha_pelea.php?pelea_id=<?=h($pid)?>&edit=1" target="_blank">✏️ Editar extras</a>
    <a class="btn" href="#" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</a>
    <span>Escala: <?=h($escala)?>% · dx: <?=h($dx)?>mm · dy: <?=h($dy)?>mm · fuente: <?=h($fs)?>mm</span>
    <div style="margin-top:6px">
      <small>Tips: <code>?escala=98</code>, <code>?dx=2&dy=1</code>, <code>?fs=3.4</code>, <code>?debug=1</code>.</small>
    </div>
  </div>

  <div class="sheet grid">
    <div class="overlay">
      <?php
      $put = function($x_mm,$y_mm,$txt,$cls='') {
        echo '<div class="txt '.$cls.'" style="left:'.$x_mm.'mm; top:'.$y_mm.'mm">'.h($txt).'</div>';
      };
      $putw = function($x_mm,$y_mm,$w_mm,$txt) {
        echo '<div class="txt wrap" style="left:'.$x_mm.'mm; top:'.$y_mm.'mm; max-width:'.$w_mm.'mm">'.h($txt).'</div>';
      };

      /* ===== Cabecera ===== */
      $put(10,10,$ev_titulo,'bold');
      $put(160,10,'Orden: '.($orden?:'-'));
      $put(10,18, 'Modalidad: '.(($X['general']['modalidad']?:$modal?:'-')));
      $put(105,18,'Categoría: '.(($X['general']['categoria']?:$categ?:'-')));
      $put(170,18,'Fecha: '.(($X['general']['fecha']?:$fecha?:'-')));

      /* ===== Izquierda (ROJA) ===== */
      $put (18,34,  ($R['apellido'].' '.$R['nombre']), 'bold');
      $put (18,41,  'Escuela: '.(($X['r']['escuela']?:$R['escuela'])?:'-'));
      $put (18,47,  'Entrenador: '.($X['r']['entrenador']?:'-'));
      $put (18,53,  'DNI: '.($R['dni']?:'-'));
      $put (18,59,  'Contacto: '.($X['r']['contacto']?:'-'));
      $put (18,65,  'Seguro: '.($X['r']['seguro']?:'-'));
      $put (18,71,  'N°: '.($X['r']['seguro_nro']?:'-'));
      $put (18,77,  'Apto: '.($X['r']['apto']?:'-'));
      $put (18,83,  'Fecha apto: '.($X['r']['apto_fecha']?:'-'));
      $put (18,89,  'Médico: '.($X['r']['medico']?:'-'));
      $put (18,95,  'Matrícula: '.($X['r']['matricula']?:'-'));
      $put (18,101, 'Antecedentes:'); $putw(18,106, 85, $X['r']['antecedentes']?:'-');
      $put (18,118, 'Alergias:');     $putw(18,123, 85, $X['r']['alergias']?:'-');
      $put (18,135, 'Lesiones:');     $putw(18,140, 85, $X['r']['lesiones']?:'-');
      $put (18,152, 'Medicación:');   $putw(18,157, 85, $X['r']['medicacion']?:'-');
      $put (18,169, 'Obs.:');         $putw(18,174, 85, $X['r']['obs']?:'-');

      /* ===== Derecha (AZUL) ===== */
      $put (120,34,  ($A['apellido'].' '.$A['nombre']), 'bold');
      $put (120,41,  'Escuela: '.(($X['a']['escuela']?:$A['escuela'])?:'-'));
      $put (120,47,  'Entrenador: '.($X['a']['entrenador']?:'-'));
      $put (120,53,  'DNI: '.($A['dni']?:'-'));
      $put (120,59,  'Contacto: '.($X['a']['contacto']?:'-'));
      $put (120,65,  'Seguro: '.($X['a']['seguro']?:'-'));
      $put (120,71,  'N°: '.($X['a']['seguro_nro']?:'-'));
      $put (120,77,  'Apto: '.($X['a']['apto']?:'-'));
      $put (120,83,  'Fecha apto: '.($X['a']['apto_fecha']?:'-'));
      $put (120,89,  'Médico: '.($X['a']['medico']?:'-'));
      $put (120,95,  'Matrícula: '.($X['a']['matricula']?:'-'));
      $put (120,101, 'Antecedentes:'); $putw(120,106, 85, $X['a']['antecedentes']?:'-');
      $put (120,118, 'Alergias:');     $putw(120,123, 85, $X['a']['alergias']?:'-');
      $put (120,135, 'Lesiones:');     $putw(120,140, 85, $X['a']['lesiones']?:'-');
      $put (120,152, 'Medicación:');   $putw(120,157, 85, $X['a']['medicacion']?:'-');
      $put (120,169, 'Obs.:');         $putw(120,174, 85, $X['a']['obs']?:'-');
      ?>

      <?php if($debug){ ?>
      <div class="rules">
        <div style="left:0;top:0">0,0</div><div style="left:50mm;top:0">50mm</div><div style="left:100mm;top:0">100mm</div><div style="left:150mm;top:0">150mm</div>
        <div style="left:0;top:50mm">50mm</div><div style="left:0;top:100mm">100mm</div><div style="left:0;top:150mm">150mm</div><div style="left:0;top:200mm">200mm</div>
      </div>
      <?php } ?>
    </div>
  </div>
  </body></html><?php
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

$bg_found = null; $probed = [];
foreach ($bg_candidates as $rel) {
  $fs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/','\\'], DIRECTORY_SEPARATOR, $rel);
  $ok = is_file($fs);
  $probed[] = ['IMG', $rel, $fs, $ok];
  if ($ok) { $bg_found = $rel; break; }
}

/* Si no hay imagen, intentar generarla desde PDF */
$gen_errors = [];
if (!$bg_found) {
  // Hallar PDF
  $pdf_path = null;
  foreach ($pdf_candidates as $rel) {
    $fs = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/','\\'], DIRECTORY_SEPARATOR, $rel);
    $ok = is_file($fs);
    $probed[] = ['PDF', $rel, $fs, $ok];
    if ($ok) { $pdf_path = $fs; break; }
  }
  if ($pdf_path) {
    // Salida deseada
    $out_rel = 'assets/img/ficha_competidor_doble_2022.png';
    $out_fs  = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/','\\'], DIRECTORY_SEPARATOR, $out_rel);

    // 1) Imagick (PHP) si está
    if (extension_loaded('imagick')) {
      try {
        $im = new Imagick();
        $im->setResolution(200, 200);         // 200 dpi -> buena calidad
        $im->readImage($pdf_path."[0]");      // primera página
        $im->setImageFormat('png');
        $im->setImageBackgroundColor('white');
        $im = $im->flattenImages();           // por si hay transparencia
        // Asegurar carpeta
        @mkdir(dirname($out_fs), 0777, true);
        $im->writeImage($out_fs);
        $im->clear(); $im->destroy();
        if (is_file($out_fs)) { $bg_found = $out_rel; }
      } catch (Throwable $e) {
        $gen_errors[] = "Imagick error: ".$e->getMessage();
      }
    }

    // 2) ImageMagick por consola (magick/convert) si aún no
    if (!$bg_found) {
      $cmds = [
        // Instalación típica moderna
        '"C:\Program Files\ImageMagick-7.1.1-Q16-HDRI\magick.exe" -density 200 "%IN%"[0] -quality 100 -alpha remove -alpha off "%OUT%"',
        // Otra ruta común
        '"C:\Program Files\ImageMagick-7.1.1-Q16\magick.exe" -density 200 "%IN%"[0] -quality 100 -alpha remove -alpha off "%OUT%"',
        // convert.exe (versiones viejas)
        '"C:\Program Files\ImageMagick-6.9.3-Q16\convert.exe" -density 200 "%IN%"[0] -quality 100 -alpha remove -alpha off "%OUT%"',
        // Si está en PATH (magick global)
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

/* === No hay imagen ni se pudo generar: diagnóstico claro === */
header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Falta imagen de ficha</title>
<style>
  body{font:14px/1.45 system-ui,Segoe UI,Roboto,Arial;margin:24px}
  pre{background:#fff8f8;border:1px solid #e7b6b6;padding:10px;border-radius:8px;white-space:pre-wrap}
  code{background:#f1f1f1;padding:2px 6px;border-radius:4px}
  a.btn{display:inline-block;padding:8px 12px;border:1px solid #333;border-radius:6px;color:#000;text-decoration:none;background:#fff;margin-right:8px}
  ul{margin-top:6px}
</style></head><body>
<h2>⚠️ No se pudo obtener la imagen de la ficha</h2>
<p>Poné el PDF de la planilla en <code>assets/pdf/</code> (por ejemplo: <code>assets/pdf/ficha competidor-doble-2022.pdf</code>) y actualizá la página. El sistema intentará generar la imagen automáticamente.</p>

<h3>Qué se probó</h3>
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

<div style="margin-top:12px">
  <a class="btn" href="generar_ficha_pelea.php?pelea_id=<?=h($pid)?>">🔁 Reintentar</a>
  <a class="btn" href="generar_ficha_pelea.php?pelea_id=<?=h($pid)?>&edit=1" target="_blank">✏️ Editar extras</a>
</div>

<hr>
<p>Mientras tanto, podés imprimir esta versión sin fondo:</p>
<?php
function render_html_simple_inline($ev_titulo,$orden,$modal,$categ,$fecha,$R,$A,$X,$pid){
  $modal = $X['general']['modalidad'] ?: $modal;
  $categ = $X['general']['categoria'] ?: $categ;
  $fecha = $X['general']['fecha']     ?: $fecha; ?>
  <div style="border:1px solid #000;padding:12px">
    <div style="display:flex;justify-content:space-between;border-bottom:2px solid #000;padding-bottom:6px">
      <div><b><?=h($ev_titulo)?></b></div><div>Orden: <b><?=h($orden??'-')?></b></div>
    </div>
    <div><?=h($modal?:'-')?> · <?=h($categ?:'-')?> · <?=h($fecha?:'-')?></div>
    <div style="display:flex;gap:12px;margin-top:12px">
      <?php $S=[['ROJA',$R,$X['r']],['AZUL',$A,$X['a']]];
      foreach($S as [$side,$C,$E]){ ?>
      <div style="width:48%;border:1px solid #000;padding:10px">
        <b>Esquina <?=h($side)?></b>
        <?php $pairs=[
          ['Apellido y Nombre',$C['apellido'].' '.$C['nombre']],
          ['Escuela/Academia',$E['escuela']?:$C['escuela']],
          ['Entrenador',$E['entrenador']],['DNI',$C['dni']],['Contacto',$E['contacto']],
          ['Seguro',$E['seguro']],['N°',$E['seguro_nro']],['Apto',$E['apto']],['Fecha apto',$E['apto_fecha']],
          ['Médico',$E['medico']],['Matrícula',$E['matricula']],
          ['Antecedentes',$E['antecedentes']],['Alergias',$E['alergias']],['Lesiones',$E['lesiones']],['Medicación',$E['medicacion']],['Obs.',$E['obs']]
        ];
        foreach($pairs as [$L,$V]){ echo '<div style="display:flex;gap:6px;margin:3px 0"><div style="width:170px;font-weight:600">'.h($L).'</div><div>'.h($V?:'-').'</div></div>'; } ?>
      </div><?php } ?>
    </div>
  </div><?php
}
render_html_simple_inline($ev_titulo,$orden,$modal,$categ,$fecha,$R,$A,$X,$pid);
?>
</body></html>
<?php
exit;
