<?php
// puntuar_pelea.php — puntos manuales + conteos + advertencias por round
if (session_status() === PHP_SESSION_NONE) session_start();

/* BYPASS para guards de asignación */
$_SESSION['__JUEZ_MODE__']        = 1;
$_SESSION['__ALLOW_UNASSIGNED__'] = 1;
$_SESSION['__BYPASS_ASIG__']      = 1;
if (!defined('BYPASS_ASIGNACION')) define('BYPASS_ASIGNACION', true);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* ===== Contexto ===== */
$juez_id  = (int)($_SESSION['juez_id'] ?? 0);
$pelea_id = isset($_GET['pelea_id']) && ctype_digit($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($juez_id<=0){ header('Location: login_juez.php?err='.urlencode('Iniciá sesión.')); exit; }
if ($pelea_id<=0){ exit('❌ Falta pelea_id.'); }

/* ===== Nombres de rincones desde la pelea (texto o JOIN por IDs) ===== */
$azul='AZUL'; $rojo='ROJO';
$has_pe = $conexion->query("SHOW TABLES LIKE 'peleas_evento'");
if ($has_pe && $has_pe->num_rows) {
  // columnas de texto
  $nameA = has_col($conexion,'peleas_evento','azul_nombre') ? 'azul_nombre' : (has_col($conexion,'peleas_evento','competidor_a') ? 'competidor_a' : null);
  $nameR = has_col($conexion,'peleas_evento','rojo_nombre') ? 'rojo_nombre' : (has_col($conexion,'peleas_evento','competidor_b') ? 'competidor_b' : null);
  if ($nameA || $nameR) {
    $sql="SELECT ".($nameA?"$nameA AS a":"NULL AS a").", ".($nameR?"$nameR AS r":"NULL AS r")." FROM peleas_evento WHERE id=? LIMIT 1";
    if ($st=$conexion->prepare($sql)){ $st->bind_param('i',$pelea_id); $st->execute(); if($res=$st->get_result()->fetch_assoc()){ $azul=trim($res['a']?:$azul); $rojo=trim($res['r']?:$rojo);} $st->close(); }
  }
  // si faltan, intentar por IDs
  if ($azul==='AZUL' || $rojo==='ROJO') {
    $idA=null; $idR=null;
    foreach(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'] as $c){ if (has_col($conexion,'peleas_evento',$c)) { $idA=$c; break; } }
    foreach(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'] as $c){ if (has_col($conexion,'peleas_evento',$c)) { $idR=$c; break; } }
    if (($idA||$idR) && ($conexion->query("SHOW TABLES LIKE 'competidores_evento'")?->num_rows)) {
      $sql="SELECT ".
           ($idA?"TRIM(CONCAT(COALESCE(ca.apellido,''),' ',COALESCE(ca.nombre,''))) AS a":"NULL AS a").",".
           ($idR?"TRIM(CONCAT(COALESCE(cr.apellido,''),' ',COALESCE(cr.nombre,''))) AS r":"NULL AS r")."
           FROM peleas_evento p ".
           ($idA?"LEFT JOIN competidores_evento ca ON p.`$idA`=ca.id ":"").
           ($idR?"LEFT JOIN competidores_evento cr ON p.`$idR`=cr.id ":"").
           "WHERE p.id=? LIMIT 1";
      if ($st=$conexion->prepare($sql)){ $st->bind_param('i',$pelea_id); $st->execute(); if($res=$st->get_result()->fetch_assoc()){ if(!empty($res['a']))$azul=trim($res['a']); if(!empty($res['r']))$rojo=trim($res['r']); } $st->close(); }
    }
  }
}

/* ===== Tablas de almacenamiento ===== */
$conexion->query("CREATE TABLE IF NOT EXISTS `puntuaciones_jueces` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pelea_id` INT NOT NULL,
  `juez_id` INT NOT NULL,
  `round` INT NOT NULL,
  `azul_puntos` INT NOT NULL,
  `rojo_puntos` INT NOT NULL,
  `azul_conteo` TINYINT(1) NOT NULL DEFAULT 0,
  `rojo_conteo` TINYINT(1) NOT NULL DEFAULT 0,
  `azul_advertencia` TINYINT(1) NOT NULL DEFAULT 0,
  `rojo_advertencia` TINYINT(1) NOT NULL DEFAULT 0,
  `observaciones` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_pelea_juez_round` (`pelea_id`,`juez_id`,`round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Migración suave si la tabla ya existía sin las columnas nuevas */
$need = [
  'azul_conteo'      => "ALTER TABLE `puntuaciones_jueces` ADD COLUMN `azul_conteo` TINYINT(1) NOT NULL DEFAULT 0",
  'rojo_conteo'      => "ALTER TABLE `puntuaciones_jueces` ADD COLUMN `rojo_conteo` TINYINT(1) NOT NULL DEFAULT 0",
  'azul_advertencia' => "ALTER TABLE `puntuaciones_jueces` ADD COLUMN `azul_advertencia` TINYINT(1) NOT NULL DEFAULT 0",
  'rojo_advertencia' => "ALTER TABLE `puntuaciones_jueces` ADD COLUMN `rojo_advertencia` TINYINT(1) NOT NULL DEFAULT 0",
];
foreach ($need as $c => $alter) { if (!has_col($conexion,'puntuaciones_jueces',$c)) { @$conexion->query($alter); } }

$conexion->query("CREATE TABLE IF NOT EXISTS `resultados_jueces` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pelea_id` INT NOT NULL,
  `juez_id` INT NOT NULL,
  `total_azul` INT NOT NULL,
  `total_rojo` INT NOT NULL,
  `ganador` ENUM('azul','rojo','empate') NOT NULL,
  `observaciones` TEXT NULL,
  `detalle_checksum` CHAR(64) DEFAULT NULL,
  `enviado_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` ENUM('enviado','retractado') NOT NULL DEFAULT 'enviado',
  UNIQUE KEY `uq_pelea_juez` (`pelea_id`,`juez_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ===== Guardado / Envío ===== */
$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = $_POST['__accion__'] ?? '';

  if ($accion==='guardar_round') {
    $round = (int)($_POST['round'] ?? 0);
    $azPts = (int)($_POST['azul_puntos'] ?? 0);
    $roPts = (int)($_POST['rojo_puntos'] ?? 0);
    $azKD  = !empty($_POST['azul_conteo']) ? 1 : 0;
    $roKD  = !empty($_POST['rojo_conteo']) ? 1 : 0;
    $azAdv = !empty($_POST['azul_advertencia']) ? 1 : 0;
    $roAdv = !empty($_POST['rojo_advertencia']) ? 1 : 0;
    $obs   = trim((string)($_POST['observaciones'] ?? ''));

    if ($round<=0) {
      $err='Round inválido.';
    } else {
      $sql="INSERT INTO `puntuaciones_jueces`
              (pelea_id,juez_id,`round`,azul_puntos,rojo_puntos,azul_conteo,rojo_conteo,azul_advertencia,rojo_advertencia,observaciones)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              azul_puntos=VALUES(azul_puntos),
              rojo_puntos=VALUES(rojo_puntos),
              azul_conteo=VALUES(azul_conteo),
              rojo_conteo=VALUES(rojo_conteo),
              azul_advertencia=VALUES(azul_advertencia),
              rojo_advertencia=VALUES(rojo_advertencia),
              observaciones=VALUES(observaciones)";
      if ($st=$conexion->prepare($sql)) {
        $st->bind_param('iiiiiiiiis',$pelea_id,$juez_id,$round,$azPts,$roPts,$azKD,$roKD,$azAdv,$roAdv,$obs);
        if ($st->execute()) $msg='Round guardado.'; else $err='No se pudo guardar el round.';
        $st->close();
      } else { $err='Error interno (prep).'; }
    }
  }

  if ($accion==='enviar_resultado') {
    $totA=0; $totR=0; $det=[];
    if ($st=$conexion->prepare("SELECT `round`,azul_puntos,rojo_puntos FROM `puntuaciones_jueces` WHERE pelea_id=? AND juez_id=? ORDER BY `round`")){
      $st->bind_param('ii',$pelea_id,$juez_id); $st->execute();
      if ($r=$st->get_result()) while($row=$r->fetch_assoc()){ $det[]=$row; $totA+=(int)$row['azul_puntos']; $totR+=(int)$row['rojo_puntos']; }
      $st->close();
    }
    if (!$det){ $err='Cargá al menos un round antes de enviar el resultado.'; }
    else {
      $gan = $totA>$totR?'azul':($totR>$totA?'rojo':'empate');
      $checksum = hash('sha256', json_encode($det,JSON_UNESCAPED_UNICODE));
      $obs = trim((string)($_POST['observaciones_final'] ?? ''));
      $sql="INSERT INTO `resultados_jueces` (pelea_id,juez_id,total_azul,total_rojo,ganador,observaciones,detalle_checksum)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE total_azul=VALUES(total_azul), total_rojo=VALUES(total_rojo),
                                    ganador=VALUES(ganador), observaciones=VALUES(observaciones),
                                    detalle_checksum=VALUES(detalle_checksum), enviado_at=CURRENT_TIMESTAMP, estado='enviado'";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param('iiiisss',$pelea_id,$juez_id,$totA,$totR,$gan,$obs,$checksum);
        if ($st->execute()) $msg='Resultado enviado al organizador.'; else $err='No se pudo enviar el resultado.';
        $st->close();
      } else { $err='Error interno (prep envío).'; }
    }
  }
}

/* ===== Rondas ya cargadas ===== */
$puntajes=[]; $totalAz=0; $totalRo=0;
$qSel = "SELECT `round`,azul_puntos,rojo_puntos,azul_conteo,rojo_conteo,azul_advertencia,rojo_advertencia,observaciones,updated_at
         FROM `puntuaciones_jueces` WHERE pelea_id=? AND juez_id=? ORDER BY `round` ASC";
if ($st=$conexion->prepare($qSel)){
  $st->bind_param('ii',$pelea_id,$juez_id); $st->execute();
  if ($r=$st->get_result()){
    $puntajes=$r->fetch_all(MYSQLI_ASSOC);
    foreach($puntajes as $pu){ $totalAz+=(int)$pu['azul_puntos']; $totalRo+=(int)$pu['rojo_puntos']; }
  }
  $st->close();
}
$next_round = $puntajes ? ((int)end($puntajes)['round']+1) : 1;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Puntuar pelea #<?= (int)$pelea_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:760px;margin:6vh auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:16px}
    .muted{color:#9ecbff}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    input,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
    .row{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer}
    .btn.gray{background:#1b2836;border-color:#2b3c4f}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left;font-size:14px}
    th{color:#9ecbff}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
    .tag{display:inline-block;padding:2px 6px;border-radius:8px;background:#1b2836;border:1px solid #2b3c4f;font-size:12px;margin-left:6px}
  </style>
  <script>
    function qs(n){return document.querySelector(n);}
    function setScore(z,r){qs('input[name=azul_puntos]').value=z; qs('input[name=rojo_puntos]').value=r;}
  </script>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2 style="margin:0 0 8px 0">🧑‍⚖️ Puntuar — Pelea #<?= (int)$pelea_id ?> · <?= h($azul) ?> (Azul) vs <?= h($rojo) ?> (Rojo)</h2>
      <?php if (!empty($msg)): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if (!empty($err)): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="__accion__" value="guardar_round">
        <div class="grid">
          <div>
            <label>Round</label>
            <input type="number" name="round" min="1" step="1" required value="<?= (int)$next_round ?>">
          </div>
          <div>
            <label><?= h($azul) ?> (Azul)</label>
            <input type="number" name="azul_puntos" min="0" step="1" required>
          </div>
          <div>
            <label><?= h($rojo) ?> (Rojo)</label>
            <input type="number" name="rojo_puntos" min="0" step="1" required>
          </div>
        </div>

        <!-- Atajos de puntaje -->
        <div class="row" style="margin-top:8px">
          <button type="button" class="btn" onclick="setScore(10,9)">10–9 Azul</button>
          <button type="button" class="btn" onclick="setScore(9,10)">9–10 Rojo</button>
          <button type="button" class="btn" onclick="setScore(10,8)">10–8 Azul</button>
          <button type="button" class="btn" onclick="setScore(8,10)">8–10 Rojo</button>
          <button type="button" class="btn gray" onclick="setScore(10,10)">10–10</button>
        </div>

        <!-- Señaladores del árbitro -->
        <div class="row">
          <label><input type="checkbox" name="azul_conteo" value="1"> 🛎️ Conteo a <b>Azul</b></label>
          <label><input type="checkbox" name="rojo_conteo" value="1"> 🛎️ Conteo a <b>Rojo</b></label>
          <label><input type="checkbox" name="azul_advertencia" value="1"> ⚠️ Advertencia a <b>Azul</b></label>
          <label><input type="checkbox" name="rojo_advertencia" value="1"> ⚠️ Advertencia a <b>Rojo</b></label>
        </div>

        <label style="margin-top:8px">Observaciones (opcional)</label>
        <textarea name="observaciones" rows="2" placeholder="Advertencias verbales, penalidades, cortes, etc."></textarea>

        <div class="row" style="margin-top:10px">
          <button class="btn" type="submit">Guardar round</button>
          <a class="btn gray" href="ver_peleas_evento.php?modo=juez">Volver al listado</a>
          <a class="btn gray" href="combate_en_vivo.php?pelea_id=<?= (int)$pelea_id ?>&modo=juez">Ver en vivo</a>
        </div>
        <div class="muted" style="margin-top:6px">Los indicadores “🛎️/⚠️” se guardan junto al round. Los puntos siguen siendo manuales.</div>
      </form>
    </div>

    <div class="card" style="margin-top:16px">
      <h3 style="margin:0 0 8px 0">📋 Rondas cargadas — Total Azul <?= (int)$totalAz ?> • Total Rojo <?= (int)$totalRo ?></h3>
      <?php if (!$puntajes): ?>
        <div class="muted">Aún no cargaste rondas.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Round</th>
              <th>Azul (<?= h($azul) ?>)</th>
              <th>Rojo (<?= h($rojo) ?>)</th>
              <th>Observaciones</th>
              <th>Actualizado</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($puntajes as $pu): ?>
            <tr>
              <td><?= (int)$pu['round'] ?></td>
              <td>
                <?= (int)$pu['azul_puntos'] ?>
                <?php if (!empty($pu['azul_conteo'])): ?><span class="tag">🛎️ Conteo</span><?php endif; ?>
                <?php if (!empty($pu['azul_advertencia'])): ?><span class="tag">⚠️ Adv.</span><?php endif; ?>
              </td>
              <td>
                <?= (int)$pu['rojo_puntos'] ?>
                <?php if (!empty($pu['rojo_conteo'])): ?><span class="tag">🛎️ Conteo</span><?php endif; ?>
                <?php if (!empty($pu['rojo_advertencia'])): ?><span class="tag">⚠️ Adv.</span><?php endif; ?>
              </td>
              <td><?= h((string)$pu['observaciones']) ?></td>
              <td><?= h((string)$pu['updated_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <form method="post" action="" style="margin-top:10px" onsubmit="return confirm('Se enviará SOLO el RESULTADO (totales/ganador) al organizador. ¿Confirmar?')">
        <input type="hidden" name="__accion__" value="enviar_resultado">
        <label>Observaciones al organizador (opcional)</label>
        <textarea name="observaciones_final" rows="2"></textarea>
        <div style="margin-top:8px">
          <button class="btn" type="submit">Enviar RESULTADO</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
