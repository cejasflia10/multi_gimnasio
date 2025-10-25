<?php
/* agregar_competidor_min.php — Interno + Público por token en el mismo archivo */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ==========================
   CONFIG LINK COMPARTIBLE
   ========================== */
if (!defined('PUBLIC_FORM_SECRET')) {
  // ⚠️ CAMBIÁ ESTA CLAVE por una larga y secreta (32+ chars)
  define('PUBLIC_FORM_SECRET', 'CAMBIA-ESTA-CLAVE-LARGA-Y-SECRETA');
}
function b64u_enc($s){ return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64u_dec($s){ return base64_decode(strtr($s, '-_', '+/')); }
function build_token(int $evento_id): string {
  $msg = (string)$evento_id;
  $mac = hash_hmac('sha256', $msg, PUBLIC_FORM_SECRET);
  return b64u_enc($msg.'.'.$mac);
}
function parse_token(?string $t): ?int {
  if (!$t) return null;
  $raw = b64u_dec($t);
  if(!$raw || strpos($raw,'.')===false) return null;
  [$msg,$mac] = explode('.', $raw, 2);
  if (!ctype_digit($msg)) return null;
  $calc = hash_hmac('sha256', $msg, PUBLIC_FORM_SECRET);
  if (!hash_equals($calc, $mac)) return null;
  return (int)$msg;
}
function current_base_url(){
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
  $scheme = $isHttps ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
  return $scheme.'://'.$host.$dir;
}

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if($r=$db->query($sql)){ $ok=(bool)$r->num_rows; $r->close(); return $ok; } return false;
}
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '{$t}'");
  $ok = ($q && $q->num_rows>0); if($q) $q->close(); return $ok;
}
function pick_sexo_col(mysqli $db): ?string {
  foreach (['sexo','genero'] as $c) if (has_col($db,'competidores_evento',$c)) return $c;
  return null;
}
function pick_catpeso_col(mysqli $db): ?string {
  foreach (['categoria_evento_id','categoria_peso_id','categoria_id'] as $c) if (has_col($db,'competidores_evento',$c)) return $c;
  return null;
}

/* ===== Verificaciones duplicados ===== */
function existe_dni_evento(mysqli $db, int $evento_id, string $dni): bool {
  $st = $db->prepare("SELECT 1 FROM competidores_evento WHERE evento_id=? AND dni=? LIMIT 1");
  if(!$st) return false;
  $st->bind_param('is',$evento_id,$dni);
  $st->execute(); $r=$st->get_result(); $ok=($r && $r->num_rows>0); $st->close(); return $ok;
}
function existe_nombre_apellido_evento(mysqli $db, int $evento_id, string $nombre, string $apellido): bool {
  $sql = "SELECT 1 FROM competidores_evento 
          WHERE evento_id=? 
            AND TRIM(LOWER(apellido))=TRIM(LOWER(?)) 
            AND TRIM(LOWER(nombre))=TRIM(LOWER(?))
          LIMIT 1";
  $st = $db->prepare($sql);
  if(!$st) return false;
  $st->bind_param('iss',$evento_id,$apellido,$nombre);
  $st->execute(); $r=$st->get_result(); $ok=($r && $r->num_rows>0); $st->close(); return $ok;
}

/* ===== Insert competidores_evento ===== */
function insert_min(mysqli $db, array $data): int {
  $cols=[]; $vals=[]; $types='';
  foreach($data as $c=>$v){
    if (has_col($db,'competidores_evento',$c)) {
      $cols[]="`$c`"; $vals[]=$v;
      $types.= is_int($v)?'i':(is_float($v)?'d':'s');
    }
  }
  if (!$cols) throw new RuntimeException('Sin columnas válidas.');
  $ph = implode(',', array_fill(0,count($cols),'?'));
  $sql = "INSERT INTO competidores_evento (".implode(',',$cols).") VALUES ($ph)";
  $st=$db->prepare($sql); if(!$st) throw new RuntimeException('Prep insert: '.$db->error);
  $refs=[]; $refs[]=&$types; foreach($vals as $i=>$_){ $refs[]=&$vals[$i]; }
  if(!call_user_func_array([$st,'bind_param'],$refs)) throw new RuntimeException($st->error);
  if(!$st->execute()) throw new RuntimeException($st->error);
  $id = (int)$db->insert_id; $st->close(); return $id;
}

/* ===== UPSERT ranking_competidores (eRanking) ===== */
function upsert_ranking_basico(mysqli $db, array $in): void {
  $tabla = 'ranking_competidores';
  if (!table_exists($db, $tabla)) return;

  $cols = [
    'apellido'=> has_col($db,$tabla,'apellido'),
    'nombre'=> has_col($db,$tabla,'nombre'),
    'dni'=> has_col($db,$tabla,'dni'),
    'edad'=> has_col($db,$tabla,'edad'),
    'escuela_nombre'=> has_col($db,$tabla,'escuela_nombre'),
    'updated_at'=> has_col($db,$tabla,'updated_at'),
    'fecha_update'=> has_col($db,$tabla,'fecha_update'),
    'wins'=> has_col($db,$tabla,'wins'),
    'losses'=> has_col($db,$tabla,'losses'),
    'draws'=> has_col($db,$tabla,'draws'),
    'no_contest'=> has_col($db,$tabla,'no_contest'),
  ];

  $id_row = null;
  if ($cols['dni'] && !empty($in['dni'])) {
    $st = $db->prepare("SELECT id FROM `$tabla` WHERE dni=? LIMIT 1");
    if ($st) { $st->bind_param('s', $in['dni']); $st->execute();
      $r = $st->get_result(); if ($r && $r->num_rows) { $id_row = (int)$r->fetch_assoc()['id']; }
      $st->close();
    }
  }
  if ($id_row===null && $cols['apellido'] && $cols['nombre']) {
    $st = $db->prepare("SELECT id FROM `$tabla` WHERE TRIM(LOWER(apellido))=TRIM(LOWER(?)) AND TRIM(LOWER(nombre))=TRIM(LOWER(?)) LIMIT 1");
    if ($st) { $st->bind_param('ss', $in['apellido'], $in['nombre']); $st->execute();
      $r=$st->get_result(); if($r && $r->num_rows){ $id_row=(int)$r->fetch_assoc()['id']; }
      $st->close();
    }
  }

  $pairs=[]; $types=''; $vals=[];
  foreach (['apellido','nombre','dni','edad','escuela_nombre'] as $c){
    if ($cols[$c] && isset($in[$c])) { $pairs[]="`$c`=?"; $vals[]=$in[$c]; $types.= is_int($in[$c])?'i':'s'; }
  }
  if ($cols['updated_at']) { $pairs[]="`updated_at`=NOW()"; }
  if ($cols['fecha_update']) { $pairs[]="`fecha_update`=NOW()"; }

  if ($id_row!==null) {
    if (!$pairs) return;
    $sql = "UPDATE `$tabla` SET ".implode(',', $pairs)." WHERE id=?";
    $st = $db->prepare($sql); if(!$st) return;
    $types2 = $types.'i'; $vals2 = $vals; $vals2[] = $id_row;
    $refs=[]; $refs[]=&$types2; foreach($vals2 as $i=>$_){ $refs[]=&$vals2[$i]; }
    @call_user_func_array([$st,'bind_param'],$refs);
    @$st->execute(); $st->close();
  } else {
    $icols=[]; $qms=[]; $typesI=''; $valsI=[];
    foreach (['apellido','nombre','dni','edad','escuela_nombre'] as $c) {
      if ($cols[$c] && isset($in[$c])) { $icols[]="`$c`"; $qms[]='?'; $valsI[]=$in[$c]; $typesI.= is_int($in[$c])?'i':'s'; }
    }
    if ($cols['updated_at']) { $icols[]='`updated_at`'; $qms[]='NOW()'; }
    if ($cols['fecha_update']) { $icols[]='`fecha_update`'; $qms[]='NOW()'; }

    if ($icols){
      $sql="INSERT INTO `$tabla` (".implode(',',$icols).") VALUES (".implode(',',$qms).")";
      $st=$db->prepare($sql); if($st){
        $refs=[]; if($typesI){ $refs[]=&$typesI; foreach($valsI as $i=>$_){ $refs[]=&$valsI[$i]; } @call_user_func_array([$st,'bind_param'],$refs); }
        @$st->execute(); $st->close();
      }
    }
  }
}

/* ===== Técnica por peleas ===== */
function get_categoria_tecnica_por_peleas(mysqli $db, int $peleas): ?int {
  $rows = [];
  if ($rs = $db->query("SELECT id, UPPER(COALESCE(codigo,'')) AS codigo, UPPER(COALESCE(descripcion,'')) AS descripcion FROM categorias_tecnicas_evento ORDER BY id ASC")) {
    while($r=$rs->fetch_assoc()){ $rows[]=$r; }
    $rs->close();
  }
  $kw = function(array $keys) use($rows): ?int {
    foreach($rows as $r){
      $txt = ($r['codigo'].' '.$r['descripcion']);
      foreach($keys as $k){ if (strpos($txt, strtoupper($k))!==false) return (int)$r['id']; }
    }
    return null;
  };
  if ($peleas <= 1)  { return $kw(['NOVATO','INICIAL','DEBUT','PRINCIPIANTE']) ?? ($rows[0]['id']??null); }
  if ($peleas <= 5)  { return $kw(['INTERMEDIO','AMATEUR']) ?? ($rows[0]['id']??null); }
  if ($peleas <= 10) { return $kw(['AVANZADO','PROAM','SEMI']) ?? ($rows[0]['id']??null); }
  return $kw(['PROFESIONAL','ELITE','ÉLITE','PRO']) ?? ($rows ? $rows[count($rows)-1]['id'] : null);
}

/* ===== Match categoría por edad+sexo ===== */
function match_categoria_edad_sexo(array $cats, int $edad, string $sexo): ?array {
  $sexo = strtolower($sexo);
  usort($cats, fn($a,$b)=>($a['peso_min']<=>$b['peso_min']) ?: ($a['id']<=>$b['id']));
  foreach ($cats as $c) {
    $gen = strtolower($c['genero'] ?? 'mixto');
    $okGenero = ($gen==='mixto') || ($sexo && $gen===$sexo);
    $okEdad = ($edad >= (int)$c['edad_min'] && $edad <= (int)$c['edad_max']);
    if ($okGenero && $okEdad) return $c;
  }
  return null;
}

/* ==========================
   Determinar modo y evento
   ========================== */
$token = $_GET['t'] ?? $_POST['t'] ?? '';
$evento_id_token = parse_token($token);
$is_public = $evento_id_token !== null; // si hay token válido, modo público

if ($is_public) {
  $evento_id = $evento_id_token;
} else {
  // Modo interno como antes
  $evento_id = (int)($_POST['evento_id'] ?? $_GET['evento_id'] ?? $_SESSION['evento_id_actual'] ?? 0);
  $_SESSION['evento_id_actual'] = $evento_id;
}

/* ========= Datos auxiliares ========= */
$escuelas = [];
$q = $conexion->query("SELECT DISTINCT TRIM(escuela_nombre) AS nombre FROM competidores_evento WHERE escuela_nombre <> '' ORDER BY nombre ASC");
if ($q) while($r = $q->fetch_assoc()){ $escuelas[] = $r['nombre']; }

$categorias = [];
$sqlCat = "SELECT id, nombre, peso_min, peso_max, genero, edad_min, edad_max FROM categorias_evento ORDER BY genero, peso_min, nombre";
if ($rs = $conexion->query($sqlCat)) {
  while ($c = $rs->fetch_assoc()) {
    $categorias[] = [
      'id' => (int)$c['id'],
      'nombre' => (string)$c['nombre'],
      'peso_min' => (float)$c['peso_min'],
      'peso_max' => (float)$c['peso_max'],
      'genero' => (string)$c['genero'],
      'edad_min' => (int)$c['edad_min'],
      'edad_max' => (int)$c['edad_max'],
    ];
  }
  $rs->close();
}
$categorias_tecnicas = $conexion->query("SELECT id, codigo, descripcion FROM categorias_tecnicas_evento ORDER BY id");

/* ===================== POST: guardar ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST'){
  // Anti-spam mínimo SOLO en modo público
  if ($is_public) {
    if (!empty($_SESSION['last_pub_submit']) && (time() - (int)$_SESSION['last_pub_submit'] < 5)) {
      $_SESSION['flash_error'] = 'Estás enviando muy rápido. Probá de nuevo en unos segundos.';
      header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
    }
    if (!empty($_POST['telefono_alt'])) { header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit; } // honeypot
  }

  $apellido = post('apellido');
  $nombre   = post('nombre');
  $dni      = preg_replace('/\D+/','', post('dni'));
  $edad     = toIntOrNull(post('edad'));
  $sexo_in  = strtolower(post('sexo')) ?: 'mixto';
  $escuela  = post('escuela_nombre');
  $modalidad_id  = toIntOrNull(post('modalidad_id'));
  $disciplina_id = toIntOrNull(post('disciplina_id'));
  $division_id   = toIntOrNull(post('division_id'));
  $categoria_tecnica_id = toIntOrNull(post('categoria_tecnica_id'));
  $peleas_previas = toIntOrNull(post('peleas_previas'));
  $categoria_evento_id = toIntOrNull(post('categoria_evento_id'));

  if ($apellido==='' || $nombre==='' || $dni==='' || $edad===null || $escuela==='' || 
      !$modalidad_id || !$disciplina_id || !$division_id || 
      $peleas_previas===null || $evento_id<=0){
    $_SESSION['flash_error']='Completá todos los campos obligatorios.';
    header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
  }

  if (existe_dni_evento($conexion,$evento_id,$dni)){
    $_SESSION['flash_error']='El DNI ya está registrado en este evento.';
    header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
  }
  if (existe_nombre_apellido_evento($conexion,$evento_id,$nombre,$apellido)){
    $_SESSION['flash_error']='Ya existe un competidor con ese nombre y apellido en este evento.';
    header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
  }

  // Auto técnica por peleas
  if (!$categoria_tecnica_id) {
    $autoTec = get_categoria_tecnica_por_peleas($conexion, (int)$peleas_previas);
    if ($autoTec) $categoria_tecnica_id = $autoTec;
  }

  // Auto categoría por edad+sexo
  $seleccion = null;
  if ($categoria_evento_id) {
    foreach ($categorias as $c) { if ($c['id']===$categoria_evento_id) { $seleccion=$c; break; } }
    if ($seleccion){
      $okEdad = ($edad >= (int)$seleccion['edad_min'] && $edad <= (int)$seleccion['edad_max']);
      $okGen  = ($seleccion['genero']==='mixto') || ($sexo_in && strtolower($seleccion['genero'])===$sexo_in);
      if (!($okEdad && $okGen)) $seleccion = null;
    }
  }
  if (!$seleccion){
    $seleccion = match_categoria_edad_sexo($categorias, (int)$edad, (string)$sexo_in);
    if ($seleccion) $categoria_evento_id = (int)$seleccion['id'];
  }
  if (!$seleccion){
    $_SESSION['flash_error']='No se encontró una categoría válida (edad/sexo). Revisá los datos.';
    header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
  }

  $sexoCol = pick_sexo_col($conexion);
  $catCol  = pick_catpeso_col($conexion);

  $data = [
    'evento_id'=>$evento_id,
    'apellido'=>$apellido,
    'nombre'=>$nombre,
    'dni'=>$dni,
    'edad'=>$edad,
    'escuela_nombre'=>$escuela,
    'modalidad_id'=>$modalidad_id,
    'disciplina_id'=>$disciplina_id,
    'division_id'=>$division_id,
    'categoria_tecnica_id'=>$categoria_tecnica_id,
    'peleas_previas'=>$peleas_previas
  ];
  if ($sexoCol) $data[$sexoCol]=$sexo_in;
  if ($catCol)  $data[$catCol]=$categoria_evento_id;

  try{
    $id = insert_min($conexion,$data);
    // eRanking solo tiene sentido en modo interno, pero no perjudica en público
    upsert_ranking_basico($conexion, [
      'apellido'=>$apellido,'nombre'=>$nombre,'dni'=>$dni,'edad'=>$edad,'escuela_nombre'=>$escuela
    ]);
    $_SESSION['flash_ok'] =
      ($is_public ? '✅ ¡Inscripción enviada!' : '✅ Competidor cargado').' '.
      '#'.$id.' • Categoría: '.$seleccion['nombre'].
      ' ('.$seleccion['genero'].' • '.$seleccion['edad_min'].'–'.$seleccion['edad_max'].' años • '.
      number_format($seleccion['peso_min'],1,',','.').'–'.number_format($seleccion['peso_max'],1,',','.').' kg).';

    if ($is_public) $_SESSION['last_pub_submit'] = time();

  } catch(Throwable $e){
    $_SESSION['flash_error'] = 'Error guardando: '.$e->getMessage();
  }
  header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
}

/* ========= UI flags ========= */
$enable_ranking_ac = !$is_public; // Autocomplete de ranking solo en modo interno
$token_for_share = build_token((int)max(0,$evento_id));
$share_url = current_base_url().'/'.basename(__FILE__).'?t='.$token_for_share;

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $is_public ? 'Inscripción de competidores' : 'Carga rápida de competidores' ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{ --bg:#0b1115; --fg:#e6eef4; --card:#0f1720; --bd:#1f2a33; --accent:#0e7ad1; }
    body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,Arial,sans-serif}
    .wrap{max-width:900px;margin:0 auto;padding:14px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:12px;margin-top:10px}
    .grid{display:grid;gap:10px;grid-template-columns:1fr}
    @media(min-width:640px){ .grid-2{grid-template-columns:repeat(2,1fr)} .grid-3{grid-template-columns:repeat(3,1fr)} .grid-4{grid-template-columns:repeat(4,1fr)} }
    label{font-size:12px;color:#cfe7ff}
    input,select{width:100%;padding:10px;border-radius:8px;border:1px solid #22313f;background:#0f1b25;color:var(--fg)}
    .btn{display:inline-block;padding:10px 12px;border-radius:8px;background:var(--accent);color:#fff;border:none;cursor:pointer}
    .alert{padding:10px;border-radius:8px;margin:10px 0}
    .ok{background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
    .mut{font-size:12px;color:#9bbad8}
    .tag{display:inline-block;font-size:12px;padding:4px 6px;border:1px solid #2a3a47;border-radius:6px;margin-right:6px}

    /* Autocomplete (ranking, solo interno) */
    .ac-wrap{position:relative}
    .ac-list{position:absolute;z-index:50;left:0;right:0;top:100%;background:#0b1620;border:1px solid #213245;border-radius:10px;margin-top:4px;max-height:260px;overflow:auto;display:none;box-shadow:0 10px 25px rgba(0,0,0,.35)}
    .ac-item{padding:10px 12px;cursor:pointer;display:flex;gap:8px;align-items:center}
    .ac-item:hover{background:#132235}
    .ac-name{font-weight:700}
    .ac-sub{font-size:12px;color:#9bbad8}
    .ac-empty{padding:10px 12px;color:#9bbad8}

    .hp{position:absolute;left:-9999px;opacity:0;height:0;width:0;overflow:hidden}
  </style>
</head>
<body>
<div class="wrap">
  <h2><?= $is_public ? '📝 Inscripción de competidores' : '🏅 Carga rápida de competidores' ?></h2>

  <?php if (!$is_public && $evento_id>0): ?>
    <div class="card" style="background:#0b1520">
      <b>🔗 Link para compartir el formulario público</b>
      <div style="display:flex;gap:8px;align-items:center;margin-top:8px">
        <input type="text" value="<?= h($share_url) ?>" readonly style="flex:1;padding:10px;border-radius:8px;border:1px solid #22313f;background:#0f1b25;color:#e6eef4">
        <button type="button" class="btn" onclick="navigator.clipboard.writeText('<?= h($share_url) ?>')">📋 Copiar</button>
      </div>
      <div class="mut" style="margin-top:6px">Cualquiera con este link puede completar la inscripción para el evento actual.</div>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash_ok'])): ?><div class="alert ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div><?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert bad"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

  <form action="" method="POST" autocomplete="off" <?= $is_public?'novalidate':'' ?>>
    <?php if (!$is_public): ?>
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <?php else: ?>
      <input type="hidden" name="t" value="<?= h($token) ?>">
      <input type="text" name="telefono_alt" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"> <!-- honeypot -->
    <?php endif; ?>

    <div class="card">
      <div class="grid grid-2">
        <?php if ($enable_ranking_ac): ?>
          <div class="ac-wrap">
            <label>Apellido* (buscar en eRanking)</label>
            <input name="apellido" id="apellido" required autocomplete="off" placeholder="Escribí el apellido o DNI">
            <div id="ac_list" class="ac-list"></div>
            <div class="mut" id="lock_msg" style="display:none;margin-top:6px">Datos completados desde eRanking (podés editar).</div>
          </div>
        <?php else: ?>
          <div>
            <label>Apellido*</label>
            <input name="apellido" id="apellido" required autocomplete="off" placeholder="Escribí el apellido">
          </div>
        <?php endif; ?>
        <div><label>Nombre*</label><input name="nombre" id="nombre" required></div>
      </div>

      <div class="grid grid-4">
        <div><label>DNI*</label><input name="dni" id="dni" required inputmode="numeric" pattern="\d+"></div>
        <div><label>Edad* (años)</label><input type="number" min="0" name="edad" id="edad" required></div>
        <div>
          <label>Sexo*</label>
          <select name="sexo" id="sexo" required>
            <option value="">—</option>
            <option value="masculino">Masculino</option>
            <option value="femenino">Femenino</option>
            <option value="mixto">Mixto</option>
          </select>
        </div>
        <div>
          <label>Cant. de peleas*</label>
          <input type="number" name="peleas_previas" id="peleas_previas" min="0" required>
        </div>
      </div>

      <div class="grid grid-2">
        <div>
          <label>Escuela / Gimnasio*</label>
          <input list="escuelas" name="escuela_nombre" id="escuela_nombre" required>
          <datalist id="escuelas">
            <?php foreach ($escuelas as $e): ?>
              <option value="<?= h($e) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div>
          <label>Categoría técnica* (auto por peleas)</label>
          <select name="categoria_tecnica_id" id="categoria_tecnica_id" required>
            <option value="">—</option>
            <?php if ($categorias_tecnicas) while($ct = $categorias_tecnicas->fetch_assoc()): ?>
              <option value="<?= (int)$ct['id'] ?>"><?= h(trim(($ct['codigo']??'').' - '.($ct['descripcion']??''), ' -')) ?></option>
            <?php endwhile; ?>
          </select>
          <div class="mut" style="margin-top:6px">Se completa automáticamente según la cantidad de peleas (podés cambiarla).</div>
        </div>
      </div>

      <div class="grid grid-3">
        <div>
          <label>Modalidad*</label>
          <select name="modalidad_id" id="modalidad_id" required>
            <option value="">—</option>
            <option value="2">Boxeo</option>
            <option value="4">Low Kick</option>
            <option value="5">K1</option>
            <option value="6">MMA</option>
            <option value="7">Muay Thai</option>
            <option value="1">Exhibición</option>
          </select>
        </div>
        <div>
          <label>Disciplina*</label>
          <select name="disciplina_id" id="disciplina_id" required>
            <option value="">—</option>
            <option value="2">Amateurs</option>
            <option value="3">ProAm</option>
            <option value="4">Profesional</option>
            <option value="1">Exhibición</option>
          </select>
        </div>
        <div>
          <label>División* (auto por edad)</label>
          <select name="division_id" id="division_id" required>
            <option value="">—</option>
            <option value="1">Infantil</option>
            <option value="2">Juvenil</option>
            <option value="3">Adultos</option>
            <option value="4">Masters</option>
            <option value="5">Veteranos</option>
          </select>
          <div class="mut" style="margin-top:6px">Se selecciona sola según la edad.</div>
        </div>
      </div>

      <div class="grid grid-2">
        <div>
          <label>Categoría de peso (auto por edad/sexo)*</label>
          <select name="categoria_evento_id" id="categoria_evento_id" required>
            <option value="">— Seleccioná edad y sexo —</option>
          </select>
          <div class="mut" id="cat_hint" style="margin-top:6px">Se elige automáticamente la primera que encaje (podés cambiarla).</div>
        </div>
      </div>

      <div id="cat_preview" class="mut" style="margin-top:6px"></div>
    </div>

    <div style="margin-top:12px">
      <button class="btn" type="submit" <?= (!$is_public && $evento_id<=0)?'disabled':'' ?>><?= $is_public ? '✅ Enviar inscripción' : '✅ Guardar' ?></button>
    </div>
  </form>
</div>

<script>
  // ===== Config (endpoint ranking solo interno) =====
  const ENABLE_RANKING = <?= $enable_ranking_ac ? 'true':'false' ?>;
  const RANKING_ENDPOINT = 'api_ranking_buscar.php'; // cambiá si está en otra ruta

  // ===== Datos de categorías (para edad+sexo) =====
  const CATEGORIAS = <?= json_encode($categorias, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  // ===== Helpers =====
  function esc(s){ return (s??'').toString().replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
  function fmtKg(n){ if(n==null||isNaN(n)) return '—'; const v=Number(n); return (Math.round(v*10)/10).toFixed(1).replace('.',','); }

  // Campos
  const dniEl = document.getElementById('dni');
  const edadEl = document.getElementById('edad');
  const sexoEl = document.getElementById('sexo');
  const catSel = document.getElementById('categoria_evento_id');
  const catPreview = document.getElementById('cat_preview');
  const divisionSel = document.getElementById('division_id');

  // DNI numérico
  if (dniEl) dniEl.addEventListener('input', e=> e.target.value=(e.target.value||'').replace(/\D+/g,''));

  // División automática por edad
  function autoDivisionPorEdad(){
    const edad = +edadEl.value || 0;
    let d = '';
    if (edad>0){
      if (edad < 12) d = '1';
      else if (edad < 18) d = '2';
      else if (edad < 26) d = '3';
      else if (edad < 46) d = '4';
      else d = '5';
    }
    if (d) divisionSel.value = d;
  }

  // Filtrar/auto elegir categoría por Edad + Sexo (muestra pesos reales)
  function filtrarCategorias(){
    const edad = +edadEl.value || 0;
    const sexo = (sexoEl.value||'').toLowerCase();

    const match = CATEGORIAS
      .filter(c=>{
        const gen = (c.genero||'mixto').toLowerCase();
        const okGenero = (gen==='mixto') || (sexo && gen===sexo);
        const okEdad = edad>0 ? (edad >= +c.edad_min && edad <= +c.edad_max) : true;
        return okGenero && okEdad;
      })
      .sort((a,b)=> (a.peso_min - b.peso_min) || (a.id - b.id));

    catSel.innerHTML = '<option value="">—</option>' + match.map(c=>{
      const label = `${esc(c.nombre)} • ${esc(c.genero)} • ${c.edad_min}–${c.edad_max} a • ${fmtKg(c.peso_min)}–${fmtKg(c.peso_max)} kg`;
      return `<option value="${c.id}">${label}</option>`;
    }).join('');

    if (match.length){
      catSel.value = match[0].id;
      const top = match[0];
      catPreview.innerHTML = `Elegida: <span class="tag">${esc(top.nombre)}</span> <span class="tag">${esc(top.genero)}</span> <span class="tag">${top.edad_min}–${top.edad_max} a</span> <span class="tag">${fmtKg(top.peso_min)}–${fmtKg(top.peso_max)} kg</span>`;
    } else {
      catPreview.textContent = '⚠️ Sin coincidencias para edad/sexo.';
    }
  }

  edadEl.addEventListener('input', ()=>{ autoDivisionPorEdad(); filtrarCategorias(); });
  sexoEl.addEventListener('change', filtrarCategorias);
  window.addEventListener('DOMContentLoaded', ()=>{ autoDivisionPorEdad(); filtrarCategorias(); });

  // Auto categoría técnica por Cant. de peleas
  const peleasEl = document.getElementById('peleas_previas');
  const tecSel = document.getElementById('categoria_tecnica_id');
  function autoTecnicaPorPeleas(){
    const n = +peleasEl.value || 0;
    const opts = [...tecSel.options].filter(o=>o.value);
    const pickByKw = kws => {
      const up = s=> (s||'').toUpperCase();
      for (const o of opts){
        const t = up(o.text);
        if (kws.some(k=> t.includes(up(k)))) return o.value;
      }
      return null;
    };
    let val = null;
    if (n <= 1)      val = pickByKw(['NOVATO','INICIAL','DEBUT','PRINCIPIANTE']);
    else if (n <= 5) val = pickByKw(['INTERMEDIO','AMATEUR']);
    else if (n <= 10)val = pickByKw(['AVANZADO','PROAM','SEMI']);
    else             val = pickByKw(['PROFESIONAL','ELITE','ÉLITE','PRO']);
    if (!val && opts.length) val = n <= 1 ? opts[0].value : opts[opts.length-1].value;
    if (val) tecSel.value = val;
  }
  peleasEl.addEventListener('input', autoTecnicaPorPeleas);
  window.addEventListener('DOMContentLoaded', autoTecnicaPorPeleas);

  // Autocomplete Ranking (solo interno)
  if (ENABLE_RANKING) (function(){
    const apeIn  = document.getElementById('apellido');
    const nomIn  = document.getElementById('nombre');
    const escIn  = document.getElementById('escuela_nombre');
    const acList = document.getElementById('ac_list');
    const lockMsg= document.getElementById('lock_msg');

    let timer = null;

    async function doLookup(q){
      try{
        const url = `${RANKING_ENDPOINT}?q=${encodeURIComponent(q)}`;
        const r = await fetch(url, {headers:{'Accept':'application/json'}});
        if(!r.ok) return renderAC([]);
        const data = await r.json();
        renderAC(Array.isArray(data)?data:[]);
      }catch(e){ renderAC([]); }
    }

    function renderAC(items){
      if (!items.length){
        acList.innerHTML = '<div class="ac-empty">Sin coincidencias…</div>';
        acList.style.display = 'block';
        return;
      }
      acList.innerHTML = items.slice(0,30).map((c,i)=>(
        `<div class="ac-item" data-i="${i}">
           <div class="ac-name">${esc(c.apellido)} ${esc(c.nombre)}</div>
           <div class="ac-sub">DNI: ${esc(c.dni||'—')}${c.escuela_nombre?' • '+esc(c.escuela_nombre):''}${c.edad?' • Edad: '+esc(c.edad):''}</div>
         </div>`
      )).join('');
      acList.style.display = 'block';
      [...acList.querySelectorAll('.ac-item')].forEach((el,idx)=> el.addEventListener('click',()=> apply(items[idx])));
    }

    function apply(c){
      if (c.apellido) apeIn.value = c.apellido;
      if (c.nombre)   nomIn.value = c.nombre;
      const dniInput = document.getElementById('dni');
      if (c.dni && dniInput) dniInput.value = String(c.dni).replace(/\D+/g,'');
      if (c.escuela_nombre) escIn.value = c.escuela_nombre;
      const edadInput = document.getElementById('edad');
      if (c.edad!=null && c.edad!==''){ edadInput.value = +c.edad; edadInput.dispatchEvent(new Event('input', {bubbles:true})); }

      // Aproximar peleas con W/L/D/NC si vienen
      let tot = 0;
      if (c.wins!=null)       tot += (+c.wins||0);
      if (c.losses!=null)     tot += (+c.losses||0);
      if (c.draws!=null)      tot += (+c.draws||0);
      if (c.no_contest!=null) tot += (+c.no_contest||0);
      const peleasInput = document.getElementById('peleas_previas');
      if (peleasInput && tot>0){ peleasInput.value = tot; peleasInput.dispatchEvent(new Event('input', {bubbles:true})); }

      lockMsg.style.display='block';
      acList.style.display='none';
    }

    apeIn.addEventListener('input', (e)=>{
      const q=(e.target.value||'').trim();
      lockMsg.style.display='none';
      if (timer) clearTimeout(timer);
      if (q.length<2){ acList.style.display='none'; return; }
      timer=setTimeout(()=> doLookup(q), 220);
    });

    document.addEventListener('click', (ev)=>{ if(!acList.contains(ev.target) && ev.target!==apeIn){ acList.style.display='none'; }});
  })();
</script>
</body>
</html>
