<?php
// puntuar_pelea.php — puntos manuales + conteos/advertencias (con límites) + cierre automático por rondas
if (session_status() === PHP_SESSION_NONE) session_start();

/* BYPASS para guards de asignación (igual que tu versión) */
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

/* ===== Nombres de rincones, evento_id y rondas ===== */
$azul='AZUL'; $rojo='ROJO'; $evento_id = 0; $rondasEsperadas = 3;
$has_pe = $conexion->query("SHOW TABLES LIKE 'peleas_evento'");
if ($has_pe && $has_pe->num_rows) {
  $nameA = has_col($conexion,'peleas_evento','azul_nombre') ? 'azul_nombre' : (has_col($conexion,'peleas_evento','competidor_a') ? 'competidor_a' : null);
  $nameR = has_col($conexion,'peleas_evento','rojo_nombre') ? 'rojo_nombre' : (has_col($conexion,'peleas_evento','competidor_b') ? 'competidor_b' : null);
  $hasRondas = has_col($conexion,'peleas_evento','rondas');

  $cols = [];
  $cols[] = $nameA ? "$nameA AS a" : "NULL AS a";
  $cols[] = $nameR ? "$nameR AS r" : "NULL AS r";
  $cols[] = $hasRondas ? "rondas" : "NULL AS rondas";
  foreach (['evento_id','event_id','id_evento'] as $c) { if (has_col($conexion,'peleas_evento',$c)) { $evCol=$c; break; } }
  $cols[] = !empty($evCol) ? "`$evCol` AS eid" : "NULL AS eid";
  $sql="SELECT ".implode(',', $cols)." FROM peleas_evento WHERE id=? LIMIT 1";
  if ($st=$conexion->prepare($sql)){
    $st->bind_param('i',$pelea_id); $st->execute();
    if ($r=$st->get_result()){
      if($row=$r->fetch_assoc()){
        if(!empty($row['a'])) $azul = trim($row['a']);
        if(!empty($row['r'])) $rojo = trim($row['r']);
        if(!empty($row['eid'])) $evento_id = (int)$row['eid'];
        if(!empty($row['rondas']) && (int)$row['rondas']>0) $rondasEsperadas = (int)$row['rondas'];
      }
    }
    $st->close();
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

/* ===== Guardado por round (con límites y cierre auto) ===== */
$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = $_POST['__accion__'] ?? '';
  if ($accion==='guardar_round') {
    // Límites solicitados
    $MAX_R = max(1, (int)$rondasEsperadas);
    [$round, $e] = int_from_post('round', 1, $MAX_R); if ($e) $err=$e;

    [$azPts, $e2] = int_from_post('azul_puntos', 7, 10); if (!$err && $e2) $err=$e2;
    [$roPts, $e3] = int_from_post('rojo_puntos', 7, 10); if (!$err && $e3) $err=$e3;

    [$azKD,  $e4] = int_from_post('azul_conteos', 0, 3); if (!$err && $e4) $err=$e4;
    [$roKD,  $e5] = int_from_post('rojo_conteos', 0, 3); if (!$err && $e5) $err=$e5;

    [$azAdv, $e6] = int_from_post('azul_advertencias', 0, 3); if (!$err && $e6) $err=$e6;
    [$roAdv, $e7] = int_from_post('rojo_advertencias', 0, 3); if (!$err && $e7) $err=$e7;

    $obs = trim((string)($_POST['observaciones'] ?? ''));

    // ¿Ya cerrada la tarjeta?
    $rs = $conexion->prepare("SELECT COUNT(*) FROM `puntuaciones_jueces` WHERE pelea_id=? AND juez_id=?");
    $rs->bind_param('ii',$pelea_id,$juez_id); $rs->execute(); $rs->bind_result($cntExisting); $rs->fetch(); $rs->close();
    if ($cntExisting >= $MAX_R) { $err='Esta tarjeta ya está cerrada. No podés cargar más rounds.'; }

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
        if ($st->execute()) {
          // Recalcular totales y ver si cerrar
          $totA=0; $totR=0; $det=[];
          if ($qp=$conexion->prepare("SELECT `round`,azul_puntos,rojo_puntos FROM `puntuaciones_jueces` WHERE pelea_id=? AND juez_id=? ORDER BY `round`")){
            $qp->bind_param('ii',$pelea_id,$juez_id); $qp->execute();
            if ($res=$qp->get_result()) {
              while($row=$res->fetch_assoc()){ $det[]=$row; $totA+=(int)$row['azul_puntos']; $totR+=(int)$row['rojo_puntos']; }
            }
            $qp->close();
          }
          $cargados = count($det);
          if ($cargados >= $MAX_R) {
            // Cerrar automáticamente (PTS)
            $gan = ($totA>$totR?'azul':($totR>$totA?'rojo':'empate'));
            $checksum = hash('sha256', json_encode($det,JSON_UNESCAPED_UNICODE));
            $ins="INSERT INTO `resultados_jueces` (pelea_id,juez_id,total_azul,total_rojo,ganador,metodo,observaciones,detalle_checksum)
                  VALUES (?,?,?,?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE total_azul=VALUES(total_azul), total_rojo=VALUES(total_rojo),
                                          ganador=VALUES(ganador), metodo=VALUES(metodo),
                                          observaciones=VALUES(observaciones),
                                          detalle_checksum=VALUES(detalle_checksum), enviado_at=CURRENT_TIMESTAMP, estado='enviado'";
            if ($sr=$conexion->prepare($ins)){
              $met='PTS'; $obsFin='';
              $sr->bind_param('iiiissss',$pelea_id,$juez_id,$totA,$totR,$gan,$met,$obsFin,$checksum);
              $sr->execute(); $sr->close();
            }
            $msg="Round guardado. ✅ Tarjeta completa ($cargados/$MAX_R). Resultado registrado: $totA–$totR (ganador: ".strtoupper($gan).").";
          } else {
            $msg="Round guardado. Progreso: $cargados/$MAX_R.";
          }
        } else {
          $err='No se pudo guardar el round.';
        }
        $st->close();
      } else { $err='Error interno (prep).'; }
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
$numRounds = count($puntajes);
$form_locked = ($numRounds >= $rondasEsperadas);
$next_round = $form_locked ? $rondasEsperadas : ($puntajes ? ((int)end($puntajes)['round']+1) : 1);
if ($next_round > $rondasEsperadas) $next_round = $rondasEsperadas;
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
    input,textarea,select,button{font-family:inherit}
    input,textarea,select{width:100%;padding:12px;border-radius:10px;border:1px solid #263341;background:#111a24;color:var(--txt);font-size:16px}
    label{display:block;margin:4px 0 6px 0;font-size:14px;color:#cfe7ff}
    .btn{padding:12px 16px;border-radius:10px;border:1px solid #27455c;background:var(--btn);color:#fff;cursor:pointer;font-weight:600}
    .btn.gray{background:var(--btn2);border-color:var(--btn2b)}
    .btn.soft{background:#173049;border-color:#27455c}
    .btn[disabled]{opacity:.6;cursor:not-allowed}
    table{width:100%;border-collapse:collapse;margin-top:12px;font-size:14px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left}
    th{color:var(--muted)}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}
    .subtle{color:var(--sub);font-size:12px}
    .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--border);border-radius:12px}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:12px}
    .tag{display:inline-block;padding:4px 8px;border-radius:999px;background:#0e2033;border:1px solid #1f3855;font-size:12px;color:#bcd8ff}
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
      <div class="subtle">Rondas: <b><?= (int)$rondasEsperadas ?></b> · Sistema: <span class="tag">Puntos 7–10</span> · <span class="tag">Conteos máx 3</span> · <span class="tag">Advertencias máx 3</span></div>

      <?php if (!empty($msg)): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if (!empty($err)): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

      <?php if ($form_locked): ?>
        <div class="ok">✅ Tarjeta cerrada. Ya cargaste los <?= (int)$rondasEsperadas ?> rounds. Resultado guardado.</div>
      <?php endif; ?>

      <!-- Control: sólo durante descanso -->
      <div class="row" style="align-items:center">
        <button id="btnDescanso" class="btn soft" <?= $form_locked?'disabled':''; ?>>🔔 Iniciar descanso (60s)</button>
        <div class="subtle" id="restInfo">Formulario bloqueado. Sólo podés puntuar durante el descanso.</div>
      </div>

      <form method="post" action="" id="frmRound" novalidate>
        <input type="hidden" name="__accion__" value="guardar_round">

        <!-- Puntos manuales (sólo 7..10) -->
        <div class="grid3">
          <div>
            <label>Round</label>
            <input type="number" name="round" min="1" max="<?= (int)$rondasEsperadas ?>" step="1" required value="<?= (int)$next_round ?>" inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($azul) ?> — Puntos (7–10)</label>
            <input type="number" name="azul_puntos" min="7" max="10" step="1" required inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($rojo) ?> — Puntos (7–10)</label>
            <input type="number" name="rojo_puntos" min="7" max="10" step="1" required inputmode="numeric" pattern="\d*">
          </div>
        </div>

        <!-- Cantidades de conteos y advertencias (0..3) -->
        <div class="grid2" style="margin-top:10px">
          <div>
            <label><?= h($azul) ?> — Conteos (0–3)</label>
            <input type="number" name="azul_conteos" min="0" max="3" step="1" value="0" inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($rojo) ?> — Conteos (0–3)</label>
            <input type="number" name="rojo_conteos" min="0" max="3" step="1" value="0" inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($azul) ?> — Advertencias (0–3)</label>
            <input type="number" name="azul_advertencias" min="0" max="3" step="1" value="0" inputmode="numeric" pattern="\d*">
          </div>
          <div>
            <label><?= h($rojo) ?> — Advertencias (0–3)</label>
            <input type="number" name="rojo_advertencias" min="0" max="3" step="1" value="0" inputmode="numeric" pattern="\d*">
          </div>
        </div>
        <div class="subtle" style="margin-top:4px">
          Ingresá valores enteros válidos. El formulario bloquea letras/símbolos y respeta los límites.
        </div>

        <label style="margin-top:8px">Observaciones (opcional)</label>
        <textarea name="observaciones" rows="2" placeholder="Advertencias verbales, penalidades, cortes, etc."></textarea>

        <div class="row" style="margin-top:10px">
          <button class="btn" type="submit" id="btnGuardar" <?= $form_locked?'disabled':''; ?>>Guardar round</button>
          <a class="btn gray" href="panel_juez.php">Volver al Panel del Juez</a>
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
        <?php if ($form_locked): ?>
          <div class="subtle" style="margin-top:6px">Tarjeta finalizada. Si hay error, avisá al supervisor para corrección administrativa.</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function(){
      /* ===== Sólo números + limpieza ===== */
      const onlyDigits = (ev) => {
        const allowed = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'];
        if (allowed.includes(ev.key)) return;
        if (!/^\d$/.test(ev.key)) { ev.preventDefault(); }
      };
      document.querySelectorAll('input[type="number"]').forEach(inp=>{
        inp.addEventListener('keydown', onlyDigits);
        inp.addEventListener('input', (e)=>{
          e.target.value = (e.target.value||'').replace(/\D+/g,'');
          const min = parseInt(e.target.getAttribute('min')||'-2147483648',10);
          const max = parseInt(e.target.getAttribute('max')||'2147483647',10);
          let v = e.target.value==='' ? '' : parseInt(e.target.value,10);
          if (v!=='' && !Number.isNaN(v)) {
            if (v < min) v = min;
            if (v > max) v = max;
            e.target.value = v;
          }
        });
        if (!inp.hasAttribute('step')) inp.setAttribute('step','1');
      });

      /* ===== Bloqueo por descanso ===== */
      const peleaId = <?= (int)$pelea_id ?>;
      const REST_SECONDS = 60;
      const KEY = 'desc_pelea_'+peleaId; // guarda timestamp fin
      const frm = document.getElementById('frmRound');
      const btnGuardar = document.getElementById('btnGuardar');
      const btnDescanso = document.getElementById('btnDescanso');
      const restInfo = document.getElementById('restInfo');
      const formLockedByRounds = <?= $form_locked ? 'true':'false' ?>;

      const setFormEnabled = (enabled)=>{
        frm.querySelectorAll('input, textarea, select, button[type=submit]').forEach(el=>{
          if (formLockedByRounds) { el.disabled = true; return; }
          // El botón de descanso no se ve afectado
          if (el === btnDescanso) return;
          el.disabled = !enabled;
        });
        restInfo.textContent = enabled ? 'Descanso activo: podés puntuar.' : 'Formulario bloqueado. Sólo podés puntuar durante el descanso.';
      };

      function nowSec(){ return Math.floor(Date.now()/1000); }
      function getRestEnd(){ const v = localStorage.getItem(KEY); return v ? parseInt(v,10) : 0; }
      function setRestEnd(ts){ if (ts>0) localStorage.setItem(KEY, String(ts)); else localStorage.removeItem(KEY); }

      let timer=null;
      function stopTicker(){ if(timer){ clearInterval(timer); timer=null; } }
      function startTicker(endTs){
        stopTicker();
        const tick = ()=>{
          const remain = endTs - nowSec();
          if (remain <= 0) {
            setRestEnd(0);
            setFormEnabled(false);
            btnDescanso.disabled = formLockedByRounds;
            btnDescanso.textContent = '🔔 Iniciar descanso (60s)';
            stopTicker();
          } else {
            setFormEnabled(true);
            btnDescanso.disabled = true;
            btnDescanso.textContent = '⏳ Descanso: '+remain+'s';
          }
        };
        tick();
        timer = setInterval(tick, 1000);
      }

      // Estado inicial
      if (formLockedByRounds) {
        setFormEnabled(false);
        btnDescanso.disabled = true;
        btnDescanso.textContent = 'Tarjeta cerrada';
      } else {
        const endTs = getRestEnd();
        if (endTs > nowSec()) { startTicker(endTs); } else { setFormEnabled(false); }
      }

      // Botón para iniciar descanso
      btnDescanso && btnDescanso.addEventListener('click', (e)=>{
        e.preventDefault();
        if (formLockedByRounds) return;
        const endTs = nowSec() + REST_SECONDS;
        setRestEnd(endTs);
        startTicker(endTs);
      });
    })();
  </script>
</body>
</html>
