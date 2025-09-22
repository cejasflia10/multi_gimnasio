<?php
// recibir_competidores.php — Importa competidores (CSV/XLSX*), soporta "Apellido y Nombre",
// mantiene evento y no redirige a ver_competidores.
// *XLSX requiere php-zip + simplexml o phpoffice/phpspreadsheet; si no, exportá a CSV.

if (session_status() === PHP_SESSION_NONE) session_start();
@set_time_limit(600); // evita timeout en importes grandes
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ==== Helpers ==== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }

/* has_col con CACHÉ para evitar timeouts */
function has_col(mysqli $db, string $table, string $col): bool {
  static $cache = [];                           // cache por tabla
  $t = strtolower($table);
  $c = strtolower($col);

  if (!isset($cache[$t])) {
    $cols = [];
    if ($res = $db->query("SHOW COLUMNS FROM `$table`")) {
      while ($row = $res->fetch_assoc()) $cols[] = strtolower($row['Field'] ?? '');
      $res->close();
    }
    $cache[$t] = $cols;
  }
  return in_array($c, $cache[$t], true);
}

function existe_dni_evento(mysqli $db, int $evento_id, string $dni): bool {
  $t='competidores_evento';
  if(!has_col($db,$t,'dni')) return false;
  if (has_col($db,$t,'evento_id')) { $st=$db->prepare("SELECT 1 FROM `$t` WHERE evento_id=? AND dni=? LIMIT 1"); $st->bind_param('is',$evento_id,$dni); }
  else { $st=$db->prepare("SELECT 1 FROM `$t` WHERE dni=? LIMIT 1"); $st->bind_param('s',$dni); }
  $st->execute(); $r=$st->get_result(); $ok=$r && $r->num_rows>0; $st->close(); return $ok;
}

/* FKs y lookups con CACHÉ */
function fk_first_id(mysqli $db, string $table): ?int {
  static $cache = [];
  $t = strtolower($table);
  if (array_key_exists($t, $cache)) return $cache[$t];
  $res=$db->query("SELECT id FROM `$table` ORDER BY id ASC LIMIT 1");
  $cache[$t] = ($res && $row=$res->fetch_assoc()) ? (int)$row['id'] : null;
  return $cache[$t];
}
function fk_ensure_id(mysqli $db, string $table, ?int $id): ?int {
  static $valid = [];
  $t = strtolower($table);
  $id = $id ?? 0;
  if ($id > 0) {
    if (!isset($valid[$t][$id])) {
      if ($st=$db->prepare("SELECT 1 FROM `$table` WHERE id=? LIMIT 1")){
        $st->bind_param('i',$id); $st->execute();
        $valid[$t][$id] = (($r=$st->get_result()) && $r->num_rows>0);
        $st->close();
      } else { $valid[$t][$id] = false; }
    }
    if (!empty($valid[$t][$id])) return $id;
  }
  return fk_first_id($db,$table);
}
function id_by_nombre(mysqli $db, string $table, string $nombre): ?int {
  static $cache = [];
  $key = strtolower($table).'|'.mb_strtolower(trim((string)$nombre), 'UTF-8');
  if (isset($cache[$key])) return $cache[$key];
  if(!has_col($db,$table,'nombre')||!has_col($db,$table,'id')) return $cache[$key]=null;
  if($st=$db->prepare("SELECT id FROM `$table` WHERE LOWER(nombre)=LOWER(?) LIMIT 1")){
    $st->bind_param('s',$nombre); $st->execute();
    $r=$st->get_result(); $id=($r&&$r->num_rows)?(int)$r->fetch_assoc()['id']:null; $st->close();
    return $cache[$key] = $id;
  }
  return $cache[$key] = null;
}

/* Insert con CACHÉ de columnas */
function insertar_competidor(mysqli $db, array $row): int {
  static $coldefs = null;                     // cache único
  $t='competidores_evento';

  if ($coldefs === null) {
    $cand = [
      'evento_id'=>'i','apellido'=>'s','nombre'=>'s','dni'=>'s','fecha_nacimiento'=>'s','edad'=>'i','sexo'=>'s',
      'escuela_nombre'=>'s','provincia'=>'s','localidad'=>'s',
      'modalidad_id'=>'i','disciplina_id'=>'i','categoria_tecnica_id'=>'i','division_id'=>'i','categoria_peso_id'=>'i',
      'wins'=>'i','losses'=>'i','draws'=>'i','no_contest'=>'i','categoria_tecnica'=>'s','division'=>'s'
    ];
    $coldefs = [];
    foreach ($cand as $c=>$tp) if (has_col($db,$t,$c)) $coldefs[$c] = $tp;
    if (!$coldefs) throw new Exception('Tabla competidores_evento sin columnas esperadas');
  }

  $cols=[]; $vals=[]; $types='';
  foreach ($coldefs as $c=>$tp) { $cols[]="`$c`"; $vals[]=$row[$c]??null; $types.=$tp; }
  $ph=rtrim(str_repeat('?,',count($cols)),','); $sql="INSERT INTO `$t`(".implode(',',$cols).") VALUES($ph)";
  $st=$db->prepare($sql); if(!$st) throw new Exception($db->error);
  $bind=[$types]; foreach($vals as $k=>&$v){ $bind[]=&$v; }
  call_user_func_array([$st,'bind_param'],$bind);
  if(!$st->execute()) throw new Exception($st->error);
  $id=(int)$db->insert_id; $st->close(); return $id;
}

function cat_tecnica_por_total(int $t): string { return $t>=10?'A':($t>=5?'B':($t>=1?'C':'N')); }

/* Normalizadores */
function normaliza_header($s){
  $s=(string)$s;
  // quita BOM si viene en CSV UTF-8
  $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
  $s = mb_strtolower(trim($s),'UTF-8');
  $s = str_replace(['á','é','í','ó','ú','ñ'],['a','e','i','o','u','n'],$s);
  $s = preg_replace('~[^a-z0-9/ ]+~','',$s);
  $s = preg_replace('~\s+~',' ',$s);
  return $s;
}
function normaliza_sexo($s){
  $s=mb_strtolower(trim((string)$s),'UTF-8');
  if(in_array($s,['m','masc','hombre','masculino']))return'masculino';
  if(in_array($s,['f','fem','mujer','femenino']))return'femenino';
  return null;
}
function normaliza_fecha($s){
  $s=trim((string)$s); if($s==='')return null;
  if(preg_match('~^\d{4}-\d{2}-\d{2}$~',$s))return $s;
  if(preg_match('~^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$~',$s,$m)){
    $d=str_pad($m[1],2,'0',STR_PAD_LEFT); $M=str_pad($m[2],2,'0',STR_PAD_LEFT); return $m[3].'-'.$M.'-'.$d;
  }
  return null;
}

/* Split "Apellido y Nombre" */
function split_apynom($s): array {
  $s=preg_replace('/\s+/',' ',trim((string)$s));
  if($s==='') return [null,null];
  if(strpos($s,',')!==false){ $p=explode(',',$s,2); return [trim($p[0]), trim($p[1])]; }
  foreach([' / ',' - ',' | '] as $sep){ if(strpos($s,$sep)!==false){ $p=explode($sep,$s,2); return [trim($p[0]), trim($p[1])]; } }
  $parts=explode(' ', $s);
  if(count($parts)>=2){ $apellido=array_shift($parts); $nombre=implode(' ',$parts); return [trim($apellido), trim($nombre)]; }
  return [null,null];
}

/* Lectores desde archivo temporal */
function leer_csv_rows_tmp(string $tmp, bool $con_headers=true): array {
  if(!is_readable($tmp)) return [];
  $rows=[]; $first='';
  if(($f=fopen($tmp,'r'))){ $first=fgets($f); fclose($f); }
  $delim = ($first && substr_count($first,',')>substr_count($first,';')) ? ',' : ';';
  if(($h=fopen($tmp,'r'))){
    $i=0; $headers=[];
    while(($data=fgetcsv($h,0,$delim))!==false){
      if($i===0 && $con_headers){ $headers=array_map('normaliza_header',$data); }
      else { $rows[] = ($con_headers && $headers) ? array_combine($headers, array_pad($data,count($headers),'')) : $data; }
      $i++;
    } fclose($h);
  } return $rows;
}
function leer_xlsx_rows_tmp(string $tmp, bool $con_headers=true): array {
  if(!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) return [];
  try{
    $spread=\PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
    $sheet=$spread->getActiveSheet(); $rs=[]; $headers=[]; $first=true;
    foreach($sheet->toArray(null,true,true,true) as $r){
      $vals=array_values($r);
      if($first && $con_headers){ $headers=array_map('normaliza_header',$vals); $first=false; continue; }
      $rs[]= ($con_headers && $headers) ? array_combine($headers, array_pad($vals,count($headers),'')) : $vals; $first=false;
    } return $rs;
  }catch(\Throwable $e){ return []; }
}

/* ==== Resolver evento (GET → POST → SESSION → fallbacks) ==== */
$evento_id = 0;
if (isset($_GET['evento_id']) && ctype_digit((string)$_GET['evento_id'])) {
  $evento_id = (int)$_GET['evento_id'];
  $_SESSION['evento_id_actual'] = $evento_id;
}
if ($evento_id<=0 && isset($_POST['evento_id']) && ctype_digit((string)$_POST['evento_id'])) {
  $evento_id = (int)$_POST['evento_id'];
  $_SESSION['evento_id_actual'] = $evento_id;
}
if ($evento_id<=0) { $evento_id = (int)($_SESSION['evento_id_actual'] ?? 0); }
if ($evento_id<=0 && $conexion->query("SHOW TABLES LIKE 'eventos_deportivos'")->num_rows) {
  $q1=$conexion->query("SELECT id FROM eventos_deportivos WHERE COALESCE(activo,1)=1 LIMIT 2");
  if ($q1 && $q1->num_rows===1) { $evento_id = (int)$q1->fetch_assoc()['id']; $_SESSION['evento_id_actual']=$evento_id; }
  if ($evento_id<=0) { $q2=$conexion->query("SELECT id FROM eventos_deportivos ORDER BY COALESCE(fecha, created_at, '1970-01-01') DESC LIMIT 1");
    if ($q2 && $row=$q2->fetch_assoc()) { $evento_id=(int)$row['id']; $_SESSION['evento_id_actual']=$evento_id; } }
}

/* ==== Plantilla CSV ==== */
if (isset($_GET['plantilla']) && $_GET['plantilla']==='csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="plantilla_competidores.csv"');
  $out=fopen('php://output','w'); fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, ['Apellido','Nombre','DNI','Fecha de Nacimiento','Sexo','Provincia','Localidad','Ganadas','Perdidas','Empates','SN','Escuela o Gimnasio','Modalidad','Disciplina'], ';');
  fputcsv($out, ['Pérez','Juan','30111222','1999-05-21','Masculino','San Luis','Concarán','3','1','0','0','Panther Gym','K1','Amateurs'], ';');
  fclose($out); exit;
}

/* ==== POST: importar (vuelve a esta misma pantalla) ==== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $con_headers = (post('con_encabezados')!==''); // checkbox marcada = tiene encabezados
  if ($evento_id<=0){ $_SESSION['flash_error']='Falta evento_id.'; header('Location: '.$_SERVER['PHP_SELF']); exit; }

  if (!isset($_FILES['archivo'])) { $_SESSION['flash_error']='No llegó el archivo.'; header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit; }
  $err=(int)($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($err!==UPLOAD_ERR_OK){
    $map=[ UPLOAD_ERR_INI_SIZE=>'Supera upload_max_filesize', UPLOAD_ERR_FORM_SIZE=>'Supera MAX_FILE_SIZE',
      UPLOAD_ERR_PARTIAL=>'Carga incompleta', UPLOAD_ERR_NO_FILE=>'Sin archivo',
      UPLOAD_ERR_NO_TMP_DIR=>'Falta /tmp', UPLOAD_ERR_CANT_WRITE=>'No se pudo escribir', UPLOAD_ERR_EXTENSION=>'Extensión bloqueó la subida'
    ];
    $_SESSION['flash_error']='Error al subir: '.($map[$err] ?? ("Código $err"));
    header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit;
  }

  $tmp=(string)$_FILES['archivo']['tmp_name']; $name=basename((string)$_FILES['archivo']['name']);
  $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if(!in_array($ext,['csv','txt','xlsx'],true)){ $_SESSION['flash_error']="Extensión no soportada: $ext. Usá CSV o XLSX."; header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit; }
  if(!is_uploaded_file($tmp) || !is_readable($tmp)){ $_SESSION['flash_error']='El archivo temporal no es válido/legible.'; header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit; }

  $rows = in_array($ext,['csv','txt']) ? leer_csv_rows_tmp($tmp,$con_headers) : leer_xlsx_rows_tmp($tmp,$con_headers);
  if($ext==='xlsx' && !$rows){
    $_SESSION['flash_error']='No se pudo leer XLSX: Falta extensión php-zip (ZipArchive) o SimpleXML. Exportá a CSV, o habilitá php-zip + simplexml, o instalá phpoffice/phpspreadsheet.';
    header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit;
  }

  $importados=0; $saltados=0; $errores=[];
  $alias=[
    // Alias para “Apellido y Nombre”
    'apynom'=>['apellido y nombre','apellidos y nombres','apeynom','apynom','ap y nom','ap y n','ayn','apellidonombre','apellido nombre','apellido/nombre','apell y nomb'],
    'apellido'=>['apellido','apellidos','ape'],
    'nombre'=>['nombre','nombres','nom'],
    // DNI con variantes comunes
    'dni'=>['dni','documento','doc','numero de documento','nro documento','nro doc','nro dni','num doc','num dni','dni numero','dni num','n documento','d n i','d.n.i'],
    'fecha_nacimiento'=>['fecha de nacimiento','f nac','f.nac','fechanac','nacimiento','fecha','fecha nacimiento'],
    'sexo'=>['sexo','genero'],
    'provincia'=>['provincia','pcia'],
    'localidad'=>['localidad','ciudad','pueblo'],
    'ganadas'=>['ganadas','w','wins'],
    'perdidas'=>['perdidas','l','losses'],
    'empates'=>['empates','d','draws'],
    'sn'=>['sn','nc','no contest','sin decision','s n'],
    'escuela'=>['escuela o gimnasio','gimnasio','escuela','academia'],
    'modalidad'=>['modalidad','regla','estilo'],
    'disciplina'=>['disciplina','nivel'],
  ];

  foreach($rows as $i=>$r){
    $assoc = count(array_filter(array_keys($r),'is_string'))>0;
    if($assoc){
      $norm=[]; foreach($r as $k=>$v){ $norm[normaliza_header($k)] = trim((string)$v); }
      $get=function(array $keys) use($norm){ foreach($keys as $k){ if(isset($norm[$k]) && $norm[$k]!=='') return $norm[$k]; } return ''; };
      $m=[
        'apynom' => $get($alias['apynom']),
        'apellido'=>$get($alias['apellido']),
        'nombre'  =>$get($alias['nombre']),
        'dni'     =>$get($alias['dni']),
        'fecha_nacimiento'=>$get($alias['fecha_nacimiento']),
        'sexo'    =>$get($alias['sexo']),
        'provincia'=>$get($alias['provincia']),
        'localidad'=>$get($alias['localidad']),
        'ganadas'  =>$get($alias['ganadas']),
        'perdidas' =>$get($alias['perdidas']),
        'empates'  =>$get($alias['empates']),
        'sn'       =>$get($alias['sn']),
        'escuela'  =>$get($alias['escuela']),
        'modalidad'=>$get($alias['modalidad']),
        'disciplina'=>$get($alias['disciplina']),
      ];
      // Si falta Apellido/Nombre pero hay "Apellido y Nombre", los separamos
      if(($m['apellido']==='' || $m['nombre']==='') && $m['apynom']!==''){
        [$ap,$nm] = split_apynom($m['apynom']);
        if($m['apellido']==='' && $ap) $m['apellido']=$ap;
        if($m['nombre']==='' && $nm) $m['nombre']=$nm;
      }
    } else {
      // Posicional (orden de la plantilla oficial)
      $m=[
        'apynom'  =>'',
        'apellido'=>(string)($r[0]??''),
        'nombre'  =>(string)($r[1]??''),
        'dni'     =>(string)($r[2]??''),
        'fecha_nacimiento'=>(string)($r[3]??''),
        'sexo'    =>(string)($r[4]??''),
        'provincia'=>(string)($r[5]??''),
        'localidad'=>(string)($r[6]??''),
        'ganadas'  =>(string)($r[7]??'0'),
        'perdidas' =>(string)($r[8]??'0'),
        'empates'  =>(string)($r[9]??'0'),
        'sn'       =>(string)($r[10]??'0'),
        'escuela'  =>(string)($r[11]??''),
        'modalidad'=>(string)($r[12]??''),
        'disciplina'=>(string)($r[13]??''),
      ];
    }

    // Validar mínimos
    $apellido=trim($m['apellido']); $nombre=trim($m['nombre']); $dni=preg_replace('/\D+/','',(string)$m['dni']);
    if($apellido===''||$nombre===''||$dni===''){ $saltados++; $errores[]="Fila ".($i+1).": faltan Apellido/Nombre/DNI."; continue; }
    if(existe_dni_evento($conexion,$evento_id,$dni)){ $saltados++; $errores[]="Fila ".($i+1).": DNI duplicado."; continue; }

    // Normalizaciones
    $fecha = normaliza_fecha((string)$m['fecha_nacimiento']);
    $sexo  = normaliza_sexo((string)$m['sexo']);
    $edad=null; $div_str=null;
    if($fecha){ try{ $hoy=new DateTime('now'); $nac=DateTime::createFromFormat('Y-m-d',$fecha); if($nac){ $diff=$hoy->diff($nac); $edad=max(0,(int)$diff->y);} }catch(\Throwable $e){} }
    if($edad!==null){
      if($edad<12)$div_str='Infantil'; elseif($edad<18)$div_str='Juvenil'; elseif($edad<26)$div_str='Adultos'; elseif($edad<46)$div_str='Masters'; else $div_str='Veteranos';
    }
    $wins=max(0,(int)($m['ganadas']!==''?$m['ganadas']:0));
    $loss=max(0,(int)($m['perdidas']!==''?$m['perdidas']:0));
    $draw=max(0,(int)($m['empates']!==''?$m['empates']:0));
    $nc  =max(0,(int)($m['sn']      !==''?$m['sn']      :0));
    $cat_tec = cat_tecnica_por_total($wins+$loss+$draw+$nc);

    // IDs por nombre (si vinieron como texto)
    $modalidad_id = trim((string)$m['modalidad'])!=='' ? id_by_nombre($conexion,'modalidades_evento',(string)$m['modalidad']) : null;
    $disciplina_id= trim((string)$m['disciplina'])!=='' ? id_by_nombre($conexion,'disciplinas_evento',(string)$m['disciplina']) : null;
    $modalidad_id  = fk_ensure_id($conexion,'modalidades_evento',$modalidad_id);
    $disciplina_id = fk_ensure_id($conexion,'disciplinas_evento',$disciplina_id);
    $categoria_tecnica_id = fk_ensure_id($conexion,'categorias_tecnicas_evento',null);
    $division_id          = fk_ensure_id($conexion,'divisiones_evento',null);
    $categoria_peso_id    = fk_ensure_id($conexion,'categorias_peso_evento',null);

    try{
      insertar_competidor($conexion,[
        'evento_id'=>$evento_id,'apellido'=>$apellido,'nombre'=>$nombre,'dni'=>$dni,
        'fecha_nacimiento'=>$fecha,'edad'=>$edad,'sexo'=>$sexo,
        'escuela_nombre'=> (string)$m['escuela'] ?: null,
        'provincia'=> (string)$m['provincia'] ?: null,
        'localidad'=> (string)$m['localidad'] ?: null,
        'modalidad_id'=>$modalidad_id,'disciplina_id'=>$disciplina_id,
        'categoria_tecnica_id'=>$categoria_tecnica_id,'division_id'=>$division_id,'categoria_peso_id'=>$categoria_peso_id,
        'wins'=>$wins,'losses'=>$loss,'draws'=>$draw,'no_contest'=>$nc,
        'categoria_tecnica'=>$cat_tec,'division'=>$div_str
      ]);
      $importados++;
    }catch(\Throwable $e){
      $saltados++; $errores[]="Fila ".($i+1).": ".$e->getMessage();
    }
  }

  $_SESSION['ok_msg']="📥 Importación: $importados importados, $saltados saltados."
    .($errores?(" Detalles: ".implode(' | ', array_slice($errores,0,15)).(count($errores)>15?(' (+' . (count($errores)-15) . ' más)'):'') ):'');

  header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id.'&ok=1');
  exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Carga masiva de competidores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --bg:#0b1115; --fg:#e6eef4; --card:#0f1720; --bd:#1f2a33; --accent:#0e7ad1; --mut:#9ecbffb3; }
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,Segoe UI,Roboto,Ubuntu,Arial}
    .wrap{max-width:680px;margin:0 auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:14px}
    label{display:block;font-size:12px;color:var(--mut);margin-bottom:6px}
    input[type="file"],input[type="checkbox"]{margin-top:6px}
    .btn{display:inline-block;margin-top:10px;padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:var(--accent);color:#fff;text-decoration:none;cursor:pointer}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .mut{font-size:.9rem;color:var(--mut)}
    .notice{margin:10px 0;padding:10px;border-radius:10px;background:#10222e;border:1px solid #1d3a55}
    .actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
  </style>
</head>
<body>
  <div class="wrap">
    <h2>📥 Carga masiva de competidores</h2>
    <div class="notice">Evento ID: <b><?= (int)$evento_id ?></b></div>

    <?php if (!empty($_SESSION['ok_msg'])): ?>
      <div class="notice"><?= h($_SESSION['ok_msg']); unset($_SESSION['ok_msg']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="notice" style="background:#2a1414;border-color:#5e2626;color:#ffb4b4"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
        <label>Archivo (.csv, .xlsx, .txt)</label>
        <input type="file" name="archivo" accept=".csv,.xlsx,.txt" required>

        <div class="row">
          <label><input type="checkbox" name="con_encabezados" checked> El archivo tiene encabezados</label>
        </div>

        <div class="mut" style="margin-top:6px">
          Acepta: <b>Apellido</b> y <b>Nombre</b> por separado o una sola columna <b>“Apellido y Nombre”</b> (APEyNOM, APyN, etc.).
          Otros campos: DNI, Fecha de Nacimiento, Sexo, Provincia, Localidad, Ganadas, Perdidas, Empates, SN, Escuela o Gimnasio, Modalidad, Disciplina.
        </div>

        <div class="actions">
          <button class="btn" type="submit">📤 Importar</button>
          <a class="btn" href="<?= h($_SERVER['PHP_SELF']) ?>?evento_id=<?= (int)$evento_id ?>&plantilla=csv">⬇️ Plantilla CSV</a>
          <a class="btn" href="ver_competidores.php?evento_id=<?= (int)$evento_id ?>">👀 Ver competidores</a>
        </div>
      </form>
    </div>

    <p class="mut" style="margin-top:10px">
      Si tu hosting no lee XLSX (falta <code>php-zip</code> / <code>simplexml</code>), exportá a CSV desde Excel/Sheets y subí ese archivo.
    </p>
  </div>
</body>
</html>
