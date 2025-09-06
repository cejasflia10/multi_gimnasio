<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['__JUEZ_MODE__'] = 1; // BYPASS guard organizador

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function table_exists(mysqli $db, string $t): bool {
  $t=$db->real_escape_string($t);
  if ($r=$db->query("SHOW TABLES LIKE '$t'")) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function first_table(mysqli $db, array $cands): ?string { foreach($cands as $t){ if(table_exists($db,$t)) return $t; } return null; }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== Contexto ===== */
$juez_id   = (int)($_SESSION['juez_id'] ?? 0);
$evento_id = (int)($_SESSION['evento_id_actual'] ?? 0);
if ($juez_id <= 0) { header('Location: login_juez.php?err='.urlencode('Iniciá sesión.')); exit; }

/* ===== Datos del juez (opcional) ===== */
$juez = null;
if (table_exists($conexion,'jueces_evento')) {
  $st=$conexion->prepare("SELECT id, dni, nombre, apellido,
     (SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jueces_evento' AND COLUMN_NAME='rol' LIMIT 1) AS t1
   FROM jueces_evento WHERE id=? LIMIT 1");
  if ($st){ $st->bind_param('i',$juez_id); $st->execute(); $r=$st->get_result(); $juez=$r?$r->fetch_assoc():null; $st->close(); }
}

/* ===== Detectar tabla de peleas ===== */
$peleas_tbl = first_table($conexion, ['peleas_evento','peleas','peleas_eventos']);
$asig_tbl   = table_exists($conexion,'pelea_jueces') ? 'pelea_jueces' : (table_exists($conexion,'peleas_jueces') ? 'peleas_jueces' : null);

/* ===== Columnas de peleas ===== */
$C_AZUL_N = $C_ROJO_N = $C_TITULO = $C_CAT = $C_RING = $C_HORA = $C_EST = null;
$C_AZUL_ID = $C_ROJO_ID = null;

if ($peleas_tbl) {
  $C_AZUL_N = has_col($conexion,$peleas_tbl,'azul_nombre') ? 'azul_nombre' : (has_col($conexion,$peleas_tbl,'competidor_a') ? 'competidor_a' : null);
  $C_ROJO_N = has_col($conexion,$peleas_tbl,'rojo_nombre') ? 'rojo_nombre' : (has_col($conexion,$peleas_tbl,'competidor_b') ? 'competidor_b' : null);
  $C_TITULO = has_col($conexion,$peleas_tbl,'titulo') ? 'titulo' : null;
  foreach (['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'] as $c) if (has_col($conexion,$peleas_tbl,$c)) { $C_AZUL_ID=$c; break; }
  foreach (['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'] as $c) if (has_col($conexion,$peleas_tbl,$c)) { $C_ROJO_ID=$c; break; }
  $C_CAT  = has_col($conexion,$peleas_tbl,'categoria')     ? 'categoria'     : null;
  $C_RING = has_col($conexion,$peleas_tbl,'ring')          ? 'ring'          : (has_col($conexion,$peleas_tbl,'tatami') ? 'tatami' : null);
  $C_HORA = has_col($conexion,$peleas_tbl,'programado_at') ? 'programado_at' : (has_col($conexion,$peleas_tbl,'horario') ? 'horario' : null);
  $C_EST  = has_col($conexion,$peleas_tbl,'estado')        ? 'estado'        : null;
}

/* ===== Query de peleas =====
   1) Si hay tabla de asignaciones, intentamos traer asignadas.
   2) Si NO hay result, caemos a TODAS las peleas del evento (o TODAS si $evento_id=0).
   3) Siempre hacemos JOIN con competidores_evento si no tenemos nombres de texto. */
$peleas = [];
$list_title = 'Peleas (modo juez)';

if ($peleas_tbl) {
  $cols = ["p.`id` AS pelea_id"];
  if ($C_AZUL_N) $cols[] = "p.".bt($C_AZUL_N)." AS azul_nombre";
  if ($C_ROJO_N) $cols[] = "p.".bt($C_ROJO_N)." AS rojo_nombre";
  if ($C_TITULO) $cols[] = "p.".bt($C_TITULO)." AS titulo";
  if ($C_CAT)    $cols[] = "p.".bt($C_CAT)." AS categoria";
  if ($C_RING)   $cols[] = "p.".bt($C_RING)." AS ring";
  if ($C_HORA)   $cols[] = "p.".bt($C_HORA)." AS horario";
  if ($C_EST)    $cols[] = "p.".bt($C_EST)." AS estado";

  $join = '';
  if ((!$C_AZUL_N || !$C_ROJO_N) && $C_AZUL_ID && $C_ROJO_ID && table_exists($conexion,'competidores_evento')) {
    $cols[] = "TRIM(CONCAT(COALESCE(ca.apellido,''),' ',COALESCE(ca.nombre,''))) AS azul_nombre_join";
    $cols[] = "TRIM(CONCAT(COALESCE(cr.apellido,''),' ',COALESCE(cr.nombre,''))) AS rojo_nombre_join";
    $join   = " LEFT JOIN `competidores_evento` ca ON p.".bt($C_AZUL_ID)." = ca.id
                LEFT JOIN `competidores_evento` cr ON p.".bt($C_ROJO_ID)." = cr.id ";
  }

  $order = $C_HORA ? " ORDER BY p.".bt($C_HORA)." ASC" : " ORDER BY p.`id` ASC";

  $res_tmp = [];
  /* 1) Asignadas, si existe tabla */
  if ($asig_tbl) {
    $sql = "SELECT ".implode(',', $cols)." FROM `".$conexion->real_escape_string($peleas_tbl)."` p
            INNER JOIN `$asig_tbl` pj ON pj.pelea_id = p.id
            WHERE pj.juez_id = ?".
            ($evento_id>0 && has_col($conexion,$peleas_tbl,'evento_id') ? " AND p.`evento_id` = ?" : "").
            $join.$order;
    if ($st=$conexion->prepare($sql)) {
      if ($evento_id>0 && has_col($conexion,$peleas_tbl,'evento_id')) { $st->bind_param('ii',$juez_id,$evento_id); }
      else { $st->bind_param('i',$juez_id); }
      $st->execute(); $r=$st->get_result(); if($r) $res_tmp=$r->fetch_all(MYSQLI_ASSOC); $st->close();
    }
    if ($res_tmp) { $peleas = $res_tmp; $list_title = 'Mis peleas asignadas'; }
  }

  /* 2) Fallback: todas del evento (o todas si no hay evento_id en sesión) */
  if (!$peleas) {
    $list_title = $evento_id>0 ? 'Peleas del evento' : 'Todas las peleas';
    $sql = "SELECT ".implode(',', $cols)." FROM `".$conexion->real_escape_string($peleas_tbl)."` p ".
           $join.
           ($evento_id>0 && has_col($conexion,$peleas_tbl,'evento_id') ? " WHERE p.`evento_id` = ".(int)$evento_id : "").
           $order;
    if ($st=$conexion->prepare($sql)) {
      $st->execute(); $r=$st->get_result(); $peleas = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
      $st->close();
    }
  }
}

/* ===== Competidores del evento (panel inferior) ===== */
$competidores = [];
if (table_exists($conexion,'competidores_evento')) {
  $sql_comp = "SELECT ce.id, ce.nombre, ce.apellido, ce.escuela_nombre,
                      dv.nombre AS division, cp.nombre AS peso, md.nombre AS modalidad
               FROM competidores_evento ce
               LEFT JOIN divisiones_evento dv ON dv.id=ce.division_id
               LEFT JOIN categorias_peso_evento cp ON cp.id=ce.categoria_peso_id
               LEFT JOIN modalidades_evento md ON md.id=ce.modalidad_id";
  if ($evento_id>0 && has_col($conexion,'competidores_evento','evento_id')) $sql_comp .= " WHERE ce.evento_id = ".(int)$evento_id;
  $sql_comp .= " ORDER BY ce.apellido, ce.nombre";
  if ($st=$conexion->prepare($sql_comp)) {
    $st->execute(); $r=$st->get_result(); $competidores = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Tarjeta de Juez</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1100px;margin:6vh auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:16px;padding:18px}
    .muted{color:#9ecbff}
    .title{margin:0 0 10px 0;font-size:22px}
    .btn{display:inline-block;padding:8px 12px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none}
    .btn.gray{background:#1b2836;border-color:#2b3c4f}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left;font-size:14px}
    th{color:#9ecbff}
    .two{display:grid;grid-template-columns:1fr;gap:16px}
    @media (min-width:1000px){ .two{grid-template-columns:2fr 1fr} }
    input[type=search]{padding:8px 10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4;width:100%}
    .top{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}
  </style>
</head>
<body>
  <div class="wrap two">
    <!-- PELEAS -->
    <div class="card">
      <div class="top">
        <h2 class="title">🥊 <?= h($list_title) ?></h2>
        <input id="qpeleas" type="search" placeholder="Filtrar peleas…">
      </div>
      <?php if (!$peleas_tbl): ?>
        <div class="muted">No encuentro la tabla de peleas (<code>peleas_evento</code> / <code>peleas</code> / <code>peleas_eventos</code>).</div>
      <?php elseif (!$peleas): ?>
        <div class="muted">No hay peleas para mostrar.</div>
      <?php else: ?>
        <table id="tblPeleas">
          <thead>
            <tr>
              <th>ID</th>
              <th>Participantes</th>
              <th>Categoría</th>
              <th>Ring/Tatami</th>
              <th>Horario</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($peleas as $p):
            $az = $p['azul_nombre'] ?? ($p['azul_nombre_join'] ?? '');
            $ro = $p['rojo_nombre'] ?? ($p['rojo_nombre_join'] ?? '');
            $vs = trim(($az?:'') . (($az||$ro)?' vs ':'') . ($ro?:''));
            if ($vs==='') $vs = $p['titulo'] ?? '—';
            $cat  = $p['categoria'] ?? '—';
            $ring = $p['ring'] ?? '—';
            $hora = $p['horario'] ?? '—';
            $est  = $p['estado'] ?? '—';
          ?>
            <tr>
              <td><?= (int)$p['pelea_id'] ?></td>
              <td><?= h($vs) ?></td>
              <td><?= h((string)$cat) ?></td>
              <td><?= h((string)$ring) ?></td>
              <td><?= h((string)$hora) ?></td>
              <td><?= h((string)$est) ?></td>
              <td style="white-space:nowrap;display:flex;gap:8px">
                <a class="btn" href="tarjeta_puntuar.php?pelea_id=<?= (int)$p['pelea_id'] ?>">Tarjeta (puntuar)</a>
                <a class="btn gray" href="combate_en_vivo.php?pelea_id=<?= (int)$p['pelea_id'] ?>&modo=juez">Ver en vivo</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- COMPETIDORES DEL EVENTO -->
    <div class="card">
      <h2 class="title">👥 Competidores del evento</h2>
      <?php if (!$competidores): ?>
        <div class="muted">No se encontraron competidores para este evento.</div>
      <?php else: ?>
        <input id="qcomp" type="search" placeholder="Filtrar competidores…">
        <table id="tblComp">
          <thead>
            <tr>
              <th>#</th>
              <th>Competidor</th>
              <th>Escuela</th>
              <th>División</th>
              <th>Peso</th>
              <th>Modalidad</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($competidores as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <td><?= h(trim(($c['apellido'] ?? '').' '.($c['nombre'] ?? ''))) ?></td>
              <td><?= h($c['escuela_nombre'] ?? '-') ?></td>
              <td><?= h($c['division'] ?? '-') ?></td>
              <td><?= h($c['peso'] ?? '-') ?></td>
              <td><?= h($c['modalidad'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="muted" style="margin-top:8px">
          Tip: indicá al juez qué pelea abrir; si no la encuentra, puede verificar por nombres acá.
        </div>
      <?php endif; ?>
    </div>
  </div>

<script>
  // Filtros rápidos
  const qP = document.getElementById('qpeleas'), tblP = document.getElementById('tblPeleas');
  if (qP && tblP) {
    qP.addEventListener('input', () => {
      const t = qP.value.toLowerCase();
      for (const tr of tblP.querySelectorAll('tbody tr')) tr.style.display = tr.innerText.toLowerCase().includes(t) ? '' : 'none';
    });
  }
  const qC = document.getElementById('qcomp'), tblC = document.getElementById('tblComp');
  if (qC && tblC) {
    qC.addEventListener('input', () => {
      const t = qC.value.toLowerCase();
      for (const tr of tblC.querySelectorAll('tbody tr')) tr.style.display = tr.innerText.toLowerCase().includes(t) ? '' : 'none';
    });
  }
</script>
</body>
</html>
