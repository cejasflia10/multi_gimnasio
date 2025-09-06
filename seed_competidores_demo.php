<?php
/* ============================================================
   seed_competidores_demo.php — Cargar 10 competidores de ejemplo
   - NO exige 'dni': usa documento/cedula/... si existen
   - Si no hay documento, deduplica por (apellido,nombre,fecha_nacimiento)
   - Crea defaults en catálogos si están vacíos
   - Inserta vínculo en evento_competidores (campos detectados)
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function table_columns(mysqli $db, string $table): array {
  $cols = [];
  if ($rs = $db->query("SHOW COLUMNS FROM `{$table}`")) {
    while ($r = $rs->fetch_assoc()) $cols[] = $r['Field'];
  }
  return $cols;
}
function pick_col(array $cols, array $candidates, $fallback=null){
  foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
  return $fallback;
}
function ensure_catalog_default(mysqli $db, string $table, string $default_name): int {
  $id = 0;
  if ($rs = $db->query("SELECT id FROM `{$table}` ORDER BY id ASC LIMIT 1")) {
    if ($row = $rs->fetch_assoc()) $id = (int)$row['id'];
  }
  if ($id > 0) return $id;
  @$db->query("INSERT INTO `{$table}` (nombre) VALUES ('".$db->real_escape_string($default_name)."')");
  if ($db->insert_id) return (int)$db->insert_id;
  if ($rs = $db->query("SELECT id FROM `{$table}` ORDER BY id ASC LIMIT 1")) {
    if ($row = $rs->fetch_assoc()) return (int)$row['id'];
  }
  return 1;
}

/* ---------- Resolver evento ---------- */
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : (int)($_SESSION['evento_id_actual'] ?? 0);
if ($evento_id <= 0) exit("⚠️ Definí ?evento_id=ID o tené \$_SESSION['evento_id_actual'].");

/* ---------- Detectar columnas reales ---------- */
$cols_comp = table_columns($conexion, 'competidores');
$cols_link = table_columns($conexion, 'evento_competidores');

/* Campos de competidores (lo que exista) */
$col_apellido        = pick_col($cols_comp, ['apellido']);
$col_nombre          = pick_col($cols_comp, ['nombre']);
$col_fecha_nac       = pick_col($cols_comp, ['fecha_nacimiento','fechaNac','nacimiento']);
$col_edad            = pick_col($cols_comp, ['edad']);
$col_sexo            = pick_col($cols_comp, ['sexo','genero']);
$col_escuela_nombre  = pick_col($cols_comp, ['escuela_nombre','escuela','gimnasio']);
$col_escuela_logo    = pick_col($cols_comp, ['escuela_logo','logo_escuela','logo']);
$col_foto            = pick_col($cols_comp, ['foto_competidor','foto_url','foto','imagen']);

/* Documento alternativo (no hay dni) */
$col_documento = pick_col($cols_comp, ['documento','num_documento','numero_documento','cedula','doc','pasaporte','doc_num']);

/* evento_competidores */
$col_evento_id      = pick_col($cols_link, ['evento_id','id_evento'], 'evento_id');
$col_competidor_id  = pick_col($cols_link, ['competidor_id','id_competidor'], 'competidor_id');
$col_modalidad_id   = pick_col($cols_link, ['modalidad_id','id_modalidad'], 'modalidad_id');
$col_disciplina_id  = pick_col($cols_link, ['disciplina_id','id_disciplina'], 'disciplina_id');
$col_peso_id        = pick_col($cols_link, ['categoria_peso_id','id_categoria_peso'], 'categoria_peso_id');
$col_tecnica_id     = pick_col($cols_link, ['categoria_tecnica_id','id_categoria_tecnica'], 'categoria_tecnica_id');
$col_division_id    = pick_col($cols_link, ['division_id','id_division'], 'division_id');
$col_pago_insc      = pick_col($cols_link, ['pago_inscripcion','inscripcion_pago','pago']);

/* ---------- Defaults de catálogos ---------- */
$default_modalidad_id  = ensure_catalog_default($conexion, 'modalidades_evento', 'General');
$default_disciplina_id = ensure_catalog_default($conexion, 'disciplinas_evento', 'General');
$default_peso_id       = ensure_catalog_default($conexion, 'categorias_peso_evento', 'Sin peso');

/* ---------- Datos de ejemplo ---------- */
$todayY = (int)date('Y');
$samples = [
  ['doc'=>'47000001','apellido'=>'García','nombre'=>'Luciano','edad'=>21,'sexo'=>'masculino', 'fn'=>($todayY-21).'-02-15','escuela'=>'Scorpions Team'],
  ['doc'=>'47000002','apellido'=>'Pérez','nombre'=>'Micaela','edad'=>19,'sexo'=>'femenino',  'fn'=>($todayY-19).'-07-03','escuela'=>'Panther Gym'],
  ['doc'=>'47000003','apellido'=>'López','nombre'=>'Agustín','edad'=>24,'sexo'=>'masculino', 'fn'=>($todayY-24).'-11-21','escuela'=>'Dragón Dojo'],
  ['doc'=>'47000004','apellido'=>'Fernández','nombre'=>'Sofía','edad'=>18,'sexo'=>'femenino', 'fn'=>($todayY-18).'-05-27','escuela'=>'Leones Team'],
  ['doc'=>'47000005','apellido'=>'Gómez','nombre'=>'Tomás','edad'=>22,'sexo'=>'masculino', 'fn'=>($todayY-22).'-01-10','escuela'=>'Scorpions Team'],
  ['doc'=>'47000006','apellido'=>'Rodríguez','nombre'=>'Valentina','edad'=>20,'sexo'=>'femenino','fn'=>($todayY-20).'-03-08','escuela'=>'Panther Gym'],
  ['doc'=>'47000007','apellido'=>'Martínez','nombre'=>'Julián','edad'=>23,'sexo'=>'masculino','fn'=>($todayY-23).'-09-18','escuela'=>'Águilas Fight'],
  ['doc'=>'47000008','apellido'=>'Sánchez','nombre'=>'Brenda','edad'=>21,'sexo'=>'femenino', 'fn'=>($todayY-21).'-04-02','escuela'=>'Fénix Club'],
  ['doc'=>'47000009','apellido'=>'Díaz','nombre'=>'Ezequiel','edad'=>25,'sexo'=>'masculino','fn'=>($todayY-25).'-12-12','escuela'=>'Gladiadores'],
  ['doc'=>'47000010','apellido'=>'Silva','nombre'=>'Milagros','edad'=>19,'sexo'=>'femenino', 'fn'=>($todayY-19).'-08-30','escuela'=>'Lobos MMA'],
];

/* ---------- Preparados dinámicos ---------- */
/* select competidor existente */
$sel_comp = null;
if ($col_documento) {
  $sel_comp = $conexion->prepare("SELECT id FROM competidores WHERE `$col_documento` = ? LIMIT 1");
} elseif ($col_apellido && $col_nombre && $col_fecha_nac) {
  $sel_comp = $conexion->prepare("SELECT id FROM competidores WHERE `$col_apellido`=? AND `$col_nombre`=? AND `$col_fecha_nac`=? LIMIT 1");
}
/* insert competidor */
$ins_cols = [];
$ins_types = '';
if ($col_apellido)        { $ins_cols[]=$col_apellido;        $ins_types.='s'; }
if ($col_nombre)          { $ins_cols[]=$col_nombre;          $ins_types.='s'; }
if ($col_documento)       { $ins_cols[]=$col_documento;       $ins_types.='s'; }
if ($col_fecha_nac)       { $ins_cols[]=$col_fecha_nac;       $ins_types.='s'; }
if ($col_edad)            { $ins_cols[]=$col_edad;            $ins_types.='i'; }
if ($col_sexo)            { $ins_cols[]=$col_sexo;            $ins_types.='s'; }
if ($col_escuela_nombre)  { $ins_cols[]=$col_escuela_nombre;  $ins_types.='s'; }
if ($col_escuela_logo)    { $ins_cols[]=$col_escuela_logo;    $ins_types.='s'; }
if ($col_foto)            { $ins_cols[]=$col_foto;            $ins_types.='s'; }

if (empty($ins_cols)) exit("⚠️ No hay columnas aprovechables en 'competidores'.");
$placeholders = implode(',', array_fill(0, count($ins_cols), '?'));
$ins_sql = "INSERT INTO competidores (".implode(',', array_map(fn($c)=>"`$c`",$ins_cols)).") VALUES ($placeholders)";
$ins_comp = $conexion->prepare($ins_sql);

/* select link existente */
$sel_link = $conexion->prepare("SELECT 1 FROM evento_competidores WHERE `$col_evento_id` = ? AND `$col_competidor_id` = ? LIMIT 1");
/* insert link */
if ($col_pago_insc && in_array($col_pago_insc, $cols_link, true)) {
  $ins_link = $conexion->prepare("
    INSERT INTO evento_competidores
      (`$col_evento_id`, `$col_competidor_id`, `$col_modalidad_id`, `$col_disciplina_id`, `$col_peso_id`, `$col_tecnica_id`, `$col_division_id`, `$col_pago_insc`)
    VALUES (?,?,?,?,?,?,?,?)
  ");
  $bind_link_types = 'iiiiiiid';
  $with_pago = true;
} else {
  $ins_link = $conexion->prepare("
    INSERT INTO evento_competidores
      (`$col_evento_id`, `$col_competidor_id`, `$col_modalidad_id`, `$col_disciplina_id`, `$col_peso_id`, `$col_tecnica_id`, `$col_division_id`)
    VALUES (?,?,?,?,?,?,?)
  ");
  $bind_link_types = 'iiiiiii';
  $with_pago = false;
}

/* ---------- Insertar/relacionar ---------- */
$ok=0;$sk=0;$err=0;$err_det=[];
$logo_demo = '/uploads/logos/demo_logo.png';
$foto_demo = '/uploads/fotos/demo_competidor.png';

foreach ($samples as $i=>$c) {
  $doc  = (string)$c['doc'];
  $apat = (string)$c['apellido'];
  $nomb = (string)$c['nombre'];
  $edad = (int)$c['edad'];
  $sexo = (string)$c['sexo'];
  $fn   = (string)$c['fn'];
  $escu = (string)$c['escuela'];

  /* buscar existente */
  $comp_id = 0;
  if ($sel_comp) {
    if ($col_documento) {
      $sel_comp->bind_param('s', $doc);
    } else {
      $sel_comp->bind_param('sss', $apat, $nomb, $fn);
    }
    if (!$sel_comp->execute()) { $err++; $err_det[]="sel_comp: ".$sel_comp->error; continue; }
    $r = $sel_comp->get_result();
    if ($r && $r->num_rows>0) {
      $comp_id = (int)$r->fetch_assoc()['id'];
    }
  }
  /* si no existe, insertarlo */
  if ($comp_id<=0) {
    $vals=[];
    foreach ($ins_cols as $col) {
      switch ($col) {
        case $col_apellido:        $vals[]=$apat; break;
        case $col_nombre:          $vals[]=$nomb; break;
        case $col_documento:       $vals[]=$doc;  break;
        case $col_fecha_nac:       $vals[]=$fn;   break;
        case $col_edad:            $vals[]=$edad; break;
        case $col_sexo:            $vals[]=$sexo; break;
        case $col_escuela_nombre:  $vals[]=$escu; break;
        case $col_escuela_logo:    $vals[]=$logo_demo; break;
        case $col_foto:            $vals[]=$foto_demo; break;
        default:                   $vals[]=null;
      }
    }
    $refs=[]; foreach($vals as $k=>$v){ $refs[$k]=&$vals[$k]; }
    $params=array_merge([$ins_types], $refs);
    if (!call_user_func_array([$ins_comp,'bind_param'],$params)) { $err++; $err_det[]="ins_comp bind"; continue; }
    if (!$ins_comp->execute()) { $err++; $err_det[]="ins_comp: ".$ins_comp->error; continue; }
    $comp_id = (int)$ins_comp->insert_id;
  }

  /* IDs catálogo y categorías */
  $modalidad_id  = $default_modalidad_id ?: 1;
  $disciplina_id = $default_disciplina_id ?: 1;
  if ($i % 6 === 1) $modalidad_id = max(2,$modalidad_id);
  if ($i % 6 === 2) $modalidad_id = max(3,$modalidad_id);
  if ($i % 6 === 3) $modalidad_id = max(4,$modalidad_id);
  if ($i % 6 === 4) $modalidad_id = max(5,$modalidad_id);
  if ($i % 6 === 5) $modalidad_id = max(6,$modalidad_id);

  if ($i % 4 === 1) $disciplina_id = max(2,$disciplina_id);
  if ($i % 4 === 2) $disciplina_id = max(3,$disciplina_id);
  if ($i % 4 === 3) $disciplina_id = max(4,$disciplina_id);

  $cat_tecnica = ($i % 4) + 1;
  $division    = ($i % 4) + 1;
  $cat_peso_id = $default_peso_id ?: 1;
  $pago        = 0.00;

  /* evitar duplicado vínculo */
  $sel_link->bind_param('ii', $evento_id, $comp_id);
  if (!$sel_link->execute()) { $err++; $err_det[]="sel_link: ".$sel_link->error; continue; }
  $rx=$sel_link->get_result();
  if ($rx && $rx->num_rows>0) { $sk++; continue; }

  /* insertar vínculo */
  if ($with_pago) {
    $ins_link->bind_param($bind_link_types, $evento_id,$comp_id,$modalidad_id,$disciplina_id,$cat_peso_id,$cat_tecnica,$division,$pago);
  } else {
    $ins_link->bind_param($bind_link_types, $evento_id,$comp_id,$modalidad_id,$disciplina_id,$cat_peso_id,$cat_tecnica,$division);
  }
  if ($ins_link->execute()) $ok++; else { $err++; $err_det[]="ins_link: ".$ins_link->error; }
}

/* ---------- Salida ---------- */
echo "<div style='font-family:system-ui,Segoe UI,Arial;padding:16px'>";
echo "<h3>✅ Carga de demo finalizada</h3>";
echo "<p>Evento: <strong>".(int)$evento_id."</strong></p>";
echo "<ul>";
echo "<li>Insertados: <strong>".(int)$ok."</strong></li>";
echo "<li>Ya vinculados (omitidos): <strong>".(int)$sk."</strong></li>";
echo "<li>Errores: <strong>".(int)$err."</strong></li>";
echo "</ul>";
if ($err>0 && $err_det){
  echo "<details open style='margin-top:8px'><summary>Ver detalles de error</summary>";
  echo "<pre style='white-space:pre-wrap;background:#111;color:#eee;padding:10px;border-radius:6px;border:1px solid #333'>";
  foreach($err_det as $e) echo h($e)."\n";
  echo "</pre></details>";
}
echo "<p><a href='ver_competidores_evento.php?evento_id=".(int)$evento_id."' style='text-decoration:none;background:#111;color:gold;padding:.4rem .6rem;border-radius:6px'>👀 Ver competidores del evento</a></p>";
echo "</div>";
