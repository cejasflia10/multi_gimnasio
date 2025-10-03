<?php
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
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>evento_id</b>. Volvé desde el evento.</div>';
  exit;
}
$_SESSION['evento_id_actual'] = $evento_id;

/* ===== Columnas peleas_evento ===== */
$cols = [];
$res = $conexion->query("SHOW COLUMNS FROM peleas_evento");
if (!$res) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #fdecea;background:#ffebee;color:#b71c1c;border-radius:8px;">No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'</div>';
  exit;
}
while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
$pick = function(array $cands) use ($cols){ foreach ($cands as $c) { $lc = strtolower($c); if (isset($cols[$lc])) return $cols[$lc]; } return null; };

$C_ID       = $pick(['id','pelea_id','id_pelea']);
$C_EVENTO   = $pick(['evento_id','id_evento','evento']);
$C_ROJO     = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL     = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_ORDEN    = $pick(['orden','orden_manual','nro','nro_orden','posicion','sequence','rank','numero','nro_pelea','sort']);
$C_OBS      = $pick(['observaciones','obs','comentarios','comentario','nota']);
$C_PESO_REAL_R = $pick(['peso_real_rojo','rojo_peso_real','peso_real_r']);
$C_PESO_REAL_A = $pick(['peso_real_azul','azul_peso_real','peso_real_a']);

if (!$C_EVENTO || !$C_ROJO || !$C_AZUL) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #fdecea;background:#ffebee;color:#b71c1c;border-radius:8px;">La tabla <b>peleas_evento</b> existe pero faltan columnas obligatorias (evento/rojo/azul).</div>';
  exit;
}

/* ===== Columnas competidores_evento ===== */
$colsC = [];
$resC = $conexion->query("SHOW COLUMNS FROM competidores_evento");
if ($resC) { while($r = $resC->fetch_assoc()){ $colsC[strtolower($r['Field'])] = $r['Field']; } }
$pickC = function(array $cands) use ($colsC){ foreach($cands as $c){ $lc=strtolower($c); if(isset($colsC[$lc])) return $colsC[$lc]; } return null; };

$CE_ID   = $pickC(['id','competidor_id']);
$CE_APE  = $pickC(['apellido','apellidos','last_name']);
$CE_NOM  = $pickC(['nombre','nombres','first_name']);
$CE_ESC  = $pickC(['escuela_nombre','escuela','gimnasio','gym']);
$CE_PESO = $pickC(['peso_kg','peso','kg','weight_kg']);   // peso cargado en la planilla
$CE_DIV  = $pickC(['division_id','id_division','division_evento_id']);
$CE_DNI  = $pickC(['dni','documento','doc','num_doc']);   // DNI para búsqueda

/* ===== Divisiones (label opcional) ===== */
$tablaDiv = null; $DIV_LABEL_COL = 'nombre';
if (($chkD=$conexion->query("SHOW TABLES LIKE 'divisiones_evento'")) && $chkD->num_rows>0){ $tablaDiv = 'divisiones_evento'; }

/* ===== Guardar pesajes (POST) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion = $_POST['accion'] ?? '';
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
        // Tabla auxiliar si no existen las columnas en peleas_evento
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
          // Fallback temporal en sesión
          foreach ($pesosR as $pid => $valR) {
            if (!is_numeric($pid)) continue;
            $pid = (int)$pid;
            $_SESSION['pesajes'][$evento_id][$pid]['r'] = ($valR!=='' ? fmt_num($valR) : null);
            $valA = trim((string)($pesosA[$pid] ?? ''));
            $_SESSION['pesajes'][$evento_id][$pid]['a'] = ($valA!=='' ? fmt_num($valA) : null);
            $guardados++;
          }
          $_SESSION['flash_warn'] = 'ℹ️ Pesajes guardados sólo en esta sesión (agregá columnas en <b>peleas_evento</b> o crea <b>pesajes_evento</b>).';
        }
      }

      $conexion->commit();
      $_SESSION['flash_ok'] = '💾 Pesajes guardados ('.$guardados.').';
    } catch(Throwable $e){
      $conexion->rollback();
      $_SESSION['flash_error'] = 'Error guardando pesajes: '.$e->getMessage();
    }
    header('Location: pesajes.php?evento_id='.(int)$evento_id.'&q='.urlencode($_GET['q'] ?? '').'&pid='.urlencode($_GET['pid'] ?? '')); exit;
  }
}

/* ===== Filtros de búsqueda ===== */
// pid (opcional para ir directo a una pelea concreta)
$pid = isset($_GET['pid']) && $_GET['pid']!=='' ? (int)$_GET['pid'] : null;
// q = DNI o Apellido (PRIORIDAD DNI si son números)
$q = trim((string)($_GET['q'] ?? ''));

/* ===== Consulta principal ===== */
$select = []; $joins = []; $where = ["p.".bt($C_EVENTO)."=?"]; $params = [$evento_id]; $types='i';

$select[] = 'p.'.bt($C_ID ?: 'id').' AS pelea_id';
$select[] = $C_ORDEN ? 'p.'.bt($C_ORDEN).' AS orden_manual' : 'NULL AS orden_manual';
$select[] = $C_OBS   ? 'p.'.bt($C_OBS).' AS observaciones'  : 'NULL AS observaciones';

/* Pesos reales (si existen columnas) */
$select[] = $C_PESO_REAL_R ? 'p.'.bt($C_PESO_REAL_R).' AS peso_real_r' : 'NULL AS peso_real_r';
$select[] = $C_PESO_REAL_A ? 'p.'.bt($C_PESO_REAL_A).' AS peso_real_a' : 'NULL AS peso_real_a';

/* Competidores (peso planilla) */
$select[] = 'cr.'.bt($CE_APE ?: 'apellido').' AS r_apellido';
$select[] = 'cr.'.bt($CE_NOM ?: 'nombre').' AS r_nombre';
$select[] = $CE_ESC ? 'cr.'.bt($CE_ESC).' AS r_escuela' : "NULL AS r_escuela";
$select[] = $CE_PESO ? 'cr.'.bt($CE_PESO).' AS r_peso'   : "NULL AS r_peso";
$select[] = $CE_DNI ? 'cr.'.bt($CE_DNI).' AS r_dni'       : "NULL AS r_dni";

$select[] = 'ca.'.bt($CE_APE ?: 'apellido').' AS a_apellido';
$select[] = 'ca.'.bt($CE_NOM ?: 'nombre').' AS a_nombre';
$select[] = $CE_ESC ? 'ca.'.bt($CE_ESC).' AS a_escuela' : "NULL AS a_escuela";
$select[] = $CE_PESO ? 'ca.'.bt($CE_PESO).' AS a_peso'   : "NULL AS a_peso";
$select[] = $CE_DNI ? 'ca.'.bt($CE_DNI).' AS a_dni'       : "NULL AS a_dni";

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

/* WHERE dinámico: pid y q (DNI o Apellido) */
if ($pid !== null) { $where[] = 'p.'.bt($C_ID ?: 'id').'=?'; $types.='i'; $params[]=$pid; }

if ($q !== '') {
  $dniLike = preg_replace('/\D+/', '', $q); // sólo dígitos
  if ($dniLike !== '' && $CE_DNI) {
    // Buscar por DNI (prefijo)
    $where[] = "(cr.".bt($CE_DNI)." LIKE CONCAT(?, '%') OR ca.".bt($CE_DNI)." LIKE CONCAT(?, '%'))";
    $types  .= 'ss';
    $params[] = $dniLike;
    $params[] = $dniLike;
  } else {
    // Buscar por Apellido (tokens con LIKE %token%)
    $tokens = preg_split('/\s+/', $q);
    $bloque = [];
    foreach ($tokens as $tk) {
      $tk = trim($tk);
      if ($tk==='') continue;
      $bloque[] = "(cr.".bt($CE_APE ?: 'apellido')." LIKE CONCAT('%', ?, '%') OR ca.".bt($CE_APE ?: 'apellido')." LIKE CONCAT('%', ?, '%'))";
      $types .= 'ss';
      $params[] = $tk; $params[] = $tk;
    }
    if ($bloque) $where[] = '('.implode(' AND ', $bloque).')';
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
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">Error preparando el listado: '.h($conexion->error).'</div>';
  exit;
}
$st->bind_param($types, ...$params);
$st->execute();
$peleas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$ph = 'assets/placeholder-user.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>⚖️ Pesaje — Día del Evento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#ffffff; --card:#ffffff; --text:#000; --muted:#222;
      --line:#cbd5e1; --ok:#e8f5e9; --warn:#fff3cd; --warn2:#ffe0b2; --dq:#ffebee;
      --pill:#e2e8f0; --btn:#1e88e5; --btn2:#e5e7eb; --danger:#d32f2f;
    }
    *{box-sizing:border-box}
    html,body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:var(--bg);color:var(--text);line-height:1.35}
    a{color:inherit;text-decoration:none}

    .wrap{max-width:980px;margin:0 auto;padding:14px}
    .toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:12px}
    .toolbar h2{margin:0;font-size:20px}
    .btn{display:inline-block;padding:8px 10px;border-radius:10px;border:1px solid transparent;cursor:pointer;background:var(--btn);color:#fff}
    .btn.sec{background:var(--btn2);color:#000}
    .btn.danger{background:var(--danger);color:#fff}
    .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px;margin-bottom:12px}
    .grid{display:grid;gap:8px}
    .grid-3{grid-template-columns:1fr 1fr 1fr}
    .field{display:flex;flex-direction:column;gap:6px}
    .field label{font-size:13px;color:#333;font-weight:600}
    .field input{height:40px;padding:8px 10px;border:1px solid #94a3b8;border-radius:10px}
    .list{display:flex;flex-direction:column;gap:10px}
    .item{border:1px solid var(--line);border-radius:12px;padding:10px;background:#fff;display:grid;grid-template-columns:52px 1fr;gap:10px}
    .foto{width:52px;height:52px;border-radius:8px;object-fit:cover;background:#f3f4f6}
    .tit{font-weight:700}
    .small{font-size:12px;color:#333}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:var(--pill);color:#000;font-size:12px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .peso-wrap{display:flex;align-items:center;gap:6px;margin-top:6px}
    .peso-wrap input{width:120px;height:36px;border-radius:8px;padding:6px 8px}
    .delta{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid #cbd5e1}
    .d-ok{background:var(--ok)}
    .d-1{background:var(--warn)}
    .d-2{background:var(--warn2)}
    .d-dq{background:var(--dq)}
    .acciones{display:flex;justify-content:flex-end;gap:8px;margin-top:8px}
    .flash{padding:10px;border-radius:10px;margin-bottom:10px}
    .ok{background:var(--ok);border:1px solid #c8e6c9}
    .warn{background:var(--warn);border:1px solid #ffeeba}
    .err{background:var(--dq);border:1px solid #ffcdd2}
    .filters{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
    .chip{padding:4px 8px;border-radius:999px;border:1px solid var(--line);font-size:12px;cursor:pointer;background:#fff}
    .chip.active{background:#e6f2ff;border-color:#90caf9}

    .summary{display:flex;gap:10px;flex-wrap:wrap}
    .sum{padding:6px 10px;border:1px solid var(--line);border-radius:10px;font-size:12px;background:#fff}

    @media (max-width:760px){
      .grid-3{grid-template-columns:1fr}
      .row{grid-template-columns:1fr}
      .item{grid-template-columns:46px 1fr}
      .foto{width:46px;height:46px}
      .peso-wrap input{width:110px}
    }
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

  <?php if (!empty($_SESSION['flash_ok'])) { ?><div class="flash ok"><?= h($_SESSION['flash_ok']); ?></div><?php unset($_SESSION['flash_ok']); } ?>
  <?php if (!empty($_SESSION['flash_warn'])) { ?><div class="flash warn"><?= $_SESSION['flash_warn']; ?></div><?php unset($_SESSION['flash_warn']); } ?>
  <?php if (!empty($_SESSION['flash_error'])) { ?><div class="flash err"><?= h($_SESSION['flash_error']); ?></div><?php unset($_SESSION['flash_error']); } ?>

  <div class="card">
    <!-- Buscador: sólo DNI o Apellido -->
    <?php $qv = h($q); ?>
    <form method="GET" class="grid grid-3" autocomplete="off">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
      <div class="field"><label>N° pelea (opcional)</label><input type="number" name="pid" value="<?= h($pid ?? '') ?>" placeholder="Ej: 12"></div>
      <div class="field"><label>Buscar por DNI o Apellido</label><input type="text" name="q" value="<?= $qv ?>" placeholder="Ej: 33600111 o González"></div>
      <div class="field" style="align-self:end"><button class="btn" type="submit">🔎 Buscar</button></div>
    </form>

    <div class="filters" style="margin-top:8px">
      <span class="chip active" data-f="all">Todos</span>
      <span class="chip" data-f="pend">Pendientes</span>
      <span class="chip" data-f="ok">En peso</span>
      <span class="chip" data-f="m1">−1 punto</span>
      <span class="chip" data-f="m2">−2 puntos</span>
      <span class="chip" data-f="dq">DQ</span>
    </div>
    <div class="summary" id="summary" style="margin-top:8px"></div>
  </div>

  <form method="POST" id="form-pesajes">
    <input type="hidden" name="accion" value="guardar_pesajes">
    <div class="list" id="lista">
    <?php if (!$peleas) { ?>
      <div class="card">No hay peleas que coincidan con tu búsqueda.</div>
    <?php } else {
      foreach ($peleas as $p) {
        $nro = $p['orden_manual']!==null ? (int)$p['orden_manual'] : (int)$p['pelea_id'];
        $rPesoPlan = ($p['r_peso']!==null && $p['r_peso']!=='') ? fmt_num($p['r_peso']) : '';
        $aPesoPlan = ($p['a_peso']!==null && $p['a_peso']!=='') ? fmt_num($p['a_peso']) : '';
        $rDiv = $p['r_division'] ?? '-';
        $aDiv = $p['a_division'] ?? '-';

        // Preferir lo ya guardado; si no hay, cargar sesión
        $pref_r = $p['peso_real_r'] ?? ($_SESSION['pesajes'][$evento_id][$p['pelea_id']]['r'] ?? '');
        $pref_a = $p['peso_real_a'] ?? ($_SESSION['pesajes'][$evento_id][$p['pelea_id']]['a'] ?? '');
    ?>
      <div class="item pelea" data-pelea="<?= (int)$p['pelea_id'] ?>">
        <img class="foto" src="<?= h($ph) ?>" alt="">
        <div>
          <div class="tit">#<?= (int)$nro ?> · <?= h($p['r_apellido'].' '.$p['r_nombre']) ?> <span class="small">vs</span> <?= h(trim(($p['a_apellido']??'').' '.($p['a_nombre']??'')) ?: '—') ?></div>
          <div class="row">
            <div>
              <div class="small">🔴 Roja — <span class="pill"><?= $rPesoPlan!==''? h($rPesoPlan.' kg') : '—' ?> / <?= h($rDiv) ?></span></div>
              <div class="peso-wrap">
                <input type="number" step="0.1" min="0" name="peso_real_r[<?= (int)$p['pelea_id'] ?>]" class="peso" data-side="r" data-plan="<?= h($rPesoPlan) ?>" value="<?= h($pref_r) ?>" placeholder="Real kg">
                <span class="delta" id="delta_r_<?= (int)$p['pelea_id'] ?>">Δ —</span>
              </div>
            </div>
            <div>
              <div class="small">🔵 Azul — <span class="pill"><?= $aPesoPlan!==''? h($aPesoPlan.' kg') : '—' ?> / <?= h($aDiv) ?></span></div>
              <div class="peso-wrap">
                <input type="number" step="0.1" min="0" name="peso_real_a[<?= (int)$p['pelea_id'] ?>]" class="peso" data-side="a" data-plan="<?= h($aPesoPlan) ?>" value="<?= h($pref_a) ?>" placeholder="Real kg">
                <span class="delta" id="delta_a_<?= (int)$p['pelea_id'] ?>">Δ —</span>
              </div>
            </div>
          </div>
          <?php if (!empty($p['observaciones'])) { ?><div class="small" style="margin-top:6px">📝 <?= h($p['observaciones']) ?></div><?php } ?>
        </div>
      </div>
    <?php } } ?>
    </div>

    <?php if ($peleas) { ?>
    <div class="acciones">
      <button type="button" class="btn sec" id="btnGuardarLocal">💾 Guardar local</button>
      <button type="submit" class="btn">💾 Guardar en BD</button>
    </div>
    <div class="small" style="margin-top:6px">Regla: ≤0.5kg ✅ · ≤1.0kg −1 punto · ≤1.5kg −2 puntos · ≥2.0kg ❌ DQ.</div>
    <?php } ?>
  </form>
</div>

<script>
(function(){
  function kg(x){ if(x===null||x===undefined||x==='') return null; const n=parseFloat(String(x).replace(',','.')); return isNaN(n)?null:n; }
  function regla(d){
    if (d===null) return {k:'pend', txt:'Δ —', cls:''};
    const ad = Math.abs(d);
    if (ad <= 0.5) return {k:'ok',  txt:`Δ ${ad.toFixed(1)} kg · ✅`, cls:'d-ok'};
    if (ad <= 1.0) return {k:'m1', txt:`Δ ${ad.toFixed(1)} kg · −1`, cls:'d-1'};
    if (ad <= 1.5) return {k:'m2', txt:`Δ ${ad.toFixed(1)} kg · −2`, cls:'d-2'};
    return {k:'dq', txt:`Δ ${ad.toFixed(1)} kg · ❌ DQ`, cls:'d-dq'};
  }
  function actualizarDelta(inp){
    const cont = inp.closest('.item'); if(!cont) return;
    const pid = cont.getAttribute('data-pelea');
    const side = inp.getAttribute('data-side');
    const plan = kg(inp.getAttribute('data-plan'));
    const real = kg(inp.value);
    const badge = document.getElementById(`delta_${side}_${pid}`);
    let info = {k:'pend',txt:'Δ —',cls:''};
    if (plan!==null && real!==null) info = regla(real - plan);
    if (badge){
      badge.textContent = info.txt;
      badge.classList.remove('d-ok','d-1','d-2','d-dq');
      if (info.cls) badge.classList.add(info.cls);
    }
    cont.setAttribute(`data-${side}`, info.k);
    // persistir local
    try{
      const key = `pesaje:<?= (int)$evento_id ?>:${pid}:${side}`;
      if (inp.value==='') localStorage.removeItem(key); else localStorage.setItem(key, inp.value);
    }catch(e){}
    refrescarResumen();
  }
  function estadoFila(el){
    const er = el.getAttribute('data-r') || 'pend';
    const ea = el.getAttribute('data-a') || 'pend';
    // Peor estado manda
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
    const items = document.querySelectorAll('.item.pelea');
    items.forEach(it=>{
      const st = estadoFila(it);
      it.style.display = (f==='all' || f===st || (f==='pend' && st==='pend')) ? '' : 'none';
    });
  }

  // Precargar localStorage si no había valor guardado en BD
  document.querySelectorAll('input.peso').forEach(inp=>{
    try{
      const cont = inp.closest('.item'); const pid = cont.getAttribute('data-pelea'); const side = inp.getAttribute('data-side');
      const key = `pesaje:<?= (int)$evento_id ?>:${pid}:${side}`;
      const saved = localStorage.getItem(key);
      if (saved && !inp.value) inp.value = saved;
    }catch(e){}
    actualizarDelta(inp);
    inp.addEventListener('input', ()=> actualizarDelta(inp));
  });

  // Guardar local
  const btnLocal = document.getElementById('btnGuardarLocal');
  if (btnLocal){ btnLocal.addEventListener('click', ()=> alert('✅ Pesajes guardados localmente (este dispositivo).')); }

  // Filtros
  const chips = document.querySelectorAll('.chip');
  chips.forEach(c=> c.addEventListener('click', ()=>{
    chips.forEach(x=>x.classList.remove('active')); c.classList.add('active');
    aplicarFiltro(c.getAttribute('data-f')||'all');
  }));

  refrescarResumen();
})();
</script>
</body>
</html>
