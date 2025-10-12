<?php
/* =========================
   ver_peleas_evento.php
   (Eliminado form anidado en “Eliminar”; ahora usa botón + JS)
   Fix: ronda leída correctamente en crear_manual
   ========================= */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


/* ========= helpers ========= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($col){ return '`'.str_replace('`','``', $col).'`'; }
function fmt_num($v){ if ($v===null || $v==='') return null; $f=(float)$v; return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.'); }
function norm($s){ return mb_strtolower(trim((string)$s), 'UTF-8'); }
function col_required(mysqli $cx, $tabla, $col): bool {
  if(!$tabla || !$col) return false;
  $tabla = preg_replace('/[^a-zA-Z0-9_]/','',$tabla);
  $col   = preg_replace('/[^a-zA-Z0-9_]/','',$col);
  $rs = $cx->query("SHOW COLUMNS FROM `$tabla` LIKE '$col'");
  if(!$rs) return false;
  $r = $rs->fetch_assoc();
  if(!$r) return false;
  $isAuto = isset($r['Extra']) && stripos($r['Extra'],'auto_increment')!==false;
  return (strtoupper($r['Null']??'')==='NO') && ($r['Default']===null) && !$isAuto;
}

/* ========= evento_id: GET → POST → SESSION ========= */
$evento_id = 0;
if (isset($_GET['evento_id']))        $evento_id = (int)$_GET['evento_id'];
elseif (isset($_POST['evento_id']))    $evento_id = (int)$_POST['evento_id'];
elseif (isset($_SESSION['evento_id_actual'])) $evento_id = (int)$_SESSION['evento_id_actual'];
elseif (isset($_SESSION['evento_id']))        $evento_id = (int)$_SESSION['evento_id'];

if ($evento_id <= 0) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>evento_id</b>. Abrí esta página desde el evento.</div>';
  exit;
}
$_SESSION['evento_id_actual'] = ($evento_id > 0 ? $evento_id : ($_SESSION['evento_id_actual'] ?? 0));

/* ========= columnas peleas_evento ========= */
$cols = [];
$res = $conexion->query("SHOW COLUMNS FROM peleas_evento");
if (!$res) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #fdecea;background:#ffebee;color:#b71c1c;border-radius:8px;">No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'</div>'; exit; }
while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
$pick = function(array $cands) use ($cols){ foreach ($cands as $c) { $lc = strtolower($c); if (isset($cols[$lc])) return $cols[$lc]; } return null; };

$C_ID       = $pick(['id','pelea_id','id_pelea']);
$C_EVENTO   = $pick(['evento_id','id_evento','evento']);
$C_ROJO     = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL     = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_RONDAS   = $pick(['rondas','rounds']);
$C_OBS      = $pick(['observaciones','obs','comentarios','comentario','nota']);
$C_FECHA    = $pick(['fecha','creado_en','created_at','created','fh_creacion']);
$C_ORDEN    = $pick(['orden','orden_manual','nro','nro_orden','posicion','position','sequence','rank','numero','nro_pelea','sort']);
/* columnas opcionales de pesaje real */
$C_PESO_REAL_R = $pick(['peso_real_rojo','rojo_peso_real','peso_real_r']);
$C_PESO_REAL_A = $pick(['peso_real_azul','azul_peso_real','peso_real_a']);

if (!$C_EVENTO || !$C_ROJO || !$C_AZUL) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #fdecea;background:#ffebee;color:#b71c1c;border-radius:8px;">La tabla <b>peleas_evento</b> existe pero faltan columnas obligatorias (evento/rojo/azul).</div>'; exit;
}
$REQ_AZUL = col_required($conexion, 'peleas_evento', $C_AZUL);

/* ========= columnas competidores_evento ========= */
$colsC = [];
$resC = $conexion->query("SHOW COLUMNS FROM competidores_evento");
if ($resC) { while($r = $resC->fetch_assoc()){ $colsC[strtolower($r['Field'])] = $r['Field']; } }
$pickC = function(array $cands) use ($colsC){ foreach($cands as $c){ $lc=strtolower($c); if(isset($colsC[$lc])) return $colsC[$lc]; } return null; };

$CE_ID       = $pickC(['id','competidor_id']);
$CE_APE      = $pickC(['apellido','apellidos','last_name']);
$CE_NOM      = $pickC(['nombre','nombres','first_name']);
$CE_ESC      = $pickC(['escuela_nombre','escuela','gimnasio','gym']);
$CE_EDAD     = $pickC(['edad','age']);
$CE_PESO     = $pickC(['peso_kg','peso','kg','weight_kg']);
$CE_EVENTO   = $pickC(['evento_id','id_evento']);
$CE_OBS      = $pickC(['observaciones','obs','comentarios']);
$CE_DNI      = $pickC(['dni','documento','doc','num_doc']);
$CE_DISC     = $pickC(['disciplina_id','disciplina','id_disciplina']);
$CE_MODAL    = $pickC(['modalidad_id','modalidad_evento_id','id_modalidad']);
$CE_DIV      = $pickC(['division_id','id_division','division_evento_id']);
$CE_FOTO     = $pickC(['foto_competidor','foto','imagen','avatar','foto_url','image_url']);
$CE_CAT_TEC  = $pickC(['categoria_tecnica_id','id_categoria_tecnica']);
$CE_SEXO     = $pickC(['sexo','genero','sexo_id']);

$REQ_DISC  = $CE_DISC  ? col_required($conexion,'competidores_evento',$CE_DISC)  : false;
$REQ_MODAL = $CE_MODAL ? col_required($conexion,'competidores_evento',$CE_MODAL) : false;
$REQ_DIV   = $CE_DIV   ? col_required($conexion,'competidores_evento',$CE_DIV)   : false;
$REQ_DNI   = $CE_DNI   ? col_required($conexion,'competidores_evento',$CE_DNI)   : false;
$REQ_SEXO  = $CE_SEXO  ? col_required($conexion,'competidores_evento',$CE_SEXO)  : false;

/* ========= catálogos ========= */
$tablaModal = null; $MOD_LABEL_COL = 'nombre'; $modalidades = [];
if (($chkMod=$conexion->query("SHOW TABLES LIKE 'modalidades_evento'")) && $chkMod->num_rows>0){ $tablaModal = 'modalidades_evento'; }
if ($tablaModal){
  $mc = []; if ($rc=$conexion->query("SHOW COLUMNS FROM $tablaModal")){ while($r=$rc->fetch_assoc()){ $mc[strtolower($r['Field'])]=$r['Field']; } }
  $MOD_LABEL_COL = $mc['nombre'] ?? ($mc['modalidad'] ?? ($mc['descripcion'] ?? ($mc['name'] ?? 'nombre')));
  $MID = $mc['id'] ?? null; $MEV = $mc['evento_id'] ?? ($mc['id_evento'] ?? ($mc['evento'] ?? null));
  if ($MID){
    if ($MEV){
      $st=$conexion->prepare("SELECT ".bt($MID)." AS id, ".bt($MOD_LABEL_COL)." AS nombre FROM $tablaModal WHERE ".bt($MEV)."=? ORDER BY ".bt($MOD_LABEL_COL)." ASC");
      if($st){ $st->bind_param('i',$evento_id); $st->execute(); $modalidades=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close(); }
    } else {
      if ($rs=$conexion->query("SELECT ".bt($MID)." AS id, ".bt($MOD_LABEL_COL)." AS nombre FROM $tablaModal ORDER BY ".bt($MOD_LABEL_COL)." ASC")) $modalidades=$rs->fetch_all(MYSQLI_ASSOC);
    }
  }
}
$tablaDisc = null; $DISC_LABEL_COL = 'nombre'; $disciplinas = [];
if (($chkDisc=$conexion->query("SHOW TABLES LIKE 'disciplinas_evento'")) && $chkDisc->num_rows>0){ $tablaDisc = 'disciplinas_evento'; }
if ($tablaDisc){
  $dc=[]; if($rc2=$conexion->query("SHOW COLUMNS FROM $tablaDisc")){ while($r=$rc2->fetch_assoc()){ $dc[strtolower($r['Field'])]=$r['Field']; } }
  $DISC_LABEL_COL=$dc['nombre'] ?? ($dc['disciplina'] ?? ($dc['descripcion'] ?? ($dc['name'] ?? 'nombre')));
  $DID=$dc['id'] ?? null; $DEV=$dc['evento_id'] ?? ($dc['id_evento'] ?? ($dc['evento'] ?? null));
  if ($DID){
    if ($DEV){
      $st=$conexion->prepare("SELECT ".bt($DID)." AS id, ".bt($DISC_LABEL_COL)." AS nombre FROM $tablaDisc WHERE ".bt($DEV)."=? ORDER BY ".bt($DISC_LABEL_COL)." ASC");
      if($st){ $st->bind_param('i',$evento_id); $st->execute(); $disciplinas=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close(); }
    } else {
      if ($rs=$conexion->query("SELECT ".bt($DID)." AS id, ".bt($DISC_LABEL_COL)." AS nombre FROM $tablaDisc ORDER BY ".bt($DISC_LABEL_COL)." ASC")) $disciplinas=$rs->fetch_all(MYSQLI_ASSOC);
    }
  }
}
$tablaDiv = null; $DIV_LABEL_COL = 'nombre'; $divisiones = [];
if (($chkD=$conexion->query("SHOW TABLES LIKE 'divisiones_evento'")) && $chkD->num_rows>0){ $tablaDiv = 'divisiones_evento'; }
if ($tablaDiv){
  $dv=[]; if($rd=$conexion->query("SHOW COLUMNS FROM $tablaDiv")){ while($r=$rd->fetch_assoc()){ $dv[strtolower($r['Field'])]=$r['Field']; } }
  $DIV_LABEL_COL=$dv['nombre'] ?? ($dv['division'] ?? ($dv['descripcion'] ?? ($dv['name'] ?? 'nombre')));
  $DVD=$dv['id'] ?? null; $DVE=$dv['evento_id'] ?? ($dv['id_evento'] ?? ($dv['evento'] ?? null));
  if ($DVD){
    if ($DVE){
      $st=$conexion->prepare("SELECT ".bt($DVD)." AS id, ".bt($DIV_LABEL_COL)." AS nombre FROM $tablaDiv WHERE ".bt($DVE)."=? ORDER BY ".bt($DIV_LABEL_COL)." ASC");
      if($st){ $st->bind_param('i',$evento_id); $st->execute(); $divisiones=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close(); }
    } else {
      if ($rs=$conexion->query("SELECT ".bt($DVD)." AS id, ".bt($DIV_LABEL_COL)." AS nombre FROM $tablaDiv ORDER BY ".bt($DIV_LABEL_COL)." ASC")) $divisiones=$rs->fetch_all(MYSQLI_ASSOC);
    }
  }
}
$tablaTec=null; $tecnicas=[]; $tecCols=[];
if (($chkT=$conexion->query("SHOW TABLES LIKE 'categorias_tecnicas_evento'")) && $chkT->num_rows>0){
  $tablaTec='categorias_tecnicas_evento';
  if($rt=$conexion->query("SHOW COLUMNS FROM $tablaTec")){ while($r=$rt->fetch_assoc()){ $tecCols[strtolower($r['Field'])]=$r['Field']; } }
  $TC_ID=$tecCols['id'] ?? null;
  if ($TC_ID){
    $TEC_LABEL_COL = $tecCols['nombre'] ?? ($tecCols['name'] ?? ($tecCols['codigo'] ?? ($tecCols['nivel'] ?? ($tecCols['grado'] ?? ($tecCols['categoria'] ?? ($tecCols['etiqueta'] ?? ($tecCols['detalle'] ?? 'id')))))));
    $TEC_DESC_COL  = $tecCols['descripcion'] ?? ($tecCols['desc'] ?? ($tecCols['detalle'] ?? null));
    $selNm = bt($TEC_LABEL_COL);
    $selDesc = ($TEC_DESC_COL && $TEC_DESC_COL!==$TEC_LABEL_COL) ? ", ".bt($TEC_DESC_COL)." AS descripcion" : "";
    $sqlTc = "SELECT ".bt($TC_ID)." AS id, $selNm AS nombre$selDesc FROM $tablaTec ORDER BY $selNm ASC";
    if($rs=$conexion->query($sqlTc)){ $tecnicas=$rs->fetch_all(MYSQLI_ASSOC); }
  }
}

/* ========= helpers/consultas de apoyo ========= */
function encontrar_competidor_existente(mysqli $cx, $map, int $evento_id, string $dni, string $ape, string $nom, string $sexo=''){
  [$CE_ID,$CE_EVENTO,$CE_DNI,$CE_APE,$CE_NOM,$CE_SEXO] = $map;
  if(!$CE_ID || !$CE_EVENTO) return null;
  if ($CE_DNI && $dni!=='') {
    $sql = "SELECT ".bt($CE_ID)." AS id FROM competidores_evento WHERE ".bt($CE_EVENTO)."=? AND ".bt($CE_DNI)."=?".($CE_SEXO&&$sexo!==''?" AND ".bt($CE_SEXO)."=?":"")." ORDER BY ".bt($CE_ID)." DESC LIMIT 1";
    $st=$cx->prepare($sql); if(!$st) return null;
    if($CE_SEXO&&$sexo!=='') $st->bind_param('iss',$evento_id,$dni,$sexo); else $st->bind_param('is',$evento_id,$dni);
    $st->execute(); $res=$st->get_result(); $row=$res?$res->fetch_assoc():null; $st->close(); return $row?(int)$row['id']:null;
  }
  if ($CE_APE && $CE_NOM) {
    $sql = "SELECT ".bt($CE_ID)." AS id FROM competidores_evento WHERE ".bt($CE_EVENTO)."=? AND ".bt($CE_APE)."=? AND ".bt($CE_NOM)."=?".($CE_SEXO&&$sexo!==''?" AND ".bt($CE_SEXO)."=?":"")." ORDER BY ".bt($CE_ID)." DESC LIMIT 1";
    $st=$cx->prepare($sql); if(!$st) return null;
    if($CE_SEXO&&$sexo!=='') $st->bind_param('isss',$evento_id,$ape,$nom,$sexo); else $st->bind_param('iss',$evento_id,$ape,$nom);
    $st->execute(); $res=$st->get_result(); $row=$res?$res->fetch_assoc():null; $st->close(); return $row?(int)$row['id']:null;
  }
  return null;
}
function peleas_de_competidor(mysqli $cx, $map, int $evento_id, int $comp_id, int $limit=5): array {
  [$C_ID,$C_EVENTO,$C_ROJO,$C_AZUL] = $map;
  $cidcol = $C_ID ?: 'id';
  $sql = "SELECT ".bt($cidcol)." AS id FROM peleas_evento WHERE ".bt($C_EVENTO)."=? AND (".bt($C_ROJO)."=? OR ".bt($C_AZUL)."=?) ORDER BY ".bt($cidcol)." ASC";
  $st=$cx->prepare($sql); if(!$st) return ['count'=>0,'ids'=>[]];
  $st->bind_param('iii',$evento_id,$comp_id,$comp_id); $st->execute(); $res=$st->get_result(); $ids=[]; if($res){ while($r=$res->fetch_assoc()){ $ids[]=(int)$r['id']; } } $st->close();
  return ['count'=>count($ids),'ids'=>array_slice($ids,0,$limit)];
}
function obtener_o_crear_bye(mysqli $cx, $mapCols, int $evento_id, string $sexo=''){
  [$CE_ID,$CE_APE,$CE_NOM,$CE_ESC,$CE_EDAD,$CE_PESO,$CE_EVENTO,$CE_OBS,$CE_DNI,$CE_DISC,$CE_MODAL,$CE_DIV,$CE_FOTO,$CE_CAT_TEC,$CE_SEXO] = $mapCols;
  $sql = "SELECT ".bt($CE_ID)." AS id FROM competidores_evento WHERE ".bt($CE_EVENTO)."=? AND ".bt($CE_APE)."='BYE' LIMIT 1";
  $st=$cx->prepare($sql); if($st){ $st->bind_param('i',$evento_id); $st->execute(); $res=$st->get_result()->fetch_assoc(); $st->close(); if($res){ return (int)$res['id']; } }
  $cols=[bt($CE_EVENTO),bt($CE_APE),bt($CE_NOM),bt($CE_OBS)]; $vals=[ $evento_id,'BYE','—','placeholder BYE']; $types='isss';
  if($CE_SEXO && $sexo!==''){ $cols[]=bt($CE_SEXO); $vals[]=$sexo; $types.='s'; }
  $sqlIns='INSERT INTO competidores_evento ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';
  $st2=$cx->prepare($sqlIns); if(!$st2) throw new RuntimeException('Prep BYE: '.$cx->error);
  $st2->bind_param($types, ...$vals); $ok=$st2->execute(); $id=(int)$st2->insert_id; $err=$st2->error; $st2->close();
  if(!$ok || $id<=0) throw new RuntimeException('Crear BYE falló: '.($err?:'sin detalle'));
  return $id;
}
function insertar_competidor_min(mysqli $cx, $mapCols, $data): int {
  [$CE_ID,$CE_APE,$CE_NOM,$CE_ESC,$CE_EDAD,$CE_PESO,$CE_EVENTO,$CE_OBS,$CE_DNI,$CE_DISC,$CE_MODAL,$CE_DIV,$CE_FOTO,$CE_CAT_TEC,$CE_SEXO] = $mapCols;
  $cols=[]; $vals=[]; $types='';
  $add=function($col,$val)use(&$cols,&$vals,&$types){ if(!$col) return; $cols[]=bt($col); $vals[]=$val; $types.=is_int($val)?'i':'s'; };
  $add($CE_EVENTO,(int)$data['evento_id']);
  $add($CE_APE,(string)$data['apellido']);
  $add($CE_NOM,(string)$data['nombre']);
  if($CE_ESC && isset($data['escuela'])) $add($CE_ESC,(string)$data['escuela']);
  if($CE_SEXO && ($data['sexo']??'')!=='') $add($CE_SEXO,(string)$data['sexo']);
  if($CE_DNI  && ($data['dni'] ??'')!=='') $add($CE_DNI,(string)$data['dni']);
  if($CE_DISC && $data['disciplina_val']!==null) $add($CE_DISC,(int)$data['disciplina_val']);
  if($CE_MODAL && $data['modalidad_val']!==null) $add($CE_MODAL,(int)$data['modalidad_val']);
  if($CE_DIV && $data['division_id']!==null) $add($CE_DIV,(int)$data['division_id']);
  if($CE_CAT_TEC && $data['cat_tec_id']!==null) $add($CE_CAT_TEC,(int)$data['cat_tec_id']);
  if($CE_PESO && ($data['peso'] ?? '')!==''){ $add($CE_PESO,(string)fmt_num($data['peso'])); }
  if($CE_EDAD && ($data['edad'] ?? null)!==null) $add($CE_EDAD,(int)$data['edad']);
  $obsTxt=trim((string)($data['obs'] ?? '')); if($CE_OBS && $obsTxt!=='') $add($CE_OBS,$obsTxt);

  if(!$cols) throw new RuntimeException('No hay columnas válidas para insertar competidor.');
  $sql='INSERT INTO competidores_evento ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';
  $st=$cx->prepare($sql); if(!$st) throw new RuntimeException('Prep competidor: '.$cx->error);
  $st->bind_param($types, ...$vals); $ok=$st->execute(); $id=(int)$st->insert_id; $err=$st->error; $st->close();
  if(!$ok || $id<=0) throw new RuntimeException('Insert competidor falló: '.($err?:'sin detalle'));
  return $id;
}

/* ========= acciones POST ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $evento_id = (int)($_POST['evento_id'] ?? $evento_id);
  $_SESSION['evento_id_actual'] = ($evento_id > 0 ? $evento_id : ($_SESSION['evento_id_actual'] ?? 0));

  $accion   = $_POST['accion'] ?? '';
  $pelea_id = isset($_POST['pelea_id']) && is_numeric($_POST['pelea_id']) ? (int)$_POST['pelea_id'] : 0;

  if ($pelea_id > 0) {
    $sqlChk = "SELECT 1 FROM peleas_evento WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID ?: 'id')."=? LIMIT 1";
    $st=$conexion->prepare($sqlChk);
    if ($st) { $st->bind_param('ii',$evento_id,$pelea_id); $st->execute(); $ok=$st->get_result()->num_rows===1; $st->close(); if(!$ok){ $pelea_id=0; } }
    else { $pelea_id=0; }
  }

  /* ===== importar peleas desde archivo ===== */
  if ($accion === 'importar_peleas' && isset($_FILES['archivo_peleas'])) {
    // (Se deja tal cual tu lógica de importación)
    $file = $_FILES['archivo_peleas'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $_SESSION['flash_error'] = 'Error subiendo archivo.';
      header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $permitidas = ['xlsx','csv','pdf'];
    if (!in_array($ext, $permitidas)) {
      $_SESSION['flash_error'] = 'Formato no permitido. Use .xlsx, .csv o .pdf';
      header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
    }

    $uploads = __DIR__ . '/uploads';
    if (!is_dir($uploads)) @mkdir($uploads, 0777, true);
    $nombre = 'peleas_evento_'.$evento_id.'_'.date('Ymd_His').'.'.$ext;
    $dest = $uploads . '/' . $nombre;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
      $_SESSION['flash_error'] = 'No se pudo guardar el archivo subido.';
      header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
    }

    if ($ext === 'pdf') {
      $_SESSION['flash_ok'] = '📄 PDF guardado como referencia (no se importaron filas).';
      header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
    }

    $filas = [];
    try {
      if ($ext === 'xlsx') {
        if (!class_exists('ZipArchive')) {
          $_SESSION['flash_error'] = 'Para leer .xlsx necesitás la extensión PHP <b>ZipArchive</b> (paquete <code>php-zip</code>). ' .
            'Alternativas: 1) instalar php-zip y recargar, 2) subir el mismo archivo como .csv. ' .
            'En Ubuntu/Debian: <code>sudo apt-get install php-zip && sudo systemctl restart apache2</code>'; 
          header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id); exit;
        }

        require_once __DIR__ . '/vendor/autoload.php';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($dest);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        if (!$rows || count($rows)<2) throw new RuntimeException('El Excel está vacío.');
        $headers = [];
        foreach($rows[1] as $col => $val){ $headers[$col] = preg_replace('/\s+/','_', trim(mb_strtolower((string)$val,'UTF-8'))); }
        for($i=2; $i<=count($rows); $i++){
          $r = [];
          foreach($rows[$i] as $col => $val){
            $key = $headers[$col] ?? ('col_'.$col);
            $r[$key] = is_string($val) ? trim($val) : $val;
          }
          $filas[] = $r;
        }
      } elseif ($ext === 'csv') {
        $fh = fopen($dest, 'r');
        if (!$fh) throw new RuntimeException('No se pudo abrir CSV.');
        $enc = fgetcsv($fh, 0, ';');
        if ($enc && count($enc)===1){ rewind($fh); $enc = fgetcsv($fh, 0, ','); $delim = ','; } else { $delim = ';'; }
        if (!$enc) throw new RuntimeException('CSV sin encabezado.');
        $headers = array_map(function($h){ return preg_replace('/\s+/','_', trim(mb_strtolower((string)$h,'UTF-8'))); }, $enc);
        while(($row = fgetcsv($fh, 0, $delim)) !== false){
          if (count($row)==1 && trim((string)$row[0])==='') continue;
          $r = [];
          foreach($headers as $i=>$hx){ $r[$hx] = isset($row[$i]) ? trim((string)$row[$i]) : ''; }
          $filas[] = $r;
        }
        fclose($fh);
      }
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = 'Error leyendo archivo: '.$e->getMessage();
      header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
    }

    $ejemplo = $filas[0] ?? [];
    $keys = array_fill_keys(array_keys($ejemplo), true);
    $pick_key = function(array $map, array $aliases){ foreach($aliases as $a){ if(isset($map[$a])) return $a; } return null; };

    $k_r_ap   = $pick_key($keys, ['rojo_apellido','r_apellido','apellido_rojo','apellido_r','apellido']);
    $k_r_nom  = $pick_key($keys, ['rojo_nombre','r_nombre','nombre_rojo','nombre_r','nombre']);
    $k_r_esc  = $pick_key($keys, ['rojo_escuela','r_escuela','escuela_rojo','escuela_r','escuela']);
    $k_r_dni  = $pick_key($keys, ['rojo_dni','r_dni','dni_rojo','dni_r','dni']);
    $k_r_peso = $pick_key($keys, ['rojo_peso','r_peso','peso_rojo','peso_r','peso']);
    $k_a_ap   = $pick_key($keys, ['azul_apellido','a_apellido','apellido_azul','apellido_a']);
    $k_a_nom  = $pick_key($keys, ['azul_nombre','a_nombre','nombre_azul','nombre_a']);
    $k_a_esc  = $pick_key($keys, ['azul_escuela','a_escuela','escuela_azul','escuela_a']);
    $k_a_dni  = $pick_key($keys, ['azul_dni','a_dni','dni_azul','dni_a']);
    $k_a_peso = $pick_key($keys, ['azul_peso','a_peso','peso_azul','peso_a']);
    $k_rondas = $pick_key($keys, ['rondas','rounds']);
    $k_obs    = $pick_key($keys, ['observaciones','obs','comentarios','nota']);
    $k_form   = $pick_key($keys, ['formato','tipo','fixture']);
    $k_sexo   = $pick_key($keys, ['sexo','genero']);
    $k_div    = $pick_key($keys, ['division_id','id_division','division']);
    $k_mod    = $pick_key($keys, ['modalidad_id','id_modalidad','modalidad']);
    $k_disc   = $pick_key($keys, ['disciplina_id','id_disciplina','disciplina']);
    $k_ctec   = $pick_key($keys, ['categoria_tecnica_id','id_categoria_tecnica','cat_tec','nivel']);
    $k_solo   = $pick_key($keys, ['solo_rojo','en_espera','solo','espera']);

    $creadas=0; $saltadas=0; $avisos=[];
    $conexion->begin_transaction();
    try{
      foreach($filas as $idx=>$row){
        $r_ap = trim((string)($k_r_ap ? ($row[$k_r_ap] ?? '') : ''));
        $r_no = trim((string)($k_r_nom? ($row[$k_r_nom]?? '') : ''));
        $a_ap = trim((string)($k_a_ap ? ($row[$k_a_ap] ?? '') : ''));
        $a_no = trim((string)($k_a_nom? ($row[$k_a_nom]?? '') : ''));
        if ($r_ap==='' || $r_no===''){ $saltadas++; continue; }

        $r_esc = trim((string)($k_r_esc? ($row[$k_r_esc]??'') : ''));
        $r_dni = trim((string)($k_r_dni? ($row[$k_r_dni]??'') : ''));
        $r_pes = ($k_r_peso && $row[$k_r_peso]!=='' ? (float)str_replace(',','.',$row[$k_r_peso]) : null);

        $a_esc = trim((string)($k_a_esc? ($row[$k_a_esc]??'') : ''));
        $a_dni = trim((string)($k_a_dni? ($row[$k_a_dni]??'') : ''));
        $a_pes = ($k_a_peso && $row[$k_a_peso]!=='' ? (float)str_replace(',','.',$row[$k_a_peso]) : null);

        $rondas = isset($_POST['rondas']) && is_numeric($_POST['rondas']) ? (int)$_POST['rondas'] : 2;
        $obs_extra = trim((string)($k_obs? ($row[$k_obs]??'') : ''));
        $formato = trim((string)($k_form? ($row[$k_form]??'') : ''));

        $sexo = strtoupper(trim((string)($k_sexo? ($row[$k_sexo]??'') : '')));
        $division_id = ($k_div && is_numeric($row[$k_div] ?? null)) ? (int)$row[$k_div] : null;
        $modalidad_val = ($k_mod && is_numeric($row[$k_mod] ?? null)) ? (int)$row[$k_mod] : null;
        $disciplina_val = ($k_disc && is_numeric($row[$k_disc] ?? null)) ? (int)$row[$k_disc] : null;
        $cat_tec_id = ($k_ctec && is_numeric($row[$k_ctec] ?? null)) ? (int)$row[$k_ctec] : null;
        $es_espera = false;
        if ($k_solo) {
          $v = trim((string)($row[$k_solo]??''));
          $es_espera = in_array(mb_strtolower($v,'UTF-8'), ['1','si','sí','true','x','s','solo','espera'], true);
        }
        if ($a_ap==='' || $a_no===''){ if (!$REQ_AZUL) $es_espera = true; }

        $r_id = insertar_competidor_min(
          $conexion,
          [$CE_ID,$CE_APE,$CE_NOM,$CE_ESC,$CE_EDAD,$CE_PESO,$CE_EVENTO,$CE_OBS,$CE_DNI,$CE_DISC,$CE_MODAL,$CE_DIV,$CE_FOTO,$CE_CAT_TEC,$CE_SEXO],
          ['evento_id'=>$evento_id,'apellido'=>$r_apellido=$r_ap,'nombre'=>$r_nombre=$r_no,'escuela'=>$r_esc,'edad'=>null,'dni'=>$r_dni,'sexo'=>$sexo,
           'disciplina_val'=>$disciplina_val,'modalidad_val'=>$modalidad_val,'division_id'=>$division_id,'cat_tec_id'=>$cat_tec_id,'peso'=>$r_pes,'obs'=>'']
        );

        if ($es_espera){
          $azul_id = $REQ_AZUL ? obtener_o_crear_bye($conexion, [$CE_ID,$CE_APE,$CE_NOM,$CE_ESC,$CE_EDAD,$CE_PESO,$CE_EVENTO,$CE_OBS,$CE_DNI,$CE_DISC,$CE_MODAL,$CE_DIV,$CE_FOTO,$CE_CAT_TEC,$CE_SEXO], $evento_id, $sexo) : null;
        } else {
          $azul_id = insertar_competidor_min(
            $conexion,
            [$CE_ID,$CE_APE,$CE_NOM,$CE_ESC,$CE_EDAD,$CE_PESO,$CE_EVENTO,$CE_OBS,$CE_DNI,$CE_DISC,$CE_MODAL,$CE_DIV,$CE_FOTO,$CE_CAT_TEC,$CE_SEXO],
            ['evento_id'=>$evento_id,'apellido'=>$a_ap,'nombre'=>$a_no,'escuela'=>$a_esc,'edad'=>null,'dni'=>$a_dni,'sexo'=>$sexo,
             'disciplina_val'=>$disciplina_val,'modalidad_val'=>$modalidad_val,'division_id'=>$division_id,'cat_tec_id'=>$cat_tec_id,'peso'=>$a_pes,'obs'=>'']
          );
        }

        $obsComp = [];
        if ($formato!=='') $obsComp[] = strtoupper($formato);
        $obsComp[] = 'Rojo: '.$r_ap.' '.$r_no.($r_esc!==''?' - '.$r_esc:'');
        $obsComp[] = 'Azul: '.($es_espera ? '(en espera)' : trim($a_ap.' '.$a_no.($a_esc!==''?' - '.$a_esc:'')));
        $obsTxt = trim(($obs_extra!==''?$obs_extra.' | ':'').implode(' | ', $obsComp));

        $colsP = [bt($C_EVENTO), bt($C_ROJO)];
        $valsP = [$evento_id, $r_id];
        $types = 'ii';
        $colsP[] = bt($C_AZUL); $valsP[] = $azul_id!==null ? (int)$azul_id : null; $types .= 'i';
        if ($C_RONDAS) { $colsP[] = bt($C_RONDAS); $valsP[] = (int)$rondas; $types .= 'i'; }
        if ($C_OBS)    { $colsP[] = bt($C_OBS);    $valsP[] = $obsTxt;   $types .= 's'; }

        if ($azul_id === null){
          $sqlP = 'INSERT INTO peleas_evento ('.bt($C_EVENTO).','.bt($C_ROJO).','.bt($C_AZUL).($C_RONDAS?','.bt($C_RONDAS):'').($C_OBS?','.bt($C_OBS):'').') VALUES (?, ?, NULL'.($C_RONDAS?', ?':'').($C_OBS?', ?':'').')';
          $st = $conexion->prepare($sqlP);
          if(!$st) throw new RuntimeException('Prep pelea (NULL azul) fila '.($idx+1).': '.$conexion->error);
          if ($C_RONDAS && $C_OBS) { $st->bind_param('iiss', $evento_id, $r_id, $rondas, $obsTxt); }
          elseif ($C_RONDAS && !$C_OBS) { $st->bind_param('iii', $evento_id, $r_id, $rondas); }
          elseif (!$C_RONDAS && $C_OBS) { $st->bind_param('iis', $evento_id, $r_id, $obsTxt); }
          else { $st->bind_param('ii', $evento_id, $r_id); }
        } else {
          $sqlP = 'INSERT INTO peleas_evento ('.implode(',', $colsP).') VALUES ('.implode(',', array_fill(0,count($colsP),'?')).')';
          $st = $conexion->prepare($sqlP);
          if(!$st) throw new RuntimeException('Prep pelea bind fila '.($idx+1).': '.$conexion->error);
          $bindTypes = 'iii'; $params = [$evento_id, $r_id, (int)$azul_id];
          if ($C_RONDAS){ $bindTypes.='i'; $params[] = (int)$rondas; }
          if ($C_OBS){ $bindTypes.='s'; $params[] = $obsTxt; }
          $st->bind_param($bindTypes, ...$params);
        }
        $okExec=$st->execute(); $st->close();
        if(!$okExec) throw new RuntimeException('Insert pelea falló (fila '.($idx+1).')');

        $creadas++;
      }

      $conexion->commit();
      $_SESSION['flash_ok'] = "✅ Importación completa: $creadas pelea(s) creadas".($saltadas>0? " · $saltadas fila(s) saltadas por datos mínimos":'').".";
      if ($avisos){ $_SESSION['flash_warn'] = implode('<br>', array_map('h',$avisos)); }
    } catch (Throwable $e){
      $conexion->rollback();
      $_SESSION['flash_error'] = 'Error importando: '.$e->getMessage();
    }

    header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
  }
  /* ===== fin importar ===== */

  if ($accion === 'guardar_orden') {
    if (!$C_ORDEN) {
      $_SESSION['flash_error'] = 'No existe una columna de orden en <b>peleas_evento</b> (ej. <code>orden</code>). Creá una para habilitar la numeración manual.';
      header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
    }
    $ordenData = $_POST['orden'] ?? [];
    if (!is_array($ordenData)) $ordenData = [];
    $conexion->begin_transaction();
    try{
      $sqlUp = "UPDATE peleas_evento SET ".bt($C_ORDEN)."=? WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID ?: 'id')."=? LIMIT 1";
      $st=$conexion->prepare($sqlUp);
      if(!$st) throw new RuntimeException('Prep update orden: '.$conexion->error);
      foreach($ordenData as $pid=>$val){
        if (!is_numeric($pid)) continue;
        $pid = (int)$pid;
        $val = ($val==='') ? null : (int)$val;
        if ($val===null){
          $sqlUpNull = "UPDATE peleas_evento SET ".bt($C_ORDEN)."=NULL WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID ?: 'id')."=? LIMIT 1";
          $st2=$conexion->prepare($sqlUpNull);
          if(!$st2) throw new RuntimeException('Prep update orden NULL: '.$conexion->error);
          $st2->bind_param('ii',$evento_id,$pid); $st2->execute(); $st2->close();
        } else {
          $st->bind_param('iii',$val,$evento_id,$pid); $st->execute();
        }
      }
      if(isset($st) && $st) $st->close();
      $conexion->commit();
      $_SESSION['flash_ok'] = '✅ Numeración actualizada.';
    } catch(Throwable $e){
      $conexion->rollback();
      $_SESSION['flash_error'] = 'Error guardando numeración: '.$e->getMessage();
    }
    header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
  }

  if ($accion === 'guardar_pesajes') {
    $pesosR = $_POST['peso_real_r'] ?? [];
    $pesosA = $_POST['peso_real_a'] ?? [];
    if (!is_array($pesosR)) $pesosR = [];
    if (!is_array($pesosA)) $pesosA = [];

    $conexion->begin_transaction();
    try{
      $guardados = 0;

      if ($C_PESO_REAL_R || $C_PESO_REAL_A) {
        foreach ($pesosR as $pid => $valR) {
          if (!is_numeric($pid)) continue;
          $pid = (int)$pid;
          $valR = trim((string)$valR);
          $valA = trim((string)($pesosA[$pid] ?? ''));
          $set = []; $types=''; $vals=[];

          if ($C_PESO_REAL_R) { $set[] = bt($C_PESO_REAL_R).'=?'; $types .= 's'; $vals[] = ($valR!=='' ? fmt_num($valR) : null); }
          if ($C_PESO_REAL_A) { $set[] = bt($C_PESO_REAL_A).'=?'; $types .= 's'; $vals[] = ($valA!=='' ? fmt_num($valA) : null); }
          if (!$set) continue;

          $sqlUp = "UPDATE peleas_evento p SET ".implode(',', $set)." WHERE p.".bt($C_EVENTO)."=? AND p.".bt($C_ID ?: 'id')."=? LIMIT 1";
          $types .= 'ii'; $vals[] = $evento_id; $vals[] = $pid;
          $st = $conexion->prepare($sqlUp);
          if (!$st) throw new RuntimeException('Guardar pesaje (pelea '.$pid.'): '.$conexion->error);
          $st->bind_param($types, ...$vals);
          $st->execute(); $guardados += max(0, $st->affected_rows); $st->close();
        }
      } else {
        $tieneTabla = false;
        if (($chk=$conexion->query("SHOW TABLES LIKE 'pesajes_evento'")) && $chk->num_rows>0) $tieneTabla = true;

        if ($tieneTabla) {
          $sqlIns = "INSERT INTO pesajes_evento (evento_id, pelea_id, peso_real_rojo, peso_real_azul, actualizado_en)
                     VALUES (?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE peso_real_rojo=VALUES(peso_real_rojo), peso_real_azul=VALUES(peso_real_azul), actualizado_en=VALUES(actualizado_en)";
          $st = $conexion->prepare($sqlIns);
          if ($st) {
            foreach ($pesosR as $pid => $valR) {
              if (!is_numeric($pid)) continue;
              $pid = (int)$pid;
              $valR = trim((string)$valR);
              $valA = trim((string)($pesosA[$pid] ?? ''));
              $vR = ($valR!=='' ? fmt_num($valR) : null);
              $vA = ($valA!=='' ? fmt_num($valA) : null);
              $st->bind_param('iiss', $evento_id, $pid, $vR, $vA);
              $st->execute(); $guardados += max(0, $st->affected_rows);
            }
            $st->close();
          }
        } else {
          foreach ($pesosR as $pid => $valR) {
            if (!is_numeric($pid)) continue;
            $pid = (int)$pid;
            $_SESSION['pesajes'][$evento_id][$pid]['r'] = ($valR!=='' ? fmt_num($valR) : null);
            $valA = trim((string)($pesosA[$pid] ?? ''));
            $_SESSION['pesajes'][$evento_id][$pid]['a'] = ($valA!=='' ? fmt_num($valA) : null);
            $guardados++;
          }
          $_SESSION['flash_warn'] = 'ℹ️ Pesajes guardados en sesión (no hay columnas en <b>peleas_evento</b> ni tabla <b>pesajes_evento</b>).';
        }
      }

      $conexion->commit();
      $_SESSION['flash_ok'] = '💾 Pesajes guardados ('.$guardados.').';
    } catch(Throwable $e){
      $conexion->rollback();
      $_SESSION['flash_error'] = 'Error guardando pesajes: '.$e->getMessage();
    }
    header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id); exit;
  }

  if ($accion === 'crear_manual') {
    $es_espera = isset($_POST['solo_rojo']) ? 1 : 0;
    $formato   = trim((string)($_POST['formato'] ?? ''));

    $r_apellido = trim((string)($_POST['r_apellido'] ?? ''));
    $r_nombre   = trim((string)($_POST['r_nombre'] ?? ''));
    $r_escuela  = trim((string)($_POST['r_escuela'] ?? ''));
    $r_edad     = ($_POST['r_edad'] ?? '') !== '' ? (int)$_POST['r_edad'] : null;
    $r_dni      = trim((string)($_POST['r_dni'] ?? ''));
    $r_peso     = ($_POST['r_peso'] ?? '') !== '' ? (float)$_POST['r_peso'] : null;

    $a_apellido = trim((string)($_POST['a_apellido'] ?? ''));
    $a_nombre   = trim((string)($_POST['a_nombre'] ?? ''));
    $a_escuela  = trim((string)($_POST['a_escuela'] ?? ''));
    $a_edad     = ($_POST['a_edad'] ?? '') !== '' ? (int)$_POST['a_edad'] : null;
    $a_dni      = trim((string)($_POST['a_dni'] ?? ''));
    $a_peso     = ($_POST['a_peso'] ?? '') !== '' ? (float)$_POST['a_peso'] : null;

    $sexo = strtoupper(trim((string)($_POST['sexo'] ?? '')));

    $disciplina_val = null;
    if (!empty($CE_DISC)) {
      if (isset($_POST['disciplina_value']) && is_numeric($_POST['disciplina_value'])) { $disciplina_val = (int)$_POST['disciplina_value']; }
      if ($REQ_DISC && $disciplina_val===null) { $_SESSION['flash_error'] = 'Falta seleccionar disciplina.'; header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit; }
    }

    $modalidad_val = null;
    if (!empty($CE_MODAL)) {
      if (isset($_POST['modalidad_value']) && is_numeric($_POST['modalidad_value'])) { $modalidad_val = (int)$_POST['modalidad_value']; }
      if ($tablaModal && $REQ_MODAL && $modalidad_val===null) { $_SESSION['flash_error'] = 'Falta seleccionar modalidad.'; header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit; }
    }

    $cat_tec_id = null;
    if (!empty($CE_CAT_TEC)) {
      if (isset($_POST['cat_tec_id']) && is_numeric($_POST['cat_tec_id'])) { $cat_tec_id = (int)$_POST['cat_tec_id']; }
    }

    $division_id = null;
    if (!empty($CE_DIV)) {
      if (isset($_POST['division_id']) && is_numeric($_POST['division_id'])) $division_id = (int)$_POST['division_id'];
      if ($REQ_DIV && $division_id===null) { $_SESSION['flash_error'] = 'Falta seleccionar división.'; header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit; }
    }

    if (!empty($CE_DNI) && $REQ_DNI) {
      if ($r_dni===''){ $_SESSION['flash_error'] = 'Falta DNI en esquina roja.'; header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit; }
      if (!$es_espera && $a_dni===''){ $_SESSION['flash_error'] = 'Falta DNI en esquina azul.'; header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit; }
    }
    if (!empty($CE_SEXO) && $REQ_SEXO && $sexo===''){ $_SESSION['flash_error'] = 'Seleccioná sexo.'; header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit; }

    // FIX: tomar rondas desde POST correctamente
    $rondas = isset($_POST['rondas']) && is_numeric($_POST['rondas']) ? (int)$_POST['rondas'] : 2;
    $obs_extra  = trim((string)($_POST['observaciones'] ?? ''));

    if ($r_apellido==='' || $r_nombre==='') {
      $_SESSION['flash_error'] = 'Completá Apellido y Nombre en esquina roja.'; header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
    }
    if (!$es_espera && ($a_apellido==='' || $a_nombre==='')) {
      $_SESSION['flash_error'] = 'Completá Apellido y Nombre en esquina azul (o tildá "Solo rojo (en espera)").'; header('Location: ver_peleas_evento.php?evento_id='.$evento_id); exit;
    }

    $warnings = [];
    $mapComp = [$CE_ID,$CE_EVENTO,$CE_DNI,$CE_APE,$CE_NOM,$CE_SEXO];
    $mapFght = [$C_ID,$C_EVENTO,$C_ROJO,$C_AZUL];

    $rid_exist = encontrar_competidor_existente($conexion, $mapComp, $evento_id, $r_dni, $r_apellido, $r_nombre, $sexo);
    if ($rid_exist) {
      $info = peleas_de_competidor($conexion, $mapFght, $evento_id, $rid_exist, 5);
      if ($info['count'] > 0) $warnings[] = h("⚠️ ROJO {$r_apellido} {$r_nombre}".($r_dni!=='' ? " (DNI $r_dni)" : "")." ya tiene {$info['count']} pelea(s): #".implode(', #', $info['ids']));
    }
    if (!$es_espera) {
      $aid_exist = encontrar_competidor_existente($conexion, $mapComp, $evento_id, $a_dni, $a_apellido, $a_nombre, $sexo);
      if ($aid_exist) {
        $info = peleas_de_competidor($conexion, $mapFght, $evento_id, $aid_exist, 5);
        if ($info['count'] > 0) $warnings[] = h("⚠️ AZUL {$a_apellido} {$a_nombre}".($a_dni!=='' ? " (DNI $a_dni)" : "")." ya tiene {$info['count']} pelea(s): #".implode(', #', $info['ids']));
      }
    }

    $conexion->begin_transaction();
    try {
      $r_id = insertar_competidor_min(
        $conexion,
        [$CE_ID,$CE_APE,$CE_NOM,$CE_ESC,$CE_EDAD,$CE_PESO,$CE_EVENTO,$CE_OBS,$CE_DNI,$CE_DISC,$CE_MODAL,$CE_DIV,$CE_FOTO,$CE_CAT_TEC,$CE_SEXO],
        ['evento_id'=>$evento_id,'apellido'=>$r_apellido,'nombre'=>$r_nombre,'escuela'=>$r_escuela,'edad'=>$r_edad,'dni'=>$r_dni,'sexo'=>$sexo,'disciplina_val'=>$disciplina_val,'modalidad_val'=>$modalidad_val,'division_id'=>$division_id,'cat_tec_id'=>$cat_tec_id,'peso'=>$r_peso,'obs'=>'']
      );

      $azul_id = null;
      if ($es_espera) {
        if ($REQ_AZUL) {
          $azul_id = obtener_o_crear_bye($conexion, [$CE_ID,$CE_APE,$CE_NOM,$CE_ESC,$CE_EDAD,$CE_PESO,$CE_EVENTO,$CE_OBS,$CE_DNI,$CE_DISC,$CE_MODAL,$CE_DIV,$CE_FOTO,$CE_CAT_TEC,$CE_SEXO], $evento_id, $sexo);
        } else {
          $azul_id = null;
        }
      } else {
        $azul_id = insertar_competidor_min(
          $conexion,
          [$CE_ID,$CE_APE,$CE_NOM,$CE_ESC,$CE_EDAD,$CE_PESO,$CE_EVENTO,$CE_OBS,$CE_DNI,$CE_DISC,$CE_MODAL,$CE_DIV,$CE_FOTO,$CE_CAT_TEC,$CE_SEXO],
          ['evento_id'=>$evento_id,'apellido'=>$a_apellido,'nombre'=>$a_nombre,'escuela'=>$a_escuela,'edad'=>$a_edad,'dni'=>$a_dni,'sexo'=>$sexo,'disciplina_val'=>$disciplina_val,'modalidad_val'=>$modalidad_val,'division_id'=>$division_id,'cat_tec_id'=>$cat_tec_id,'peso'=>$a_peso,'obs'=>'']
        );
      }

      $colsP = [bt($C_EVENTO), bt($C_ROJO)];
      $valsP = [$evento_id, $r_id];
      $types = 'ii';
      $colsP[] = bt($C_AZUL); $valsP[] = $azul_id!==null ? (int)$azul_id : null; $types .= 'i';
      if ($C_RONDAS) { $colsP[] = bt($C_RONDAS); $valsP[] = $rondas; $types .= 'i'; }

      $obsComp = [];
      if ($formato!=='') { $obsComp[] = strtoupper($formato); }
      $obsComp[] = 'Común: ' . ($sexo!==''?$sexo.' ':'') . ($division_id?('Div#'.$division_id):'');
      $obsComp[] = 'Rojo: '.($r_edad!==null?$r_edad.'a':'-').($r_escuela!==''?' - '.$r_escuela:'');
      if ($es_espera) $obsComp[] = 'Azul: (en espera)'; else $obsComp[] = 'Azul: '.($a_edad!==null?$a_edad.'a':'-').($a_escuela!==''?' - '.$a_escuela:'');
      $obsTxt = trim(($obs_extra!==''?$obs_extra.' | ':'').implode(' | ', $obsComp));
      if ($C_OBS) { $colsP[] = bt($C_OBS); $valsP[] = $obsTxt; $types .= 's'; }

      if ($valsP[2] === null){
        $sqlP = 'INSERT INTO peleas_evento ('.bt($C_EVENTO).','.bt($C_ROJO).','.bt($C_AZUL).($C_RONDAS?','.bt($C_RONDAS):'').($C_OBS?','.bt($C_OBS):'').') VALUES (?, ?, NULL'.($C_RONDAS?', ?':'').($C_OBS?', ?':'').')';
        $st = $conexion->prepare($sqlP);
        if(!$st) throw new RuntimeException('Prep pelea: '.$conexion->error);
        if ($C_RONDAS && $C_OBS) { $st->bind_param('iiss', $evento_id, $r_id, $rondas, $obsTxt); }
        elseif ($C_RONDAS && !$C_OBS) { $st->bind_param('iii', $evento_id, $r_id, $rondas); }
        elseif (!$C_RONDAS && $C_OBS) { $st->bind_param('iis', $evento_id, $r_id, $obsTxt); }
        else { $st->bind_param('ii', $evento_id, $r_id); }
      } else {
        $sqlP = 'INSERT INTO peleas_evento ('.implode(',', $colsP).') VALUES ('.implode(',', array_fill(0, count($colsP), '?')).')';
        $st = $conexion->prepare($sqlP);
        if(!$st) throw new RuntimeException('Prep pelea: '.$conexion->error);
        $bindTypes = 'iii'; $params = [$evento_id, $r_id, (int)$valsP[2]];
        if ($C_RONDAS){ $bindTypes.='i'; $params[]=(int)$rondas; }
        if ($C_OBS){ $bindTypes.='s'; $params[]=$obsTxt; }
        $st->bind_param($bindTypes, ...$params);
      }

      $okExec=$st->execute(); $errExec=$st->error; $aff=$st->affected_rows; $st->close();
      if (!$okExec || $aff <= 0) { throw new RuntimeException('Insert pelea falló: '.($errExec ?: 'sin detalle')); }

      $conexion->commit();
      $_SESSION['flash_ok'] = '✅ Pelea creada. Rojo #'.$r_id.($valsP[2]!==null?(' / Azul #'.$valsP[2]):' (en espera)').'.';
      if (!empty($warnings)) { $_SESSION['flash_warn'] = implode('<br>', $warnings); }
    } catch (Throwable $e) {
      $conexion->rollback();
      $_SESSION['flash_error'] = 'Error creando pelea: '.$e->getMessage();
    }
    header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id); exit;
  }

  if ($accion === 'delete' && $pelea_id > 0) {
    $sqlD = "DELETE FROM peleas_evento WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID ?: 'id')."=? LIMIT 1";
    $st=$conexion->prepare($sqlD);
    if ($st) { $st->bind_param('ii',$evento_id,$pelea_id); $st->execute(); $st->close(); }
    $_SESSION['flash_ok'] = '🗑️ Pelea eliminada.';
    header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id); exit;
  }
}

/* ========= listado de peleas ========= */
$orderPieces = [];
if ($C_ORDEN) $orderPieces[] = 'p.'.bt($C_ORDEN).' IS NULL';
if ($C_ORDEN) $orderPieces[] = 'p.'.bt($C_ORDEN);
if ($C_FECHA) $orderPieces[] = 'p.'.bt($C_FECHA);
$orderPieces[] = 'p.'.bt($C_ID ?: 'id');
$orderBy = implode(', ', $orderPieces);

$selectRondas = $C_RONDAS ? (', p.'.bt($C_RONDAS).' AS rondas') : ', NULL AS rondas';
$selectObs    = $C_OBS    ? (', p.'.bt($C_OBS).' AS observaciones') : ', NULL AS observaciones';
$selectOrden  = $C_ORDEN  ? (', p.'.bt($C_ORDEN).' AS orden_manual') : ', NULL AS orden_manual';

$selectParts = [];
$joins = [];
$selectParts[] = 'p.'.bt($C_ID ?: 'id').' AS pelea_id';
$selectParts[] = substr($selectOrden, 2);
$selectParts[] = substr($selectRondas, 2);
$selectParts[] = substr($selectObs, 2);

/* pesos reales */
$selectParts[] = $C_PESO_REAL_R ? 'p.'.bt($C_PESO_REAL_R).' AS peso_real_r' : "NULL AS peso_real_r";
$selectParts[] = $C_PESO_REAL_A ? 'p.'.bt($C_PESO_REAL_A).' AS peso_real_a' : "NULL AS peso_real_a";

/* competidores */
$selectParts[] = 'cr.'.bt($CE_APE ?: 'apellido').' AS r_apellido';
$selectParts[] = 'cr.'.bt($CE_NOM ?: 'nombre').' AS r_nombre';
$selectParts[] = $CE_ESC ? 'cr.'.bt($CE_ESC).' AS r_escuela' : "NULL AS r_escuela";
$selectParts[] = $CE_FOTO ? 'cr.'.bt($CE_FOTO).' AS r_foto'   : "NULL AS r_foto";
$selectParts[] = 'ca.'.bt($CE_APE ?: 'apellido').' AS a_apellido';
$selectParts[] = 'ca.'.bt($CE_NOM ?: 'nombre').' AS a_nombre';
$selectParts[] = $CE_ESC ? 'ca.'.bt($CE_ESC).' AS a_escuela' : "NULL AS a_escuela";
$selectParts[] = $CE_FOTO ? 'ca.'.bt($CE_FOTO).' AS a_foto'   : "NULL AS a_foto";

/* pesos declarados */
$selectParts[] = $CE_PESO ? 'cr.'.bt($CE_PESO).' AS r_peso' : 'NULL AS r_peso';
$selectParts[] = $CE_PESO ? 'ca.'.bt($CE_PESO).' AS a_peso' : 'NULL AS a_peso';

/* divisiones */
if ($tablaDiv && $CE_DIV) {
  $joins[] = "LEFT JOIN $tablaDiv dvr ON dvr.id = cr.".bt($CE_DIV);
  $joins[] = "LEFT JOIN $tablaDiv dva ON dva.id = ca.".bt($CE_DIV);
  $selectParts[] = 'dvr.'.bt($DIV_LABEL_COL).' AS r_division';
  $selectParts[] = 'dva.'.bt($DIV_LABEL_COL).' AS a_division';
} else {
  $selectParts[] = "NULL AS r_division";
  $selectParts[] = "NULL AS a_division";
}

/* modalidad */
if ($tablaModal && $CE_MODAL) {
  $joins[] = "LEFT JOIN $tablaModal mr ON mr.id = cr.".bt($CE_MODAL);
  $joins[] = "LEFT JOIN $tablaModal ma ON ma.id = ca.".bt($CE_MODAL);
  $selectParts[] = 'mr.'.bt($MOD_LABEL_COL).' AS r_modalidad';
  $selectParts[] = 'ma.'.bt($MOD_LABEL_COL).' AS a_modalidad';
} else {
  $selectParts[] = "NULL AS r_modalidad";
  $selectParts[] = "NULL AS a_modalidad";
}

/* categoría técnica */
if ($tablaTec && $CE_CAT_TEC) {
  $joins[] = "LEFT JOIN $tablaTec ctr ON ctr.id = cr.".bt($CE_CAT_TEC);
  $joins[] = "LEFT JOIN $tablaTec cta ON cta.id = ca.".bt($CE_CAT_TEC);
  $TEC_LABEL_COL = $tecCols['nombre'] ?? ($tecCols['name'] ?? ($tecCols['codigo'] ?? ($tecCols['nivel'] ?? ($tecCols['grado'] ?? ($tecCols['categoria'] ?? ($tecCols['etiqueta'] ?? ($tecCols['detalle'] ?? 'id')))))));
  $TEC_DESC_COL  = $tecCols['descripcion'] ?? ($tecCols['desc'] ?? ($tecCols['detalle'] ?? null));
  $selectParts[] = 'ctr.'.bt($TEC_LABEL_COL).' AS r_cat_tec';
  $selectParts[] = 'cta.'.bt($TEC_LABEL_COL).' AS a_cat_tec';
  if ($TEC_DESC_COL) { $selectParts[] = 'ctr.'.bt($TEC_DESC_COL).' AS r_cat_tec_desc'; $selectParts[] = 'cta.'.bt($TEC_DESC_COL).' AS a_cat_tec_desc'; }
  else { $selectParts[] = "NULL AS r_cat_tec_desc"; $selectParts[] = "NULL AS a_cat_tec_desc"; }
} else {
  $selectParts[] = "NULL AS r_cat_tec";
  $selectParts[] = "NULL AS a_cat_tec";
  $selectParts[] = "NULL AS r_cat_tec_desc";
  $selectParts[] = "NULL AS a_cat_tec_desc";
}

$sql = "SELECT
  ".implode(",\n  ", $selectParts)."
FROM peleas_evento p
JOIN competidores_evento cr ON p.".bt($C_ROJO)." = cr.".bt($CE_ID ?: 'id')."
LEFT JOIN competidores_evento ca ON p.".bt($C_AZUL)." = ca.".bt($CE_ID ?: 'id')."
".implode("\n", $joins)."
WHERE p.".bt($C_EVENTO)." = ?
ORDER BY $orderBy";
$st = $conexion->prepare($sql);
if (!$st) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">Error preparando la lista de peleas: '.h($conexion->error).'</div>'; exit; }
$st->bind_param('i', $evento_id);
$st->execute();
$peleas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* ========= clasificación/etiqueta por bloque ========= */
function clasificar_bloque($row){
  $modR = norm($row['r_modalidad'] ?? '');
  $modA = norm($row['a_modalidad'] ?? '');
  $obs  = norm($row['observaciones'] ?? '');
  $mod  = $modR.' '.$modA.' '.$obs;
  $isExhib = (bool)preg_match('/exhib/i', $mod);
  $isAma   = (bool)preg_match('/amateur|amat\b/i', $mod);
  $isProAm = (bool)preg_match('/pro[\s\-]?am/i', $mod);
  $isPro   = (bool)preg_match('/\bpro\b|prof(esional)?/i', $mod);
  $isK1    = (bool)preg_match('/\bk1\b/i', $mod);
  $isMMA   = (bool)preg_match('/\bmma\b/i', $mod);
  $isBox   = (bool)preg_match('/box|boxeo/i', $mod);
  $isLow   = (bool)preg_match('/low[\s\-]?kick/i', $mod);
  if ($isExhib && $isBox) return [1, 'Exhibición Boxeo'];
  if ($isExhib && $isLow) return [2, 'Exhibición Lowkick'];
  if ($isAma && $isBox)   return [3, 'Amateur Boxeo'];
  if ($isAma && $isLow)   return [4, 'Amateur Lowkick'];
  if ($isAma && $isK1)    return [5, 'Amateur K1'];
  if ($isProAm)           return [6, 'ProAm'];
  if ($isK1)              return [7, 'K1'];
  if ($isMMA)             return [8, 'MMA'];
  if ($isPro)             return [9, 'Pro'];
  return [99, 'Otros'];
}
$enumerado = [];
$contador = 1;
foreach ($peleas as $p) {
  [$prio, $lbl] = clasificar_bloque($p);
  $p['_bloque_lbl'] = $lbl;
  $p['_n_auto'] = $contador++;
  $enumerado[] = $p;
}
$peleas = $enumerado;

$ph = 'assets/placeholder-user.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🥊 Peleas del Evento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#ffffff; --card:#ffffff; --text:#000000; --muted:#000000; --line:#cbd5e1;
      --pill-bg:#e2e8f0; --pill-text:#000000;
      --ok-bg:#e8f5e9; --ok-bd:#c8e6c9; --er-bg:#ffebee; --er-bd:#ffcdd2; --wa-bg:#fff3cd; --wa-bd:#ffeeba;
      --btn:#1e88e5; --btn-text:#000000; --btn-sec-bg:#e5e7eb; --btn-sec-tx:#000000; --btn-dg:#d32f2f; --btn-dg-tx:#000000;
      --thead:#e2e8f0; --thead-text:#000000; --zebra:#f9fbff;
    }
    *,*::before,*::after{box-sizing:border-box}
    html,body{background:var(--bg);color:var(--text);line-height:1.4}
    a{color:inherit}
    .contenedor{max-width:1150px;margin:0 auto;padding:14px;}
    .toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:10px;background:#fff;border:1px solid #cbd5e1;border-radius:10px;padding:8px 10px}
    .toolbar h2{margin:0;color:#000;font-weight:700;letter-spacing:.2px}
    .orden-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .btn{display:inline-block;padding:7px 10px;border-radius:10px;border:0;cursor:pointer;text-decoration:none}
    .btn-primary{background:var(--btn);color:#fff !important}
    .btn-secondary{background:var(--btn-sec-bg);color:#000 !important}
    .btn-danger{background:var(--btn-dg);color:#fff !important}
    .btn-mini{padding:4px 8px;font-size:11px;border-radius:6px}
    .btn-xxs{padding:3px 6px;font-size:10.5px;border-radius:6px}
    .card{border:1px solid var(--line);border-radius:12px;padding:12px;margin-bottom:12px;background:var(--card);box-shadow:0 1px 2px rgba(0,0,0,.03)}
    .grid{display:grid;gap:8px}
    .grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    .grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
    .grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}
    .grid > *{min-width:0}
    .field{display:flex;flex-direction:column;gap:6px}
    .field label{font-size:12px;color:#111;font-weight:600;line-height:1.25}
    .field input,.field select,.orden-input{width:100%;height:38px;padding:8px 10px;border:1px solid #94a3b8;border-radius:8px;background:#fff;color:#000;line-height:1.25}
    .helper{font-size:11.5px;color:#111}
    .table-wrap{width:100%;overflow-x:auto;margin-top:6px}
    table{width:100%;border-collapse:collapse;min-width:1120px;background:var(--card)}
    th,td{border:1px solid var(--line);padding:8px 10px;vertical-align:middle}
    th{background:var(--thead);color:var(--thead-text);font-size:13.2px;white-space:normal}
    td{font-size:13px}
    tbody tr:nth-child(even){background:var(--zebra)}
    .avatar{width:44px;height:44px;object-fit:cover;border-radius:8px;display:inline-block;vertical-align:middle}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:var(--pill-bg);color:var(--pill-text);font-size:12px;white-space:normal}
    .muted{color:#000;font-size:12px}
    .acciones{text-align:center;white-space:nowrap}
    .vs{font-weight:700;text-align:center;color:#111}
    .row-actions{display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:wrap}
    .bloque{font-size:11.5px;color:#111}
    .num{font-weight:700}
    #form-orden .orden-input{width:64px;text-align:center;border-radius:8px;border:1px solid #94a3b8;padding:6px 8px;opacity:.85;pointer-events:none}
    #form-orden.editing .orden-input{opacity:1;pointer-events:auto}
    #orden-actions{display:none}
    #form-orden.editing #orden-actions{display:flex}
    .pesaje{display:block;font-size:12px;margin-top:4px}
    .pesaje input{width:90px;height:30px;padding:4px 6px;border:1px solid #94a3b8;border-radius:6px}
    .delta-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;margin-left:6px;border:1px solid #cbd5e1}
    .delta-ok{background:#e8f5e9} .delta-1{background:#fff3cd} .delta-2{background:#ffe0b2} .delta-dq{background:#ffebee}
    .table-wrap table col.num{ width:68px } .table-wrap table col.bloque{ width:170px } .table-wrap table col.foto{ width:58px }
    .table-wrap table col.nombre{ width:220px } .table-wrap table col.info{ width:320px } .table-wrap table col.escuela{ width:200px }
    .table-wrap table col.tecnica{ width:240px } .table-wrap table col.vs{ width:56px } .table-wrap table col.rondas{ width:84px }
    .table-wrap table col.obs{ width:auto } .table-wrap table col.acc{ width:220px }
    th.num,td.num,th.vs,td.vs,th.rondas,td.rondas{ text-align:center } td.acc{text-align:left}
    @media (max-width:980px){ .grid-4{grid-template-columns:1fr 1fr} table{min-width:980px} }
    @media (max-width:640px){ .grid-4,.grid-3,.grid-2{grid-template-columns:1fr} .toolbar{gap:10px} table{min-width:900px} .pesaje input{width:84px} }
    @media print{ .toolbar,.form-actions,.row-actions,.btn{display:none !important} body{background:#fff} table{min-width:100%} }
    .flash.ok{border:1px solid #c8e6c9;background:#e8f5e9;color:#1b5e20;padding:8px 10px;border-radius:8px;margin:8px 0}
    .flash.warn{border:1px solid #ffeeba;background:#fff3cd;color:#856404;padding:8px 10px;border-radius:8px;margin:8px 0}
    .flash.err{border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;padding:8px 10px;border-radius:8px;margin:8px 0}
  </style>
  
</head>
<body>
<div class="contenedor">
  <div class="toolbar">
    <h2>📋 Peleas programadas — Evento #<?= (int)$evento_id ?></h2>
    <div class="orden-tools">
      <?php if ($C_ORDEN) { ?>
        <button class="btn btn-secondary" type="button" id="btnEditarOrden">✏️ Editar numeración</button>
      <?php } else { ?>
        <span class="helper">ℹ️ Para numeración manual, agregá una columna <code>orden</code> (INT) en <b>peleas_evento</b>.</span>
      <?php } ?>
      <a class="btn btn-mini btn-secondary" href="organizar_pelea.php?evento_id=<?= (int)$evento_id ?>">➕ Nueva pelea</a>
      <a class="btn btn-mini btn-secondary" href="pesajes.php?evento_id=<?= (int)$evento_id ?>">⚖️ Pesajes</a>
      <button class="btn btn-mini btn-secondary" type="button" onclick="window.print()">🖨️ Imprimir / PDF</button>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_ok'])) { ?><div class="flash ok"><?= h($_SESSION['flash_ok']); ?></div><?php unset($_SESSION['flash_ok']); } ?>
  <?php if (!empty($_SESSION['flash_warn'])) { ?><div class="flash warn"><?= $_SESSION['flash_warn']; ?></div><?php unset($_SESSION['flash_warn']); } ?>
  <?php if (!empty($_SESSION['flash_error'])) { ?><div class="flash err"><?= h($_SESSION['flash_error']); ?></div><?php unset($_SESSION['flash_error']); } ?>

  <!-- ============== Carga desde archivo (opcional) ============== -->
  <div class="card">
    <h3 style="margin:0 0 10px 0">📥 Subir listado de peleas (Excel/CSV o PDF)</h3>
    <form method="POST" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="accion" value="importar_peleas">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
      <div class="grid grid-3">
        <div class="field">
          <label>Archivo</label>
          <input type="file" name="archivo_peleas" accept=".xlsx,.csv,.pdf" required>
          <div class="helper">.xlsx / .csv (se importan filas) · .pdf (se guarda como referencia)</div>
        </div>
        <div class="field" style="align-self:end;">
          <button class="btn btn-primary" type="submit">⬆️ Subir e importar</button>
        </div>
      </div>
      <div class="helper">Encabezados sugeridos: <code>r_apellido</code>, <code>r_nombre</code>, <code>r_escuela</code>, <code>r_dni</code>, <code>r_peso</code>, <code>a_apellido</code>, <code>a_nombre</code>, <code>a_escuela</code>, <code>a_dni</code>, <code>a_peso</code>, <code>rondas</code>, <code>observaciones</code>, <code>sexo</code>, <code>division_id</code>, <code>modalidad_id</code>, <code>disciplina_id</code>, <code>categoria_tecnica_id</code>, <code>solo_rojo</code>.</div>
    </form>
  </div>
  <!-- ============== FIN ============== -->

  <div class="card">
    <h3 style="margin:0 0 10px 0">⚡ Alta manual rápida de competidores + pelea</h3>
    <form method="POST" autocomplete="off" id="form-pelea">
      <input type="hidden" name="accion" value="crear_manual">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
      <div class="grid grid-2">
        <div>
          <h4 style="margin:0 0 6px 0">🔴 Esquina Roja</h4>
          <div class="grid grid-3">
            <div class="field"><label>Apellido*</label><input name="r_apellido" required></div>
            <div class="field"><label>Nombre*</label><input name="r_nombre" required></div>
            <div class="field"><label>DNI<?= ($CE_DNI && $REQ_DNI)?'*':'' ?></label><input name="r_dni" <?= ($CE_DNI && $REQ_DNI)?'required':'' ?> inputmode="numeric" pattern="[0-9]*"></div>
          </div>
          <div class="grid grid-3">
            <div class="field"><label>Escuela</label><input name="r_escuela" placeholder="Scorpion"></div>
            <div class="field"><label>Edad</label><input name="r_edad" type="number" min="0" step="1" placeholder="22"></div>
            <div class="field"><label>Peso (kg)</label><input name="r_peso" type="number" step="0.1" min="0" placeholder="70.5"></div>
          </div>
        </div>
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <h4 style="margin:0 0 6px 0">🔵 Esquina Azul</h4>
            <label class="helper" style="display:flex;align-items:center;gap:6px"><input type="checkbox" id="solo_rojo" name="solo_rojo"> Solo rojo (en espera)</label>
          </div>
          <div class="grid grid-3">
            <div class="field"><label>Apellido*</label><input name="a_apellido" id="a_apellido"></div>
            <div class="field"><label>Nombre*</label><input name="a_nombre" id="a_nombre"></div>
            <div class="field"><label>DNI<?= ($CE_DNI && $REQ_DNI)?'*':'' ?></label><input name="a_dni" id="a_dni" inputmode="numeric" pattern="[0-9]*"></div>
          </div>
          <div class="grid grid-3">
            <div class="field"><label>Escuela</label><input name="a_escuela" id="a_escuela" placeholder="La Academia"></div>
            <div class="field"><label>Edad</label><input name="a_edad" id="a_edad" type="number" min="0" step="1" placeholder="22"></div>
            <div class="field"><label>Peso (kg)</label><input name="a_peso" id="a_peso" type="number" step="0.1" min="0" placeholder="72.0"></div>
          </div>
        </div>
      </div>

      <div class="grid grid-4" style="margin-top:8px">
        <?php if (!empty($CE_SEXO)) { ?>
        <div class="field">
          <label>Sexo<?= $REQ_SEXO?'*':'' ?></label>
          <select name="sexo" id="sexo" <?= $REQ_SEXO?'required':'' ?>>
            <option value="">—</option>
            <option value="M">Masculino</option>
            <option value="F">Femenino</option>
          </select>
        </div>
        <?php } ?>

        <?php if (!empty($CE_DISC)) { ?>
        <div class="field">
          <label>Disciplina<?= $REQ_DISC?'*':'' ?></label>
          <?php if (!empty($disciplinas)) { ?>
            <select name="disciplina_value" <?= $REQ_DISC?'required':'' ?>>
              <option value="">—</option>
              <?php foreach($disciplinas as $d){ ?><option value="<?= (int)$d['id'] ?>"><?= h($d['nombre']) ?></option><?php } ?>
            </select>
          <?php } else { ?>
            <input name="disciplina_value" type="number" min="1" <?= $REQ_DISC?'required':'' ?> placeholder="ID de disciplina">
          <?php } ?>
        </div>
        <?php } ?>

        <?php if (!empty($CE_MODAL)) { ?>
        <div class="field">
          <label>Modalidad<?= $REQ_MODAL?'*':'' ?></label>
          <?php if (!empty($modalidades)) { ?>
            <select name="modalidad_value" <?= $REQ_MODAL?'required':'' ?>>
              <option value="">—</option>
              <?php foreach($modalidades as $d){ ?><option value="<?= (int)$d['id'] ?>"><?= h($d['nombre']) ?></option><?php } ?>
            </select>
          <?php } else { ?>
            <input name="modalidad_value" type="number" min="1" <?= $REQ_MODAL?'required':'' ?> placeholder="ID de modalidad">
          <?php } ?>
        </div>
        <?php } ?>

        <?php if (!empty($CE_CAT_TEC)) { ?>
        <div class="field">
          <label>Categoría técnica</label>
          <?php if (!empty($tecnicas)) { ?>
            <select name="cat_tec_id">
              <option value="">—</option>
              <?php foreach($tecnicas as $t){ $lbl = ($t['nombre'] ?? ''); if (!empty($t['descripcion'])) { $lbl .= ' — '.$t['descripcion']; } ?>
                <option value="<?= (int)$t['id'] ?>"><?= h($lbl) ?></option>
              <?php } ?>
            </select>
          <?php } else { ?>
            <input name="cat_tec_id" type="number" min="1" placeholder="ID categoría técnica">
          <?php } ?>
        </div>
        <?php } ?>

        <?php if (!empty($CE_DIV)) { ?>
        <div class="field">
          <label>División<?= $REQ_DIV?'*':'' ?></label>
          <?php if (!empty($divisiones)) { ?>
            <select name="division_id" <?= $REQ_DIV?'required':'' ?>>
              <option value="">—</option>
              <?php foreach($divisiones as $dv){ ?><option value="<?= (int)$dv['id'] ?>"><?= h($dv['nombre']) ?></option><?php } ?>
            </select>
          <?php } else { ?>
            <input name="division_id" type="number" min="1" <?= $REQ_DIV?'required':'' ?> placeholder="ID división">
          <?php } ?>
        </div>
        <?php } ?>

        <div class="field">
          <label>Formato</label>
          <select name="formato" id="formato">
            <option value="">Normal</option>
            <option value="triangular">Triangular</option>
            <option value="cuadrangular">Cuadrangular</option>
          </select>
          <div class="helper">Se agrega a Observaciones (p. ej. “TRIANGULAR”).</div>
        </div>

        <div class="field"><label>Rondas</label><input name="rondas" type="number" min="1" max="12" value="2"></div>
        <div class="field" style="grid-column:span 1"><label>Observaciones (opcional)</label><input name="observaciones" placeholder="Ej: Semifinal A"></div>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">⬆️ Cargar pelea</button>
        <button class="btn btn-secondary" type="reset">↺ Limpiar</button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <!-- Un solo form para orden y pesajes -->
    <form method="POST" id="form-orden">
      <input type="hidden" id="accionInput" name="accion" value="guardar_orden">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
      <table>
        <colgroup>
          <col class="num"><col class="bloque">
          <col class="foto"><col class="nombre"><col class="info"><col class="escuela"><col class="tecnica">
          <col class="vs">
          <col class="foto"><col class="nombre"><col class="info"><col class="escuela"><col class="tecnica">
          <col class="rondas"><col class="obs"><col class="acc">
        </colgroup>
        <thead>
          <tr>
            <th style="width:70px">N°</th>
            <th>Bloque</th>
            <th colspan="5">Esquina Roja</th>
            <th class="vs">VS</th>
            <th colspan="5">Esquina Azul</th>
            <th>Rondas</th>
            <th>Obs.</th>
            <th class="acciones">Acciones</th>
          </tr>
          <tr>
            <th></th><th></th>
            <th>Foto</th><th>Nombre</th><th>Info</th><th>Escuela</th><th>Técnica</th>
            <th></th>
            <th>Foto</th><th>Nombre</th><th>Info</th><th>Escuela</th><th>Técnica</th>
            <th></th><th></th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$peleas) { ?>
          <tr><td colspan="16">No hay peleas programadas todavía.</td></tr>
        <?php } else { foreach ($peleas as $p){
          $rFoto = !empty($p['r_foto']) ? $p['r_foto'] : $ph;
          $aFoto = !empty($p['a_foto']) ? $p['a_foto'] : $ph;
          $rPesoTxt = ($p['r_peso']!==null && $p['r_peso']!=='') ? fmt_num($p['r_peso']).' kg' : '—';
          $aPesoTxt = ($p['a_peso']!==null && $p['a_peso']!=='') ? fmt_num($p['a_peso']).' kg' : '—';
          $rInfo = trim($rPesoTxt.' / '.($p['r_division'] ?? '-'));
          $aInfo = trim($aPesoTxt.' / '.($p['a_division'] ?? '-'));
          $rondasVal = isset($p['rondas']) && is_numeric($p['rondas']) ? (int)$p['rondas'] : 2;
          $obsVal = (string)($p['observaciones'] ?? '');
          $rTec = trim((string)($p['r_cat_tec'] ?? '')); if (!empty($p['r_cat_tec_desc'])) { $rTec .= ($rTec!==''?' — ':'').$p['r_cat_tec_desc']; }
          $aTec = trim((string)($p['a_cat_tec'] ?? '')); if (!empty($p['a_cat_tec_desc'])) { $aTec .= ($aTec!==''?' — ':'').$p['a_cat_tec_desc']; }
          $nroMostrar = $p['orden_manual']!==null ? (int)$p['orden_manual'] : (int)$p['_n_auto'];

          $pref_r = $p['peso_real_r'] ?? ($_SESSION['pesajes'][$evento_id][$p['pelea_id']]['r'] ?? '');
          $pref_a = $p['peso_real_a'] ?? ($_SESSION['pesajes'][$evento_id][$p['pelea_id']]['a'] ?? '');
        ?>
          <tr>
            <td class="num">
              <?php if ($C_ORDEN) { ?>
                <input class="orden-input" type="number" name="orden[<?= (int)$p['pelea_id'] ?>]" value="<?= h($p['orden_manual']) ?>" disabled>
              <?php } else { ?><?= (int)$nroMostrar ?><?php } ?>
            </td>
            <td class="bloque"><?= h($p['_bloque_lbl']) ?></td>

            <td><img src="<?= h($rFoto) ?>" class="avatar" alt="Roja"></td>
            <td><?= h($p['r_apellido'].' '.$p['r_nombre']) ?></td>
            <td>
              <span class="pill"><?= h($rInfo) ?></span>
              <div class="pesaje">
                Real:
                <input type="number" step="0.1" min="0"
                  name="peso_real_r[<?= (int)$p['pelea_id'] ?>]"
                  class="peso-real" data-side="r" data-pelea="<?= (int)$p['pelea_id'] ?>"
                  placeholder="kg" value="<?= h($pref_r) ?>">
                <span class="delta-pill" id="delta_r_<?= (int)$p['pelea_id'] ?>">Δ —</span>
              </div>
            </td>
            <td class="muted"><?= h($p['r_escuela'] ?? '-') ?></td>
            <td class="muted"><?= h($rTec !== '' ? $rTec : '-') ?></td>

            <td class="vs">vs</td>

            <td><img src="<?= h($aFoto) ?>" class="avatar" alt="Azul"></td>
            <td><?= h(trim(($p['a_apellido']??'').' '.($p['a_nombre']??'')) ?: '—') ?></td>
            <td>
              <span class="pill"><?= h($aInfo) ?></span>
              <div class="pesaje">
                Real:
                <input type="number" step="0.1" min="0"
                  name="peso_real_a[<?= (int)$p['pelea_id'] ?>]"
                  class="peso-real" data-side="a" data-pelea="<?= (int)$p['pelea_id'] ?>"
                  placeholder="kg" value="<?= h($pref_a) ?>">
                <span class="delta-pill" id="delta_a_<?= (int)$p['pelea_id'] ?>">Δ —</span>
              </div>
            </td>
            <td class="muted"><?= h($p['a_escuela'] ?? '-') ?></td>
            <td class="muted"><?= h($aTec !== '' ? $aTec : '-') ?></td>

            <td><?= (int)$rondasVal ?></td>
            <td><?= h($obsVal) ?></td>
            <td class="acciones">
              <div class="row-actions">
                <a class="btn btn-xxs btn-primary" title="Editar" href="editar_pelea.php?evento_id=<?= (int)$evento_id ?>&pelea_id=<?= (int)$p['pelea_id'] ?>">✏️ Editar</a>

                <!-- ELIMINAR: sin form anidado; botón + JS -->
                <button type="button" class="btn btn-xxs btn-danger"
                        title="Eliminar"
                        onclick="eliminarPelea(<?= (int)$p['pelea_id'] ?>)">
                  🗑️ Eliminar
                </button>

                <a
                  class="btn btn-xxs btn-secondary"
                  title="Iniciar en vivo"
                  href="combate_en_vivo.php?evento_id=<?= (int)$evento_id ?>
                        &pelea_id=<?= (int)$p['pelea_id'] ?>
                        &nro=<?= (int)$nroMostrar ?>
                        <?= $C_RONDAS ? '&rondas='.(int)$rondasVal : '' ?>
                        &dur=180&rest=60">
                  ▶️ Iniciar
                </a>
              </div>
            </td>
          </tr>
        <?php } } ?>
        </tbody>
      </table>

      <div class="form-actions" id="orden-actions" style="margin-top:10px">
        <button class="btn btn-secondary" type="button" id="btnAutoSec">↻ Auto-secuenciar</button>
        <button class="btn btn-primary" type="button" id="btnGuardarOrden">💾 Guardar numeración</button>
      </div>

      <div class="form-actions" style="margin-top:10px; justify-content:space-between">
        <div class="helper">Regla de pesaje: ≤0.5kg ✅ en peso · ≤1.0kg −1 punto · ≤1.5kg −2 puntos · ≥2.0kg ❌ descalificado.</div>
        <div>
          <button class="btn btn-primary" type="button" id="btnGuardarPesajes">💾 Guardar pesajes</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  // Eliminar pelea (POST aislado, sin forms anidados)
  window.eliminarPelea = function(peleaId){
    if(!confirm('¿Eliminar esta pelea? Esta acción no se puede deshacer.')) return;
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = 'ver_peleas_evento.php';
    const add = (n,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; f.appendChild(i); };
    add('accion','delete');
    add('evento_id','<?= (int)$evento_id ?>');
    add('pelea_id', String(peleaId));
    document.body.appendChild(f);
    f.submit();
  };

  // Toggle SOLO ROJO
  const chk = document.getElementById('solo_rojo');
  const blueIds = ['a_apellido','a_nombre','a_dni','a_escuela','a_edad','a_peso'];
  function setBlue(disabled){
    blueIds.forEach(id=>{
      const el=document.getElementById(id);
      if(!el) return;
      el.disabled = !!disabled;
      if(disabled){ el.removeAttribute('required'); el.value=''; }
    });
  }
  if(chk){ chk.addEventListener('change', ()=> setBlue(chk.checked)); setBlue(chk.checked); }

  // Numeración manual
  const btnEditar = document.getElementById('btnEditarOrden');
  const formOrden = document.getElementById('form-orden');
  const inputsOrden = document.querySelectorAll('#form-orden .orden-input');
  const btnAuto = document.getElementById('btnAutoSec');
  const accionInput = document.getElementById('accionInput');
  const btnGuardarOrden = document.getElementById('btnGuardarOrden');
  const btnGuardarPesajes = document.getElementById('btnGuardarPesajes');

  function setEditing(on){
    if(!formOrden) return;
    if(on){ formOrden.classList.add('editing'); }
    else  { formOrden.classList.remove('editing'); }
    inputsOrden.forEach(i=> i.disabled = !on);
    if(btnEditar){ btnEditar.textContent = on ? '🙈 Terminar edición' : '✏️ Editar numeración'; }
    document.getElementById('orden-actions').style.display = on ? 'flex' : 'none';
  }
  if(btnEditar){ btnEditar.addEventListener('click', ()=> setEditing(!formOrden.classList.contains('editing'))); }
  if(btnAuto){
    btnAuto.addEventListener('click', ()=>{
      const inputs = document.querySelectorAll('#form-orden .orden-input');
      let n=1; inputs.forEach(i=> i.value = n++);
    });
  }
  if (btnGuardarOrden) {
    btnGuardarOrden.addEventListener('click', ()=>{
      accionInput.value = 'guardar_orden';
      formOrden.submit();
    });
  }
  if (btnGuardarPesajes) {
    btnGuardarPesajes.addEventListener('click', ()=>{
      accionInput.value = 'guardar_pesajes';
      formOrden.submit();
    });
  }
  setEditing(false);

  // Pesaje: diferencia vs declarado
  function parseKg(s){ const n = parseFloat((s||'').toString().replace(',', '.')); return isNaN(n)?null:n; }
  function regla(diffKg){
    if (diffKg === null) return {txt:'Δ —', cls:''};
    const d = Math.abs(diffKg);
    if (d <= 0.5) return {txt:`Δ ${d.toFixed(1)} kg · ✅ En peso`, cls:'delta-ok'};
    if (d <= 1.0) return {txt:`Δ ${d.toFixed(1)} kg · −1 punto`, cls:'delta-1'};
    if (d <= 1.5) return {txt:`Δ ${d.toFixed(1)} kg · −2 puntos`, cls:'delta-2'};
    return {txt:`Δ ${d.toFixed(1)} kg · ❌ Descalificado`, cls:'delta-dq'};
  }
  function declaradoDesdeChip(chipText){
    const m = (chipText||'').match(/([\d\.,]+)\s*kg/i);
    return m ? parseKg(m[1]) : null;
  }
  function actualizarFila(input){
    const peleaId = input.getAttribute('data-pelea');
    const side = input.getAttribute('data-side');
    const td = input.closest('td');
    if (!td) return;
    const chip = td.querySelector('.pill');
    const declared = chip ? declaradoDesdeChip(chip.textContent) : null;
    const real = parseKg(input.value);
    const deltaEl = td.querySelector(`#delta_${side}_${peleaId}`);
    const res = regla(real!==null && declared!==null ? real - declared : null);
    if (deltaEl){
      deltaEl.textContent = res.txt;
      deltaEl.classList.remove('delta-ok','delta-1','delta-2','delta-dq');
      if (res.cls) deltaEl.classList.add(res.cls);
    }
    try{
      const key = `pesaje:<?= (int)$evento_id ?>:${peleaId}:${side}`;
      if (input.value === '') localStorage.removeItem(key); else localStorage.setItem(key, input.value);
    }catch(e){}
  }
  document.querySelectorAll('input.peso-real').forEach(inp=>{
    try{
      const key = `pesaje:<?= (int)$evento_id ?>:${inp.getAttribute('data-pelea')}:${inp.getAttribute('data-side')}`;
      const saved = localStorage.getItem(key);
      if (saved && !inp.value) inp.value = saved;
    }catch(e){}
    actualizarFila(inp);
    inp.addEventListener('input', ()=> actualizarFila(inp));
  });
})();
</script>
</body>
</html>
