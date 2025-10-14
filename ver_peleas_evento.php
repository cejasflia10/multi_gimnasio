<?php
/* =========================
   ver_peleas_evento.php — Lista de peleas con impresión/compartir optimizada
   • Buscador por Apellido y Escuela/Academia (filtra rojo/azul)
   • Modalidad visible (prioriza pelea > texto > obs)
   • SIN columna “Técnica”
   • Tarjetas con recuadros marcados
   • PDF A4 vertical centrado; texto aprovecha ancho
   • Encabezado con NOMBRE DEL EVENTO BIEN GRANDE (eventos_deportivos.titulo si existe)
   • Pesajes (inputs + delta) — en share/print se ocultan inputs y se muestra texto
   • Link de “Vista para imprimir/compartir”: ?share=1
   • NUEVO: share se auto-actualiza (polling) sin reenviar link
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
function iniciales($ap,$nom){
  $a = mb_substr(trim((string)$ap),0,1,'UTF-8');
  $n = mb_substr(trim((string)$nom),0,1,'UTF-8');
  $res = trim($a.$n);
  return $res !== '' ? mb_strtoupper($res,'UTF-8') : '—';
}
function inicial($txt){
  $x = mb_substr(trim((string)$txt),0,1,'UTF-8');
  return $x !== '' ? mb_strtoupper($x,'UTF-8') : 'G';
}

/* ========= flags / params ========= */
$evento_id = (int)($_GET['evento_id'] ?? $_POST['evento_id'] ?? $_SESSION['evento_id_actual'] ?? $_SESSION['evento_id'] ?? 0);
if ($evento_id <= 0) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>evento_id</b>. Abrí esta página desde el evento.</div>';
  exit;
}
$_SESSION['evento_id_actual'] = $evento_id;

$SHARE = (isset($_GET['share']) && (string)$_GET['share'] === '1'); // vista limpia para imprimir/compartir

/* === parámetros de búsqueda (nuevo) === */
$s_ape = trim((string)($_GET['ape'] ?? '')); // Apellido
$s_esc = trim((string)($_GET['esc'] ?? '')); // Escuela/Academia

/* ========= (NUEVO) utilidades de firma/versión + endpoint poll ========= */
function pick_col_from_list(array $colsMap, array $cands){
  foreach ($cands as $c){ $lc=strtolower($c); if (isset($colsMap[$lc])) return $colsMap[$lc]; }
  return null;
}

/** Calcula firma estable del estado de un evento (cambia si cambian sus peleas/orden/fechas) */
function compute_event_signature(mysqli $cx, int $evento_id, array $colsMap): string {
  if ($evento_id <= 0) return 'ev0';

  $C_ID  = pick_col_from_list($colsMap, ['id','pelea_id','id_pelea']);
  $C_EVT = pick_col_from_list($colsMap, ['evento_id','id_evento','evento']);
  $C_ORD = pick_col_from_list($colsMap, ['orden','orden_manual','nro','nro_orden','posicion','position','sequence','rank','numero','nro_pelea','sort']);
  $C_FEC = pick_col_from_list($colsMap, ['updated_at','modificado_en','editado_en','last_update','ts','timestamp','fecha','creado_en','created_at','fh_creacion']);

  $parts = ["COUNT(*) AS c"];
  if ($C_ID)  $parts[] = "MAX(`$C_ID`) AS mid";
  if ($C_ORD) $parts[] = "MAX(`$C_ORD`) AS mord";
  if ($C_FEC) $parts[] = "MAX(`$C_FEC`) AS mfec";

  $sql = "SELECT ".implode(", ", $parts)." FROM `peleas_evento` WHERE ".($C_EVT ? "`$C_EVT`=?" : "1=0");
  if (!($st = $cx->prepare($sql))) {
    return 'ev_fallback_'.md5((string)time());
  }
  $st->bind_param('i',$evento_id);
  $st->execute();
  $res = $st->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $st->close();

  $sigBase = json_encode($row ?: []);
  return 'ev'.md5($sigBase ?: 'x');
}

/* Endpoint AJAX: ver_peleas_evento.php?ajax=poll&evento_id=123  -> {ok:true, ver:"..."} */
if (isset($_GET['ajax']) && $_GET['ajax']==='poll') {
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Set-Cookie');
  header('Content-Type: application/json; charset=utf-8');

  $evento_id_poll = (int)($_GET['evento_id'] ?? 0);

  $colsMap = [];
  if ($r = $conexion->query("SHOW COLUMNS FROM `peleas_evento`")) {
    while($c = $r->fetch_assoc()){ $colsMap[strtolower($c['Field'])] = $c['Field']; }
    $r->close();
  }
  $ver = compute_event_signature($conexion, $evento_id_poll, $colsMap);
  echo json_encode(['ok'=>true,'ver'=>$ver], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ========= nombre del evento ========= */
function obtener_nombre_evento(mysqli $cx, int $evento_id, bool $debug=false): string {
  if ($evento_id <= 0) return 'Evento #'.$evento_id;

  $logs = [];
  $log = function($m) use (&$logs,$debug){ if($debug) $logs[] = $m; };
  $dumpLogs = function() use (&$logs,$debug){ if($debug){ foreach($logs as $l){ echo "<!-- $l -->\n"; } }};

  $existe = function(string $tabla) use ($cx): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    if (!($st = $cx->prepare($sql))) return false;
    $st->bind_param('s',$tabla); $st->execute();
    $res = $st->get_result(); $ok = $res && $res->num_rows===1; $st->close();
    return $ok;
  };
  $trySimple = function(string $sql, int $id, string $nota) use ($cx,$log){
    $log("Intento: $nota");
    if(!($st=$cx->prepare($sql))){ $log("  ✗ prepare: ".$cx->error); return null; }
    $st->bind_param('i',$id); $st->execute();
    $res = $st->get_result(); $out=null;
    if($res && ($row=$res->fetch_assoc())){
      $nom = trim((string)($row['nombre'] ?? $row['titulo'] ?? ''));
      if($nom!=='') $out=$nom;
    }
    $st->close();
    $log($out ? "  ✓ $out" : "  ✗ sin filas");
    return $out;
  };

  $tED = $existe('eventos_deportivos');
  $tEv = $existe('eventos');
  $tEvt= $existe('evento');
  $tPE = $existe('peleas_evento');
  $tCE = $existe('competidores_evento');

  if ($tED) {
    if ($nom = $trySimple("SELECT `titulo` FROM `eventos_deportivos` WHERE `id`=? LIMIT 1", $evento_id, "eventos_deportivos.id -> titulo")) {
      $dumpLogs(); return $nom;
    }
  }
  if ($tEv) {
    if ($nom = $trySimple("SELECT `nombre` FROM `eventos` WHERE `id`=? LIMIT 1", $evento_id, "eventos.id -> nombre")) {
      $dumpLogs(); return $nom;
    }
  }
  if ($tEvt) {
    if ($nom = $trySimple("SELECT `nombre` FROM `evento` WHERE `id`=? LIMIT 1", $evento_id, "evento.id -> nombre")) {
      $dumpLogs(); return $nom;
    }
  }
  if ($tPE && $tED) {
    if ($nom = $trySimple(
      "SELECT ed.`titulo` AS titulo
         FROM `peleas_evento` p
         JOIN `eventos_deportivos` ed ON ed.`id` = p.`evento_id`
        WHERE p.`evento_id`=? LIMIT 1",
      $evento_id,
      "JOIN peleas_evento → eventos_deportivos"
    )) { $dumpLogs(); return $nom; }
  }
  if ($tCE && $tED) {
    if ($nom = $trySimple(
      "SELECT ed.`titulo` AS titulo
         FROM `competidores_evento` c
         JOIN `eventos_deportivos` ed ON ed.`id` = c.`evento_id`
        WHERE c.`evento_id`=? LIMIT 1",
      $evento_id,
      "JOIN competidores_evento → eventos_deportivos"
    )) { $dumpLogs(); return $nom; }
  }

  $dumpLogs();
  return 'Evento #'.$evento_id;
}
$evento_nombre = obtener_nombre_evento($conexion, $evento_id, isset($_GET['debug']));

/* ========= columnas peleas_evento ========= */
$cols = [];
$res = $conexion->query("SHOW COLUMNS FROM peleas_evento");
if (!$res) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'</div>'; exit; }
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
$C_PESO_REAL_R = $pick(['peso_real_rojo','rojo_peso_real','peso_real_r']);
$C_PESO_REAL_A = $pick(['peso_real_azul','azul_peso_real','peso_real_a']);

/* Modalidad a nivel PELEA (si existe) */
$C_MODAL_P_ID  = $pick(['modalidad_id','id_modalidad','modalidad_evento_id']);
$C_MODAL_P_TXT = $pick(['modalidad','modo','reglamento']);

if (!$C_EVENTO || !$C_ROJO || !$C_AZUL) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #fdecea;background:#ffebee;color:#b71c1c;border-radius:8px;">Faltan columnas obligatorias en <b>peleas_evento</b> (evento/rojo/azul).</div>'; exit;
}

/* ========= (NUEVO) firma actual del evento ========= */
$__evento_sig = compute_event_signature($conexion, $evento_id, $cols);

/* ========= catálogos ========= */
$tablaModal = (($t=$conexion->query("SHOW TABLES LIKE 'modalidades_evento'")) && $t->num_rows>0) ? 'modalidades_evento' : null;
$tablaDiv   = (($t=$conexion->query("SHOW TABLES LIKE 'divisiones_evento'")) && $t->num_rows>0) ? 'divisiones_evento' : null;

$MOD_LABEL_COL='nombre'; $DIV_LABEL_COL='nombre';
if ($tablaModal){
  $mc=[]; if($rc=$conexion->query("SHOW COLUMNS FROM $tablaModal")){ while($r=$rc->fetch_assoc()){ $mc[strtolower($r['Field'])]=$r['Field']; } }
  $MOD_LABEL_COL=$mc['nombre']??($mc['modalidad']??($mc['descripcion']??($mc['name']??'nombre')));
}
if ($tablaDiv){
  $dv=[]; if($rc=$conexion->query("SHOW COLUMNS FROM $tablaDiv")){ while($r=$rc->fetch_assoc()){ $dv[strtolower($r['Field'])]=$r['Field']; } }
  $DIV_LABEL_COL=$dv['nombre']??($dv['division']??($dv['descripcion']??($dv['name']??'nombre')));
}

/* ========= acciones POST ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$SHARE) {
  $evento_id = (int)($_POST['evento_id'] ?? $evento_id);
  $_SESSION['evento_id_actual'] = $evento_id;
  $accion   = $_POST['accion'] ?? '';
  $pelea_id = isset($_POST['pelea_id']) && is_numeric($_POST['pelea_id']) ? (int)$_POST['pelea_id'] : 0;

  if ($accion === 'guardar_orden' && $C_ORDEN) {
    $ordenData = $_POST['orden'] ?? [];
    $conexion->begin_transaction();
    try{
      $sqlUp = "UPDATE peleas_evento SET ".bt($C_ORDEN)."=? WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID ?: 'id')."=? LIMIT 1";
      $st=$conexion->prepare($sqlUp);
      if(!$st) throw new RuntimeException('Prep update orden: '.$conexion->error);
      foreach($ordenData as $pid=>$val){
        if (!is_numeric($pid)) continue;
        $pid = (int)$pid;
        $val = ($val==='') ? null : (int)$val;
        if ($val===null) continue;
        $st->bind_param('iii',$val,$evento_id,$pid);
        $st->execute();
      }
      $st->close();
      $conexion->commit();
      $_SESSION['flash_ok'] = '✅ Numeración actualizada y preservada.';
    } catch(Throwable $e){
      $conexion->rollback();
      $_SESSION['flash_error'] = 'Error guardando numeración: '.$e->getMessage();
    }
    header('Location: ver_peleas_evento.php?evento_id='.$evento_id.'&ape='.urlencode($s_ape).'&esc='.urlencode($s_esc)); exit;
  }

  if ($accion === 'guardar_pesajes') {
    $pesosR = $_POST['peso_real_r'] ?? [];
    $pesosA = $_POST['peso_real_a'] ?? [];
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
        foreach ($pesosR as $pid => $valR) {
          if (!is_numeric($pid)) continue;
          $pid = (int)$pid;
          $_SESSION['pesajes'][$evento_id][$pid]['r'] = ($valR!=='' ? fmt_num($valR) : null);
          $valA = trim((string)($pesosA[$pid] ?? ''));
          $_SESSION['pesajes'][$evento_id][$pid]['a'] = ($valA!=='' ? fmt_num($valA) : null);
          $guardados++;
        }
        $_SESSION['flash_warn'] = 'ℹ️ Pesajes guardados en sesión (no hay columnas en <b>peleas_evento</b>).';
      }
      $conexion->commit();
      $_SESSION['flash_ok'] = '💾 Pesajes guardados ('.$guardados.').';
    } catch(Throwable $e){
      $conexion->rollback();
      $_SESSION['flash_error'] = 'Error guardando pesajes: '.$e->getMessage();
    }
    header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id.'&ape='.urlencode($s_ape).'&esc='.urlencode($s_esc)); exit;
  }

  if ($accion === 'delete' && $pelea_id > 0) {
    $st=$conexion->prepare("DELETE FROM peleas_evento WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID ?: 'id')."=? LIMIT 1");
    if ($st) { $st->bind_param('ii',$evento_id,$pelea_id); $st->execute(); $st->close(); }
    $_SESSION['flash_ok'] = '🗑️ Pelea eliminada.';
    header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id.'&ape='.urlencode($s_ape).'&esc='.urlencode($s_esc)); exit;
  }
}

/* ========= listado de peleas con filtros (NUEVO) ========= */
$orderPieces = [];
if ($C_ORDEN) $orderPieces[] = 'p.'.bt($C_ORDEN).' IS NULL';
if ($C_ORDEN) $orderPieces[] = 'p.'.bt($C_ORDEN);
if ($C_FECHA) $orderPieces[] = 'p.'.bt($C_FECHA);
$orderPieces[] = 'p.'.bt($C_ID ?: 'id');
$orderBy = implode(', ', $orderPieces);

$selectParts = [];
$joins = [];
$selectParts[] = 'p.'.bt($C_ID ?: 'id').' AS pelea_id';
$selectParts[] = $C_ORDEN  ? 'p.'.bt($C_ORDEN).' AS orden_manual' : 'NULL AS orden_manual';
$selectParts[] = $C_RONDAS ? 'p.'.bt($C_RONDAS).' AS rondas' : 'NULL AS rondas';
$selectParts[] = $C_OBS    ? 'p.'.bt($C_OBS).' AS observaciones' : 'NULL AS observaciones';
$selectParts[] = $C_PESO_REAL_R ? 'p.'.bt($C_PESO_REAL_R).' AS peso_real_r' : "NULL AS peso_real_r";
$selectParts[] = $C_PESO_REAL_A ? 'p.'.bt($C_PESO_REAL_A).' AS peso_real_a' : "NULL AS peso_real_a";
if ($C_MODAL_P_TXT) { $selectParts[] = 'p.'.bt($C_MODAL_P_TXT).' AS modalidad_pelea_txt'; } else { $selectParts[] = "NULL AS modalidad_pelea_txt"; }
if ($tablaModal && $C_MODAL_P_ID) { $joins[] = "LEFT JOIN $tablaModal mp ON mp.id = p.".bt($C_MODAL_P_ID); $selectParts[] = 'mp.'.bt($MOD_LABEL_COL).' AS modalidad_pelea'; }
else { $selectParts[] = "NULL AS modalidad_pelea"; }
/* competidores (ajustá si tus nombres reales difieren) */
$selectParts[] = 'cr.apellido AS r_apellido';
$selectParts[] = 'cr.nombre   AS r_nombre';
$selectParts[] = 'cr.escuela_nombre AS r_escuela';
$selectParts[] = 'cr.foto_competidor AS r_foto';
$selectParts[] = 'cr.escuela_logo AS r_logo';
$selectParts[] = 'ca.apellido AS a_apellido';
$selectParts[] = 'ca.nombre   AS a_nombre';
$selectParts[] = 'ca.escuela_nombre AS a_escuela';
$selectParts[] = 'ca.foto_competidor AS a_foto';
$selectParts[] = 'ca.escuela_logo AS a_logo';
$selectParts[] = 'cr.peso_kg AS r_peso';
$selectParts[] = 'ca.peso_kg AS a_peso';

if ($tablaDiv) {
  $joins[] = "LEFT JOIN $tablaDiv dvr ON dvr.id = cr.division_id";
  $joins[] = "LEFT JOIN $tablaDiv dva ON dva.id = ca.division_id";
  $selectParts[] = 'dvr.'.bt($DIV_LABEL_COL).' AS r_division';
  $selectParts[] = 'dva.'.bt($DIV_LABEL_COL).' AS a_division';
} else {
  $selectParts[] = "NULL AS r_division";
  $selectParts[] = "NULL AS a_division";
}

/* --- WHERE dinámico con filtros --- */
$where = ["p.".bt($C_EVENTO)." = ?"];
$types = 'i';
$params = [$evento_id];

if ($s_ape !== '') {
  $tokens = preg_split('/\s+/', $s_ape);
  foreach ($tokens as $tk) {
    $tk = trim($tk); if ($tk==='') continue;
    // Busca en APELLIDO (y también nombre por si acaso)
    $where[] = "(cr.apellido LIKE CONCAT('%', ?, '%')
                 OR ca.apellido LIKE CONCAT('%', ?, '%')
                 OR cr.nombre LIKE CONCAT('%', ?, '%')
                 OR ca.nombre LIKE CONCAT('%', ?, '%'))";
    $types .= 'ssss';
    $params[] = $tk; $params[] = $tk; $params[] = $tk; $params[] = $tk;
  }
}

if ($s_esc !== '') {
  $tokens = preg_split('/\s+/', $s_esc);
  foreach ($tokens as $tk) {
    $tk = trim($tk); if ($tk==='') continue;
    // Escuela / Academia (ambas esquinas)
    $where[] = "(cr.escuela_nombre LIKE CONCAT('%', ?, '%')
                 OR ca.escuela_nombre LIKE CONCAT('%', ?, '%'))";
    $types .= 'ss';
    $params[] = $tk; $params[] = $tk;
  }
}

$sql = "SELECT
  ".implode(",\n  ", $selectParts)."
FROM peleas_evento p
JOIN competidores_evento cr ON p.".bt($C_ROJO)." = cr.id
LEFT JOIN competidores_evento ca ON p.".bt($C_AZUL)." = ca.id
".implode("\n", $joins)."
WHERE ".implode(' AND ', $where)."
ORDER BY $orderBy";
$st = $conexion->prepare($sql);
if (!$st) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">Error preparando la lista de peleas: '.h($conexion->error).'</div>'; exit; }
$st->bind_param($types, ...$params);
$st->execute();
$peleas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🥊 <?= h($evento_nombre) ?> — Peleas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#ffffff; --card:#ffffff; --text:#0b0f19; --muted:#111827; --line:#94a3b8;
      --pill-bg:#e2e8f0; --pill-text:#111;
      --btn:#1e88e5; --btn-sec-bg:#e5e7eb; --btn-dg:#d32f2f;
      --thead:#e2e8f0; --thead-text:#0b0f19;
      --ph1:#f1f5f9; --ph2:#e2e8f0; --ink:#0b0f19;
      --row-shadow: 0 2px 10px rgba(0,0,0,.06);
    }
    *,*::before,*::after{box-sizing:border-box}
    html,body{background:var(--bg);color:var(--text);line-height:1.45}
    a{color:inherit}
    body.solo-vista .toolbar,
    body.solo-vista .form-actions,
    body.solo-vista .row-actions,
    body.solo-vista .btn,
    body.solo-vista #orden-actions,
    body.solo-vista input.peso-real,
    body.solo-vista .delta-pill { display:none !important; }
    body.solo-vista .real-text{ display:inline !important; }

    .contenedor{max-width:1200px;margin:0 auto;padding:14px;}
    .titulo-evento{font-size:clamp(28px,5vw,46px);font-weight:900;letter-spacing:.2px;margin:4px 0 14px;text-align:center}
    .toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:12px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px 12px}
    .toolbar h2{margin:0;color:#0b0f19;font-weight:800;letter-spacing:.2px}
    .orden-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .btn{display:inline-block;padding:8px 11px;border-radius:10px;border:0;cursor:pointer;text-decoration:none;color:#0b0f19;font-weight:700}
    .btn-primary{background:var(--btn);color:#fff !important}
    .btn-secondary{background:var(--btn-sec-bg)}
    .btn-danger{background:var(--btn-dg);color:#fff !important}
    .btn-mini{padding:4px 8px;font-size:11px;border-radius:6px;font-weight:700}
    .btn-xxs{padding:3px 6px;font-size:10.5px;border-radius:6px;font-weight:700}

    .table-wrap{width:100%;overflow-x:auto;margin-top:6px}
    table{width:100%;border-collapse:separate;border-spacing:0 10px;background:transparent}
    thead th{border:1px solid var(--line);background:var(--thead);color:var(--thead-text);font-size:13.4px;font-weight:800;padding:9px 10px}
    tbody tr.row-card td{
      background:#fff;border:2px solid var(--line);box-shadow:var(--row-shadow)
    }
    tbody tr.row-card td:first-child{border-top-left-radius:12px;border-bottom-left-radius:12px}
    tbody tr.row-card td:last-child {border-top-right-radius:12px;border-bottom-right-radius:12px}
    tbody tr.row-card:hover td{outline:2px solid #cbd5e1}

    th,td{vertical-align:middle}
    td{font-size:13.2px;color:#0b0f19;padding:10px}

    .avatar{width:46px;height:46px;object-fit:cover;border-radius:8px;display:inline-block;border:1px solid #cbd5e1;background:#f1f5f9}
    .logo{width:28px;height:28px;object-fit:cover;border-radius:4px;border:1px solid #cbd5e1;background:#f1f5f9}
    .ph-avatar,.ph-logo{display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(180deg,var(--ph1),var(--ph2));color:var(--ink);font-weight:900;letter-spacing:.5px;text-transform:uppercase;user-select:none}
    .ph-avatar{width:46px;height:46px;border-radius:8px;border:1px solid #cbd5e1;font-size:14px}
    .ph-logo{width:28px;height:28px;border-radius:4px;border:1px solid #cbd5e1;font-size:12px}

    .pill{display:inline-block;padding:3px 9px;border-radius:999px;background:var(--pill-bg);color:var(--pill-text);font-size:12px;font-weight:700}
    .muted{color:var(--muted);font-size:12.5px}
    .acciones{text-align:center;white-space:nowrap}
    .vs{font-weight:900;text-transform:uppercase;text-align:center;color:#0b0f19}
    .modalidad{font-size:12.3px;color:#0b0f19;font-weight:800}
    .num{font-weight:900}
    #form-orden .orden-input{width:64px;text-align:center;border-radius:8px;border:1px solid #94a3b8;padding:6px 8px;opacity:.85;pointer-events:none;font-weight:800}
    #form-orden.editing .orden-input{opacity:1;pointer-events:auto}

    .pesaje{display:block;font-size:12px;margin-top:6px}
    .pesaje input.peso-real{width:94px;height:32px;padding:4px 6px;border:1px solid #94a3b8;border-radius:6px}
    .delta-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;margin-left:6px;border:1px solid #cbd5e1;font-weight:700}
    .delta-ok{background:#e8f5e9} .delta-1{background:#fff3cd} .delta-2{background:#ffe0b2} .delta-dq{background:#ffebee}
    .real-text{display:none;margin-left:6px;font-weight:800}

    @media print {
      @page { size: A4 portrait; margin: 10mm; }
      .toolbar, .form-actions, .row-actions, .btn { display: none !important; }
      body{ background:#fff !important; }
      .contenedor{ max-width:none; padding:0; }
      .table-wrap { overflow: visible !important; }
      table { width: 100% !important; margin: 0 auto !important; border-collapse: separate !important; border-spacing: 0 8px !important; }
      th, td { white-space: normal !important; word-break: normal !important; overflow-wrap: break-word !important; hyphens: auto !important; }
      input.peso-real, .delta-pill { display:none !important; }
      .real-text{ display:inline !important; }
      tbody tr.row-card { break-inside: avoid !important; page-break-inside: avoid !important; }
      tbody tr.row-card td { background:#fff !important; box-shadow:none !important; outline:1px solid #cbd5e1 !important; border:0 !important; padding:8px 10px !important; }
      img, .ph-avatar, .ph-logo { break-inside: avoid !important; page-break-inside: avoid !important; }
      .titulo-evento{ font-size:28pt !important; margin-bottom:8mm !important; }
    }

    .flash.ok{border:1px solid #c8e6c9;background:#e8f5e9;color:#1b5e20;padding:8px 10px;border-radius:8px;margin:8px 0;font-weight:700}
    .flash.warn{border:1px solid #ffeeba;background:#fff3cd;color:#856404;padding:8px 10px;border-radius:8px;margin:8px 0;font-weight:700}
    .flash.err{border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;padding:8px 10px;border-radius:8px;margin:8px 0;font-weight:700}

    /* ---- Encabezado sticky ---- */
    .topbar-sticky{
      position: sticky;
      top: 0;
      z-index: 1000;
      background: #fff;
      border-bottom: 1px solid var(--line);
      box-shadow: 0 4px 12px rgba(0,0,0,.06);
      margin-left: -14px;
      margin-right: -14px;
      padding-left: 14px;
      padding-right: 14px;
      will-change: transform;
    }
    body.solo-vista .topbar-sticky{ position: static; box-shadow: none; border-bottom: 0; }
    @media print{ .topbar-sticky{ position: static !important; box-shadow: none !important; border-bottom: 0 !important; } }

    /* form buscador */
    .search-grid{display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:end}
    .search-grid .field{display:flex;flex-direction:column;gap:4px}
    .search-grid input{height:36px;border:1px solid #94a3b8;border-radius:8px;padding:6px 8px}
  </style>
</head>
<?php $bodyClass = $SHARE ? 'solo-vista' : ''; ?>
<body class="<?= $bodyClass ?>" data-ver="<?= h($__evento_sig) ?>" data-evento="<?= (int)$evento_id ?>">
<div class="contenedor">
  <div class="topbar-sticky">
    <h1 class="titulo-evento">🥊 <?= h($evento_nombre) ?></h1>

    <div class="toolbar">
      <h2>Peleas programadas</h2>
      <div class="orden-tools">
        <?php if (!$SHARE) { ?>
          <?php if (!empty($C_ORDEN)) { ?>
            <button class="btn btn-secondary" type="button" id="btnEditarOrden">✏️ Editar numeración</button>
          <?php } else { ?>
            <span class="muted">ℹ️ Para numeración manual, agregá una columna <b>orden</b> (INT) en <b>peleas_evento</b>.</span>
          <?php } ?>
          <a class="btn btn-mini btn-secondary" href="organizar_pelea.php?evento_id=<?= (int)$evento_id ?>">➕ Nueva pelea</a>
          <a class="btn btn-mini btn-secondary" href="pesajes.php?evento_id=<?= (int)$evento_id ?>">⚖️ Pesajes</a>
          <button class="btn btn-mini btn-secondary" type="button" onclick="window.print()">🖨️ Imprimir / PDF</button>
        <?php } ?>
        <a class="btn btn-mini btn-secondary" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?>&share=1" target="_blank">🔗 Vista para imprimir/compartir</a>
      </div>
    </div>

    <!-- === BUSCADOR (Apellido + Escuela/Academia) === -->
    <form method="GET" class="search-grid" autocomplete="off" action="ver_peleas_evento.php" style="margin-top:8px;margin-bottom:4px">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
      <?php if ($SHARE) { ?><input type="hidden" name="share" value="1"><?php } ?>
      <div class="field">
        <label style="font-weight:700">Apellido</label>
        <input type="text" name="ape" value="<?= h($s_ape) ?>" placeholder="Ej: González">
      </div>
      <div class="field">
        <label style="font-weight:700">Escuela / Academia</label>
        <input type="text" name="esc" value="<?= h($s_esc) ?>" placeholder="Ej: Academia Central">
      </div>
      <div class="field" style="gap:6px;flex-direction:row">
        <button class="btn btn-primary" type="submit">🔎 Buscar</button>
        <a class="btn btn-secondary" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?><?= $SHARE?'&share=1':'' ?>">Limpiar</a>
      </div>
    </form>
    <!-- === /BUSCADOR === -->
  </div>

  <?php if (!$SHARE) { ?>
    <?php if (!empty($_SESSION['flash_ok'])) { ?><div class="flash ok"><?= h($_SESSION['flash_ok']); ?></div><?php unset($_SESSION['flash_ok']); } ?>
    <?php if (!empty($_SESSION['flash_warn'])) { ?><div class="flash warn"><?= $_SESSION['flash_warn']; ?></div><?php unset($_SESSION['flash_warn']); } ?>
    <?php if (!empty($_SESSION['flash_error'])) { ?><div class="flash err"><?= h($_SESSION['flash_error']); ?></div><?php unset($_SESSION['flash_error']); } ?>
  <?php } ?>

  <div class="table-wrap">
    <form method="POST" id="form-orden" <?= $SHARE ? 'onsubmit="return false"' : '' ?>>
      <input type="hidden" id="accionInput" name="accion" value="guardar_orden">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
      <table>
        <colgroup>
          <col class="num"><col class="modalidad">
          <col class="foto"><col class="nombre"><col class="info"><col class="escuela">
          <col class="vs">
          <col class="foto"><col class="nombre"><col class="info"><col class="escuela">
          <col class="rondas"><col class="obs"><col class="acc">
        </colgroup>
        <thead>
          <tr>
            <th style="width:70px">N°</th>
            <th>Modalidad</th>
            <th colspan="4">Esquina Roja</th>
            <th class="vs">VS</th>
            <th colspan="4">Esquina Azul</th>
            <th>Rondas</th>
            <th class="obs">Obs.</th>
            <th class="acciones">Acciones</th>
          </tr>
          <tr>
            <th></th><th></th>
            <th>Foto</th><th>Nombre</th><th>Info</th><th>Escuela</th>
            <th></th>
            <th>Foto</th><th>Nombre</th><th>Info</th><th>Escuela</th>
            <th></th><th class="obs"></th><th class="acciones"></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$peleas) { ?>
          <tr class="row-card"><td colspan="14">No hay peleas programadas con esos filtros.</td></tr>
        <?php } else { foreach ($peleas as $p){
          $rFoto = trim((string)($p['r_foto'] ?? ''));
          $aFoto = trim((string)($p['a_foto'] ?? ''));
          $rLogo = trim((string)($p['r_logo'] ?? ''));
          $aLogo = trim((string)($p['a_logo'] ?? ''));

          $rPesoTxt = ($p['r_peso']!==null && $p['r_peso']!=='') ? fmt_num($p['r_peso']).' kg' : '—';
          $aPesoTxt = ($p['a_peso']!==null && $p['a_peso']!=='') ? fmt_num($p['a_peso']).' kg' : '—';
          $rInfo = trim(($p['r_division'] ?? '-') . ' • ' . $rPesoTxt);
          $aInfo = trim(($p['a_division'] ?? '-') . ' • ' . $aPesoTxt);

          $rondasVal = isset($p['rondas']) && is_numeric($p['rondas']) ? (int)$p['rondas'] : 2;
          $obsVal = (string)($p['observaciones'] ?? '');

          $nroMostrar = $p['orden_manual']!==null ? (int)$p['orden_manual'] : (int)$p['pelea_id'];

          $mP   = trim((string)($p['modalidad_pelea'] ?? ''));
          $mPT  = trim((string)($p['modalidad_pelea_txt'] ?? ''));
          $modalidadLbl = '—';
          if ($mP !== '')       $modalidadLbl = $mP;
          elseif ($mPT !== '')  $modalidadLbl = $mPT;
          elseif ($obsVal !== '') $modalidadLbl = $obsVal;

          $rAp = (string)($p['r_apellido'] ?? ''); $rNo = (string)($p['r_nombre'] ?? '');
          $aAp = (string)($p['a_apellido'] ?? ''); $aNo = (string)($p['a_nombre'] ?? '');
          $rName = trim(strtoupper($rAp).($rAp!==''?', ':'').ucwords(mb_strtolower($rNo,'UTF-8')));
          $aName = trim(strtoupper($aAp).($aAp!==''?', ':'').ucwords(mb_strtolower($aNo,'UTF-8')));
          $rIni = iniciales($rAp,$rNo);
          $aIni = iniciales($aAp,$aNo);
          $rGymIni = inicial($p['r_escuela'] ?? '');
          $aGymIni = inicial($p['a_escuela'] ?? '');

          $pref_r = $p['peso_real_r'] ?? ($_SESSION['pesajes'][$evento_id][$p['pelea_id']]['r'] ?? '');
          $pref_a = $p['peso_real_a'] ?? ($_SESSION['pesajes'][$evento_id][$p['pelea_id']]['a'] ?? '');
        ?>
          <tr class="row-card">
            <td class="num">
              <?php if ($C_ORDEN) { ?>
                <input class="orden-input" type="number" name="orden[<?= (int)$p['pelea_id'] ?>]" value="<?= h($p['orden_manual']) ?>" disabled>
              <?php } else { ?><?= (int)$nroMostrar ?><?php } ?>
            </td>
            <td class="modalidad"><?= h($modalidadLbl) ?></td>

            <!-- ROJA -->
            <td style="text-align:center">
              <?php if ($rFoto!=='') { ?>
                <img src="<?= h($rFoto) ?>" class="avatar" alt="Roja" onerror="this.onerror=null;this.replaceWith(phAvatar('<?= h($rIni) ?>'))">
              <?php } else { ?>
                <div class="ph-avatar"><?= h($rIni) ?></div>
              <?php } ?>
            </td>
            <td style="font-weight:800"><?= h($rName !== '' ? $rName : '—') ?></td>
            <td>
              <span class="pill"><?= h($rInfo) ?></span>
              <div class="pesaje">
                Real:
                <input type="number" step="0.1" min="0"
                  name="peso_real_r[<?= (int)$p['pelea_id'] ?>]"
                  class="peso-real" data-side="r" data-pelea="<?= (int)$p['pelea_id'] ?>"
                  placeholder="kg" value="<?= h($pref_r) ?>" <?= $SHARE ? 'disabled' : '' ?>>
                <span class="real-text" id="real_r_<?= (int)$p['pelea_id'] ?>"><?= ($pref_r!=='' && $pref_r!==null) ? h(fmt_num($pref_r)).' kg' : '—' ?></span>
                <span class="delta-pill" id="delta_r_<?= (int)$p['pelea_id'] ?>">Δ —</span>
              </div>
            </td>
            <td class="muted">
              <div style="display:flex;align-items:center;gap:8px">
                <?php if ($rLogo!=='') { ?>
                  <img src="<?= h($rLogo) ?>" class="logo" alt="Logo escuela roja" onerror="this.onerror=null;this.replaceWith(phLogo('<?= h($rGymIni) ?>'))">
                <?php } else { ?>
                  <div class="ph-logo"><?= h($rGymIni) ?></div>
                <?php } ?>
                <span style="font-weight:700"><?= h($p['r_escuela'] ?? '-') ?></span>
              </div>
            </td>

            <td class="vs">vs</td>

            <!-- AZUL -->
            <td style="text-align:center">
              <?php if ($aFoto!=='') { ?>
                <img src="<?= h($aFoto) ?>" class="avatar" alt="Azul" onerror="this.onerror=null;this.replaceWith(phAvatar('<?= h($aIni) ?>'))">
              <?php } else { ?>
                <div class="ph-avatar"><?= h($aIni) ?></div>
              <?php } ?>
            </td>
            <td style="font-weight:800"><?= h($aName !== '' ? $aName : '—') ?></td>
            <td>
              <span class="pill"><?= h($aInfo) ?></span>
              <div class="pesaje">
                Real:
                <input type="number" step="0.1" min="0"
                  name="peso_real_a[<?= (int)$p['pelea_id'] ?>]"
                  class="peso-real" data-side="a" data-pelea="<?= (int)$p['pelea_id'] ?>"
                  placeholder="kg" value="<?= h($pref_a) ?>" <?= $SHARE ? 'disabled' : '' ?>>
                <span class="real-text" id="real_a_<?= (int)$p['pelea_id'] ?>"><?= ($pref_a!=='' && $pref_a!==null) ? h(fmt_num($pref_a)).' kg' : '—' ?></span>
                <span class="delta-pill" id="delta_a_<?= (int)$p['pelea_id'] ?>">Δ —</span>
              </div>
            </td>
            <td class="muted">
              <div style="display:flex;align-items:center;gap:8px">
                <?php if ($aLogo!=='') { ?>
                  <img src="<?= h($aLogo) ?>" class="logo" alt="Logo escuela azul" onerror="this.onerror=null;this.replaceWith(phLogo('<?= h($aGymIni) ?>'))">
                <?php } else { ?>
                  <div class="ph-logo"><?= h($aGymIni) ?></div>
                <?php } ?>
                <span style="font-weight:700"><?= h($p['a_escuela'] ?? '-') ?></span>
              </div>
            </td>

            <td style="font-weight:800"><?= (int)$rondasVal ?></td>
            <td class="obs" style="font-weight:700"><?= h($obsVal) ?></td>
            <td class="acciones">
              <div class="row-actions">
                <a class="btn btn-xxs btn-primary" title="Editar" href="editar_pelea_evento.php?evento_id=<?= (int)$evento_id ?>&pelea_id=<?= (int)$p['pelea_id'] ?>">✏️ Editar</a>
                <button type="button" class="btn btn-xxs btn-danger" title="Eliminar" onclick="eliminarPelea(<?= (int)$p['pelea_id'] ?>)">🗑️ Eliminar</button>
                <a class="btn btn-xxs btn-secondary" title="Iniciar en vivo"
                   href="combate_en_vivo.php?evento_id=<?= (int)$evento_id ?>&pelea_id=<?= (int)$p['pelea_id'] ?>&nro=<?= (int)$nroMostrar ?><?= $C_RONDAS ? '&rondas='.(int)$rondasVal : '' ?>&dur=180&rest=60">
                  ▶️ Iniciar
                </a>
              </div>
            </td>
          </tr>
        <?php } } ?>
        </tbody>
      </table>

      <?php if (!$SHARE) { ?>
        <div class="form-actions" id="orden-actions" style="margin-top:10px; display:flex; gap:8px; align-items:center">
          <button class="btn btn-secondary" type="button" id="btnAutoSec">↻ Auto-secuenciar</button>
          <button class="btn btn-primary" type="button" id="btnGuardarOrden">💾 Guardar edición de numeración</button>
          <span class="muted" style="font-weight:700">Tip: activá “✏️ Editar numeración” para escribir a mano, o guardá directo.</span>
        </div>

        <div class="form-actions" style="margin-top:10px; display:flex; align-items:center; justify-content:space-between">
          <div class="muted" style="font-weight:700">Regla de pesaje: ≤0.5kg ✅ · ≤1.0kg −1 · ≤1.5kg −2 · ≥2.0kg ❌</div>
          <div>
            <button class="btn btn-primary" type="button" id="btnGuardarPesajes">💾 Guardar pesajes</button>
          </div>
        </div>
      <?php } ?>
    </form>
  </div>
</div>

<script>
(function(){
  window.phAvatar = function(text){ const d=document.createElement('div'); d.className='ph-avatar'; d.textContent=text||'—'; return d; };
  window.phLogo   = function(text){ const d=document.createElement('div'); d.className='ph-logo';   d.textContent=text||'G';  return d; };

  window.eliminarPelea = function(peleaId){
    if(!confirm('¿Eliminar esta pelea?')) return;
    const f = document.createElement('form');
    f.method = 'POST'; f.action = 'ver_peleas_evento.php';
    const add = (n,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; f.appendChild(i); };
    add('accion','delete'); add('evento_id','<?= (int)$evento_id ?>'); add('pelea_id', String(peleaId));
    document.body.appendChild(f); f.submit();
  };

  const SHARE = <?= $SHARE ? 'true':'false' ?>;
  const btnEditar = document.getElementById('btnEditarOrden');
  const formOrden = document.getElementById('form-orden');
  const inputsOrden = document.querySelectorAll('#form-orden .orden-input');
  const btnAuto = document.getElementById('btnAutoSec');
  const accionInput = document.getElementById('accionInput');
  const btnGuardarOrden = document.getElementById('btnGuardarOrden');
  const btnGuardarPesajes = document.getElementById('btnGuardarPesajes');

  function setEditing(on){
    if(!formOrden) return;
    if(on){ formOrden.classList.add('editing'); } else { formOrden.classList.remove('editing'); }
    inputsOrden.forEach(i=> i.disabled = !on);
    if(btnEditar){ btnEditar.textContent = on ? '🙈 Terminar edición' : '✏️ Editar numeración'; }
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
      setEditing(true);
      document.querySelectorAll('#form-orden .orden-input').forEach(i => i.disabled = false);
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

  function parseKg(s){ const n = parseFloat((s||'').toString().replace(',', '.')); return isNaN(n)?null:n; }
  function regla(diffKg){
    if (diffKg === null) return {txt:'Δ —', cls:''};
    const d = Math.abs(diffKg);
    if (d <= 0.5) return {txt:`Δ ${d.toFixed(1)} kg · ✅`, cls:'delta-ok'};
    if (d <= 1.0) return {txt:`Δ ${d.toFixed(1)} kg · −1`, cls:'delta-1'};
    if (d <= 1.5) return {txt:`Δ ${d.toFixed(1)} kg · −2`, cls:'delta-2'};
    return {txt:`Δ ${d.toFixed(1)} kg · ❌`, cls:'delta-dq'};
  }
  function declaradoDesdeChip(chipText){
    const m = (chipText||'').match(/([\d\.,]+)\s*kg/i);
    return m ? parseKg(m[1]) : null;
  }
  function actualizarFila(input){
    const peleaId = input.getAttribute('data-pelea');
    theSide = input.getAttribute('data-side');
    const side = input.getAttribute('data-side');
    const td = input.closest('td'); if (!td) return;
    const chip = td.querySelector('.pill');
    const declared = chip ? declaradoDesdeChip(chip.textContent) : null;
    const real = parseKg(input.value);
    const deltaEl = td.querySelector(`#delta_${side}_${peleaId}`);
    const realText = td.querySelector(`#real_${side}_${peleaId}`);
    const res = regla(real!==null && declared!==null ? real - declared : null);
    if (deltaEl){
      deltaEl.textContent = res.txt;
      deltaEl.classList.remove('delta-ok','delta-1','delta-2','delta-dq');
      if (res.cls) deltaEl.classList.add(res.cls);
    }
    if (realText){
      realText.textContent = (real!==null) ? `${real.toFixed(1)} kg` : '—';
    }
  }
  document.querySelectorAll('input.peso-real').forEach(inp=>{
    actualizarFila(inp);
    if (!SHARE) inp.addEventListener('input', ()=> actualizarFila(inp));
  });

  /* ===== (NUEVO) Auto-refresh SOLO en vista de compartir (share=1) ===== */
  (function(){
    const isShare = SHARE === true;
    if (!isShare) return;

    const eventoId = parseInt(document.body.getAttribute('data-evento'), 10) || 0;
    const ver0 = document.body.getAttribute('data-ver') || '';

    const baseUrl = new URL(window.location.href);
    function reloadSameQS(){
      baseUrl.searchParams.set('_r', String(Date.now())); // cache-bust
      window.location.replace(baseUrl.toString());
    }

    let lastVer = ver0;
    let backoff = 10000; // 10s
    let timer = null;

    async function tick(){
      try{
        const u = new URL(window.location.origin + '/ver_peleas_evento.php');
        u.searchParams.set('ajax','poll');
        u.searchParams.set('evento_id', String(eventoId));
        u.searchParams.set('_', String(Date.now()));
        const ctrl = new AbortController();
        const t = setTimeout(()=>ctrl.abort(), 8000);
        const r = await fetch(u.toString(), {cache:'no-store', signal:ctrl.signal});
        clearTimeout(t);
        if (!r.ok) throw new Error('HTTP '+r.status);
        const j = await r.json();
        if (j && j.ok && j.ver){
          if (j.ver !== lastVer){
            reloadSameQS();
            return;
          }
        }
        backoff = 10000; // estable
      }catch(e){
        backoff = Math.min(backoff + 5000, 30000); // backoff suave en error
      }finally{
        timer = setTimeout(tick, backoff);
      }
    }

    document.addEventListener('visibilitychange', ()=>{
      if (document.visibilityState === 'visible'){
        if (timer) clearTimeout(timer);
        backoff = 1000;
        tick();
      }
    });

    tick();
  })();

})();
</script>
</body>
</html>
