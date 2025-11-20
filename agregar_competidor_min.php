<?php
/* agregar_competidor_min.php — Interno + Público por token con detección de columna de evento y técnica por peleas (Clase D/C/B/A) */
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

/* === Detección de columnas variables === */
function pick_event_col(mysqli $db): ?string {
  foreach (['evento_id','id_evento','evento','evento_deportivo_id','id_evento_deportivo'] as $c){
    if (has_col($db,'competidores_evento',$c)) return $c;
  }
  return null;
}
function pick_sexo_col(mysqli $db): ?string {
  foreach (['sexo','genero'] as $c) if (has_col($db,'competidores_evento',$c)) return $c;
  return null;
}
function pick_catpeso_col(mysqli $db): ?string {
  foreach (['categoria_evento_id','categoria_peso_id','categoria_id'] as $c) if (has_col($db,'competidores_evento',$c)) return $c;
  return null;
}

/* ===== Verificaciones duplicados (usando columna evento detectada) ===== */
function existe_dni_evento(mysqli $db, string $evento_col, int $evento_id, string $dni): bool {
  $sql = "SELECT 1 FROM competidores_evento WHERE `$evento_col`=? AND dni=? LIMIT 1";
  $st = $db->prepare($sql);
  if(!$st) return false;
  $st->bind_param('is',$evento_id,$dni);
  $st->execute(); $r=$st->get_result(); $ok=($r && $r->num_rows>0); $st->close(); return $ok;
}
function existe_nombre_apellido_evento(mysqli $db, string $evento_col, int $evento_id, string $nombre, string $apellido): bool {
  $sql = "SELECT 1 FROM competidores_evento 
          WHERE `$evento_col`=? 
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

/* ==========================================================
   AJAX: búsqueda rápida en competidores_evento
   - Primero intenta por DNI exacto (solo números)
   - Si NO hay DNI, busca por Apellido + Nombre exactos
   - Devuelve datos: apellido, nombre, dni, edad, escuela, sexo,
     peleas_totales (wins+losses+draws+no_contest),
     modalidad_id, disciplina_id, division_id,
     categoria_tecnica_id, categoria_evento_id, peso_kg (si existe)
   ========================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar_competidor') {
  header('Content-Type: application/json; charset=utf-8');

  $tabla = 'competidores_evento';
  if (!table_exists($conexion, $tabla)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE); exit;
  }

  $dni = isset($_GET['dni']) ? preg_replace('/\D+/', '', (string)$_GET['dni']) : '';
  $ape = trim((string)($_GET['apellido'] ?? ''));
  $nom = trim((string)($_GET['nombre'] ?? ''));

  if ($dni === '' && ($ape === '' || $nom === '')) {
    echo json_encode([], JSON_UNESCAPED_UNICODE); exit;
  }

  // Preparamos SELECT con columnas típicas
  $campos = [
    'id','apellido','nombre','dni','edad','escuela_nombre',
    'sexo','genero',
    'peleas_previas',
    'wins','losses','draws','no_contest',
    'modalidad_id','disciplina_id','division_id',
    'categoria_tecnica_id','categoria_evento_id',
    'peso_kg','peso'
  ];
  $colsSel = [];
  foreach ($campos as $c) {
    if (has_col($conexion, $tabla, $c)) $colsSel[] = "`$c`";
  }
  if (!$colsSel) {
    echo json_encode([], JSON_UNESCAPED_UNICODE); exit;
  }
  $colsSql = implode(',', $colsSel);

  if ($dni !== '') {
    // Buscar por DNI exacto (limpiando puntos/espacios/guiones)
    $sql = "SELECT $colsSql FROM `$tabla`
            WHERE REPLACE(REPLACE(REPLACE(dni,'.',''),' ',''),'-','') = ?
            ORDER BY id DESC
            LIMIT 20";
    $st = $conexion->prepare($sql);
    if (!$st) { echo json_encode([], JSON_UNESCAPED_UNICODE); exit; }
    $st->bind_param('s', $dni);
  } else {
    // Buscar por Apellido + Nombre exactos (sin parecidos)
    $sql = "SELECT $colsSql FROM `$tabla`
            WHERE TRIM(LOWER(apellido)) = TRIM(LOWER(?))
              AND TRIM(LOWER(nombre))   = TRIM(LOWER(?))
            ORDER BY id DESC
            LIMIT 20";
    $st = $conexion->prepare($sql);
    if (!$st) { echo json_encode([], JSON_UNESCAPED_UNICODE); exit; }
    $st->bind_param('ss', $ape, $nom);
  }

  $out = [];
  if ($st && $st->execute()) {
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
      // Sexo normalizado
      $sexoNorm = null;
      $sexoRaw = null;
      if (isset($row['sexo']))      $sexoRaw = $row['sexo'];
      elseif (isset($row['genero'])) $sexoRaw = $row['genero'];
      if ($sexoRaw !== null) {
        $sx = strtolower(trim((string)$sexoRaw));
        if (in_array($sx, ['m','mas','masc','masculino','hombre']))    $sexoNorm = 'masculino';
        elseif (in_array($sx, ['f','fem','femenino','mujer']))         $sexoNorm = 'femenino';
        elseif (in_array($sx, ['mix','mixto','x']))                    $sexoNorm = 'mixto';
        else $sexoNorm = $sx ?: null;
      }

      // Peso (si existiera alguna columna típica)
      $pesoKg = null;
      if (isset($row['peso_kg']) && is_numeric($row['peso_kg'])) {
        $pesoKg = (float)$row['peso_kg'];
      } elseif (isset($row['peso']) && is_numeric($row['peso'])) {
        $pesoKg = (float)$row['peso'];
      }

      // TOTAL de peleas desde wins/losses/draws/no_contest
      $wins  = isset($row['wins'])        && $row['wins']        !== '' ? (int)$row['wins']        : 0;
      $loss  = isset($row['losses'])      && $row['losses']      !== '' ? (int)$row['losses']      : 0;
      $draw  = isset($row['draws'])       && $row['draws']       !== '' ? (int)$row['draws']       : 0;
      $nc    = isset($row['no_contest'])  && $row['no_contest']  !== '' ? (int)$row['no_contest']  : 0;
      $totPeleas = $wins + $loss + $draw + $nc;

      // Si por algún motivo no hay wins/losses/draws/no_contest, usamos peleas_previas si existe
      if ($totPeleas === 0 && isset($row['peleas_previas']) && $row['peleas_previas'] !== '') {
        $totPeleas = (int)$row['peleas_previas'];
      }

      $out[] = [
        'id'                 => isset($row['id']) ? (int)$row['id'] : null,
        'apellido'           => (string)($row['apellido'] ?? ''),
        'nombre'             => (string)($row['nombre'] ?? ''),
        'dni'                => isset($row['dni']) ? preg_replace('/\D+/', '', (string)$row['dni']) : '',
        'edad'               => isset($row['edad']) ? (int)$row['edad'] : null,
        'escuela_nombre'     => (string)($row['escuela_nombre'] ?? ''),
        'sexo'               => $sexoNorm,
        'peleas_previas'     => $totPeleas,   // para que JS la use directamente
        'peleas_totales'     => $totPeleas,
        'modalidad_id'       => isset($row['modalidad_id']) ? (int)$row['modalidad_id'] : null,
        'disciplina_id'      => isset($row['disciplina_id']) ? (int)$row['disciplina_id'] : null,
        'division_id'        => isset($row['division_id']) ? (int)$row['division_id'] : null,
        'categoria_tecnica_id'=> isset($row['categoria_tecnica_id']) ? (int)$row['categoria_tecnica_id'] : null,
        'categoria_evento_id'=> isset($row['categoria_evento_id']) ? (int)$row['categoria_evento_id'] : null,
        'peso_kg'            => $pesoKg,
      ];
    }
    $res->close();
  }
  if ($st) $st->close();

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
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

/* ========= Columna de evento detectada ========= */
$EVENT_COL = pick_event_col($conexion);

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

/* ===== Detectar IDs de técnica por palabras clave (Clase A/B/C/D) ===== */
function detectar_tecnica_ids(mysqli $db): array {
  $rows = [];
  if ($rs = $db->query("SELECT id, UPPER(COALESCE(codigo,'')) AS codigo, UPPER(COALESCE(descripcion,'')) AS descripcion FROM categorias_tecnicas_evento")) {
    while($r=$rs->fetch_assoc()){ $rows[]=$r; }
    $rs->close();
  }
  $matchId = function(array $kws) use($rows): ?int {
    foreach ($rows as $r) {
      $txt = trim(($r['codigo'] ?? '').' '.($r['descripcion'] ?? ''));
      $txt = strtoupper($txt);
      foreach ($kws as $k) {
        if (strpos($txt, strtoupper($k)) !== false) return (int)$r['id'];
      }
    }
    return null;
  };
  return [
    'A' => $matchId(['CLASE A','PROFESIONAL','ELITE','ÉLITE','PRO']),
    'B' => $matchId(['CLASE B','AVANZADO','PROAM','AMATEUR AVANZADO']),
    'C' => $matchId(['CLASE C','INTERMEDIO','AMATEUR INICIAL']),
    'D' => $matchId(['CLASE D','DEBUT','NOVATO','INICIAL']),
  ];
}
$TEC_IDS = detectar_tecnica_ids($conexion);

/* ===== Técnica por peleas (servidor) usando TEC_IDS ===== */
function get_categoria_tecnica_por_peleas(mysqli $db, int $peleas): ?int {
  global $TEC_IDS;

  $pick = function(string $cls) use ($TEC_IDS): ?int {
    return isset($TEC_IDS[$cls]) && $TEC_IDS[$cls] ? (int)$TEC_IDS[$cls] : null;
  };

  if ($peleas <= 0)              return $pick('D');
  if ($peleas >= 1 && $peleas <= 3)  return $pick('C');
  if ($peleas >= 4 && $peleas <= 10) return $pick('B');
  return $pick('A');
}

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

  // Duplicados por evento (si la tabla tiene columna de evento)
  if ($EVENT_COL) {
    if (existe_dni_evento($conexion,$EVENT_COL,$evento_id,$dni)){
      $_SESSION['flash_error']='El DNI ya está registrado en este evento.';
      header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
    }
    if (existe_nombre_apellido_evento($conexion,$EVENT_COL,$evento_id,$nombre,$apellido)){
      $_SESSION['flash_error']='Ya existe un competidor con ese nombre y apellido en este evento.';
      header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
    }
  }

  // Auto técnica por peleas (Clase D/C/B/A) si no vino marcada
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
    // Match por edad/sexo (primera que encaje)
    usort(
      $categorias,
      fn($a,$b) => ($a['peso_min'] <=> $b['peso_min']) ?: ($a['id'] <=> $b['id'])
    );
    foreach ($categorias as $c) {
      $gen = strtolower($c['genero'] ?? 'mixto');
      $okGenero = ($gen==='mixto') || ($sexo_in && $gen===$sexo_in);
      $okEdad   = ($edad >= (int)$c['edad_min'] && $edad <= (int)$c['edad_max']);
      if ($okGenero && $okEdad) { $seleccion = $c; $categoria_evento_id = (int)$c['id']; break; }
    }
  }
  if (!$seleccion){
    $_SESSION['flash_error']='No se encontró una categoría válida (edad/sexo). Revisá los datos.';
    header('Location: '.($_SERVER['REQUEST_URI'] ?? '')); exit;
  }

  $sexoCol = pick_sexo_col($conexion);
  $catCol  = pick_catpeso_col($conexion);

  /* Data a insertar, mapeando la columna real del evento */
  $data = [
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
  if ($EVENT_COL) $data[$EVENT_COL] = $evento_id; // ← usar la columna detectada
  if ($sexoCol)  $data[$sexoCol]   = $sexo_in;
  if ($catCol)   $data[$catCol]    = $categoria_evento_id;

  try{
    $id = insert_min($conexion,$data);

    // eRanking: actualiza o crea si no existe (si existe tabla ranking_competidores)
    upsert_ranking_basico($conexion, [
      'apellido'=>$apellido,
      'nombre'=>$nombre,
      'dni'=>$dni,
      'edad'=>$edad,
      'escuela_nombre'=>$escuela
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
    .ac-item{padding:10px 12px;cursor:pointer;display:flex;flex-direction:column;gap:4px}
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
            <label>Apellido* (buscar en competidores)</label>
            <input name="apellido" id="apellido" required autocomplete="off" placeholder="Escribí el apellido">
            <div id="ac_list" class="ac-list"></div>
            <div class="mut" id="lock_msg" style="display:none;margin-top:6px">
              Datos cargados desde competidores_evento (podés editar y se guardan actualizados).
            </div>
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
  // IDs detectados en servidor para Clase A/B/C/D
  const TEC_IDS = <?= json_encode($TEC_IDS, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
</script>

<script>
  // ===== Config (endpoint búsqueda competidor solo interno) =====
  const ENABLE_RANKING = <?= $enable_ranking_ac ? 'true':'false' ?>;
  const COMP_ENDPOINT = '<?= basename(__FILE__) ?>?ajax=buscar_competidor';

  // ===== Datos de categorías (para edad+sexo) =====
  const CATEGORIAS = <?= json_encode($categorias, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  // ===== Helpers =====
  function esc(s){ return (s??'').toString().replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
  function fmtKg(n){ if(n==null||isNaN(n)) return '—'; const v=Number(n); return (Math.round(v*10)/10).toFixed(1).replace('.',','); }

  const dniEl = document.getElementById('dni');
  const edadEl = document.getElementById('edad');
  const sexoEl = document.getElementById('sexo');
  const catSel = document.getElementById('categoria_evento_id');
  const catPreview = document.getElementById('cat_preview');
  const divisionSel = document.getElementById('division_id');

  if (dniEl) dniEl.addEventListener('input', e=> e.target.value=(e.target.value||'').replace(/\D+/g,''));

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

  const peleasEl = document.getElementById('peleas_previas');
  const tecSel   = document.getElementById('categoria_tecnica_id');

  function optionExistsByValue(val){
    if (!tecSel || !val) return false;
    return Array.from(tecSel.options).some(o => String(o.value) === String(val));
  }
  function setTecById(val){
    if (!tecSel || !val) return false;
    if (optionExistsByValue(val)) {
      tecSel.value = String(val);
      return true;
    }
    return false;
  }

  function autoTecnicaPorPeleas(){
    const n = +(peleasEl?.value ?? 0) || 0;

    let prefer = null;
    if (n <= 0)              prefer = TEC_IDS?.D || null;
    else if (n <= 3)         prefer = TEC_IDS?.C || null;
    else if (n <= 10)        prefer = TEC_IDS?.B || null;
    else                     prefer = TEC_IDS?.A || null;

    if (setTecById(prefer)) return;

    function pickByKeywords(kws){
      const up = s => (s||'').toUpperCase();
      for (const o of Array.from(tecSel.options).filter(o=>o.value)){
        const t = up(o.text);
        if (kws.some(k => t.includes(up(k)))) return o.value;
      }
      return null;
    }

    let alt = null;
    if (n <= 0)              alt = pickByKeywords(['CLASE D','DEBUT','NOVATO','INICIAL']);
    else if (n <= 3)         alt = pickByKeywords(['CLASE C','INTERMEDIO','AMATEUR INICIAL']);
    else if (n <= 10)        alt = pickByKeywords(['CLASE B','AVANZADO','PROAM','AMATEUR AVANZADO']);
    else                     alt = pickByKeywords(['CLASE A','PROFESIONAL','ELITE','ÉLITE','PRO']);

    if (alt && setTecById(alt)) return;

    const vals = Array.from(tecSel.options).filter(o=>o.value).map(o=>o.value);
    if (!vals.length) return;
    if (n <= 0)       tecSel.value = vals[0];
    else if (n <= 3)  tecSel.value = vals[Math.min(1, vals.length-1)];
    else if (n <= 10) tecSel.value = vals[Math.min(2, vals.length-1)];
    else              tecSel.value = vals[vals.length-1];
  }

  if (peleasEl) {
    peleasEl.addEventListener('input', autoTecnicaPorPeleas);
    window.addEventListener('DOMContentLoaded', autoTecnicaPorPeleas);
  }

  if (ENABLE_RANKING) (function(){
    const apeIn   = document.getElementById('apellido');
    const nomIn   = document.getElementById('nombre');
    const escIn   = document.getElementById('escuela_nombre');
    const acList  = document.getElementById('ac_list');
    const lockMsg = document.getElementById('lock_msg');

    let timerNombre = null;
    let timerDni    = null;

    async function doLookup(params, fromDni){
      try{
        const usp = new URLSearchParams();
        if (params.dni)      usp.append('dni', params.dni);
        if (params.apellido) usp.append('apellido', params.apellido);
        if (params.nombre)   usp.append('nombre', params.nombre);

        const url = `${COMP_ENDPOINT}&${usp.toString()}`;
        const r = await fetch(url, {headers:{'Accept':'application/json'}});
        if(!r.ok) { renderAC([]); return; }
        const data = await r.json();
        const items = Array.isArray(data) ? data : [];

        if (fromDni && items.length === 1) {
          apply(items[0]);
          acList.style.display = 'none';
          return;
        }

        renderAC(items);
      }catch(e){
        renderAC([]);
      }
    }

    function renderAC(items){
      if (!items.length){
        acList.innerHTML = '<div class="ac-empty">Sin coincidencias…</div>';
        acList.style.display = 'block';
        return;
      }
      acList.innerHTML = items.slice(0,30).map((c,i)=>{
        const peleas = (c.peleas_previas!=null ? c.peleas_previas : (c.peleas_totales!=null ? c.peleas_totales : 0));
        const sexo   = c.sexo ? ` • Sexo: ${esc(c.sexo)}` : '';
        const peso   = (c.peso_kg!=null ? ` • Peso: ${fmtKg(c.peso_kg)} kg` : '');
        return (
        `<div class="ac-item" data-i="${i}">
           <div class="ac-name">${esc(c.apellido)} ${esc(c.nombre)}</div>
           <div class="ac-sub">
             DNI: ${esc(c.dni||'—')}
             ${c.escuela_nombre ? ' • '+esc(c.escuela_nombre) : ''}
             ${c.edad ? ' • Edad: '+esc(c.edad) : ''}
             ${peleas ? ' • Peleas: '+peleas : ''}
             ${sexo}${peso}
           </div>
         </div>`);
      }).join('');
      acList.style.display = 'block';
      [...acList.querySelectorAll('.ac-item')].forEach((el,idx)=> el.addEventListener('click',()=> apply(items[idx])));
    }

    function apply(c){
      if (c.apellido) apeIn.value = c.apellido;
      if (c.nombre)   nomIn.value = c.nombre;

      const dniInput = document.getElementById('dni');
      if (c.dni && dniInput) dniInput.value = String(c.dni).replace(/\D+/g,'');

      if (c.escuela_nombre && escIn) escIn.value = c.escuela_nombre;

      const edadInput = document.getElementById('edad');
      if (c.edad!=null && c.edad!==''){
        edadInput.value = +c.edad;
        edadInput.dispatchEvent(new Event('input', {bubbles:true}));
      }

      const sexoSel = document.getElementById('sexo');
      if (sexoSel && c.sexo){
        const sx = String(c.sexo).toLowerCase();
        if (['masculino','m'].includes(sx)) sexoSel.value = 'masculino';
        else if (['femenino','f'].includes(sx)) sexoSel.value = 'femenino';
        else if (['mixto','mix','x'].includes(sx)) sexoSel.value = 'mixto';
        sexoSel.dispatchEvent(new Event('change',{bubbles:true}));
      }

      const modSel = document.getElementById('modalidad_id');
      if (modSel && c.modalidad_id != null) modSel.value = String(c.modalidad_id);

      const disSel = document.getElementById('disciplina_id');
      if (disSel && c.disciplina_id != null) disSel.value = String(c.disciplina_id);

      const divSel = document.getElementById('division_id');
      if (divSel && c.division_id != null) divSel.value = String(c.division_id);

      if (tecSel && c.categoria_tecnica_id != null) {
        tecSel.value = String(c.categoria_tecnica_id);
      }

      if (catSel && c.categoria_evento_id != null) {
        catSel.value = String(c.categoria_evento_id);
      }

      let tot = 0;
      if (c.peleas_previas != null) {
        tot = +c.peleas_previas || 0;
      } else if (c.peleas_totales != null) {
        tot = +c.peleas_totales || 0;
      }
      const peleasInput = document.getElementById('peleas_previas');
      if (peleasInput && tot>0){
        peleasInput.value = tot;
        peleasInput.dispatchEvent(new Event('input', {bubbles:true}));
      }

      lockMsg.style.display='block';
      acList.style.display='none';
    }

    if (dniEl) {
      dniEl.addEventListener('input', (e)=>{
        const v=(e.target.value||'').trim();
        lockMsg.style.display='none';
        if (timerDni) clearTimeout(timerDni);
        if (v.length < 6) {
          return;
        }
        timerDni = setTimeout(()=> doLookup({dni:v}, true), 220);
      });
    }

    function triggerNombreBusqueda(){
      const dniVal = (dniEl && dniEl.value.trim()) || '';
      if (dniVal.length >= 6) {
        return;
      }
      const apeVal = (apeIn.value||'').trim();
      const nomVal = (nomIn.value||'').trim();
      lockMsg.style.display='none';
      if (timerNombre) clearTimeout(timerNombre);
      if (apeVal.length < 2 || nomVal.length < 2){
        acList.style.display='none';
        return;
      }
      timerNombre = setTimeout(()=> doLookup({apellido:apeVal, nombre:nomVal}, false), 250);
    }

    apeIn.addEventListener('input', triggerNombreBusqueda);
    nomIn.addEventListener('input', triggerNombreBusqueda);

    document.addEventListener('click', (ev)=>{
      if(!acList.contains(ev.target) && ev.target!==apeIn){
        acList.style.display='none';
      }
    });
  })();
</script>
</body>
</html>
