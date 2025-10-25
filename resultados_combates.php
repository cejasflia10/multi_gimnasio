<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__ . '/menu_eventos.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$DEBUG = (isset($_GET['debug']) && $_GET['debug']=='1');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getv($k){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : ''; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function table_exists($db,$t){
  if (!($db instanceof mysqli)) return false;
  $t=$db->real_escape_string($t);
  if ($r=$db->query("SHOW TABLES LIKE '{$t}'")) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col($db,$t,$c){
  if (!($db instanceof mysqli)) return false;
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function safe_prepare($db,$sql,&$err){
  $st = $db->prepare($sql);
  if (!$st) $err = $db->error;
  return $st;
}

/* ===== Detección de columnas clave en peleas_evento ===== */
$C_ROJO = $C_AZUL = null;
$can_join_pe = table_exists($conexion,'peleas_evento');
if ($can_join_pe) {
  $cands = [];
  if ($rs=$conexion->query("SHOW COLUMNS FROM `peleas_evento`")){
    while($r=$rs->fetch_assoc()) $cands[strtolower($r['Field'])]=$r['Field'];
    $rs->close();
  }
  $C_ROJO = $cands['competidor_rojo_id'] ?? ($cands['rojo_id'] ?? ($cands['id_rojo'] ?? null));
  $C_AZUL = $cands['competidor_azul_id'] ?? ($cands['azul_id'] ?? ($cands['id_azul'] ?? null));
}
$can_names = $can_join_pe && $C_ROJO && $C_AZUL;

/* ===== Nombre en competidores_evento ===== */
function name_expr_ce($db, $alias='ce'){
  $parts=[];
  if (has_col($db,'competidores_evento','display_name'))   $parts[]="{$alias}.`display_name`";
  if (has_col($db,'competidores_evento','nombre_completo'))$parts[]="{$alias}.`nombre_completo`";
  if (has_col($db,'competidores_evento','nombreyapellido'))$parts[]="{$alias}.`nombreyapellido`";
  $ap = has_col($db,'competidores_evento','apellido')  ? "{$alias}.`apellido`"
     : (has_col($db,'competidores_evento','apellidos') ? "{$alias}.`apellidos`" : "NULL");
  $no = has_col($db,'competidores_evento','nombre')    ? "{$alias}.`nombre`"
     : (has_col($db,'competidores_evento','nombres')   ? "{$alias}.`nombres`"   : "NULL");
  $parts[] = "CONCAT_WS(' ', {$ap}, {$no})";
  if (has_col($db,'competidores_evento','alias')) $parts[]="{$alias}.`alias`";
  if (has_col($db,'competidores_evento','apodo')) $parts[]="{$alias}.`apodo`";
  if (has_col($db,'competidores_evento','nick'))  $parts[]="{$alias}.`nick`";
  return "NULLIF(TRIM(COALESCE(".implode(', ',$parts).")), '')";
}

/* ===== Parámetros / sesión ===== */
$combate_id = toIntOrNull(getv('id'));
$pelea_id_q = toIntOrNull(getv('pelea_id'));
$evento_id_actual = isset($_SESSION['evento_id_actual']) ? (int)$_SESSION['evento_id_actual'] : null;

$flash_ok    = $_SESSION['flash_ok']    ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

$view_mode = 'historial';
$view      = [];

/* ===== Redirección por pelea_id -> ficha dentro del evento ===== */
if (!$combate_id && $pelea_id_q){
  $err=null;
  $sql="SELECT id FROM resultados_combates WHERE pelea_id=? AND evento_id=? ORDER BY id DESC LIMIT 1";
  if ($st=safe_prepare($conexion,$sql,$err)){
    $st->bind_param('ii',$pelea_id_q,$evento_id_actual);
    $st->execute(); $r=$st->get_result(); $row=$r?$r->fetch_assoc():null; $st->close();
    if ($row){ header("Location: resultados_combates.php?id=".$row['id']); exit; }
    else { $flash_error='No hay resultados para esa pelea en tu evento.'; }
  } else { $flash_error='No se pudo preparar la búsqueda por pelea_id. '.$err; }
}

/* ===== FICHA por id ===== */
if ($combate_id){
  $err = null;
  // Query MUY directa, solo usa competidores_evento
  $select = [
    "rc.id","rc.pelea_id","rc.evento_id",
    "rc.ganador_color","rc.ganador_id","rc.metodo","rc.detalle",
    "rc.puntos_rojo","rc.puntos_azul","rc.creado_en"
  ];
  $from = " FROM resultados_combates rc";
  $joins=[];
  if ($can_names){
    $joins[] = "LEFT JOIN peleas_evento pe ON pe.id = rc.pelea_id";
    $joins[] = "LEFT JOIN competidores_evento cr ON cr.id = pe.".bt($C_ROJO);
    $joins[] = "LEFT JOIN competidores_evento ca ON ca.id = pe.".bt($C_AZUL);
    $joins[] = "LEFT JOIN competidores_evento cg ON cg.id = rc.ganador_id";
    $loserIdCase = "CASE WHEN rc.ganador_color='rojo' THEN pe.".bt($C_AZUL)." WHEN rc.ganador_color='azul' THEN pe.".bt($C_ROJO)." ELSE NULL END";
    $joins[] = "LEFT JOIN competidores_evento cp ON cp.id = {$loserIdCase}";
    $R_NAME = name_expr_ce($conexion,'cr');
    $A_NAME = name_expr_ce($conexion,'ca');
    $WIN_N  = name_expr_ce($conexion,'cg');
    $LOSE_N = name_expr_ce($conexion,'cp');
  } else {
    // Sin columnas rojo/azul en peleas_evento: al menos traemos ganador por su ID
    $joins[] = "LEFT JOIN competidores_evento cg ON cg.id = rc.ganador_id";
    $R_NAME = "NULL"; $A_NAME="NULL";
    $WIN_N  = name_expr_ce($conexion,'cg');
    $LOSE_N = "NULL";
  }

  $select[] = "{$R_NAME} AS r_name";
  $select[] = "{$A_NAME} AS a_name";
  $select[] = "{$WIN_N} AS ganador_name";
  $select[] = "{$LOSE_N} AS perdedor_name";

  $sql = "SELECT ".implode(', ',$select).$from.' '.implode(' ',$joins)
       . " WHERE rc.id=? AND rc.evento_id=? LIMIT 1";

  if ($st=safe_prepare($conexion,$sql,$err)){
    $st->bind_param('ii',$combate_id,$evento_id_actual);
    if ($st->execute()){
      $row = $st->get_result()->fetch_assoc(); $st->close();
      if ($row){
        $gcol = strtolower((string)($row['ganador_color'] ?? 'empate'));
        $rnom = trim((string)($row['r_name'] ?? ''));
        $anom = trim((string)($row['a_name'] ?? ''));
        $gan  = trim((string)($row['ganador_name'] ?? ''));
        $per  = trim((string)($row['perdedor_name'] ?? ''));
        if ($gcol!=='rojo' && $gcol!=='azul'){ $gan='⚖️ Empate'; $per=''; }

        $view = [
          'view'          => 'ficha',
          'evento_id'     => (int)$row['evento_id'],
          'evento_titulo' => '',
          'pelea_id'      => (int)($row['pelea_id'] ?? 0),
          'combate_id'    => (int)($row['id'] ?? 0),
          'rojo_name'     => $rnom,
          'azul_name'     => $anom,
          'ganador_color' => $gcol,
          'ganador_name'  => $gan,
          'perdedor_name' => $per,
          'metodo'        => (string)($row['metodo'] ?? ''),
          'detalle'       => (string)($row['detalle'] ?? ''),
          'pts_rojo'      => (int)($row['puntos_rojo'] ?? 0),
          'pts_azul'      => (int)($row['puntos_azul'] ?? 0),
          'fecha'         => (string)($row['creado_en'] ?? '')
        ];
        $view_mode='ficha';
      } else {
        $flash_error='No se encontró el combate en tu evento.';
      }
    } else {
      $flash_error='Fallo execute (ficha): '.$conexion->error;
    }
  } else {
    $flash_error='No se pudo preparar la consulta de ficha. '.$err;
  }
} else {
  $view_mode='historial';
}

/* ===== Render ===== */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= $view_mode==='ficha' ? 'Resultados del Combate #'.(int)($view['combate_id'] ?? 0) : 'Historial de Combates — Tu evento' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <style>
    :root{
      --bg:#fff; --fg:#111; --muted:#6b7280; --border:#e5e7eb;
      --card:#fff; --card-border:#e5e7eb; --badge:#f3f4f6; --badge-fg:#111;
      --ok-bg:#ecfdf5; --ok-bd:#10b981; --ok-fg:#065f46;
      --bad-bg:#fef2f2; --bad-bd:#ef4444; --bad-fg:#7f1d1d;
      --btn:#111; --btn-fg:#fff; --btn2:#f3f4f6; --btn2-fg:#111;
      --gold:#d4af37; --green:#10b981; --winR:#b91c1c; --winA:#1d4ed8; --draw:#92400e; --th:#f9fafb;
    }
    *{box-sizing:border-box} body{background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1100px;margin:0 auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--card-border);border-radius:14px;padding:14px;margin-top:12px}
    h2{margin:.2rem 0 .8rem} .row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
    .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:var(--badge);color:var(--badge-fg);font-size:12px}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid var(--border);text-decoration:none;cursor:pointer}
    .btn-gray{background:var(--btn2);color:var(--btn2-fg)}
    .btn-green{background:var(--green);color:#fff;border:0}
    .winR{color:var(--winR);font-weight:800} .winA{color:var(--winA);font-weight:800} .draw{color:var(--draw);font-weight:800}
    table{width:100%;border-collapse:collapse;margin-top:8px} th,td{border:1px solid var(--border);padding:8px 10px;text-align:left} th{background:var(--th)}
    .ok{padding:10px;border-radius:10px;background:var(--ok-bg);border:1px solid var(--ok-bd);color:var(--ok-fg);margin-top:10px}
    .bad{padding:10px;border-radius:10px;background:var(--bad-bg);border:1px solid var(--bad-bd);color:var(--bad-fg);margin-top:10px}
    .nowrap{white-space:nowrap} .muted{color:var(--muted)}
  </style>
</head>
<body>
<div class="wrap">
<?php if ($view_mode==='ficha'): ?>
  <h2>🥊 Resultados del Combate</h2>

  <?php if (!empty($flash_ok)): ?><div class="ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if (!empty($flash_error)): ?><div class="bad"><?= h($flash_error) ?></div><?php endif; ?>
  <?php if ($DEBUG && !empty($sql)): ?>
    <div class="ok" style="white-space:pre-wrap"><b>SQL Ficha:</b> <?= h($sql) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="row" style="justify-content:space-between">
      <div>
        <div class="badge">Evento #<?= (int)($view['evento_id'] ?? 0) ?></div>
        <?php if (!empty($view['pelea_id'])): ?><div class="badge" style="margin-left:6px">Pelea #<?= (int)$view['pelea_id'] ?></div><?php endif; ?>
        <div class="badge" style="margin-left:6px">Combate #<?= (int)($view['combate_id'] ?? 0) ?></div>
      </div>
      <?php if (!empty($view['fecha'])): ?><div class="badge">Fecha: <?= h($view['fecha']) ?></div><?php endif; ?>
    </div>

    <table>
      <tbody>
        <tr><th style="width:35%">Rincón Rojo</th><td class="winR"><?= h($view['rojo_name'] ?? '') ?></td></tr>
        <tr><th>Rincón Azul</th><td class="winA"><?= h($view['azul_name'] ?? '') ?></td></tr>
        <tr>
          <th>Ganador</th>
          <td>
            <?php
              $g = $view['ganador_color'] ?? 'empate';
              if ($g==='rojo')   echo '<span class="winR">🔴 '.h($view['ganador_name']).'</span>';
              elseif ($g==='azul') echo '<span class="winA">🔵 '.h($view['ganador_name']).'</span>';
              else                 echo '<span class="draw">⚖️ Empate</span>';
            ?>
            <?php if (!empty($view['metodo'])): ?><span class="badge" style="margin-left:6px">Método: <?= h($view['metodo']) ?></span><?php endif; ?>
          </td>
        </tr>
        <?php if (!empty($view['perdedor_name'])): ?><tr><th>Perdedor</th><td><?= h($view['perdedor_name']) ?></td></tr><?php endif; ?>
        <tr><th>Totales por puntos</th><td>Rojo <?= (int)($view['pts_rojo']??0) ?> · Azul <?= (int)($view['pts_azul']??0) ?></td></tr>
      </tbody>
    </table>

    <div class="ok" style="<?= empty($view['detalle'])?'display:none':'' ?>"><b>Detalle:</b> <?= h($view['detalle']??'') ?></div>

    <div class="row" style="margin-top:12px">
      <a class="btn btn-gray" href="resultados_combates.php">↩️ Volver al historial</a>
    </div>
  </div>

<?php else: /* HISTORIAL */ ?>
  <h2>📜 Historial de Combates — Tu evento</h2>

  <?php if ($evento_id_actual === null): ?>
    <div class="bad">No hay un <b>evento activo</b> en la sesión. Configurá <code>$_SESSION['evento_id_actual']</code> durante el login.</div>
  <?php else: ?>
    <?php
      $err=null;
      $select = [
        "rc.id","rc.creado_en","rc.pelea_id",
        "rc.ganador_color","rc.ganador_id","rc.detalle","rc.metodo",
        "rc.puntos_rojo","rc.puntos_azul"
      ];
      $from = " FROM resultados_combates rc";
      $joins=[];
      if ($can_names){
        $joins[] = "LEFT JOIN peleas_evento pe ON pe.id = rc.pelea_id";
        $joins[] = "LEFT JOIN competidores_evento cr ON cr.id = pe.".bt($C_ROJO);
        $joins[] = "LEFT JOIN competidores_evento ca ON ca.id = pe.".bt($C_AZUL);
        $joins[] = "LEFT JOIN competidores_evento cg ON cg.id = rc.ganador_id";
        $loserIdCase = "CASE WHEN rc.ganador_color='rojo' THEN pe.".bt($C_AZUL)." WHEN rc.ganador_color='azul' THEN pe.".bt($C_ROJO)." ELSE NULL END";
        $joins[] = "LEFT JOIN competidores_evento cp ON cp.id = {$loserIdCase}";
        $R_NAME = name_expr_ce($conexion,'cr');
        $A_NAME = name_expr_ce($conexion,'ca');
        $WIN_N  = name_expr_ce($conexion,'cg');
        $LOSE_N = name_expr_ce($conexion,'cp');
      } else {
        $joins[] = "LEFT JOIN competidores_evento cg ON cg.id = rc.ganador_id";
        $R_NAME = "NULL"; $A_NAME="NULL";
        $WIN_N  = name_expr_ce($conexion,'cg');
        $LOSE_N = "NULL";
      }
      $select[] = "{$R_NAME} AS r_name";
      $select[] = "{$A_NAME} AS a_name";
      $select[] = "{$WIN_N} AS ganador_name";
      $select[] = "{$LOSE_N} AS perdedor_name";

      $sql = "SELECT ".implode(', ',$select).$from.' '.implode(' ',$joins)
           . " WHERE rc.evento_id = ? ORDER BY IFNULL(rc.creado_en,'1000-01-01 00:00:00') DESC, rc.id DESC LIMIT 500";

      if ($st=safe_prepare($conexion,$sql,$err)){
        $st->bind_param('i',$evento_id_actual);
        if ($st->execute()){
          $rows=[]; $res=$st->get_result(); while($res && $row=$res->fetch_assoc()){ $rows[]=$row; } $st->close();
        } else {
          echo '<div class="bad">Fallo execute (historial): '.h($conexion->error).'</div>';
          $rows=[];
        }
      } else {
        echo '<div class="bad">No se pudo preparar el historial. '.h($err).'</div>';
        $rows=[];
      }

      if ($DEBUG && !empty($sql)){
        echo '<div class="ok" style="white-space:pre-wrap"><b>SQL Historial:</b> '.h($sql)."</div>";
      }
    ?>

    <?php if (empty($rows)): ?>
      <div class="bad">No hay combates registrados para tu evento en <code>resultados_combates</code>.</div>
    <?php else: ?>
      <div class="card">
        <table>
          <thead>
            <tr>
              <th class="nowrap">Fecha y Hora</th>
              <th class="nowrap">Pelea</th>
              <th>Rojo</th>
              <th>Azul</th>
              <th class="nowrap">Ganador</th>
              <th class="nowrap">Perdedor</th>
              <th>Detalle</th>
              <th class="nowrap">Ver</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r):
              $rnom = trim((string)($r['r_name'] ?? ''));
              $anom = trim((string)($r['a_name'] ?? ''));
              $gan  = trim((string)($r['ganador_name'] ?? ''));
              $per  = trim((string)($r['perdedor_name'] ?? ''));
              $g    = strtolower((string)($r['ganador_color'] ?? 'empate'));
              if ($g!=='rojo' && $g!=='azul'){ $gan='⚖️ Empate'; $per=''; }
            ?>
            <tr>
              <td class="nowrap"><?= h($r['creado_en'] ?? '') ?></td>
              <td class="nowrap">#<?= (int)$r['pelea_id'] ?></td>
              <td class="winR"><?= h($rnom) ?></td>
              <td class="winA"><?= h($anom) ?></td>
              <td class="nowrap"><?= h($gan) ?></td>
              <td class="nowrap"><?= h($per) ?></td>
              <td><?= h($r['detalle'] ?? '') ?><?= !empty($r['metodo']) ? ' — Método: '.h($r['metodo']) : '' ?></td>
              <td class="nowrap"><a class="btn btn-green" href="resultados_combates.php?id=<?= (int)$r['id'] ?>">👁 Ver</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
