<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers con guard ===== */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('table_exists')) {
  function table_exists(mysqli $db, string $t): bool {
    $t = $db->real_escape_string($t);
    if ($r = $db->query("SHOW TABLES LIKE '$t'")) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
    return false;
  }
}
if (!function_exists('has_col')) {
  function has_col(mysqli $db, string $table, string $col): bool {
    $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
    $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
    if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
    return false;
  }
}
if (!function_exists('first_table')) {
  function first_table(mysqli $db, array $cands): ?string { foreach($cands as $t){ if (table_exists($db,$t)) return $t; } return null; }
}

/* ===== Verificamos sesión del juez (login_juez.php la debe crear) ===== */
$juez_id = (int)($_SESSION['juez_id'] ?? 0);
if ($juez_id <= 0) { header('Location: login_juez.php?err='.urlencode('Iniciá sesión como juez.')); exit; }

/* ===== BASE PATH absoluto para links correctos ===== */
$BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($BASE === '') $BASE = '/';   // ej: /multi_gimnasio

/* ===== Tablas / columnas ===== */
$peleas_tbl = first_table($conexion, ['peleas_evento','peleas','peleas_eventos']);
if (!$peleas_tbl) { exit('No se encontró la tabla de peleas.'); }

$C_EVT  = has_col($conexion,$peleas_tbl,'evento_id')     ? 'evento_id'     : null;
$C_CAT  = has_col($conexion,$peleas_tbl,'categoria')     ? 'categoria'     : null;
$C_RING = has_col($conexion,$peleas_tbl,'ring')          ? 'ring'          : (has_col($conexion,$peleas_tbl,'tatami') ? 'tatami' : null);
$C_HORA = has_col($conexion,$peleas_tbl,'programado_at') ? 'programado_at' : (has_col($conexion,$peleas_tbl,'horario') ? 'horario' : null);
$C_EST  = has_col($conexion,$peleas_tbl,'estado')        ? 'estado'        : null;

$C_AZUL_N = has_col($conexion,$peleas_tbl,'azul_nombre') ? 'azul_nombre' : (has_col($conexion,$peleas_tbl,'competidor_a') ? 'competidor_a' : null);
$C_ROJO_N = has_col($conexion,$peleas_tbl,'rojo_nombre') ? 'rojo_nombre' : (has_col($conexion,$peleas_tbl,'competidor_b') ? 'competidor_b' : null);

$C_AZUL_ID = $C_ROJO_ID = null;
foreach (['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'] as $c){ if (has_col($conexion,$peleas_tbl,$c)) { $C_AZUL_ID=$c; break; } }
foreach (['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'] as $c){ if (has_col($conexion,$peleas_tbl,$c)) { $C_ROJO_ID=$c; break; } }

/* Si guardás evento en sesión, se usa para filtrar (opcional) */
$evento_id = (int)($_SESSION['evento_id_actual'] ?? 0);

/* ===== Query ===== */
$cols = ["p.id AS pelea_id"];
if ($C_EVT)   $cols[] = "p.$C_EVT AS evento_id";
if ($C_AZUL_N)$cols[] = "p.$C_AZUL_N AS azul_nombre";
if ($C_ROJO_N)$cols[] = "p.$C_ROJO_N AS rojo_nombre";
if ($C_CAT)   $cols[] = "p.$C_CAT AS categoria";
if ($C_RING)  $cols[] = "p.$C_RING AS ring";
if ($C_HORA)  $cols[] = "p.$C_HORA AS horario";
if ($C_EST)   $cols[] = "p.$C_EST AS estado";

$join = '';
if ((!$C_AZUL_N || !$C_ROJO_N) && ($C_AZUL_ID || $C_ROJO_ID) && table_exists($conexion,'competidores_evento')) {
  if ($C_AZUL_ID) $cols[] = "TRIM(CONCAT(COALESCE(ca.apellido,''),' ',COALESCE(ca.nombre,''))) AS azul_nombre_join";
  if ($C_ROJO_ID) $cols[] = "TRIM(CONCAT(COALESCE(cr.apellido,''),' ',COALESCE(cr.nombre,''))) AS rojo_nombre_join";
  $join  = ($C_AZUL_ID ? " LEFT JOIN competidores_evento ca ON p.`$C_AZUL_ID`=ca.id " : "");
  $join .= ($C_ROJO_ID ? " LEFT JOIN competidores_evento cr ON p.`$C_ROJO_ID`=cr.id " : "");
}

$where = ($C_EVT && $evento_id>0) ? " WHERE p.$C_EVT = ".(int)$evento_id : "";
$order = $C_HORA ? " ORDER BY p.$C_HORA ASC" : " ORDER BY p.id ASC";

$sql = "SELECT ".implode(',', $cols)." FROM `$peleas_tbl` p $join $where $order";
$peleas = [];
if ($st=$conexion->prepare($sql)) { $st->execute(); $r=$st->get_result(); $peleas = $r ? $r->fetch_all(MYSQLI_ASSOC) : []; $st->close(); }

/* Páginas destino (archivos que sí existen en tu app) */
$puntuarUrl = file_exists(__DIR__.'/puntuar_pelea.php') ? '/puntuar_pelea.php'
            : (file_exists(__DIR__.'/tarjeta_puntuar.php') ? '/tarjeta_puntuar.php' : '/puntuar_pelea.php');
$enVivoUrl  = '/combate_en_vivo.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Peleas — Juez</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1100px;margin:6vh auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:16px;padding:18px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left;font-size:14px}
    th{color:#9ecbff}
    .btn{display:inline-block;padding:8px 12px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none}
    .btn.gray{background:#1b2836;border-color:#2b3c4f}
    .title{margin:0 0 10px 0;font-size:22px}
    .top{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}
    input[type=search]{padding:8px 10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4;width:320px;max-width:100%}
    .acts{white-space:nowrap;display:flex;gap:8px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="top">
        <h2 class="title">🥊 Peleas — Juez</h2>
        <input id="q" type="search" placeholder="Filtrar peleas…">
      </div>

      <?php if (!$peleas): ?>
        <div style="color:#9ecbff">No hay peleas para mostrar.</div>
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
              <th style="width:220px"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($peleas as $p):
            $az = $p['azul_nombre'] ?? ($p['azul_nombre_join'] ?? '');
            $ro = $p['rojo_nombre'] ?? ($p['rojo_nombre_join'] ?? '');
            $vs = trim(($az?:'').(($az||$ro)?' vs ':'').($ro?:''));
            if ($vs==='') $vs = '—';

            $cat  = $p['categoria'] ?? '—';
            $ring = $p['ring'] ?? '—';
            $hora = $p['horario'] ?? '—';
            $est  = $p['estado'] ?? '—';
            $id   = (int)$p['pelea_id'];
            $qs   = '?pelea_id='.$id; // SIN modo=juez
          ?>
            <tr>
              <td><?= $id ?></td>
              <td><?= h($vs) ?></td>
              <td><?= h((string)$cat) ?></td>
              <td><?= h((string)$ring) ?></td>
              <td><?= h((string)$hora) ?></td>
              <td><?= h((string)$est) ?></td>
              <td class="acts">
                <a class="btn" href="<?= $BASE . $puntuarUrl . $qs ?>">Puntuar</a>
                <a class="btn gray" href="<?= $BASE . $enVivoUrl . $qs ?>">En vivo</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const q = document.getElementById('q'), tbl = document.getElementById('tbl');
    if (q && tbl) {
      q.addEventListener('input', () => {
        const t = q.value.toLowerCase();
        for (const tr of tbl.querySelectorAll('tbody tr')) {
          tr.style.display = tr.innerText.toLowerCase().includes(t) ? '' : 'none';
        }
      });
    }
  </script>
</body>
</html>
