<?php
// puntuar_pelea.php — puntos manuales + conteos/advertencias por round + método de resultado
if (session_status() === PHP_SESSION_NONE) session_start();

/* BYPASS para guards de asignación (dejado igual que tu versión) */
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

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function only_digits($v): bool { return is_string($v) && $v!=='' && preg_match('/^\d+$/', $v); }
function int_from_post(string $key, int $min=0, ?int $max=null): array {
  $raw = $_POST[$key] ?? '';
  if (!only_digits((string)$raw)) return [null, "Campo $key inválido (solo números enteros)."];
  $n = (int)$raw;
  if ($n < $min) return [null, "Campo $key debe ser ≥ $min."];
  if ($max !== null && $n > $max) return [null, "Campo $key debe ser ≤ $max."];
  return [$n, null];
}

/* ===== Contexto ===== */
$juez_id  = (int)($_SESSION['juez_id'] ?? 0);
$pelea_id = isset($_GET['pelea_id']) && ctype_digit($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($juez_id<=0){ header('Location: login_juez.php?err='.urlencode('Iniciá sesión.')); exit; }
if ($pelea_id<=0){ exit('❌ Falta pelea_id.'); }

/* ===== Nombres de rincones y (opcional) evento_id ===== */
$azul='AZUL'; $rojo='ROJO'; $evento_id = 0;
$has_pe = $conexion->query("SHOW TABLES LIKE 'peleas_evento'");
if ($has_pe && $has_pe->num_rows) {
  $nameA = has_col($conexion,'peleas_evento','azul_nombre') ? 'azul_nombre' : (has_col($conexion,'peleas_evento','competidor_a') ? 'competidor_a' : null);
  $nameR = has_col($conexion,'peleas_evento','rojo_nombre') ? 'rojo_nombre' : (has_col($conexion,'peleas_evento','competidor_b') ? 'competidor_b' : null);
  if ($nameA || $nameR) {
    $sql="SELECT ".($nameA?"$nameA AS a":"NULL AS a").", ".($nameR?"$nameR AS r":"NULL AS r")." FROM peleas_evento WHERE id=? LIMIT 1";
    if ($st=$conexion->prepare($sql)){ $st->bind_param('i',$pelea_id); $st->execute(); if($res=$st->get_result()->fetch_assoc()){ $azul=trim($res['a']?:$azul); $rojo=trim($res['r']?:$rojo);} $st->close(); }
  }
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
  foreach (['evento_id','event_id','id_evento'] as $c) { if (has_col($conexion,'peleas_evento',$c)) { $evCol=$c; break; } }
  if (!empty($evCol)) {
    if ($st=$conexion->prepare("SELECT `$evCol` AS eid FROM peleas_evento WHERE id=? LIMIT 1")){
      $st->bind_param('i',$pelea_id); $st->execute();
      if ($r=$st->get_result()) { $row=$r->fetch_assoc(); $evento_id = (int)($row['eid'] ?? 0); }
      $st->close();
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
  `azul_conteos` INT NOT NULL DEFAULT 0,
  `rojo_conteos` INT NOT NULL DEFAULT 0,
  `azul_advertencias` INT NOT NULL DEFAULT 0,
  `rojo_advertencias` INT NOT NULL DEFAULT 0,
  `observaciones` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_pelea_juez_round` (`pelea_id`,`juez_id`,`round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$oldCols = [
  'azul_conteo'      => 'azul_conteos',
  'rojo_conteo'      => 'rojo_conteos',
  'azul_advertencia' => 'azul_advertencias',
  'rojo_advertencia' => 'rojo_advertencias',
];
foreach ($oldCols as $old => $new) {
  if (!has_col($conexion,'puntuaciones_jueces',$new)) {
    @$conexion->query("ALTER TABLE `puntuaciones_jueces` ADD COLUMN `$new` INT NOT NULL DEFAULT 0");
  }
  if (has_col($conexion,'puntuaciones_jueces',$old)) {
    @$conexion->query("UPDATE `puntuaciones_jueces` SET `$new` = IFNULL(`$old`,0) WHERE `$new` = 0");
  }
}

$conexion->query("CREATE TABLE IF NOT EXISTS `resultados_jueces` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pelea_id` INT NOT NULL,
  `juez_id` INT NOT NULL,
  `total_azul` INT NOT NULL,
  `total_rojo` INT NOT NULL,
  `ganador` ENUM('azul','rojo','empate') NOT NULL,
  `metodo` ENUM('PTS','KO','KOT','RSC','SURRENDER','IRC','ABANDONO','EMPATE') NOT NULL DEFAULT 'PTS',
  `observaciones` TEXT NULL,
  `detalle_checksum` CHAR(64) DEFAULT NULL,
  `enviado_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` ENUM('enviado','retractado') NOT NULL DEFAULT 'enviado',
  UNIQUE KEY `uq_pelea_juez` (`pelea_id`,`juez_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if (!has_col($conexion,'resultados_jueces','metodo')) {
  @$conexion->query("ALTER TABLE `resultados_jueces` ADD COLUMN `metodo`
    ENUM('PTS','KO','KOT','RSC','SURRENDER','IRC','ABANDONO','EMPATE') NOT NULL DEFAULT 'PTS' AFTER `ganador`");
}

/* ===== Guardado / Envío ===== */
$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = $_POST['__accion__'] ?? '';

  if ($accion==='guardar_round') {
    [$round, $e] = int_from_post('round', 1, 50); if ($e) $err=$e;
    [$azPts, $e2] = int_from_post('azul_puntos', 0, 100); if (!$err && $e2) $err=$e2;
    [$roPts, $e3] = int_from_post('rojo_puntos', 0, 100); if (!$err && $e3) $err=$e3;
    [$azKD,  $e4] = int_from_post('azul_conteos', 0, 20); if (!$err && $e4) $err=$e4;
    [$roKD,  $e5] = int_from_post('rojo_conteos', 0, 20); if (!$err && $e5) $err=$e5;
    [$azAdv, $e6] = int_from_post('azul_advertencias', 0, 20); if (!$err && $e6) $err=$e6;
    [$roAdv, $e7] = int_from_post('rojo_advertencias', 0, 20); if (!$err && $e7) $err=$e7;
    $obs = trim((string)($_POST['observaciones'] ?? ''));

    if (!$err) {
      $sql="INSERT INTO `puntuaciones_jueces`
              (pelea_id,juez_id,`round`,azul_puntos,rojo_puntos,azul_conteos,rojo_conteos,azul_advertencias,rojo_advertencias,observaciones)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              azul_puntos=VALUES(azul_puntos),
              rojo_puntos=VALUES(rojo_puntos),
              azul_conteos=VALUES(azul_conteos),
              rojo_conteos=VALUES(rojo_conteos),
              azul_advertencias=VALUES(azul_advertencias),
              rojo_advertencias=VALUES(rojo_advertencias),
              observaciones=VALUES(observaciones)";
      if ($st=$conexion->prepare($sql)) {
        $st->bind_param('iiiiiiiiss',$pelea_id,$juez_id,$round,$azPts,$roPts,$azKD,$roKD,$azAdv,$roAdv,$obs);
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
      $metodos_validos = ['PTS','KO','KOT','RSC','SURRENDER','IRC','ABANDONO','EMPATE'];
      $metodo = strtoupper(trim((string)($_POST['metodo'] ?? 'PTS')));
      if (!in_array($metodo, $metodos_validos, true)) $metodo = 'PTS';
      $gm = $_POST['ganador_manual'] ?? '';
      $gm = in_array($gm, ['azul','rojo','empate'], true) ? $gm : '';
      if ($metodo === 'EMPATE') $gan = 'empate';
      else $gan = $gm ?: ($totA>$totR?'azul':($totR>$totA?'rojo':'empate'));

      $checksum = hash('sha256', json_encode($det,JSON_UNESCAPED_UNICODE));
      $obs = trim((string)($_POST['observaciones_final'] ?? ''));

      $sql="INSERT INTO `resultados_jueces` (pelea_id,juez_id,total_azul,total_rojo,ganador,metodo,observaciones,detalle_checksum)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE total_azul=VALUES(total_azul), total_rojo=VALUES(total_rojo),
                                    ganador=VALUES(ganador), metodo=VALUES(metodo),
                                    observaciones=VALUES(observaciones),
                                    detalle_checksum=VALUES(detalle_checksum), enviado_at=CURRENT_TIMESTAMP, estado='enviado'";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param('iiiissss',$pelea_id,$juez_id,$totA,$totR,$gan,$metodo,$obs,$checksum);
        if ($st->execute()) $msg='Resultado enviado al organizador.'; else $err='No se pudo enviar el resultado.';
        $st->close();
      } else { $err='Error interno (prep envío).'; }
    }
  }
}

/* ===== Rondas ya cargadas ===== */
$puntajes=[]; $totalAz=0; $totalRo=0;
$qSel = "SELECT `round`,azul_puntos,rojo_puntos,azul_conteos,rojo_conteos,azul_advertencias,rojo_advertencias,observaciones,updated_at
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
    :root{
      --bg:#0b1115; --card:#0f1720; --border:#1f2a33; --txt:#e6eef4; --muted:#9ecbff;
      --btn:#0e7ad1; --btn2:#1b2836; --btn2b:#2b3c4f; --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1;
      --badbg:#2a1414; --badbd:#5e2626; --badt:#ffb4b4; --sub:#bcd8ff;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:920px;margin:4vh auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px}
    .muted{color:var(--muted)}
    .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .grid2{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
    .row{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}
    input,textarea,select{width:100%;padding:12px;border-radius:10px;border:1px solid #263341;background:#111a24;color:var(--txt);font-size:16px}
    label{display:block;margin:4px 0 6px 0;font-size:14px;color:#cfe7ff}
    .btn{padding:12px 16px;border-radius:10px;border:1px solid #27455c;background:var(--btn);color:#fff;cursor:pointer;font-weight:600}
    .btn.gray{background:var(--btn2);border-color:var(--btn2b)}
    table{width:100%;border-collapse:collapse;margin-top:12px;font-size:14px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left}
    th{color:var(--muted)}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}
    .subtle{color:var(--sub);font-size:12px}
    .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--border);border-radius:12px}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:12px}
    @media (max-width: 720px){
      .wrap{padding:10px}
      .grid3{grid-template-columns:1fr}
      .grid2{grid-template-columns:1fr}
      .row .btn{flex:1 1 100%}
      table{font-size:13px}
      input,textarea,select{font-size:16px}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2 style="margin:0 0 8px 0">🧑‍⚖️ Puntuar — Pelea #<?= (int)$pelea_id ?> · <?= h($azul) ?> (Azul) vs <?= h($rojo) ?> (Rojo)</h2>
      <?php if (!empty($msg)): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if (!empty($err)): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

      <form method="post" action="" id="frmRound" novalidate>
        <input type="hidden" name="__accion__" value="guardar_round">

        <!-- Puntos manuales (solo números) -->
        <div class="grid3">
          <div>
            <label>Round</label>
            <input type="number" name="round" min="1" step="1" required value="<?= (int)$next_round ?>" inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($azul) ?> — Puntos</label>
            <input type="number" name="azul_puntos" min="0" step="1" required inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($rojo) ?> — Puntos</label>
            <input type="number" name="rojo_puntos" min="0" step="1" required inputmode="numeric" pattern="\d*">
          </div>
        </div>

        <!-- Cantidades de conteos y advertencias -->
        <div class="grid2" style="margin-top:10px">
          <div>
            <label><?= h($azul) ?> — Conteos (knockdowns)</label>
            <input type="number" name="azul_conteos" min="0" step="1" value="0" inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($rojo) ?> — Conteos (knockdowns)</label>
            <input type="number" name="rojo_conteos" min="0" step="1" value="0" inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($azul) ?> — Advertencias del árbitro</label>
            <input type="number" name="azul_advertencias" min="0" step="1" value="0" inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($rojo) ?> — Advertencias del árbitro</label>
            <input type="number" name="rojo_advertencias" min="0" step="1" value="0" inputmode="numeric" pattern="\d*">
          </div>
        </div>
        <div class="subtle" style="margin-top:4px">
          Ingresá valores enteros (0, 1, 2, …). El formulario bloquea letras y símbolos.
        </div>

        <label style="margin-top:8px">Observaciones (opcional)</label>
        <textarea name="observaciones" rows="2" placeholder="Advertencias verbales, penalidades, cortes, etc."></textarea>

        <div class="row" style="margin-top:10px">
          <button class="btn" type="submit">Guardar round</button>
          <!-- 🔁 CAMBIO: vuelve al Panel del Juez -->
          <a class="btn gray" href="panel_juez.php">Volver al Panel del Juez</a>
          <a class="btn gray" href="combate_en_vivo.php?pelea_id=<?= (int)$pelea_id ?>&modo=juez">Ver en vivo</a>
        </div>
      </form>
    </div>

    <div class="card" style="margin-top:16px">
      <h3 style="margin:0 0 8px 0">📋 Rondas cargadas — <span class="pill">Total Azul <?= (int)$totalAz ?></span> • <span class="pill">Total Rojo <?= (int)$totalRo ?></span></h3>
      <?php if (!$puntajes): ?>
        <div class="muted">Aún no cargaste rondas.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Round</th>
                <th>Azul (<?= h($azul) ?>)</th>
                <th>Rojo (<?= h($rojo) ?>)</th>
                <th>Conteos A/R</th>
                <th>Advert. A/R</th>
                <th>Observaciones</th>
                <th>Actualizado</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($puntajes as $pu): ?>
              <tr>
                <td><?= (int)$pu['round'] ?></td>
                <td><?= (int)$pu['azul_puntos'] ?></td>
                <td><?= (int)$pu['rojo_puntos'] ?></td>
                <td><?= (int)$pu['azul_conteos'] ?> / <?= (int)$pu['rojo_conteos'] ?></td>
                <td><?= (int)$pu['azul_advertencias'] ?> / <?= (int)$pu['rojo_advertencias'] ?></td>
                <td><?= h((string)$pu['observaciones']) ?></td>
                <td><?= h((string)$pu['updated_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- Envío de resultado (método + ganador) -->
      <form method="post" action="" style="margin-top:12px" id="frmResultado"
            onsubmit="return confirm('Se enviará el RESULTADO al organizador. ¿Confirmar?')">
        <input type="hidden" name="__accion__" value="enviar_resultado">

        <div class="grid2">
          <div>
            <label>Método del resultado</label>
            <select name="metodo" required>
              <option value="PTS" selected>PTS — Por puntos</option>
              <option value="KO">KO — Knockout</option>
              <option value="KOT">KOT — Knockout técnico</option>
              <option value="RSC">RSC — Detención del árbitro</option>
              <option value="SURRENDER">SURRENDER — Rendición</option>
              <option value="IRC">IRC — Interrupción por corte</option>
              <option value="ABANDONO">ABANDONO</option>
              <option value="EMPATE">EMPATE</option>
            </select>
          </div>
          <div>
            <label>Ganador</label>
            <div class="row" style="gap:8px">
              <?php
                $default_gan = $totalAz>$totalRo?'azul':($totalRo>$totalAz?'rojo':'empate');
              ?>
              <label class="pill"><input type="radio" name="ganador_manual" value="azul" <?= $default_gan==='azul'?'checked':''; ?>> Azul (<?= h($azul) ?>)</label>
              <label class="pill"><input type="radio" name="ganador_manual" value="rojo" <?= $default_gan==='rojo'?'checked':''; ?>> Rojo (<?= h($rojo) ?>)</label>
              <label class="pill"><input type="radio" name="ganador_manual" value="empate" <?= $default_gan==='empate'?'checked':''; ?>> Empate</label>
            </div>
            <div class="subtle" style="margin-top:4px">Si elegís <b>EMPATE</b> como método, se registrará empate sin importar los puntos.</div>
          </div>
        </div>

        <label style="margin-top:8px">Observaciones al organizador (opcional)</label>
        <textarea name="observaciones_final" rows="2" placeholder="Cortar por golpe, esquina tira la toalla, etc."></textarea>

        <div style="margin-top:8px">
          <button class="btn" type="submit">Enviar RESULTADO</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      const onlyDigits = (ev) => {
        const allowed = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'];
        if (allowed.includes(ev.key)) return;
        if (!/^\d$/.test(ev.key)) { ev.preventDefault(); }
      };
      document.querySelectorAll('input[type="number"]').forEach(inp=>{
        inp.addEventListener('keydown', onlyDigits);
        inp.addEventListener('input', (e)=>{
          e.target.value = (e.target.value||'').replace(/\D+/g,'');
        });
        if (!inp.hasAttribute('step')) inp.setAttribute('step','1');
      });

      const metodoSel = document.querySelector('select[name="metodo"]');
      const radios = Array.from(document.querySelectorAll('input[name="ganador_manual"]'));
      const syncWinner = () => {
        const empate = metodoSel.value === 'EMPATE';
        radios.forEach(r=>{
          if (empate) {
            r.disabled = r.value !== 'empate';
            if (r.value === 'empate') r.checked = true;
          } else {
            r.disabled = false;
          }
        });
      };
      if (metodoSel) {
        metodoSel.addEventListener('change', syncWinner);
        syncWinner();
      }
    })();
  </script>
</body>
</html>
