<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_eventos.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ========= Flash helpers ========= */
function flash_err($msg){ $_SESSION['flash_error'] = $msg; }
function flash_ok($msg){ $_SESSION['flash_ok'] = $msg; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ========= CSRF ========= */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];
function csrf_ok($t){ return !empty($_SESSION['csrf_token']) && !empty($t) && hash_equals($_SESSION['csrf_token'], $t); }

/* ====== evento_id del contexto ====== */
$evento_id = (int)($_GET['evento_id'] ?? $_SESSION['evento_id_actual'] ?? 0);
if ($evento_id <= 0) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">
          Falta <b>evento_id</b>. Abrí esta pantalla desde el evento (botón “Organizar peleas”).
        </div>';
  exit;
}
$_SESSION['evento_id_actual'] = $evento_id;

/* ============== Utilidades ============== */
function bt($col){ return '`'.str_replace('`','``',$col).'`'; }
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  $rs = $db->query("SHOW TABLES LIKE '$name'");
  return $rs && $rs->num_rows > 0;
}

/* ============== Columnas dinámicas ============== */
function pe_get_cols(mysqli $db){
  $res = $db->query("SHOW COLUMNS FROM peleas_evento");
  if (!$res) { return ['error' => 'No se pudo leer columnas de peleas_evento: '.$db->error]; }
  $cols = [];
  while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r; }
  $find = function(array $cands) use ($cols){
    foreach ($cands as $c) if (isset($cols[strtolower($c)])) return $c;
    return null;
  };
  $map = [
    'id'        => $find(['id','pelea_id','id_pelea']),
    'evento'    => $find(['evento_id','id_evento','evento']),
    'rojo'      => $find(['rojo_id','id_rojo','competidor_rojo_id','id_competidor_rojo','rojo']),
    'azul'      => $find(['azul_id','id_azul','competidor_azul_id','id_competidor_azul','azul']),
    'rondas'    => $find(['rondas','rounds']),
    'obs'       => $find(['observaciones','obs','comentarios','comentario','nota']),
    'estado'    => $find(['estado','status']),
    'fecha'     => $find(['fecha','creado_en','created_at','created','fh_creacion']),
    // opcional
    'modalidad' => $find(['modalidad_id','id_modalidad','mod_id','modalidad']),
  ];
  $map['_all'] = array_keys($cols);
  return $map;
}

function cat_get_cols(mysqli $db){
  $res = $db->query("SHOW COLUMNS FROM categorias_evento");
  if (!$res) { return ['error' => 'No se pudo leer columnas de categorias_evento: '.$db->error]; }
  $cols = [];
  while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
  $pick = function(array $cands) use ($cols){
    foreach ($cands as $c) { $lc = strtolower($c); if (isset($cols[$lc])) return $cols[$lc]; }
    return null;
  };
  return [
    'id'        => $pick(['id','categoria_id']),
    'nombre'    => $pick(['nombre','categoria','clase','weight_class','titulo','title','descripcion']),
    'peso_min'  => $pick(['peso_min','min_peso','desde','min','minimo','peso_minimo']),
    'peso_max'  => $pick(['peso_max','max_peso','hasta','max','maximo','peso_maximo']),
    'genero'    => $pick(['genero','sexo','gender']),
    'edad_min'  => $pick(['edad_min','edad_desde','min_edad','desde_edad']),
    'edad_max'  => $pick(['edad_max','edad_hasta','max_edad','hasta_edad']),
    '_all'      => array_values($cols),
  ];
}

/* Detectar columnas de categoría técnica en competidores_evento */
function ce_get_cols(mysqli $db){
  $res = $db->query("SHOW COLUMNS FROM competidores_evento");
  if (!$res) { return ['error' => 'No se pudo leer columnas de competidores_evento: '.$db->error]; }
  $cols = [];
  while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
  $pick = function(array $cands) use ($cols){
    foreach ($cands as $c) { $lc = strtolower($c); if (isset($cols[$lc])) return $cols[$lc]; }
    return null;
  };
  return [
    // Texto como "A/B/C/N", "CBA", etc.
    'cat_tec_text' => $pick(['categoria_tecnica','cat_tecnica','categoria_nivel','nivel_tecnico']),
    // ID a catálogo (opcional)
    'cat_tec_id'   => $pick(['categoria_tecnica_id','cat_tecnica_id','categoria_nivel_id']),
    // (peso) id de categoria_peso si existe (en muchos esquemas se llama categoria_peso_id o similar)
    'cat_peso_id'  => $pick(['categoria_peso_id','categoria_evento_id','cat_peso_id','categoria_id']),
    // Escuela (para regla 2)
    'escuela_id'   => $pick(['escuela_id','id_escuela']),
  ];
}

/* Detectar tabla de catálogo para técnica (si se usa ID) */
function tec_catalog_info(mysqli $db){
  $cands = ['categorias_tecnicas_evento','categorias_tecnicas','categorias_nivel'];
  foreach ($cands as $t) {
    if (table_exists($db, $t)) {
      $rs = $db->query("SHOW COLUMNS FROM $t");
      if (!$rs) continue;
      $have = [];
      while($r = $rs->fetch_assoc()) $have[strtolower($r['Field'])] = $r['Field'];
      $id = null; $nom = null;
      foreach (['id','categoria_id','cat_id'] as $k) if (isset($have[$k])) { $id=$have[$k]; break; }
      foreach (['nombre','name','titulo','title','descripcion'] as $k) if (isset($have[$k])) { $nom=$have[$k]; break; }
      if ($id && $nom) return ['table'=>$t,'id'=>$id,'nombre'=>$nom];
    }
  }
  return null;
}

/* ====== Cargar mapas ====== */
$pe_cols  = pe_get_cols($conexion);
if (isset($pe_cols['error'])) { echo '<div class="alert error">'.h($pe_cols['error']).'</div>'; exit; }
if (!$pe_cols['evento'] || !$pe_cols['rojo'] || !$pe_cols['azul']) {
  echo '<div class="alert error">Faltan columnas obligatorias en <b>peleas_evento</b>. Detectadas: <code>'.h(implode(', ', $pe_cols['_all'])).'</code></div>'; exit;
}

$cat_cols = cat_get_cols($conexion);
if (isset($cat_cols['error'])) { echo '<div class="alert error">'.h($cat_cols['error']).'</div>'; exit; }
if (!$cat_cols['id']) {
  echo '<div class="alert error">La tabla <b>categorias_evento</b> no tiene <code>id</code> detectable. Columnas: <code>'.h(implode(', ', $cat_cols['_all'])).'</code></div>'; exit;
}

$ce_cols = ce_get_cols($conexion);
if (isset($ce_cols['error'])) { echo '<div class="alert error">'.h($ce_cols['error']).'</div>'; exit; }
$tec_catalog = tec_catalog_info($conexion);

/* ====== Helpers de presentación ====== */
function fmt_kg($n){
  if ($n === null || $n === '' ) return null;
  if (is_numeric($n)) $n = (float)$n;
  return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
}
function label_peso_cat($row){
  $min = isset($row['ct_peso_min']) ? fmt_kg($row['ct_peso_min']) : null;
  $max = isset($row['ct_peso_max']) ? fmt_kg($row['ct_peso_max']) : null;
  $nom = trim((string)($row['ct_nombre'] ?? ''));
  $gen = trim((string)($row['ct_genero'] ?? ''));
  $eMin = isset($row['ct_edad_min']) ? (string)$row['ct_edad_min'] : '';
  $eMax = isset($row['ct_edad_max']) ? (string)$row['ct_edad_max'] : '';

  $peso = '-';
  if ($min !== null && $max !== null && $min !== '' && $max !== '')       $peso = $min.'–'.$max.' kg';
  elseif ($min !== null && $min !== '')                                   $peso = $min.' kg';
  elseif ($max !== null && $max !== '')                                   $peso = $max.' kg';
  elseif ($nom !== '')                                                    $peso = $nom;

  $suf = [];
  if ($gen !== '') $suf[] = $gen;
  if ($eMin !== '' || $eMax !== '') $suf[] = trim($eMin.'–'.$eMax);
  return $peso.( $suf ? '('.implode(', ', $suf).')' : '' );
}

/* =========================
   POST: Acciones (eliminar/crear)
   ========================= */

if (($_POST['accion'] ?? '') === 'eliminar_comp') {
  $token = $_POST['csrf'] ?? '';
  $comp_id = isset($_POST['comp_id']) && is_numeric($_POST['comp_id']) ? (int)$_POST['comp_id'] : 0;
  if (!csrf_ok($token)) { flash_err('CSRF inválido.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
  if ($comp_id <= 0) { flash_err('ID de competidor inválido.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

  $sqlChk = "SELECT 1 FROM peleas_evento
             WHERE ".bt($pe_cols['evento'])." = ?
               AND (".bt($pe_cols['rojo'])." = ? OR ".bt($pe_cols['azul'])." = ?)
             LIMIT 1";
  $st = $conexion->prepare($sqlChk);
  if (!$st) { flash_err('No se pudo validar referencias: '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
  $st->bind_param('iii', $evento_id, $comp_id, $comp_id);
  $st->execute(); $ref = $st->get_result(); $st->close();
  if ($ref && $ref->num_rows > 0) {
    flash_err('No podés eliminar: el competidor ya está en una pelea (Rojo/Azul). Eliminá/edita esas peleas primero.');
    header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
  }

  $del = $conexion->prepare("DELETE FROM competidores_evento WHERE id=? AND evento_id=?");
  if (!$del) { flash_err('No se pudo preparar la eliminación: '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
  $del->bind_param('ii', $comp_id, $evento_id);
  if ($del->execute() && $del->affected_rows > 0) {
    flash_ok('Competidor eliminado del evento.');
  } else {
    flash_err('No se pudo eliminar el competidor (puede no existir o no corresponder al evento).');
  }
  $del->close();
  header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
}

/* ========= Helpers de validación de reglas ========= */
function cargar_info_competidores(mysqli $db, array $ids, array $ce_cols, array $cat_cols){
  if (!$ids) return [];
  $place = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  // Notas:
  // - disciplina_id, modalidad_id, division_id -> coincidir obligatorios
  // - categoria_peso: usamos ce.categoria_peso_id si existe; si no, caemos a ce.categoria_tecnica_id (muchos esquemas lo usaron para peso)
  // - técnica: id/texto
  $catPesoCol = $ce_cols['cat_peso_id'] ?: 'categoria_tecnica_id'; // fallback por compatibilidad
  $escuelaId  = $ce_cols['escuela_id'] ?: null;

  $selEscuelaId = $escuelaId ? "ce.".bt($escuelaId)." AS escuela_id," : "NULL AS escuela_id,";
  $selTecText   = !empty($ce_cols['cat_tec_text']) ? "ce.".bt($ce_cols['cat_tec_text'])." AS cat_tec_text," : "NULL AS cat_tec_text,";
  $selTecId     = !empty($ce_cols['cat_tec_id'])   ? "ce.".bt($ce_cols['cat_tec_id'])."   AS cat_tec_id,"   : "NULL AS cat_tec_id,";

  $sql = "
    SELECT
      ce.id,
      ce.disciplina_id,
      ce.modalidad_id,
      ce.division_id,
      $selTecText
      $selTecId
      ce.".bt($catPesoCol)." AS cat_peso_id,
      ce.escuela_nombre
    FROM competidores_evento ce
    WHERE ce.id IN ($place)
  ";
  $st = $db->prepare($sql);
  if (!$st) throw new Exception('SQL prepare (info competidores): '.$db->error);
  $bind = []; $bind[]=$types; foreach ($ids as $i=>&$v) $bind[]=&$v;
  call_user_func_array([$st,'bind_param'],$bind);
  $st->execute();
  $res = $st->get_result();
  $out = [];
  while($r = $res->fetch_assoc()){
    $r['disciplina_id'] = (int)($r['disciplina_id'] ?? 0);
    $r['modalidad_id']  = (int)($r['modalidad_id'] ?? 0);
    $r['division_id']   = (int)($r['division_id'] ?? 0);
    $r['cat_peso_id']   = is_null($r['cat_peso_id']) ? null : (int)$r['cat_peso_id'];
    $r['cat_tec_id']    = isset($r['cat_tec_id']) ? (int)$r['cat_tec_id'] : null;
    $r['cat_tec_text']  = isset($r['cat_tec_text']) ? trim((string)$r['cat_tec_text']) : null;
    $out[(int)$r['id']] = $r;
  }
  $st->close();
  return $out;
}

function ya_tiene_pelea(mysqli $db, int $evento_id, int $comp_id, array $pe_cols): bool {
  $sql = "SELECT 1 FROM peleas_evento
          WHERE ".bt($pe_cols['evento'])." = ?
            AND (".bt($pe_cols['rojo'])." = ? OR ".bt($pe_cols['azul'])." = ?)
          LIMIT 1";
  $st = $db->prepare($sql);
  if (!$st) return false;
  $st->bind_param('iii', $evento_id, $comp_id, $comp_id);
  $st->execute();
  $ret = $st->get_result();
  $st->close();
  return $ret && $ret->num_rows > 0;
}

/* Crear pelea(s) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_pelea') {
  $token = $_POST['csrf'] ?? '';
  if (!csrf_ok($token)) { flash_err('CSRF inválido.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

  $formato = $_POST['formato'] ?? 'simple';
  $rondas  = isset($_POST['rondas']) && is_numeric($_POST['rondas']) ? (int)$_POST['rondas'] : 3;
  $obsBase = isset($_POST['observaciones']) ? trim((string)$_POST['observaciones']) : '';
  if ($rondas < 1 || $rondas > 12) { $rondas = 3; }

  $pairs = []; $todos = [];

  if ($formato === 'simple') {
    $rojo_id = (int)($_POST['rojo_id'] ?? 0);
    $azul_id = (int)($_POST['azul_id'] ?? 0);
    if ($rojo_id <= 0 || $azul_id <= 0) { flash_err('Seleccioná ambas esquinas (1 vs 1).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if ($rojo_id === $azul_id) { flash_err('No podés elegir al mismo competidor en ambas esquinas.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $todos = [$rojo_id, $azul_id];
    $pairs[] = [$rojo_id, $azul_id, ''];

  } elseif ($formato === 'triangular') {
    $tri_r = (int)($_POST['tri_rojo_id'] ?? 0);
    $tri_a = (int)($_POST['tri_azul_id'] ?? 0);
    $tri_l = (int)($_POST['tri_libre_id'] ?? 0);
    if ($tri_r<=0 || $tri_a<=0 || $tri_l<=0) { flash_err('Completá los 3 slots: Rojo (SF), Azul (SF) y Libre.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if (count(array_unique([$tri_r,$tri_a,$tri_l])) !== 3) { flash_err('Los 3 competidores deben ser distintos en Triangular.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $todos = [$tri_r,$tri_a,$tri_l];
    $pairs[] = [$tri_r, $tri_a, ' (Triangular - Semifinal)'];

  } else { // super4
    $sf1r = (int)($_POST['sf1_rojo_id'] ?? 0);
    $sf1a = (int)($_POST['sf1_azul_id'] ?? 0);
    $sf2r = (int)($_POST['sf2_rojo_id'] ?? 0);
    $sf2a = (int)($_POST['sf2_azul_id'] ?? 0);
    if ($sf1r<=0 || $sf1a<=0 || $sf2r<=0 || $sf2a<=0) { flash_err('Completá los 4 slots de semifinales (SF1 y SF2).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if (count(array_unique([$sf1r,$sf1a,$sf2r,$sf2a])) !== 4) { flash_err('No repitas competidores entre las semifinales (Super 4).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $todos = [$sf1r,$sf1a,$sf2r,$sf2a];
    $pairs[] = [$sf1r, $sf1a, ' (Super 4 - SF1)'];
    $pairs[] = [$sf2r, $sf2a, ' (Super 4 - SF2)'];
  }

  // Pertenencia al evento
  if ($todos) {
    $place = implode(',', array_fill(0, count($todos), '?'));
    $types = str_repeat('i', count($todos) + 1);
    $sql = "SELECT COUNT(*) AS c FROM competidores_evento WHERE evento_id = ? AND id IN ($place)";
    $st = $conexion->prepare($sql); if (!$st) { flash_err('SQL prepare (pertenencia): '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $bind = []; $bind[] = $types; $ev_copy = $evento_id; $bind[] = &$ev_copy; foreach ($todos as $i=>&$v) { $bind[] = &$v; }
    call_user_func_array([$st,'bind_param'],$bind);
    $st->execute(); $cOk = (int)($st->get_result()->fetch_assoc()['c'] ?? 0); $st->close();
    if ($cOk !== count($todos)) { flash_err('Algún competidor no pertenece al evento.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
  }

  // ✅ Validar MODALIDAD: todos con la misma (Regla #6, bloquea)
  if ($todos) {
    $place = implode(',', array_fill(0, count($todos), '?'));
    $types = str_repeat('i', count($todos));
    $sqlMod = "SELECT COUNT(DISTINCT modalidad_id) AS k FROM competidores_evento WHERE id IN ($place)";
    $st = $conexion->prepare($sqlMod);
    if (!$st) { flash_err('SQL prepare (validar modalidad): '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $bind = []; $bind[] = $types; foreach ($todos as $i=>&$v) { $bind[] = &$v; }
    call_user_func_array([$st,'bind_param'],$bind);
    $st->execute(); $k = (int)($st->get_result()->fetch_assoc()['k'] ?? 0); $st->close();
    if ($k !== 1) { flash_err('Los competidores no comparten la misma <b>modalidad</b> (Regla #6).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

    // obtener modalidad concreta
    $st = $conexion->prepare("SELECT modalidad_id FROM competidores_evento WHERE id IN ($place) LIMIT 1");
    if ($st) {
      $bind = []; $bind[] = $types; foreach ($todos as $i=>&$v) { $bind[] = &$v; }
      call_user_func_array([$st,'bind_param'],$bind);
      $st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close();
      $modalidad_compartida = isset($row['modalidad_id']) ? (int)$row['modalidad_id'] : null;
    } else { $modalidad_compartida = null; }
  }

  // Duplicadas exactas?
  foreach ($pairs as [$r,$a]) {
    $sql = "SELECT 1 FROM peleas_evento
            WHERE ".bt($pe_cols['evento'])." = ?
              AND ((".bt($pe_cols['rojo'])." = ? AND ".bt($pe_cols['azul'])." = ?) OR (".bt($pe_cols['rojo'])." = ? AND ".bt($pe_cols['azul'])." = ?))
            LIMIT 1";
    $st = $conexion->prepare($sql);
    if (!$st) { flash_err('SQL prepare (duplicadas): '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $st->bind_param('iiiii', $evento_id, $r, $a, $a, $r);
    $st->execute(); $dupe = $st->get_result(); $st->close();
    if ($dupe && $dupe->num_rows > 0) { flash_err('Alguna pelea ya existe (mismas esquinas).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
  }

  /* ===== Carga y validación de REGLAS ===== */
  // Cargamos toda la info necesaria una vez
  $info = cargar_info_competidores($conexion, $todos, $ce_cols, $cat_cols);

  // Reglas que BLOQUEAN: #3 (disciplina), #4 (división), #6 (ya validada), #7 (técnica)
  foreach ($pairs as [$r,$a]) {
    $R = $info[$r] ?? null; $A = $info[$a] ?? null;
    if (!$R || !$A) { flash_err('No se pudo cargar info de competidores para validar reglas.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

    // #3 misma disciplina (bloquea)
    if ((int)$R['disciplina_id'] !== (int)$A['disciplina_id']) {
      flash_err('Los competidores no comparten la misma <b>disciplina</b> (Regla #3).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
    }
    // #4 misma división (bloquea)
    if ((int)$R['division_id'] !== (int)$A['division_id']) {
      flash_err('Los competidores no comparten la misma <b>división</b> (Regla #4).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
    }
    // #7 misma categoría técnica (bloquea). Si hay ID, comparamos ID; si no, texto normalizado.
    $tec_ok = true;
    if (!is_null($R['cat_tec_id']) && !is_null($A['cat_tec_id'])) {
      $tec_ok = ((int)$R['cat_tec_id'] === (int)$A['cat_tec_id']);
    } else {
      $tR = mb_strtoupper(trim((string)$R['cat_tec_text'])); 
      $tA = mb_strtoupper(trim((string)$A['cat_tec_text']));
      // si alguno está vacío, consideramos distinto
      $tec_ok = ($tR !== '' && $tA !== '' && $tR === $tA);
    }
    if (!$tec_ok) {
      flash_err('Los competidores no comparten la misma <b>categoría técnica</b> (Regla #7).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
    }
  }

  // Reglas que AVISAN/PERMITE: #1 ya tiene pelea, #2 misma escuela, #5 peso distinto => PACTADA
  $avisos_globales = []; // para mostrar en flash_ok
  $sufijos_por_pair = []; // sufijo de observaciones por cada par

  foreach ($pairs as $idx => [$r,$a,$obsSuf]) {
    $R = $info[$r]; $A = $info[$a];
    $avisos = [];

    // #1 Ya tiene pelea (rojo/azul)
    if (ya_tiene_pelea($conexion, $evento_id, $r, $pe_cols)) { $avisos[] = 'Rojo ya tenía pelea en este evento'; }
    if (ya_tiene_pelea($conexion, $evento_id, $a, $pe_cols)) { $avisos[] = 'Azul ya tenía pelea en este evento'; }

    // #2 Misma escuela (avisar)
    $escR = trim((string)($R['escuela_nombre'] ?? ''));
    $escA = trim((string)($A['escuela_nombre'] ?? ''));
    if ($escR !== '' && $escR === $escA) {
      $avisos[] = 'Misma escuela';
    }

    // #5 Peso: si cat_peso_id distintos => PACTADA
    $pactada = false;
    $pesoR = $R['cat_peso_id']; $pesoA = $A['cat_peso_id'];
    if (!is_null($pesoR) && !is_null($pesoA) && (int)$pesoR !== (int)$pesoA) {
      $pactada = true;
      $avisos[] = 'PACTADA por peso';
    }

    $suf = '';
    if ($avisos) {
      $avisos_globales[] = '• '.($formato==='simple' ? '1 vs 1' : (strpos($obsSuf,'Super 4')!==false?'Super 4':'Triangular')).": ".implode(' — ', $avisos);
      $suf = ' ['.implode('] [', $avisos).']';
    }
    $sufijos_por_pair[$idx] = $suf;
  }

  // INSERT
  $conexion->begin_transaction();
  try {
    foreach ($pairs as $i => [$r,$a,$obsSuf]) {
      $cols = [$pe_cols['evento'], $pe_cols['rojo'], $pe_cols['azul']];
      $vals = [$evento_id, $r, $a];
      $types = 'iii';

      if (!empty($pe_cols['modalidad']) && isset($modalidad_compartida)) { $cols[] = $pe_cols['modalidad']; $vals[] = $modalidad_compartida; $types .= 'i'; }
      if ($pe_cols['rondas']) { $cols[] = $pe_cols['rondas']; $vals[] = $rondas; $types .= 'i'; }

      $obs_final = trim($obsBase.$obsSuf.$sufijos_por_pair[$i]);
      if ($pe_cols['obs'])    { $cols[] = $pe_cols['obs']; $vals[] = $obs_final; $types .= 's'; }

      $cols_bt = array_map('bt', $cols);
      $ph = implode(',', array_fill(0, count($cols_bt), '?'));
      $sql = "INSERT INTO peleas_evento (".implode(',', $cols_bt).") VALUES ($ph)";
      $st = $conexion->prepare($sql);
      if (!$st) throw new Exception('SQL prepare (insert): '.$conexion->error);

      $bind = []; $bind[] = $types; foreach ($vals as $k=>&$v) { $bind[] = &$v; }
      call_user_func_array([$st, 'bind_param'], $bind);
      if (!$st->execute()) { $err = $st->error; $st->close(); throw new Exception('No se pudo guardar una pelea: '.$err); }
      $st->close();
    }
    $conexion->commit();
  } catch (Throwable $e) {
    $conexion->rollback();
    flash_err($e->getMessage());
    header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
  }

  $creadas = count($pairs);
  $txtFmt = ($formato==='simple' ? '1 vs 1' : ($formato==='triangular' ? 'Triangular (SF + Libre)' : 'Super 4 (semifinales)'));
  $warnTxt = $avisos_globales ? "<br><small class=\"muted\">Avisos:<br>".implode('<br>', array_map('h',$avisos_globales))."</small>" : '';
  flash_ok("Se crearon $creadas pelea(s) — formato $txtFmt.$warnTxt");
  header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id);
  exit;
}

/* ====== Catálogos (para filtros) ====== */
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas_evento ORDER BY nombre");
$modalidades = $conexion->query("SELECT id, nombre FROM modalidades_evento ORDER BY nombre");
$divisiones  = $conexion->query("SELECT id, nombre FROM divisiones_evento ORDER BY id");

/* ====== SELECT dinámicos para categorias_evento ====== */
$selNombre = $cat_cols['nombre']   ? "ct.".bt($cat_cols['nombre'])."   AS ct_nombre"    : "NULL AS ct_nombre";
$selPmin   = $cat_cols['peso_min'] ? "ct.".bt($cat_cols['peso_min'])." AS ct_peso_min"  : "NULL AS ct_peso_min";
$selPmax   = $cat_cols['peso_max'] ? "ct.".bt($cat_cols['peso_max'])." AS ct_peso_max"  : "NULL AS ct_peso_max";
$selGenero = $cat_cols['genero']   ? "ct.".bt($cat_cols['genero'])."   AS ct_genero"    : "NULL AS ct_genero";
$selEmin   = $cat_cols['edad_min'] ? "ct.".bt($cat_cols['edad_min'])." AS ct_edad_min"  : "NULL AS ct_edad_min";
$selEmax   = $cat_cols['edad_max'] ? "ct.".bt($cat_cols['edad_max'])." AS ct_edad_max"  : "NULL AS ct_edad_max";

/* ====== Query de competidores (listado) ====== */
$selTecText = "NULL AS cat_tec_text";
$selTecId   = "NULL AS cat_tec_id";
if (!empty($ce_cols['cat_tec_text'])) $selTecText = "ce.".bt($ce_cols['cat_tec_text'])." AS cat_tec_text";
if (!empty($ce_cols['cat_tec_id']))   $selTecId   = "ce.".bt($ce_cols['cat_tec_id'])."   AS cat_tec_id";

/* Nombre técnica desde catálogo si existe */
$selTecName = "NULL AS cat_tec_name";
$joinTec    = "";
if (!empty($ce_cols['cat_tec_id']) && $tec_catalog) {
  $joinTec  = "LEFT JOIN ".$tec_catalog['table']." tcat ON tcat.".bt($tec_catalog['id'])." = ce.".bt($ce_cols['cat_tec_id']);
  $selTecName = "tcat.".bt($tec_catalog['nombre'])." AS cat_tec_name";
}

/* AVISO: muchos esquemas guardaron el id de categoría de PESO en ce.categoria_tecnica_id.
   Para mantener compatibilidad, dejamos el JOIN así. */
$sql = "
SELECT
  ce.id,
  ce.apellido, ce.nombre, ce.dni, ce.edad,
  ce.foto_competidor, ce.escuela_logo, ce.escuela_nombre,
  ce.disciplina_id AS disc_id,
  ce.modalidad_id  AS mod_id,
  ce.division_id   AS div_id,
  $selTecText,
  $selTecId,
  $selTecName,
  d.nombre  AS disciplina,
  m.nombre  AS modalidad,
  dv.nombre AS division,
  ct.".bt($cat_cols['id'])." AS ct_id,
  $selNombre,
  $selPmin,
  $selPmax,
  $selGenero,
  $selEmin,
  $selEmax
FROM competidores_evento ce
LEFT JOIN disciplinas_evento d  ON d.id  = ce.disciplina_id
LEFT JOIN modalidades_evento m  ON m.id  = ce.modalidad_id
LEFT JOIN divisiones_evento dv ON dv.id = ce.division_id
LEFT JOIN categorias_evento ct  ON ct.".bt($cat_cols['id'])." = ce.categoria_tecnica_id
$joinTec
WHERE ce.evento_id = ?
";

$types = 'i';
$params = [$evento_id];

$f_disciplina_id        = (isset($_GET['disciplina_id'])        && is_numeric($_GET['disciplina_id']))        ? (int)$_GET['disciplina_id']        : null;
$f_modalidad_id         = (isset($_GET['modalidad_id'])         && is_numeric($_GET['modalidad_id']))         ? (int)$_GET['modalidad_id']         : null;
$f_division_id          = (isset($_GET['division_id'])          && is_numeric($_GET['division_id']))          ? (int)$_GET['division_id']          : null;
$f_categoria_peso_id    = (isset($_GET['categoria_peso_id'])    && is_numeric($_GET['categoria_peso_id']))    ? (int)$_GET['categoria_peso_id']    : null;
$f_categoria_tecnica_id = (isset($_GET['categoria_tecnica_id']) && is_numeric($_GET['categoria_tecnica_id'])) ? (int)$_GET['categoria_tecnica_id'] : null;

if (!is_null($f_disciplina_id))        { $sql .= " AND ce.disciplina_id = ?";        $types.='i'; $params[]=$f_disciplina_id; }
if (!is_null($f_modalidad_id))         { $sql .= " AND ce.modalidad_id = ?";         $types.='i'; $params[]=$f_modalidad_id; }
if (!is_null($f_division_id))          { $sql .= " AND ce.division_id = ?";          $types.='i'; $params[]=$f_division_id; }
if (!is_null($f_categoria_peso_id))    { $sql .= " AND ce.categoria_tecnica_id = ?"; $types.='i'; $params[]=$f_categoria_peso_id; }
/* Si querés filtrar por técnica real con ID, dejá habilitado; si usás texto, ignorá este filtro */
if (!is_null($f_categoria_tecnica_id) && !empty($ce_cols['cat_tec_id'])) {
  $sql .= " AND ce.".bt($ce_cols['cat_tec_id'])." = ?"; $types.='i'; $params[]=$f_categoria_tecnica_id;
}

$sql .= " ORDER BY ct.".bt($cat_cols['id']).", dv.nombre, ce.apellido, ce.nombre";

$st = $conexion->prepare($sql);
if (!$st) { http_response_code(500); exit('❌ SQL prepare (listar competidores): '.$conexion->error); }
$refs = []; foreach ($params as $k=>&$v) { $refs[$k] = &$v; } array_unshift($refs, $types);
call_user_func_array([$st,'bind_param'], $refs);
$st->execute(); $res = $st->get_result();
$competidores = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

/* ====== Opciones de peso y técnica para filtros ====== */
$pesos = [];
$cat_rs = $conexion->query("SELECT ct.".bt($cat_cols['id'])." AS ct_id, $selNombre, $selPmin, $selPmax, $selGenero, $selEmin, $selEmax FROM categorias_evento ct ORDER BY ct.".bt($cat_cols['id']));
if ($cat_rs) while($t = $cat_rs->fetch_assoc()){ $pesos[] = ['id'=>(int)$t['ct_id'], 'nombre'=>label_peso_cat($t)]; }

/* Catálogo técnica (si aplica con ID) */
$tec_opts = [];
if (!empty($ce_cols['cat_tec_id']) && $tec_catalog) {
  $rs = $conexion->query("SELECT ".bt($tec_catalog['id'])." AS id, ".bt($tec_catalog['nombre'])." AS nombre FROM ".$tec_catalog['table']." ORDER BY 1");
  if ($rs) while($r = $rs->fetch_assoc()) $tec_opts[] = ['id'=>(int)$r['id'],'nombre'=>$r['nombre']];
}

$placeholderFoto = 'assets/placeholder-user.png';
$placeholderLogo = 'assets/placeholder-logo.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Organizar Peleas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor { max-width: 1200px; margin: 0 auto; padding: 16px; }
    .alert { padding:10px 12px;border-radius:8px;margin-bottom:12px }
    .alert.error{background:#fdecea;color:#b71c1c;border:1px solid #f5c6cb}
    .alert.ok{background:#e6f4ea;color:#0f5132;border:1px solid #badbcc}
    .filters { display:grid; grid-template-columns: repeat(6, minmax(160px,1fr)); gap:12px; align-items:end; margin-bottom: 14px; }
    @media (max-width: 1000px){ .filters{ grid-template-columns: repeat(3,1fr);} }
    @media (max-width: 640px){ .filters{ grid-template-columns: 1fr;} }
    label { font-weight:600; font-size:14px; }
    select, button, input[type=number], input[type=text] { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:8px; }
    .table-wrap { width:100%; overflow-x:auto; }
    table { width:100%; border-collapse:collapse; min-width: 1200px; }
    th, td { border:1px solid #e7e7e7; padding:8px 10px; vertical-align:middle; }
    th { background:#f6f7f9; text-align:left; }
    .avatar { width:50px; height:50px; object-fit:cover; border-radius:8px; }
    .logo   { width:50px; height:50px; object-fit:contain; }
    .cols { display:grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    @media (max-width: 880px){ .cols{ grid-template-columns: 1fr; } }
    .btn-primary { background:#1e88e5; color:#fff; border:0; padding:10px 14px; border-radius:10px; cursor:pointer; }
    .btn-primary:disabled{ opacity:.6; cursor:not-allowed; }
    .btn-secondary{background:#e9ecef;color:#0f172a;border:0;padding:10px 14px;border-radius:10px;cursor:pointer;text-decoration:none;display:inline-block}
    .btn-danger{background:#dc2626;color:#fff;border:0;padding:8px 12px;border-radius:8px;cursor:pointer}
    .muted { color:#475569; font-size:13px; }
    form.inline { display:inline; }
    .slot-grid { display:grid; grid-template-columns: repeat(2,minmax(220px,1fr)); gap:10px; }
    .slot-grid .full { grid-column: 1 / -1; }
  </style>
</head>
<body>
<div class="contenedor">
  <h2>🥊 Organización de Peleas — Evento #<?= (int)$evento_id ?></h2>

  <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert error"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['flash_ok'])): ?>
    <div class="alert ok"><?= $_SESSION['flash_ok']; unset($_SESSION['flash_ok']); ?></div>
  <?php endif; ?>

  <!-- Filtros -->
  <form method="GET" class="filters">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <div>
      <label>Disciplina</label>
      <select name="disciplina_id">
        <option value="">Todas</option>
        <?php while($d = $disciplinas->fetch_assoc()): ?>
          <option value="<?= (int)$d['id'] ?>" <?= ($f_disciplina_id===(int)$d['id'])?'selected':'' ?>><?= h($d['nombre']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>Modalidad</label>
      <select name="modalidad_id">
        <option value="">Todas</option>
        <?php while($m = $modalidades->fetch_assoc()): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ($f_modalidad_id===(int)$m['id'])?'selected':'' ?>><?= h($m['nombre']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>División</label>
      <select name="division_id">
        <option value="">Todas</option>
        <?php while($dv = $divisiones->fetch_assoc()): ?>
          <option value="<?= (int)$dv['id'] ?>" <?= ($f_division_id===(int)$dv['id'])?'selected':'' ?>><?= h($dv['nombre']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>Categoría de Peso</label>
      <select name="categoria_peso_id">
        <option value="">Todas</option>
        <?php foreach ($pesos as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ($f_categoria_peso_id===(int)$p['id'])?'selected':'' ?>>
            <?= h($p['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="muted">* Desde <b>categorias_evento</b> (min–max kg / género / edades).</small>
    </div>
    <div>
      <label>Categoría Técnica</label>
      <?php if (!empty($ce_cols['cat_tec_id']) && $tec_catalog): ?>
        <select name="categoria_tecnica_id">
          <option value="">Todas</option>
          <?php foreach ($tec_opts as $opt): ?>
            <option value="<?= (int)$opt['id'] ?>" <?= ($f_categoria_tecnica_id===(int)$opt['id'])?'selected':'' ?>>
              <?= h($opt['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input type="text" name="categoria_tecnica_text" value="<?= h($_GET['categoria_tecnica_text'] ?? '') ?>" placeholder="A / B / C / N / CBA…" />
      <?php endif; ?>
      <small class="muted">* Técnica = A/B/C/N, CBA, etc.</small>
    </div>
    <div>
      <button type="submit" class="btn-primary">🔍 Buscar</button>
    </div>
  </form>

  <!-- CREACIÓN DE PELEA(S) -->
  <form method="POST" action="" id="form-bout">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <input type="hidden" name="accion" value="crear_pelea">

    <div class="cols" style="margin:12px 0;">
      <div>
        <label>Formato</label>
        <select name="formato" id="formato">
          <option value="simple">1 vs 1</option>
          <option value="triangular">Triangular (3 competidores)</option>
          <option value="super4">Super 4 (cuadrangular)</option>
        </select>
      </div>
      <div>
        <label>Rondas</label>
        <input type="number" name="rondas" id="rondas_input" min="1" max="12" value="3">
      </div>
      <div>
        <label>Observaciones</label>
        <input type="text" name="observaciones" placeholder="(opcional)">
      </div>
    </div>

    <!-- SLOTS DINÁMICOS -->
    <div id="slots-container" class="slot-grid" style="margin-bottom:10px;"></div>

    <div class="cols" style="grid-template-columns: 1fr auto; align-items:center;">
      <div class="muted">
        <b>Triangular:</b> SF (Rojo vs Azul) + <u>Libre</u> (espera al ganador).<br>
        <b>Super 4:</b> SF1 y SF2. La final queda libre y se arma luego con ganadores.
      </div>
      <div>
        <button type="submit" class="btn-primary" id="btn-guardar">✅ Confirmar y Agregar pelea(s)</button>
        <a class="btn-secondary" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?>">📋 Ver / Editar / Eliminar peleas</a>
      </div>
    </div>
  </form> <!-- 👈 CERRADO ANTES DEL LISTADO PARA EVITAR ANIDAR FORMS -->

  <!-- LISTADO (fuera del form de creación) -->
  <div class="table-wrap" style="margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th>Foto</th>
          <th>Apellido y Nombre</th>
          <th>DNI</th>
          <th>Edad</th>
          <th>Disciplina</th>
          <th>Modalidad</th>
          <th>Cat. Técnica</th>
          <th>Cat. de Peso</th>
          <th>Peso (min–max)</th>
          <th>División</th>
          <th>Escuela</th>
          <th>Logo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$competidores): ?>
        <tr><td colspan="13">No hay competidores con esos filtros.</td></tr>
      <?php else: foreach ($competidores as $c):
        $srcFoto = !empty($c['foto_competidor']) ? $c['foto_competidor'] : $placeholderFoto;
        $srcLogo = !empty($c['escuela_logo'])    ? $c['escuela_logo']    : $placeholderLogo;

        // Técnica real (texto directo o desde catálogo)
        $catTec = '-';
        if (!empty($c['cat_tec_name']))      $catTec = $c['cat_tec_name'];
        elseif (!empty($c['cat_tec_text']))  $catTec = $c['cat_tec_text'];

        $catLbl  = label_peso_cat([
          'ct_peso_min'=>$c['ct_peso_min'] ?? null,
          'ct_peso_max'=>$c['ct_peso_max'] ?? null,
          'ct_nombre'  =>$c['ct_nombre']   ?? '',
          'ct_genero'  =>$c['ct_genero']   ?? '',
          'ct_edad_min'=>$c['ct_edad_min'] ?? '',
          'ct_edad_max'=>$c['ct_edad_max'] ?? '',
        ]);
        $pesoSolo = (function($min,$max){
          $min = fmt_kg($min); $max = fmt_kg($max);
          if ($min && $max) return $min.'–'.$max.' kg';
          if ($min) return $min.' kg';
          if ($max) return $max.' kg';
          return '-';
        })($c['ct_peso_min'] ?? null, $c['ct_peso_max'] ?? null);

        $min = isset($c['ct_peso_min']) ? fmt_kg($c['ct_peso_min']) : '';
        $max = isset($c['ct_peso_max']) ? fmt_kg($c['ct_peso_max']) : '';
        $pesoEtiqueta = ($min && $max) ? "{$min}–{$max} kg" : (($min || $max) ? (($min?:$max).' kg') : ($c['ct_nombre'] ?? '-'));
        $labelOption = trim(($c['apellido'].' '.$c['nombre']).' — '.$pesoEtiqueta.' / '.($c['division'] ?? '-').' / '.($c['modalidad'] ?? '-'));
      ?>
        <tr>
          <td><img src="<?= h($srcFoto) ?>" class="avatar" alt="Foto"></td>
          <td><?= h($c['apellido'].' '.$c['nombre']) ?></td>
          <td><?= h($c['dni']) ?></td>
          <td><?= h($c['edad']) ?></td>
          <td><span class="muted"><?= h($c['disciplina'] ?? '-') ?></span></td>
          <td><?= h($c['modalidad'] ?? '-') ?></td>
          <td><?= h($catTec) ?></td>
          <td><?= h($catLbl) ?></td>
          <td><?= h($pesoSolo) ?></td>
          <td><?= h($c['division'] ?? '-') ?></td>
          <td><?= h($c['escuela_nombre'] ?? '-') ?></td>
          <td><img src="<?= h($srcLogo) ?>" class="logo" alt="Logo"></td>
          <td>
            <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar competidor del evento?');">
              <input type="hidden" name="accion" value="eliminar_comp">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="comp_id" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="btn-danger">🗑️ Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Opciones para los selects (sin PHP corto dentro de JS) -->
  <?php
    ob_start();
    echo '<option value="">Seleccioná competidor…</option>';
    foreach ($competidores as $c) {
      $min = isset($c['ct_peso_min']) ? fmt_kg($c['ct_peso_min']) : '';
      $max = isset($c['ct_peso_max']) ? fmt_kg($c['ct_peso_max']) : '';
      $peso = ($min && $max) ? "{$min}–{$max} kg" : (($min || $max) ? (($min?:$max).' kg') : ($c['ct_nombre'] ?? '-'));
      $label = trim(($c['apellido'].' '.$c['nombre']).' — '.$peso.' / '.($c['division'] ?? '-').' / '.($c['modalidad'] ?? '-'));
      $val   = (int)$c['id'];
      $modId = (int)($c['mod_id'] ?? 0);
      $divId = (int)($c['div_id'] ?? 0);
      $disId = (int)($c['disc_id'] ?? 0);
      echo '<option value="'.$val.'" data-mod="'.$modId.'" data-div="'.$divId.'" data-disc="'.$disId.'">'.h($label).'</option>';
    }
    $OPTIONS_HTML = ob_get_clean();
  ?>
</div>

<script>
  const formatoSel = document.getElementById('formato');
  const slots = document.getElementById('slots-container');
  const btn = document.getElementById('btn-guardar');
  const optionsHTML = <?php echo json_encode($OPTIONS_HTML, JSON_UNESCAPED_UNICODE); ?>;

  function selectTpl(name, label){
    return `
      <div>
        <label>${label}</label>
        <select name="${name}" class="slot-select">
          ${optionsHTML}
        </select>
      </div>
    `;
  }

  function renderSlots(){
    const fmt = formatoSel.value;
    let html = '';
    if (fmt === 'simple'){
      html = `${selectTpl('rojo_id', 'Rincón Rojo')}${selectTpl('azul_id', 'Rincón Azul')}`;
    } else if (fmt === 'triangular'){
      html = `
        ${selectTpl('tri_rojo_id', 'Triangular — SF (Rojo)')}
        ${selectTpl('tri_azul_id', 'Triangular — SF (Azul)')}
        <div class="full" style="height:0;"></div>
        ${selectTpl('tri_libre_id', 'Triangular — Libre (espera la final)')}
      `;
    } else {
      html = `
        <div class="full" style="font-weight:600;">Semifinal 1</div>
        ${selectTpl('sf1_rojo_id', 'SF1 — Rojo')}
        ${selectTpl('sf1_azul_id', 'SF1 — Azul')}
        <div class="full" style="font-weight:600;margin-top:6px;">Semifinal 2</div>
        ${selectTpl('sf2_rojo_id', 'SF2 — Rojo')}
        ${selectTpl('sf2_azul_id', 'SF2 — Azul')}
        <div class="full muted" style="margin-top:6px;">Final libre: se arma luego con ganadores</div>
      `;
    }
    slots.innerHTML = html;
    attachUniqueLogic();
    validar();
  }

  function getAttr(selectEl, attr){
    const v = selectEl.value;
    if (!v) return null;
    const opt = selectEl.querySelector(`option[value="${v}"]`);
    if (!opt) return null;
    const raw = opt.getAttribute(attr);
    return raw === null ? null : raw;
  }
  function getIntAttr(selectEl, attr){
    const raw = getAttr(selectEl, attr);
    return raw === null ? 0 : parseInt(raw||'0');
  }

  function attachUniqueLogic(){
    const selects = Array.from(document.querySelectorAll('.slot-select'));
    function refreshDisables(){
      const used = new Set(selects.map(s => s.value).filter(v => v));
      selects.forEach(sel => {
        const cur = sel.value;
        Array.from(sel.options).forEach(opt => {
          if (!opt.value) return;
          opt.disabled = (opt.value !== cur) && used.has(opt.value);
        });
      });
    }
    selects.forEach(sel => sel.addEventListener('change', () => { refreshDisables(); validar(); }));
    refreshDisables();
  }

  function validar(){
    if (!btn) return;
    const fmt = formatoSel.value;

    function getVal(name){ const s = document.querySelector(`[name="${name}"]`); return s ? parseInt(s.value||'0') : 0; }
    function getSel(name){ return document.querySelector(`[name="${name}"]`); }
    function same(list, attr){
      const vals = list.map(n => getIntAttr(getSel(n), attr)).filter(Boolean);
      if (vals.length < list.length) return false;
      return new Set(vals).size === 1;
    }

    if (fmt === 'simple'){
      const r = getVal('rojo_id'), a = getVal('azul_id');
      const okBase = (r && a && r!==a);
      const okMod  = same(['rojo_id','azul_id'], 'data-mod'); // Regla 6 (cliente)
      const okDiv  = same(['rojo_id','azul_id'], 'data-div'); // Regla 4 (cliente)
      const okDis  = same(['rojo_id','azul_id'], 'data-disc'); // Regla 3 (cliente)
      btn.disabled = !(okBase && okMod && okDiv && okDis);
      btn.title = btn.disabled ? "Elegí Rojo y Azul distintos y con MISMA modalidad, división y disciplina." : "";
      return;
    }
    if (fmt === 'triangular'){
      const r = getVal('tri_rojo_id'), a = getVal('tri_azul_id'), l = getVal('tri_libre_id');
      const all = [r,a,l].filter(Boolean);
      const okBase = (r && a && l && (new Set(all).size===3));
      const okMod  = same(['tri_rojo_id','tri_azul_id','tri_libre_id'],'data-mod');
      const okDiv  = same(['tri_rojo_id','tri_azul_id','tri_libre_id'],'data-div');
      const okDis  = same(['tri_rojo_id','tri_azul_id','tri_libre_id'],'data-disc');
      btn.disabled = !(okBase && okMod && okDiv && okDis);
      btn.title = btn.disabled ? "Los 3 deben ser distintos y compartir MISMA modalidad, división y disciplina." : "";
      return;
    }
    const names = ['sf1_rojo_id','sf1_azul_id','sf2_rojo_id','sf2_azul_id'];
    const vals = names.map(getVal).filter(Boolean);
    const okBase = (vals.length===4 && (new Set(vals).size===4));
    const okMod  = same(names,'data-mod');
    const okDiv  = same(names,'data-div');
    const okDis  = same(names,'data-disc');
    btn.disabled = !(okBase && okMod && okDiv && okDis);
    btn.title = btn.disabled ? "Completá SF1 y SF2 con 4 distintos y MISMA modalidad, división y disciplina para todos." : "";
  }

  formatoSel.addEventListener('change', renderSlots);
  renderSlots();
</script>
</body>
</html>
