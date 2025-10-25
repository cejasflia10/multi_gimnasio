<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($col){ return '`'.str_replace('`','``', $col).'`'; }
function fmt_num($v){ if ($v===null || $v==='') return null; $f=(float)str_replace(',', '.', $v); return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.'); }

/* ===== evento_id ===== */
$evento_id = (int)($_GET['evento_id'] ?? $_SESSION['evento_id_actual'] ?? $_SESSION['evento_id'] ?? 0);
if ($evento_id <= 0) {
  echo '<div style="max-width:980px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>evento_id</b>. Volvé desde el evento.</div>';
  exit;
}
$_SESSION['evento_id_actual'] = $evento_id;

/* ===== Columnas peleas_evento ===== */
$cols = [];
$res = $conexion->query("SHOW COLUMNS FROM peleas_evento");
if (!$res) {
  echo '<div style="max-width:980px;margin:16px auto;padding:12px;border:1px solid #fdecea;background:#ffebee;color:#b71c1c;border-radius:8px;">No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'</div>';
  exit;
}
while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
$pick = function(array $cands) use ($cols){ foreach ($cands as $c) { $lc = strtolower($c); if (isset($cols[$lc])) return $cols[$lc]; } return null; };

$C_ID         = $pick(['id','pelea_id','id_pelea']);
$C_EVENTO     = $pick(['evento_id','id_evento','evento']);
$C_ROJO       = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL       = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_ORDEN      = $pick(['orden','orden_manual','nro','nro_orden','posicion','sequence','rank','numero','nro_pelea','sort']);
$C_OBS        = $pick(['observaciones','obs','comentarios','comentario','nota']);
$C_PESO_REAL_R= $pick(['peso_real_rojo','rojo_peso_real','peso_real_r']);
$C_PESO_REAL_A= $pick(['peso_real_azul','azul_peso_real','peso_real_a']);
$C_ORIGEN_R   = $pick(['origen_pesaje_rojo','origen_rojo','origen_r','pesaje_origen_r']);
$C_ORIGEN_A   = $pick(['origen_pesaje_azul','origen_azul','origen_a','pesaje_origen_a']);

/* ===== Columnas competidores_evento ===== */
$colsC = [];
$resC = $conexion->query("SHOW COLUMNS FROM competidores_evento");
if ($resC) { while($r = $resC->fetch_assoc()){ $colsC[strtolower($r['Field'])] = $r['Field']; } }
$pickC = function(array $cands) use ($colsC){ foreach($cands as $c){ $lc=strtolower($c); if(isset($colsC[$lc])) return $colsC[$lc]; } return null; };

$CE_ID   = $pickC(['id','competidor_id']);
$CE_APE  = $pickC(['apellido','apellidos','last_name']);
$CE_NOM  = $pickC(['nombre','nombres','first_name']);
$CE_ESC  = $pickC(['escuela_nombre','escuela','gimnasio','gym']);
$CE_PESO = $pickC(['peso_kg','peso','kg','weight_kg']);   // peso de planilla (cargado por el competidor)
$CE_DIV  = $pickC(['division_id','id_division','division_evento_id']);
$CE_DNI  = $pickC(['dni','documento','doc','num_doc']);

/* ===== Divisiones (label opcional) ===== */
$tablaDiv = null; $DIV_LABEL_COL = 'nombre';
if (($chkD=$conexion->query("SHOW TABLES LIKE 'divisiones_evento'")) && $chkD->num_rows>0){ $tablaDiv = 'divisiones_evento'; }

/* ===== POST: guardar peso INDIVIDUAL por lado ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion'] ?? '')==='guardar_peso_lado') {
  $pelea_id = isset($_POST['pelea_id']) ? (int)$_POST['pelea_id'] : 0;
  $side     = ($_POST['side'] ?? '') === 'a' ? 'a' : 'r'; // por defecto Roja
  $peso     = isset($_POST['peso_real']) && $_POST['peso_real']!=='' ? fmt_num($_POST['peso_real']) : null;
  $origen   = trim((string)($_POST['origen'] ?? 'manual'));

  if ($pelea_id<=0 || $evento_id<=0){ $_SESSION['flash_error']='Parámetros inválidos.'; header('Location: pesajes.php?evento_id='.$evento_id.'#p'.$pelea_id); exit; }
  if ($peso===null && $origen!=='sistema'){ $_SESSION['flash_error']='Ingresá un peso real (o elegí “sistema”).'; header('Location: pesajes.php?evento_id='.$evento_id.'#p'.$pelea_id); exit; }

  $col_peso  = ($side==='r') ? $C_PESO_REAL_R : $C_PESO_REAL_A;
  $col_origen= ($side==='r') ? $C_ORIGEN_R    : $C_ORIGEN_A;

  // ¿ya guardado ese lado?
  $sqlChk = "SELECT ".($col_peso?bt($col_peso):'NULL')." AS pr
             FROM peleas_evento WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID?:'id')."=? LIMIT 1";
  $st = $conexion->prepare($sqlChk);
  if(!$st){ $_SESSION['flash_error']='Error preparando consulta: '.$conexion->error; header('Location: pesajes.php?evento_id='.$evento_id.'#p'.$pelea_id); exit; }
  $st->bind_param('ii',$evento_id,$pelea_id); $st->execute();
  $row = $st->get_result()->fetch_assoc(); $st->close();
  $lado_guardado = ($row && $row['pr']!==null && $row['pr']!==''); // bloquea si ya hubo oficial

  if ($lado_guardado){
    $_SESSION['flash_warn']='Ese lado ya estaba guardado. No se modificó (bloqueado).';
    header('Location: ver_peleas_evento.php?evento_id='.$evento_id.'#p'.$pelea_id); exit;
  }

  // Guardar
  if ($col_peso || $col_origen) {
    $set=[]; $types=''; $vals=[];
    if ($col_peso){   $set[] = bt($col_peso).'=?';   $types.='s'; $vals[]=$peso; } else { $_SESSION['pesajes'][$evento_id][$pelea_id][$side]=$peso; }
    if ($col_origen){ $set[] = bt($col_origen).'=?'; $types.='s'; $vals[]=$origen; } else { $_SESSION['origen'][$evento_id][$pelea_id][$side]=$origen; }
    if ($set){
      $sqlUp = "UPDATE peleas_evento SET ".implode(',', $set)." WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID?:'id')."=? LIMIT 1";
      $types.='ii'; $vals[]=$evento_id; $vals[]=$pelea_id;
      $st = $conexion->prepare($sqlUp);
      if (!$st){ $_SESSION['flash_error']='Error al guardar: '.$conexion->error; header('Location: pesajes.php?evento_id='.$evento_id.'#p'.$pelea_id); exit; }
      $st->bind_param($types, ...$vals); $st->execute(); $st->close();
    }
  } else {
    $_SESSION['pesajes'][$evento_id][$pelea_id][$side]=$peso;
    $_SESSION['origen'][$evento_id][$pelea_id][$side]=$origen;
  }

  $_SESSION['flash_ok'] = '✅ Guardado el peso oficial del lado '.($side==='r'?'ROJO':'AZUL').'. Visible para profesores.';
  // Redirigimos a la vista pública para que lo vean los profes
  header('Location: ver_peleas_evento.php?evento_id='.$evento_id.'#p'.$pelea_id); exit;
}

/* ===== Filtros de búsqueda ===== */
$pid = isset($_GET['pid']) && $_GET['pid']!=='' ? (int)$_GET['pid'] : null;
$q = trim((string)($_GET['q'] ?? ''));

/* ===== Consulta principal ===== */
$select = []; $joins = []; $where = ["p.".bt($C_EVENTO)."=?"]; $params = [$evento_id]; $types='i';

$select[] = 'p.'.bt($C_ID ?: 'id').' AS pelea_id';
$select[] = $C_ORDEN ? 'p.'.bt($C_ORDEN).' AS orden_manual' : 'NULL AS orden_manual';
$select[] = $C_OBS   ? 'p.'.bt($C_OBS).' AS observaciones'  : 'NULL AS observaciones';

/* Pesos reales + origen (día del evento) */
$select[] = $C_PESO_REAL_R ? 'p.'.bt($C_PESO_REAL_R).' AS peso_real_r' : 'NULL AS peso_real_r';
$select[] = $C_PESO_REAL_A ? 'p.'.bt($C_PESO_REAL_A).' AS peso_real_a' : 'NULL AS peso_real_a';
$select[] = $C_ORIGEN_R ? 'p.'.bt($C_ORIGEN_R).' AS origen_r' : "NULL AS origen_r";
$select[] = $C_ORIGEN_A ? 'p.'.bt($C_ORIGEN_A).' AS origen_a' : "NULL AS origen_a";

/* Competidores (planilla) */
$select[] = 'cr.'.bt($CE_APE ?: 'apellido').' AS r_apellido';
$select[] = 'cr.'.bt($CE_NOM ?: 'nombre').' AS r_nombre';
$select[] = $CE_ESC ? 'cr.'.bt($CE_ESC).' AS r_escuela' : "NULL AS r_escuela";
$select[] = $CE_PESO ? 'cr.'.bt($CE_PESO).' AS r_peso_plan' : "NULL AS r_peso_plan";

$select[] = 'ca.'.bt($CE_APE ?: 'apellido').' AS a_apellido';
$select[] = 'ca.'.bt($CE_NOM ?: 'nombre').' AS a_nombre';
$select[] = $CE_ESC ? 'ca.'.bt($CE_ESC).' AS a_escuela' : "NULL AS a_escuela";
$select[] = $CE_PESO ? 'ca.'.bt($CE_PESO).' AS a_peso_plan' : "NULL AS a_peso_plan";

/* Categoría sugerida por peso de planilla (Sistema) */
if ($CE_PESO) {
  $colPesoR = 'cr.'.bt($CE_PESO);
  $colPesoA = 'ca.'.bt($CE_PESO);

  $joins[] = "LEFT JOIN categorias_evento crcat_sug
              ON $colPesoR IS NOT NULL
             AND crcat_sug.peso_min <= $colPesoR
             AND crcat_sug.peso_max >= $colPesoR";

  $joins[] = "LEFT JOIN categorias_evento cacat_sug
              ON $colPesoA IS NOT NULL
             AND cacat_sug.peso_min <= $colPesoA
             AND cacat_sug.peso_max >= $colPesoA";

  $select[] = "crcat_sug.nombre   AS r_cat_sug_nombre";
  $select[] = "crcat_sug.peso_min AS r_cat_sug_min";
  $select[] = "crcat_sug.peso_max AS r_cat_sug_max";
  $select[] = "cacat_sug.nombre   AS a_cat_sug_nombre";
  $select[] = "cacat_sug.peso_min AS a_cat_sug_min";
  $select[] = "cacat_sug.peso_max AS a_cat_sug_max";
} else {
  $select[] = "NULL AS r_cat_sug_nombre";
  $select[] = "NULL AS r_cat_sug_min";
  $select[] = "NULL AS r_cat_sug_max";
  $select[] = "NULL AS a_cat_sug_nombre";
  $select[] = "NULL AS a_cat_sug_min";
  $select[] = "NULL AS a_cat_sug_max";
}

/* División (label) */
if ($tablaDiv && $CE_DIV) {
  $joins[]  = "LEFT JOIN $tablaDiv dvr ON dvr.id = cr.".bt($CE_DIV);
  $joins[]  = "LEFT JOIN $tablaDiv dva ON dva.id = ca.".bt($CE_DIV);
  $select[] = 'dvr.'.bt($DIV_LABEL_COL).' AS r_division';
  $select[] = 'dva.'.bt($DIV_LABEL_COL).' AS a_division';
} else {
  $select[] = "NULL AS r_division";
  $select[] = "NULL AS a_division";
}

/* WHERE dinámico: pid y q */
if ($pid !== null) { $where[] = 'p.'.bt($C_ID ?: 'id').'=?'; $types.='i'; $params[]=$pid; }
if ($q !== '') {
  $dniLike = preg_replace('/\D+/', '', $q);
  if ($dniLike !== '' && $CE_DNI) { $where[] = "(cr.".bt($CE_DNI)." LIKE CONCAT(?, '%') OR ca.".bt($CE_DNI)." LIKE CONCAT(?, '%'))"; $types.='ss'; $params[]=$dniLike; $params[]=$dniLike; }
  else {
    $tokens = preg_split('/\s+/', $q); $bloque=[];
    foreach ($tokens as $tk) { $tk=trim($tk); if(!$tk) continue;
      $bloque[] = "(cr.".bt($CE_APE ?: 'apellido')." LIKE CONCAT('%', ?, '%') OR ca.".bt($CE_APE ?: 'apellido')." LIKE CONCAT('%', ?, '%'))";
      $types.='ss'; $params[]=$tk; $params[]=$tk;
    }
    if ($bloque) $where[]='('.implode(' AND ',$bloque).')';
  }
}

$sql = "SELECT
  ".implode(",\n  ", $select)."
FROM peleas_evento p
JOIN competidores_evento cr ON p.".bt($C_ROJO)." = cr.".bt($CE_ID ?: 'id')."
LEFT JOIN competidores_evento ca ON p.".bt($C_AZUL)." = ca.".bt($CE_ID ?: 'id')."
".implode("\n", $joins)."
WHERE ".implode(' AND ', $where)."
ORDER BY ".($C_ORDEN ? "p.".bt($C_ORDEN)." IS NULL, p.".bt($C_ORDEN).", " : "")." p.".bt($C_ID ?: 'id');

$st = $conexion->prepare($sql);
if (!$st) {
  echo '<div style="max-width:980px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">Error preparando el listado: '.h($conexion->error).'</div>';
  exit;
}
$st->bind_param($types, ...$params);
$st->execute();
$peleas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* ===== Autocompletar buscador (apellidos simples) ===== */
$ac = [];
if (!empty($peleas)) {
  foreach ($peleas as $p) {
    if (!empty($p['r_apellido'])) $ac[$p['r_apellido']] = true;
    if (!empty($p['a_apellido'])) $ac[$p['a_apellido']] = true;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>⚖️ Pesaje — Día del Evento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#ffffff; --card:#ffffff; --text:#000;
      --line:#cbd5e1; --ok:#e8f5e9; --warn:#fff3cd; --warn2:#ffe0b2; --dq:#ffebee;
      --pill:#e2e8f0; --btn:#1e88e5; --btn2:#e5e7eb; --danger:#d32f2f;
    }
    *{box-sizing:border-box}
    html,body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:var(--bg);color:var(--text);line-height:1.35}
    a{color:inherit;text-decoration:none}

    .wrap{max-width:980px;margin:0 auto;padding:14px}
    .toolbar{position:sticky;top:0;z-index:5;background:#fff;padding:8px 0;border-bottom:1px solid var(--line);display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:12px}
    .toolbar h2{margin:0;font-size:20px}
    .btn{display:inline-block;padding:8px 10px;border-radius:10px;border:1px solid transparent;cursor:pointer;background:var(--btn);color:#fff}
    .btn.sec{background:var(--btn2);color:#000}
    .btn.danger{background:var(--danger);color:#fff}
    .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px;margin-bottom:12px}
    .center{text-align:center}
    .field{display:flex;flex-direction:column;gap:6px}
    .field input, .field select{height:40px;padding:8px 10px;border:1px solid #94a3b8;border-radius:10px}
    .list{display:flex;flex-direction:column;gap:12px}
    .item{border:1px solid var(--line);border-radius:12px;padding:14px;background:#fff}
    .tit{font-weight:800;font-size:16px;margin-bottom:8px;text-align:center}
    .small{font-size:12px;color:#111;text-align:center}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#e2e8f0;color:#000;font-size:12px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .peso-wrap{display:flex;align-items:center;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:8px}
    .peso-wrap input{width:140px;height:40px}
    .peso-wrap select{height:40px}
    .delta{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid #cbd5e1}
    .d-ok{background:var(--ok)}
    .d-1{background:var(--warn)}
    .d-2{background:var(--warn2)}
    .d-dq{background:var(--dq)}
    .acciones{display:flex;justify-content:center;gap:8px;margin-top:10px}
    .flash{padding:10px;border-radius:10px;margin-bottom:10px}
    .ok{background:var(--ok);border:1px solid #bbf7d0}
    .warn{background:var(--warn);border:1px solid #ffeeba}
    .err{background:var(--dq);border:1px solid #ffcdd2}
    .filters{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;justify-content:center}
    .chip{padding:4px 8px;border-radius:999px;border:1px solid var(--line);font-size:12px;cursor:pointer;background:#fff}
    .chip.active{background:#e6f2ff;border-color:#90caf9}

    .summary{display:flex;gap:10px;flex-wrap:wrap;justify-content:center}
    .sum{padding:6px 10px;border:1px solid var(--line);border-radius:10px;font-size:12px;background:#fff}

    @media (max-width:760px){ .row{grid-template-columns:1fr} }
    @media print{ .toolbar,.acciones,.btn,.filters{display:none !important} }
  </style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <h2>⚖️ Pesaje — Evento #<?= (int)$evento_id ?></h2>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <a class="btn sec" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?>">← Volver</a>
      <button class="btn sec" onclick="window.print()">🖨️ Imprimir/PDF</button>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_ok'])) { ?><div class="flash ok center"><?= h($_SESSION['flash_ok']); ?></div><?php unset($_SESSION['flash_ok']); } ?>
  <?php if (!empty($_SESSION['flash_warn'])) { ?><div class="flash warn center"><?= $_SESSION['flash_warn']; ?></div><?php unset($_SESSION['flash_warn']); } ?>
  <?php if (!empty($_SESSION['flash_error'])) { ?><div class="flash err center"><?= h($_SESSION['flash_error']); ?></div><?php unset($_SESSION['flash_error']); } ?>

  <div class="card">
    <div class="small center" style="font-weight:700;margin-bottom:8px">
      Se toma la <u>categoría sugerida</u> por el <u>peso de planilla</u> (rango en <code>categorias_evento</code>).<br>
      <b>Tolerancia Amateur</b>: +0.5 kg. Luego, <b>−1 punto por cada 1 kg</b> extra (ceil). Con exceso ≥ 2.0 kg ⇒ ❌ DQ.
    </div>

    <!-- Buscador -->
    <?php $qv = h($q); ?>
    <form method="GET" class="center" autocomplete="off" style="display:grid;grid-template-columns:1fr 1fr 120px;gap:8px;max-width:720px;margin:0 auto">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
      <div class="field"><input type="number" name="pid" value="<?= h($pid ?? '') ?>" placeholder="N° pelea (opcional)"></div>
      <div class="field">
        <input list="ac_comp" type="text" name="q" value="<?= $qv ?>" placeholder="DNI o Apellido">
        <datalist id="ac_comp">
          <?php foreach(array_keys($ac) as $opt): ?>
            <option value="<?= h($opt) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="field"><button class="btn" type="submit" style="width:100%">🔎 Buscar</button></div>
    </form>

    <div class="filters">
      <span class="chip active" data-f="all">Todos</span>
      <span class="chip" data-f="pend">Pendientes</span>
      <span class="chip" data-f="ok">En peso</span>
      <span class="chip" data-f="m1">−1 punto</span>
      <span class="chip" data-f="m2">−2 puntos</span>
      <span class="chip" data-f="dq">DQ</span>
    </div>
    <div class="summary" id="summary" style="margin-top:8px"></div>
  </div>

  <div class="list" id="lista">
  <?php if (!$peleas) { ?>
    <div class="card center">No hay peleas que coincidan con tu búsqueda.</div>
  <?php } else {
    foreach ($peleas as $p) {
      $nro = $p['orden_manual']!==null ? (int)$p['orden_manual'] : (int)$p['pelea_id'];

      $rPlan = ($p['r_peso_plan']!==null && $p['r_peso_plan']!=='') ? fmt_num($p['r_peso_plan']) : '';
      $aPlan = ($p['a_peso_plan']!==null && $p['a_peso_plan']!=='') ? fmt_num($p['a_peso_plan']) : '';

      $rDiv = $p['r_division'] ?? '-';
      $aDiv = $p['a_division'] ?? '-';

      // Categorías sugeridas por planilla
      $rCatNom = $p['r_cat_sug_nombre'] ?: '—';
      $aCatNom = $p['a_cat_sug_nombre'] ?: '—';
      $rCatMin = $p['r_cat_sug_min']!==null ? fmt_num($p['r_cat_sug_min']) : '';
      $rCatMax = $p['r_cat_sug_max']!==null ? fmt_num($p['r_cat_sug_max']) : '';
      $aCatMin = $p['a_cat_sug_min']!==null ? fmt_num($p['a_cat_sug_min']) : '';
      $aCatMax = $p['a_cat_sug_max']!==null ? fmt_num($p['a_cat_sug_max']) : '';

      $pref_real_r = $p['peso_real_r'] ?? ($_SESSION['pesajes'][$evento_id][$p['pelea_id']]['r'] ?? '');
      $pref_real_a = $p['peso_real_a'] ?? ($_SESSION['pesajes'][$evento_id][$p['pelea_id']]['a'] ?? '');
      $pref_org_r  = $p['origen_r']     ?? ($_SESSION['origen'][$evento_id][$p['pelea_id']]['r'] ?? 'manual');
      $pref_org_a  = $p['origen_a']     ?? ($_SESSION['origen'][$evento_id][$p['pelea_id']]['a'] ?? 'manual');

      $lockedR = ($p['peso_real_r']!==null && $p['peso_real_r']!=='');
      $lockedA = ($p['peso_real_a']!==null && $p['peso_real_a']!=='');
  ?>
    <div class="item pelea" id="p<?= (int)$p['pelea_id'] ?>" data-pelea="<?= (int)$p['pelea_id'] ?>"
         data-catmax-r="<?= h($rCatMax) ?>" data-catmax-a="<?= h($aCatMax) ?>">
      <div class="tit">#<?= (int)$nro ?> · <?= h($p['r_apellido'].' '.$p['r_nombre']) ?> <span class="small">vs</span> <?= h(trim(($p['a_apellido']??'').' '.($p['a_nombre']??'')) ?: '—') ?></div>

      <div class="small" style="margin-bottom:6px">
        🔴 Roja — <span class="pill"><?= h($rCatNom) ?><?= ($rCatMin!==''&&$rCatMax!=='')?' • '.h($rCatMin.'–'.$rCatMax.' kg'):'' ?></span>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        🔵 Azul — <span class="pill"><?= h($aCatNom) ?><?= ($aCatMin!==''&&$aCatMax!=='')?' • '.h($aCatMin.'–'.$aCatMax.' kg'):'' ?></span>
      </div>
      <div class="small" style="margin-bottom:6px;opacity:.8">
        (Planilla: 🔴 <?= $rPlan!==''? h($rPlan.' kg') : '—' ?> / <?= h($rDiv) ?> · 🔵 <?= $aPlan!==''? h($aPlan.' kg') : '—' ?> / <?= h($aDiv) ?>)
      </div>

      <div class="row">
        <!-- LADO ROJO -->
        <div>
          <form method="POST" class="center" style="margin:0">
            <input type="hidden" name="accion" value="guardar_peso_lado">
            <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
            <input type="hidden" name="pelea_id"  value="<?= (int)$p['pelea_id'] ?>">
            <input type="hidden" name="side" value="r">

            <div class="peso-wrap">
              <select name="origen" data-side="r" class="origen" <?= $lockedR?'disabled':'' ?>>
                <option value="manual"  <?= ($pref_org_r==='manual'?'selected':'') ?>>Manual</option>
                <option value="sistema" <?= ($pref_org_r==='sistema'?'selected':'') ?>>Sistema</option>
              </select>
              <input type="number" step="0.1" min="0" name="peso_real"
                class="peso" data-side="r" placeholder="Real Roja (kg)"
                value="<?= h($pref_real_r) ?>" <?= $lockedR?'disabled':'' ?>>
              <span class="delta" id="delta_r_<?= (int)$p['pelea_id'] ?>">Δ —</span>
            </div>
            <div class="acciones">
              <button type="submit" class="btn" <?= $lockedR?'disabled':'' ?>>💾 Guardar ROJA</button>
            </div>
            <?php if($lockedR): ?><div class="small" style="margin-top:6px">🔒 Lado ROJO ya guardado.</div><?php endif; ?>
          </form>
        </div>

        <!-- LADO AZUL -->
        <div>
          <form method="POST" class="center" style="margin:0">
            <input type="hidden" name="accion" value="guardar_peso_lado">
            <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
            <input type="hidden" name="pelea_id"  value="<?= (int)$p['pelea_id'] ?>">
            <input type="hidden" name="side" value="a">

            <div class="peso-wrap">
              <select name="origen" data-side="a" class="origen" <?= $lockedA?'disabled':'' ?>>
                <option value="manual"  <?= ($pref_org_a==='manual'?'selected':'') ?>>Manual</option>
                <option value="sistema" <?= ($pref_org_a==='sistema'?'selected':'') ?>>Sistema</option>
              </select>
              <input type="number" step="0.1" min="0" name="peso_real"
                class="peso" data-side="a" placeholder="Real Azul (kg)"
                value="<?= h($pref_real_a) ?>" <?= $lockedA?'disabled':'' ?>>
              <span class="delta" id="delta_a_<?= (int)$p['pelea_id'] ?>">Δ —</span>
            </div>
            <div class="acciones">
              <button type="submit" class="btn" <?= $lockedA?'disabled':'' ?>>💾 Guardar AZUL</button>
            </div>
            <?php if($lockedA): ?><div class="small" style="margin-top:6px">🔒 Lado AZUL ya guardado.</div><?php endif; ?>
          </form>
        </div>
      </div>

      <?php if (!empty($p['observaciones'])) { ?>
        <div class="small" style="margin-top:6px">📝 <?= h($p['observaciones']) ?></div>
      <?php } ?>
    </div>
  <?php } } ?>
  </div>
</div>

<script>
(function(){
  // === Reglas (Amateurs por defecto) ===
  const TOL = 0.5; // +0.5 kg
  function kg(x){ if(x===null||x===undefined||x==='') return null; const n=parseFloat(String(x).replace(',','.')); return isNaN(n)?null:n; }

  // Evalúa contra el peso máximo de la categoría SUGERIDA (por planilla).
  // Solo penaliza cuando se pasa el máximo + tolerancia.
  function evaluar(realOf, catMax){
    if (realOf===null || catMax===null) return {k:'pend', txt:'Δ —', cls:''};
    const exceso = realOf - catMax;
    if (exceso <= TOL) return {k:'ok', txt:'OK', cls:'d-ok'};

    const postTol = exceso - TOL;          // cuánto se pasó sobre la tolerancia
    if (postTol >= 2.0) return {k:'dq', txt:`❌ DQ (${postTol.toFixed(1)} kg)`, cls:'d-dq'};
    const puntos = Math.ceil(postTol / 1.0);
    if (puntos === 1) return {k:'m1', txt:`−1 punto (${postTol.toFixed(1)} kg)`, cls:'d-1'};
    return {k:'m2', txt:`−2 puntos (${postTol.toFixed(1)} kg)`, cls:'d-2'};
  }

  function actualizarDelta(card, side){
    const inp      = card.querySelector(`input.peso[data-side="${side}"]`);
    const selOrg   = card.querySelector(`select.origen[data-side="${side}"]`);
    const catMax   = kg(card.getAttribute(`data-catmax-${side}`));
    const real     = kg(inp ? inp.value : null);
    const org      = selOrg ? selOrg.value : 'manual';
    const realOf   = (org === 'sistema') ? catMax : real;  // “sistema” usa el máximo de la cat sugerida

    const pid   = card.getAttribute('data-pelea');
    const badge = document.getElementById(`delta_${side}_${pid}`);

    const info = evaluar(realOf, catMax);
    if (badge){
      badge.textContent = info.txt;
      badge.classList.remove('d-ok','d-1','d-2','d-dq');
      if (info.cls) badge.classList.add(info.cls);
    }
    card.setAttribute(`data-${side}`, info.k);
    refrescarResumen();
  }

  function estadoFila(card){
    const er = card.getAttribute('data-r') || 'pend';
    const ea = card.getAttribute('data-a') || 'pend';
    const score = (k)=>({ok:0,m1:1,m2:2,dq:3,pend:4}[k] ?? 4);
    const worst = Math.max(score(er), score(ea));
    return ['ok','m1','m2','dq','pend'][worst] || 'pend';
  }

  function refrescarResumen(){
    const items = Array.from(document.querySelectorAll('.item.pelea'));
    const cnt = {all:items.length, pend:0, ok:0, m1:0, m2:0, dq:0};
    items.forEach(it=>{ cnt[estadoFila(it)]++; });
    const sum = document.getElementById('summary');
    if (sum){
      sum.innerHTML = `
        <div class="sum">Total: <b>${cnt.all}</b></div>
        <div class="sum">Pendientes: <b>${cnt.pend}</b></div>
        <div class="sum">En peso: <b>${cnt.ok}</b></div>
        <div class="sum">−1 punto: <b>${cnt.m1}</b></div>
        <div class="sum">−2 puntos: <b>${cnt.m2}</b></div>
        <div class="sum">DQ: <b>${cnt.dq}</b></div>
      `;
    }
  }

  function aplicarFiltro(f){
    document.querySelectorAll('.item.pelea').forEach(it=>{
      const st = estadoFila(it);
      it.style.display = (f==='all' || f===st || (f==='pend' && st==='pend')) ? '' : 'none';
    });
  }

  // Inicializar
  document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('.item.pelea').forEach(card=>{
      ['r','a'].forEach(side=>{
        const inp  = card.querySelector(`input.peso[data-side="${side}"]`);
        const selO = card.querySelector(`select.origen[data-side="${side}"]`);
        const pid  = card.getAttribute('data-pelea');

        const locked = (inp && inp.hasAttribute('disabled'));
        const key = `pesaje:<?= (int)$evento_id ?>:${pid}:${side}`;

        if (inp && !locked){
          const saved = localStorage.getItem(key);
          if (saved && !inp.value) inp.value = saved;
          inp.addEventListener('input', ()=>{
            try{ inp.value===''?localStorage.removeItem(key):localStorage.setItem(key, inp.value);}catch(e){}
            actualizarDelta(card, side);
          });
        }
        if (selO && !locked){ selO.addEventListener('change', ()=> actualizarDelta(card, side)); }
        actualizarDelta(card, side);
      });
    });

    // Filtros
    const chips = document.querySelectorAll('.chip');
    chips.forEach(c=> c.addEventListener('click', ()=>{
      chips.forEach(x=>x.classList.remove('active')); c.classList.add('active');
      aplicarFiltro(c.getAttribute('data-f')||'all');
    }));

    refrescarResumen();
  });
})();
</script>
</body>
</html>
