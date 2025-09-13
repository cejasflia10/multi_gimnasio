<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }

/* =========================================================
   Modo A) POST: viene desde “combate en vivo” y guardamos
   Modo B) GET ?id=... : sólo mostramos el detalle guardado
   ========================================================= */
$modo_post = ($_SERVER['REQUEST_METHOD']==='POST');

/* =========================================================
   Lectura común: nombres de columnas variables en peleas_evento
   ========================================================= */
$cols = [];
if ($rs = $conexion->query("SHOW COLUMNS FROM `peleas_evento`")) {
  while($r=$rs->fetch_assoc()){ $cols[strtolower($r['Field'])]=$r['Field']; }
  $rs->close();
}
$C_ROJO = $cols['competidor_rojo_id'] ?? ($cols['rojo_id'] ?? ($cols['id_rojo'] ?? null));
$C_AZUL = $cols['competidor_azul_id'] ?? ($cols['azul_id'] ?? ($cols['id_azul'] ?? null));
$C_DUR  = $cols['duracion_segundos'] ?? ($cols['duracion_round'] ?? ($cols['round_seconds'] ?? null));
$C_DESC = $cols['descanso_segundos'] ?? ($cols['descanso_round'] ?? ($cols['rest_seconds'] ?? null));
$C_EVEN = $cols['evento_id'] ?? ($cols['id_evento'] ?? ($cols['evento'] ?? null));

/* =========================================================
   A) Guardado (POST)
   ========================================================= */
$combate_id = null;
$view = []; // datos para render

if ($modo_post) {
  /* ---- Entrada desde formulario ---- */
  $pelea_id   = toIntOrNull(post('pelea_id')) ?? 0;
  $evento_id  = toIntOrNull(post('evento_id')) ?? 0;

  $mayoria        = post('mayoria'); // 'rojo'|'azul'|'empate'|'' (según tarjetas)
  $votos_azul     = toIntOrNull(post('votos_azul')) ?? 0;
  $votos_rojo     = toIntOrNull(post('votos_rojo')) ?? 0;
  $votos_empate   = toIntOrNull(post('votos_empate')) ?? 0;
  $sum_total_azul = toIntOrNull(post('sum_total_azul')) ?? 0;
  $sum_total_rojo = toIntOrNull(post('sum_total_rojo')) ?? 0;

  $pts_total_azul = toIntOrNull(post('pts_total_azul')) ?? 0; // suma de puntos AZUL (todas las tarjetas/rounds)
  $pts_total_rojo = toIntOrNull(post('pts_total_rojo')) ?? 0; // suma de puntos ROJO
  $ganador_pts    = post('ganador_por_puntos');               // 'rojo'|'azul'|'empate'

  $cierre_forzado = (post('cierre_forzado') === '1');
  $update_ranking = (post('update_ranking') !== '0');

  if ($pelea_id<=0 || $evento_id<=0) {
    $_SESSION['flash_error']='Faltan pelea_id o evento_id.';
    // mostramos una vista mínima de error:
    $modo_post = false;
    $view['error'] = 'No se pudo registrar: faltan parámetros.';
  } else {
    /* ---- Traer IDs rojo/azul + tiempos de peleas_evento ---- */
    if (!$C_ROJO || !$C_AZUL) {
      $view['error'] = 'No se detectaron columnas de rojo/azul en peleas_evento.';
      $modo_post = false;
    } else {
      $sql = "SELECT ".
             ($C_EVEN? bt($C_EVEN)." AS evento_id,":"NULL AS evento_id,").
             bt($C_ROJO)." AS rojo_id, ".bt($C_AZUL)." AS azul_id, ".
             ($C_DUR? bt($C_DUR)." AS duracion_segundos,":"NULL AS duracion_segundos,").
             ($C_DESC? bt($C_DESC)." AS descanso_segundos":"NULL AS descanso_segundos").
             " FROM `peleas_evento` WHERE id=? LIMIT 1";
      $st = $conexion->prepare($sql);
      $st->bind_param('i',$pelea_id);
      $st->execute();
      $st->bind_result($X_evento,$rojo_id,$azul_id,$dur_seg,$desc_seg);
      $ok = $st->fetch();
      $st->close();

      if (!$ok) {
        $view['error'] = 'No se encontró la pelea para registrar resultados.';
        $modo_post = false;
      } else {
        $evento_id = (int)($X_evento ?: $evento_id);
        $dur_seg   = (int)($dur_seg ?: 180);
        $desc_seg  = (int)($desc_seg ?: 60);

        /* ---- Determinar ganador final ---- */
        // Priorizamos puntos totales (si hay), si no, mayoría de tarjetas:
        $ganador_final = 'empate';
        if ($ganador_pts==='rojo' || $mayoria==='rojo') $ganador_final = 'rojo';
        if ($ganador_pts==='azul' || $mayoria==='azul') $ganador_final = 'azul';

        /* ---- Detalle de resultado (human-readable) ---- */
        $detalle = "Puntos totales — Rojo {$pts_total_rojo} / Azul {$pts_total_azul}";
        if ($mayoria!=='') {
          $detalle .= " · Mayoría tarjetas: R{$votos_rojo}-A{$votos_azul}-E{$votos_empate} ".
                      "(Σ Rojo {$sum_total_rojo} / Azul {$sum_total_azul})";
        }
        if ($cierre_forzado) $detalle .= " · *Cierre anticipado*";

        /* ---- UPSERT en combates_evento ---- */
        $t = 'combates_evento';
        $colsC = []; $valsC = []; $typesC = '';
        $add = function($c,$v,$tp) use(&$colsC,&$valsC,&$typesC){ $colsC[]="`$c`"; $valsC[]=$v; $typesC.=$tp; };
        $has = function($c) use($conexion,$t){ return has_col($conexion,$t,$c); };

        if ($has('evento_id'))        $add('evento_id',$evento_id,'i');
        if ($has('pelea_id'))         $add('pelea_id',$pelea_id,'i');
        if ($has('rojo_id'))          $add('rojo_id',$rojo_id,'i');
        if ($has('azul_id'))          $add('azul_id',$azul_id,'i');
        if ($has('ganador'))          $add('ganador',$ganador_final,'s');
        if ($has('resultado'))        $add('resultado',$detalle,'s');
        if ($has('minutos_combate'))  $add('minutos_combate', round($dur_seg/60,2),'d');
        if ($has('minutos_descanso')) $add('minutos_descanso',round($desc_seg/60,2),'d');
        if ($has('pts_total_rojo'))   $add('pts_total_rojo',$pts_total_rojo,'i');
        if ($has('pts_total_azul'))   $add('pts_total_azul',$pts_total_azul,'i');
        if ($has('votos_rojo'))       $add('votos_rojo',$votos_rojo,'i');
        if ($has('votos_azul'))       $add('votos_azul',$votos_azul,'i');
        if ($has('votos_empate'))     $add('votos_empate',$votos_empate,'i');
        if ($has('mayoria'))          $add('mayoria',$mayoria,'s');
        if ($has('fecha'))            $add('fecha', date('Y-m-d H:i:s'),'s');

        // ¿Existe ya por pelea_id?
        $existe_id = null;
        if ($has('pelea_id')) {
          $chk = $conexion->prepare("SELECT id FROM `$t` WHERE pelea_id=? LIMIT 1");
          $chk->bind_param('i',$pelea_id); $chk->execute();
          $r = $chk->get_result();
          if ($r && $r->num_rows) { $existe_id = (int)$r->fetch_assoc()['id']; }
          $chk->close();
        }

        if ($existe_id) {
          $set=[]; foreach($colsC as $c){ $set[]="$c=?"; }
          $sqlU = "UPDATE `$t` SET ".implode(',', $set)." WHERE id=?";
          $stU  = $conexion->prepare($sqlU);
          $types2=$typesC.'i'; $vals2=$valsC; $vals2[]=$existe_id;
          $bind = [$types2]; foreach($vals2 as $k=>$v){ $bind[]=&$vals2[$k]; }
          call_user_func_array([$stU,'bind_param'],$bind);
          $stU->execute(); $stU->close();
          $combate_id = $existe_id;
        } else {
          $ph = rtrim(str_repeat('?,', count($colsC)), ',');
          $sqlI = "INSERT INTO `$t` (".implode(',', $colsC).") VALUES ($ph)";
          $stI  = $conexion->prepare($sqlI);
          $bind = [$typesC]; foreach($valsC as $k=>$v){ $bind[]=&$valsC[$k]; }
          call_user_func_array([$stI,'bind_param'],$bind);
          $stI->execute(); $stI->close();
          $combate_id = (int)$conexion->insert_id;
        }

        /* ---- Actualizar ranking competidores_evento ---- */
        if ($update_ranking) {
          $tC = 'competidores_evento';
          $hasW = has_col($conexion,$tC,'wins');
          $hasL = has_col($conexion,$tC,'losses');
          $hasD = has_col($conexion,$tC,'draws');
          if ($hasW && $hasL && $hasD) {
            if ($ganador_final === 'rojo') {
              $conexion->query("UPDATE `$tC` SET wins = wins + 1 WHERE id=".(int)$rojo_id);
              $conexion->query("UPDATE `$tC` SET losses = losses + 1 WHERE id=".(int)$azul_id);
            } elseif ($ganador_final === 'azul') {
              $conexion->query("UPDATE `$tC` SET wins = wins + 1 WHERE id=".(int)$azul_id);
              $conexion->query("UPDATE `$tC` SET losses = losses + 1 WHERE id=".(int)$rojo_id);
            } else {
              $conexion->query("UPDATE `$tC` SET draws = draws + 1 WHERE id=".(int)$rojo_id);
              $conexion->query("UPDATE `$tC` SET draws = draws + 1 WHERE id=".(int)$azul_id);
            }
          }
        }

        /* ---- Preparar datos para la vista (ganador/perdedor) ---- */
        $view['evento_id']      = $evento_id;
        $view['pelea_id']       = $pelea_id;
        $view['combate_id']     = $combate_id;
        $view['rojo_id']        = $rojo_id;
        $view['azul_id']        = $azul_id;
        $view['ganador_final']  = $ganador_final;
        $view['pts_rojo']       = $pts_total_rojo;
        $view['pts_azul']       = $pts_total_azul;
        $view['mayoria']        = $mayoria;
        $view['votos_rojo']     = $votos_rojo;
        $view['votos_azul']     = $votos_azul;
        $view['votos_empate']   = $votos_empate;
        $view['sum_rojo']       = $sum_total_rojo;
        $view['sum_azul']       = $sum_total_azul;
        $view['cierre_forzado'] = $cierre_forzado;

        // Traer nombres de competidores
        $qN = $conexion->prepare("SELECT id, apellido, nombre FROM competidores_evento WHERE id IN (?,?)");
        $qN->bind_param('ii',$rojo_id,$azul_id);
        $qN->execute();
        $resN=$qN->get_result();
        $noms = [];
        while($resN && $row=$resN->fetch_assoc()){ $noms[(int)$row['id']] = trim(($row['apellido']??'').' '.($row['nombre']??'')); }
        $qN->close();
        $view['rojo_name'] = $noms[$rojo_id] ?? ('#'.$rojo_id);
        $view['azul_name'] = $noms[$azul_id] ?? ('#'.$azul_id);
      }
    }
  }
}

/* =========================================================
   B) Vista por GET ?id=... (ver detalle guardado)
   ========================================================= */
if (!$modo_post && empty($view['error'])) {
  $combate_id = toIntOrNull($_GET['id'] ?? '') ?? null;
  if ($combate_id) {
    $sql = "
      SELECT ce.*, 
             cr.apellido AS r_apellido, cr.nombre AS r_nombre,
             ca.apellido AS a_apellido, ca.nombre AS a_nombre
      FROM combates_evento ce
      LEFT JOIN competidores_evento cr ON ce.rojo_id = cr.id
      LEFT JOIN competidores_evento ca ON ce.azul_id = ca.id
      WHERE ce.id = ? LIMIT 1";
    $st = $conexion->prepare($sql);
    $st->bind_param('i',$combate_id);
    $st->execute();
    $r = $st->get_result(); $row = $r ? $r->fetch_assoc() : null;
    $st->close();

    if ($row) {
      $view['evento_id']     = (int)($row['evento_id'] ?? 0);
      $view['pelea_id']      = (int)($row['pelea_id'] ?? 0);
      $view['combate_id']    = (int)$combate_id;
      $view['rojo_id']       = (int)($row['rojo_id'] ?? 0);
      $view['azul_id']       = (int)($row['azul_id'] ?? 0);
      $view['rojo_name']     = trim(($row['r_apellido']??'').' '.($row['r_nombre']??''));
      $view['azul_name']     = trim(($row['a_apellido']??'').' '.($row['a_nombre']??''));
      $view['ganador_final'] = (string)($row['ganador'] ?? 'empate');
      $view['detalle']       = (string)($row['resultado'] ?? '');
      $view['pts_rojo']      = (int)($row['pts_total_rojo'] ?? 0);
      $view['pts_azul']      = (int)($row['pts_total_azul'] ?? 0);
      $view['fecha']         = (string)($row['fecha'] ?? '');
    } else {
      $view['error'] = 'No se encontró el combate solicitado.';
    }
  } else {
    // Nada que mostrar
    $view['error'] = 'No se indicó un id de combate.';
  }
}

/* =========================================================
   Render
   ========================================================= */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Resultados del Combate<?= isset($view['combate_id']) ? ' #'.(int)$view['combate_id'] : '' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{background:#0c0c0c;color:#fff;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:900px;margin:0 auto;padding:16px}
    .card{background:#121212;border:1px solid #2a2a2a;border-radius:14px;padding:14px;margin-top:12px}
    h2{margin:.2rem 0 .8rem}
    .row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
    .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#1f1f1f;font-size:12px}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:0;text-decoration:none;cursor:pointer}
    .btn-gold{background:#d4af37;color:#111}
    .btn-gray{background:#2a2a2a;color:#fff}
    .btn-green{background:#00c853;color:#111}
    .winR{color:#ff6b6b;font-weight:800}
    .winA{color:#6bb6ff;font-weight:800}
    .draw{color:#ffd54f;font-weight:800}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    th,td{border:1px solid #2b2b2b;padding:8px 10px;text-align:left}
    th{background:#171717}
    .ok{padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1;margin-top:10px}
    .bad{padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4;margin-top:10px}
  </style>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>

<div class="wrap">
  <h2>🥊 Resultados del Combate</h2>

  <?php if (!empty($view['error'])): ?>
    <div class="bad"><?= h($view['error']) ?></div>
  <?php else: ?>

    <div class="card">
      <div class="row" style="justify-content:space-between">
        <div>
          <div class="badge">Evento #<?= (int)($view['evento_id'] ?? 0) ?></div>
          <?php if (!empty($view['pelea_id'])): ?>
            <div class="badge" style="margin-left:6px">Pelea #<?= (int)$view['pelea_id'] ?></div>
          <?php endif; ?>
          <?php if (!empty($view['combate_id'])): ?>
            <div class="badge" style="margin-left:6px">Combate #<?= (int)$view['combate_id'] ?></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($view['fecha'])): ?>
          <div class="badge">Fecha: <?= h($view['fecha']) ?></div>
        <?php endif; ?>
      </div>

      <table>
        <tbody>
          <tr>
            <th style="width:40%">Rincón Rojo</th>
            <td class="winR"><?= h($view['rojo_name'] ?? '#'.$view['rojo_id']) ?></td>
          </tr>
          <tr>
            <th>Rincón Azul</th>
            <td class="winA"><?= h($view['azul_name'] ?? '#'.$view['azul_id']) ?></td>
          </tr>
          <tr>
            <th>Ganador</th>
            <td>
              <?php
                $g = $view['ganador_final'] ?? 'empate';
                if ($g==='rojo')   echo '<span class="winR">🔴 ROJO</span>';
                elseif ($g==='azul') echo '<span class="winA">🔵 AZUL</span>';
                else                 echo '<span class="draw">⚖️ EMPATE</span>';
              ?>
            </td>
          </tr>
          <tr>
            <th>Totales por puntos</th>
            <td>Rojo <?= (int)($view['pts_rojo'] ?? 0) ?> · Azul <?= (int)($view['pts_azul'] ?? 0) ?></td>
          </tr>
          <?php if (!empty($view['mayoria'])): ?>
          <tr>
            <th>Mayoría tarjetas</th>
            <td>
              <?= 'R'.$view['votos_rojo'].' - A'.$view['votos_azul'].' - E'.$view['votos_empate'] ?>
              <?php if (isset($view['sum_rojo']) || isset($view['sum_azul'])): ?>
                <span class="badge" style="margin-left:6px">Σ Rojo <?= (int)($view['sum_rojo'] ?? 0) ?> / Azul <?= (int)($view['sum_azul'] ?? 0) ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          <?php if (!empty($view['cierre_forzado'])): ?>
          <tr>
            <th>Observación</th>
            <td>Se registró <i>cierre anticipado</i> del combate.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (!empty($view['detalle'])): ?>
        <div class="ok"><b>Detalle:</b> <?= h($view['detalle']) ?></div>
      <?php elseif ($modo_post): ?>
        <div class="ok">✅ Resultado registrado correctamente.</div>
      <?php endif; ?>

      <div class="row" style="margin-top:12px">
        <?php if (!empty($view['evento_id'])): ?>
          <a class="btn btn-gray" href="ver_peleas_evento.php?evento_id=<?= (int)$view['evento_id'] ?>">↩️ Volver al evento</a>
          <a class="btn btn-gold" href="ranking_competidores.php?evento_id=<?= (int)$view['evento_id'] ?>">📈 Ver ranking actualizado</a>
        <?php endif; ?>
        <?php if (!empty($view['combate_id'])): ?>
          <a class="btn btn-green" href="ver_combate.php?id=<?= (int)$view['combate_id'] ?>">👁 Ver ficha del combate</a>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>
</div>
</body>
</html>
