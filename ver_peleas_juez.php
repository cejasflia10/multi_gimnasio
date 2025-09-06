<?php
// ver_peleas_evento.php — MODO JUEZ: lista TODAS las peleas y agrega links para puntuar/en vivo
if (session_status() === PHP_SESSION_NONE) session_start();
$MODO_JUEZ = (isset($_GET['modo']) && $_GET['modo'] === 'juez');
if ($MODO_JUEZ) { $_SESSION['__JUEZ_MODE__'] = 1; } // evita guard del organizador

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function table_exists(mysqli $db, string $t): bool {
  $t=$db->real_escape_string($t);
  if ($r=$db->query("SHOW TABLES LIKE '$t'")) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function first_table(mysqli $db, array $cands): ?string { foreach ($cands as $t) if (table_exists($db,$t)) return $t; return null; }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Detectar tabla de peleas */
$peleas_tbl = first_table($conexion, ['peleas_evento','peleas','peleas_eventos']);
if (!$peleas_tbl) { exit('❌ No encuentro tabla de peleas.'); }

/* Columnas */
$C_EVENTO = has_col($conexion,$peleas_tbl,'evento_id') ? 'evento_id' : (has_col($conexion,$peleas_tbl,'id_evento') ? 'id_evento' : null);
$C_AZUL_N = has_col($conexion,$peleas_tbl,'azul_nombre') ? 'azul_nombre' : (has_col($conexion,$peleas_tbl,'competidor_a') ? 'competidor_a' : null);
$C_ROJO_N = has_col($conexion,$peleas_tbl,'rojo_nombre') ? 'rojo_nombre' : (has_col($conexion,$peleas_tbl,'competidor_b') ? 'competidor_b' : null);
$C_TITULO = has_col($conexion,$peleas_tbl,'titulo') ? 'titulo' : null;
$C_CAT  = has_col($conexion,$peleas_tbl,'categoria') ? 'categoria' : null;
$C_RING = has_col($conexion,$peleas_tbl,'ring') ? 'ring' : (has_col($conexion,$peleas_tbl,'tatami') ? 'tatami' : null);
$C_HORA = has_col($conexion,$peleas_tbl,'programado_at') ? 'programado_at' : (has_col($conexion,$peleas_tbl,'horario') ? 'horario' : null);
$C_EST  = has_col($conexion,$peleas_tbl,'estado') ? 'estado' : null;

/* IDs para join de nombres si hiciera falta */
$C_AZUL_ID=null; $C_ROJO_ID=null;
foreach (['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'] as $c) if (has_col($conexion,$peleas_tbl,$c)) { $C_AZUL_ID=$c; break; }
foreach (['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'] as $c) if (has_col($conexion,$peleas_tbl,$c)) { $C_ROJO_ID=$c; break; }

$cols = ["p.`id` AS pelea_id"];
if ($C_EVENTO) $cols[] = "p.".bt($C_EVENTO)." AS evento_id";
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

/* IMPORTANTE: en MODO JUEZ NO SE FILTRA por asignaciones ni por organizador.
   (Opcional) Si querés filtrar por evento en sesión, descomentá: */
// $evento_id = (int)($_SESSION['evento_id_actual'] ?? 0);
// $where = ($C_EVENTO && $evento_id>0) ? " WHERE p.".bt($C_EVENTO)." = ".(int)$evento_id : "";
$where = ""; // ver todas

$order = $C_HORA ? " ORDER BY p.".bt($C_HORA)." ASC" : " ORDER BY p.`id` ASC";
$sql = "SELECT ".implode(',', $cols)." FROM `".$conexion->real_escape_string($peleas_tbl)."` p ".$join.$where.$order;

$st = $conexion->prepare($sql);
if (!$st) { exit('❌ Error preparando listado: '.h($conexion->error)); }
$st->execute();
$res = $st->get_result();
$peleas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $MODO_JUEZ?'🥊 Peleas (modo juez)':'🥊 Peleas del evento' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1100px;margin:32px auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:18px}
    h1{margin:0 0 10px 0;font-size:22px}
    .muted{color:#8bb3d9}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left;font-size:14px}
    th{color:#9ecbff}
    .btn{display:inline-block;padding:8px 12px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none}
    .btn.gray{background:#1b2836;border-color:#2b3c4f}
    .top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    input[type=search]{padding:8px 10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="top">
        <h1><?= $MODO_JUEZ?'🥊 Peleas (modo juez)':'🥊 Peleas del evento' ?></h1>
        <input id="q" type="search" placeholder="Filtrar…">
      </div>
      <?php if (!$peleas): ?>
        <div class="muted">No hay peleas para mostrar.</div>
      <?php else: ?>
        <table id="tbl">
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
                <!-- Link para puntuar -->
                <a class="btn" href="tarjeta_puntuar.php?pelea_id=<?= (int)$p['pelea_id'] ?>">Tarjeta (puntuar)</a>
                <!-- Link a combate en vivo (lee get_tablero_tarjetas.php) -->
                <a class="btn gray" href="combate_en_vivo.php?pelea_id=<?= (int)$p['pelea_id'] ?>&modo=juez">Ver en vivo</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
<script>
  const q=document.getElementById('q'), tbl=document.getElementById('tbl');
  if(q&&tbl){ q.addEventListener('input',()=>{ const t=q.value.toLowerCase(); for(const tr of tbl.querySelectorAll('tbody tr')){ tr.style.display = tr.innerText.toLowerCase().includes(t) ? '' : 'none'; } }); }
</script>
</body>
</html>
<?php
// ver_peleas_evento.php — MODO JUEZ: lista TODAS las peleas y agrega links para puntuar/en vivo
if (session_status() === PHP_SESSION_NONE) session_start();
$MODO_JUEZ = (isset($_GET['modo']) && $_GET['modo'] === 'juez');
if ($MODO_JUEZ) { $_SESSION['__JUEZ_MODE__'] = 1; } // evita guard del organizador

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function table_exists(mysqli $db, string $t): bool {
  $t=$db->real_escape_string($t);
  if ($r=$db->query("SHOW TABLES LIKE '$t'")) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function first_table(mysqli $db, array $cands): ?string { foreach ($cands as $t) if (table_exists($db,$t)) return $t; return null; }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Detectar tabla de peleas */
$peleas_tbl = first_table($conexion, ['peleas_evento','peleas','peleas_eventos']);
if (!$peleas_tbl) { exit('❌ No encuentro tabla de peleas.'); }

/* Columnas */
$C_EVENTO = has_col($conexion,$peleas_tbl,'evento_id') ? 'evento_id' : (has_col($conexion,$peleas_tbl,'id_evento') ? 'id_evento' : null);
$C_AZUL_N = has_col($conexion,$peleas_tbl,'azul_nombre') ? 'azul_nombre' : (has_col($conexion,$peleas_tbl,'competidor_a') ? 'competidor_a' : null);
$C_ROJO_N = has_col($conexion,$peleas_tbl,'rojo_nombre') ? 'rojo_nombre' : (has_col($conexion,$peleas_tbl,'competidor_b') ? 'competidor_b' : null);
$C_TITULO = has_col($conexion,$peleas_tbl,'titulo') ? 'titulo' : null;
$C_CAT  = has_col($conexion,$peleas_tbl,'categoria') ? 'categoria' : null;
$C_RING = has_col($conexion,$peleas_tbl,'ring') ? 'ring' : (has_col($conexion,$peleas_tbl,'tatami') ? 'tatami' : null);
$C_HORA = has_col($conexion,$peleas_tbl,'programado_at') ? 'programado_at' : (has_col($conexion,$peleas_tbl,'horario') ? 'horario' : null);
$C_EST  = has_col($conexion,$peleas_tbl,'estado') ? 'estado' : null;

/* IDs para join de nombres si hiciera falta */
$C_AZUL_ID=null; $C_ROJO_ID=null;
foreach (['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'] as $c) if (has_col($conexion,$peleas_tbl,$c)) { $C_AZUL_ID=$c; break; }
foreach (['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'] as $c) if (has_col($conexion,$peleas_tbl,$c)) { $C_ROJO_ID=$c; break; }

$cols = ["p.`id` AS pelea_id"];
if ($C_EVENTO) $cols[] = "p.".bt($C_EVENTO)." AS evento_id";
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

/* IMPORTANTE: en MODO JUEZ NO SE FILTRA por asignaciones ni por organizador.
   (Opcional) Si querés filtrar por evento en sesión, descomentá: */
// $evento_id = (int)($_SESSION['evento_id_actual'] ?? 0);
// $where = ($C_EVENTO && $evento_id>0) ? " WHERE p.".bt($C_EVENTO)." = ".(int)$evento_id : "";
$where = ""; // ver todas

$order = $C_HORA ? " ORDER BY p.".bt($C_HORA)." ASC" : " ORDER BY p.`id` ASC";
$sql = "SELECT ".implode(',', $cols)." FROM `".$conexion->real_escape_string($peleas_tbl)."` p ".$join.$where.$order;

$st = $conexion->prepare($sql);
if (!$st) { exit('❌ Error preparando listado: '.h($conexion->error)); }
$st->execute();
$res = $st->get_result();
$peleas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $MODO_JUEZ?'🥊 Peleas (modo juez)':'🥊 Peleas del evento' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1100px;margin:32px auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:18px}
    h1{margin:0 0 10px 0;font-size:22px}
    .muted{color:#8bb3d9}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left;font-size:14px}
    th{color:#9ecbff}
    .btn{display:inline-block;padding:8px 12px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none}
    .btn.gray{background:#1b2836;border-color:#2b3c4f}
    .top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    input[type=search]{padding:8px 10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="top">
        <h1><?= $MODO_JUEZ?'🥊 Peleas (modo juez)':'🥊 Peleas del evento' ?></h1>
        <input id="q" type="search" placeholder="Filtrar…">
      </div>
      <?php if (!$peleas): ?>
        <div class="muted">No hay peleas para mostrar.</div>
      <?php else: ?>
        <table id="tbl">
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
                <!-- Link para puntuar -->
                <a class="btn" href="tarjeta_puntuar.php?pelea_id=<?= (int)$p['pelea_id'] ?>">Tarjeta (puntuar)</a>
                <!-- Link a combate en vivo (lee get_tablero_tarjetas.php) -->
                <a class="btn gray" href="combate_en_vivo.php?pelea_id=<?= (int)$p['pelea_id'] ?>&modo=juez">Ver en vivo</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
<script>
  const q=document.getElementById('q'), tbl=document.getElementById('tbl');
  if(q&&tbl){ q.addEventListener('input',()=>{ const t=q.value.toLowerCase(); for(const tr of tbl.querySelectorAll('tbody tr')){ tr.style.display = tr.innerText.toLowerCase().includes(t) ? '' : 'none'; } }); }
</script>
</body>
</html>
