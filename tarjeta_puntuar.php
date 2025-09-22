<?php
// tarjeta_puntuar.php — Stateless (GET/POST juez_id). Un solo submit:
// guarda el round y, si tildás "finalizar", registra KO/KOT/DQ/RTD/NC y redirige a combate_en_vivo.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',(string)$c).'`'; }
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  if ($r = $db->query("SHOW TABLES LIKE '$name'")) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf_token'];
function csrf_ok($t){ return !empty($_SESSION['csrf_token']) && !empty($t) && hash_equals($_SESSION['csrf_token'], $t); }

/* ===== Parámetros ===== */
$pelea_id = isset($_GET['pelea_id']) && is_numeric($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : (int)($_POST['pelea_id'] ?? 0);
$juez_id  = isset($_GET['juez_id'])  && is_numeric($_GET['juez_id'])  ? (int)$_GET['juez_id']  : (int)($_POST['juez_id'] ?? 0);

if ($pelea_id <= 0) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>pelea_id</b>.</div>'; exit; }
if ($juez_id  <= 0) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffe08a;background:#fff6da;color:#664d03;border-radius:8px;">Falta <b>juez_id</b>. Abrí tu link: <code>?pelea_id=...&juez_id=...</code></div>'; exit; }

/* ===== Info de pelea ===== */
if (!table_exists($conexion,'peleas_evento')) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No existe <b>peleas_evento</b>.</div>'; exit; }
$cols=[]; if($r=$conexion->query("SHOW COLUMNS FROM `peleas_evento`")){ while($c=$r->fetch_assoc()){ $cols[strtolower($c['Field'])]=$c['Field']; } $r->close(); }
$pick=function($cands)use($cols){foreach($cands as $c){$lc=strtolower($c);if(isset($cols[$lc]))return $cols[$lc];}return null;};

$C_EVENTO   = $pick(['evento_id','id_evento','evento']);
$C_ROJO_ID  = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL_ID  = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_ROJO_TXT = $pick(['rojo_nombre','competidor_b']);
$C_AZUL_TXT = $pick(['azul_nombre','competidor_a']);
$C_RONDAS   = $pick(['rondas','total_rounds']);

$evento_id = null; $rojo_nom='Rojo'; $azul_nom='Azul'; $rondasMax=3;
$haveComp = table_exists($conexion,'competidores_evento');

if ($C_ROJO_ID && $C_AZUL_ID && $haveComp) {
  $sel_evt = $C_EVENTO ? ("p.".bt($C_EVENTO)." AS evento_id") : "NULL AS evento_id";
  $sel_rnd = $C_RONDAS ? (", p.".bt($C_RONDAS)." AS rondas") : "";
  $sql="SELECT $sel_evt $sel_rnd, cr.apellido, cr.nombre, ca.apellido, ca.nombre
        FROM `peleas_evento` p
        JOIN `competidores_evento` cr ON p.".bt($C_ROJO_ID)." = cr.id
        JOIN `competidores_evento` ca ON p.".bt($C_AZUL_ID)." = ca.id
        WHERE p.id=? LIMIT 1";
  if($st=$conexion->prepare($sql)){
    $st->bind_param('i',$pelea_id);
    $st->execute();
    if ($C_RONDAS) $st->bind_result($evento_id,$rondasMax,$r_ap,$r_no,$a_ap,$a_no);
    else           $st->bind_result($evento_id,            $r_ap,$r_no,$a_ap,$a_no);
    if($st->fetch()){
      $rojo_nom = trim(($r_ap??'').' '.($r_no??'')) ?: 'Rojo';
      $azul_nom = trim(($a_ap??'').' '.($a_no??'')) ?: 'Azul';
      $rondasMax = (int)$rondasMax>0?(int)$rondasMax:3;
    }
    $st->close();
  }
} else {
  $sel_evt = $C_EVENTO ? ("p.".bt($C_EVENTO)." AS evento_id") : "NULL AS evento_id";
  $pieces = ["$sel_evt"];
  if ($C_RONDAS)   $pieces[] = "p.".bt($C_RONDAS)." AS rondas";
  if ($C_ROJO_TXT) $pieces[] = "p.".bt($C_ROJO_TXT)." AS rojo_nom";
  if ($C_AZUL_TXT) $pieces[] = "p.".bt($C_AZUL_TXT)." AS azul_nom";
  $sql = "SELECT ".implode(', ', $pieces)." FROM `peleas_evento` p WHERE p.id=? LIMIT 1";
  if($st=$conexion->prepare($sql)){
    $st->bind_param('i',$pelea_id);
    $st->execute();
    if ($C_RONDAS && $C_ROJO_TXT && $C_AZUL_TXT) {
      $st->bind_result($evento_id, $rondasMax, $r_nom, $a_nom);
      if($st->fetch()){ $rojo_nom=trim($r_nom?:'Rojo'); $azul_nom=trim($a_nom?:'Azul'); $rondasMax=(int)$rondasMax>0?(int)$rondasMax:3; }
    } elseif ($C_ROJO_TXT && $C_AZUL_TXT) {
      $st->bind_result($evento_id, $r_nom, $a_nom);
      if($st->fetch()){ $rojo_nom=trim($r_nom?:'Rojo'); $azul_nom=trim($a_nom?:'Azul'); }
    } elseif ($C_RONDAS) {
      $st->bind_result($evento_id, $rondasMax); $st->fetch(); $rondasMax=(int)$rondasMax>0?(int)$rondasMax:3;
    } else {
      $st->bind_result($evento_id); $st->fetch();
    }
    $st->close();
  }
}

/* ===== Estructura de puntuaciones_jueces ===== */
if (!table_exists($conexion,'puntuaciones_jueces')) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No existe <b>puntuaciones_jueces</b>.</div>'; exit;
}
$pc=[]; if($c=$conexion->query("SHOW COLUMNS FROM `puntuaciones_jueces`")){ while($r=$c->fetch_assoc()){ $pc[strtolower($r['Field'])]=$r['Field']; } $c->close(); }

$C_PELEA    = $pc['pelea_id'] ?? ($pc['id_pelea'] ?? ($pc['pelea'] ?? null));
$C_JUEZ     = $pc['juez_id']  ?? ($pc['id_juez']  ?? null);
$C_ROUND    = $pc['round']    ?? ($pc['ronda']    ?? null);
$C_AZUL_PTS = $pc['azul_puntos'] ?? ($pc['puntos_azul'] ?? ($pc['azul'] ?? null));
$C_ROJO_PTS = $pc['rojo_puntos'] ?? ($pc['puntos_rojo'] ?? ($pc['rojo'] ?? null));
$C_AZUL_KD  = $pc['azul_conteos'] ?? ($pc['azul_conteo'] ?? null);
$C_ROJO_KD  = $pc['rojo_conteos'] ?? ($pc['rojo_conteo'] ?? null);
$C_AZUL_ADV = $pc['azul_advertencias'] ?? ($pc['azul_advertencia'] ?? null);
$C_ROJO_ADV = $pc['rojo_advertencias'] ?? ($pc['rojo_advertencia'] ?? null);
$C_OBS      = $pc['observaciones'] ?? null;
$C_UPDATED  = $pc['updated_at'] ?? null;

if(!$C_PELEA || !$C_JUEZ || !$C_ROUND || !$C_AZUL_PTS || !$C_ROJO_PTS){
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Faltan columnas esenciales en <b>puntuaciones_jueces</b>.</div>'; exit;
}

/* ===== Tabla fallos_jueces ===== */
$conexion->query("CREATE TABLE IF NOT EXISTS `fallos_jueces`(
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pelea_id` INT NOT NULL,
  `juez_id` INT NOT NULL,
  `ganador` ENUM('azul','rojo','ninguno') NOT NULL DEFAULT 'ninguno',
  `tipo` ENUM('KO','KOT','DQ','RTD','NC') NOT NULL DEFAULT 'KO',
  `round_fin` INT NULL,
  `tiempo_segundos` INT NULL,
  `observaciones` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_fallo` (`pelea_id`,`juez_id`),
  KEY `idx_fallo_busq` (`pelea_id`,`juez_id`,`round_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ===== POST (un solo submit) ===== */
$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!csrf_ok($_POST['csrf'] ?? '')) { $err='CSRF inválido.'; }
  if (!$err) {
    // 1) Guardar/actualizar round
    $round = max(1, min(99, (int)($_POST['round'] ?? 1)));
    $azPts = max(0, min(1000,(int)($_POST['azul_puntos'] ?? 10)));
    $roPts = max(0, min(1000,(int)($_POST['rojo_puntos'] ?? 9)));
    $azKD  = max(0, min(99, (int)($_POST['azul_conteos'] ?? 0)));
    $roKD  = max(0, min(99, (int)($_POST['rojo_conteos'] ?? 0)));
    $azAd  = max(0, min(99, (int)($_POST['azul_advertencias'] ?? 0)));
    $roAd  = max(0, min(99, (int)($_POST['rojo_advertencias'] ?? 0)));
    $obs   = substr(trim((string)($_POST['observaciones'] ?? '')),0,250);

    $sql="INSERT INTO `puntuaciones_jueces`
            (".bt($C_PELEA).",".bt($C_JUEZ).",".bt($C_ROUND).",".bt($C_AZUL_PTS).",".bt($C_ROJO_PTS)
            .($C_AZUL_KD? ",".bt($C_AZUL_KD):"")
            .($C_ROJO_KD? ",".bt($C_ROJO_KD):"")
            .($C_AZUL_ADV? ",".bt($C_AZUL_ADV):"")
            .($C_ROJO_ADV? ",".bt($C_ROJO_ADV):"")
            .($C_OBS? ",".bt($C_OBS):"").")
          VALUES (?,?,?,?,?"
            .($C_AZUL_KD? ",?":"")
            .($C_ROJO_KD? ",?":"")
            .($C_AZUL_ADV? ",?":"")
            .($C_ROJO_ADV? ",?":"")
            .($C_OBS? ",?":"").")
          ON DUPLICATE KEY UPDATE
            ".bt($C_AZUL_PTS)."=VALUES(".bt($C_AZUL_PTS)."),
            ".bt($C_ROJO_PTS)."=VALUES(".bt($C_ROJO_PTS).")"
            .($C_AZUL_KD?  ", ".bt($C_AZUL_KD)."=VALUES(".bt($C_AZUL_KD).")":"")
            .($C_ROJO_KD?  ", ".bt($C_ROJO_KD)."=VALUES(".bt($C_ROJO_KD).")":"")
            .($C_AZUL_ADV? ", ".bt($C_AZUL_ADV)."=VALUES(".bt($C_AZUL_ADV).")":"")
            .($C_ROJO_ADV? ", ".bt($C_ROJO_ADV)."=VALUES(".bt($C_ROJO_ADV).")":"")
            .($C_OBS?      ", ".bt($C_OBS)."=VALUES(".bt($C_OBS).")":"")
            .($C_UPDATED?  ", ".bt($C_UPDATED)."=NOW()":"");
    $vals = [$pelea_id,$juez_id,$round,$azPts,$roPts];
    if ($C_AZUL_KD)  $vals[]=$azKD;
    if ($C_ROJO_KD)  $vals[]=$roKD;
    if ($C_AZUL_ADV) $vals[]=$azAd;
    if ($C_ROJO_ADV) $vals[]=$roAd;
    if ($C_OBS)      $vals[]=$obs;

    $types=''; foreach($vals as $v){ $types .= is_int($v)?'i':'s'; }

    if ($st=$conexion->prepare($sql)){
      $bind = [$st, $types]; foreach($vals as $k=>$_){ $bind[] = &$vals[$k]; }
      call_user_func_array('mysqli_stmt_bind_param', $bind);
      if ($st->execute()){ $msg="Round {$round} guardado para juez #{$juez_id}."; } else { $err='No se pudo guardar el round: '.$st->error; }
      $st->close();
    } else { $err='Error interno (prepare round).'; }

    // 2) Si tildaron "finalizar", registrar fallo y redirigir
    $finalizar = isset($_POST['finalizar']) && $_POST['finalizar']==='1';
    if (!$err && $finalizar) {
      $ganador = strtolower(trim((string)($_POST['ganador'] ?? 'azul')));
      if (!in_array($ganador, ['azul','rojo','ninguno'], true)) $ganador='azul';

      $tipo = strtoupper(trim((string)($_POST['tipo'] ?? 'KO')));
      $tipos_ok = ['KO','KOT','DQ','RTD','NC'];
      if (!in_array($tipo, $tipos_ok, true)) $tipo='KO';

      $round_fin = (int)($_POST['round_fin'] ?? $round);
      if ($round_fin < 1) $round_fin = 1;
      if ($round_fin > $rondasMax) $round_fin = $rondasMax;

      // tiempo mm:ss (opcional)
      $tiempo_seg = null;
      $tstr = trim((string)($_POST['tiempo'] ?? ''));
      if ($tstr !== '') {
        if (preg_match('/^\s*(\d{1,2}):([0-5]\d)\s*$/', $tstr, $m)) {
          $tiempo_seg = ((int)$m[1])*60 + (int)$m[2];
        } elseif (ctype_digit($tstr)) {
          $tiempo_seg = (int)$tstr;
        }
        if ($tiempo_seg !== null && $tiempo_seg < 0) $tiempo_seg = 0;
        if ($tiempo_seg !== null && $tiempo_seg > 60*60) $tiempo_seg = 60*60;
      }
      $obs_fallo = substr(trim((string)($_POST['observaciones_fallo'] ?? '')), 0, 250);

      // UPSERT dinámico
      $cols = ['pelea_id','juez_id','ganador','tipo','round_fin'];
      $ph   = ['?','?','?','?','?'];
      $vals = [$pelea_id,$juez_id,$ganador,$tipo,$round_fin];
      $types='iissi';

      if ($tiempo_seg !== null) { $cols[]='tiempo_segundos'; $ph[]='?'; $vals[]=(int)$tiempo_seg; $types.='i'; }
      $cols[]='observaciones'; $ph[]='?'; $vals[]=$obs_fallo; $types.='s';

      $sql = "INSERT INTO `fallos_jueces` (".implode(',',$cols).")
              VALUES (".implode(',',$ph).")
              ON DUPLICATE KEY UPDATE
                ganador=VALUES(ganador),
                tipo=VALUES(tipo),
                round_fin=VALUES(round_fin)".
                ($tiempo_seg!==null ? ", tiempo_segundos=VALUES(tiempo_segundos)" : "") .",
                observaciones=VALUES(observaciones),
                updated_at=NOW()";
      if ($st=$conexion->prepare($sql)){
        $bind = [$st, $types]; foreach($vals as $k=>$_){ $bind[] = &$vals[$k]; }
        call_user_func_array('mysqli_stmt_bind_param', $bind);
        if ($st->execute()){
          if (has_col($conexion,'peleas_evento','estado')) {
            if ($pst=$conexion->prepare("UPDATE peleas_evento SET estado='finalizada' WHERE id=? LIMIT 1")){
              $pst->bind_param('i',$pelea_id); $pst->execute(); $pst->close();
            }
          }
          header('Location: combate_en_vivo.php?pelea_id='.$pelea_id);
          exit;
        } else { $err .= ($err?' ':'').('No se pudo registrar el fallo: '.$st->error); }
        $st->close();
      } else { $err .= ($err?' ':'').'Error interno (prepare fallo).'; }
    }
  }
}

/* ===== Leer fallo ya cargado (si vuelve sin redirigir) ===== */
$fallo = null;
if ($st=$conexion->prepare("SELECT ganador, tipo, round_fin, tiempo_segundos, observaciones, updated_at
                            FROM fallos_jueces
                            WHERE pelea_id=? AND juez_id=? LIMIT 1")){
  $st->bind_param('ii',$pelea_id,$juez_id);
  $st->execute();
  $st->bind_result($f_gan,$f_tipo,$f_rfin,$f_tiemp,$f_obs,$f_upd);
  if($st->fetch()){
    $fallo = [
      'ganador'=>$f_gan,'tipo'=>$f_tipo,'round_fin'=>$f_rfin,
      'tiempo_segundos'=>$f_tiemp,'observaciones'=>$f_obs,'updated_at'=>$f_upd
    ];
  }
  $st->close();
}

/* ===== Rondas ya cargadas por ESTE juez ===== */
$puntajes=[]; $totalAz=0; $totalRo=0;
$sel = ["COALESCE(".bt($C_ROUND).",1) AS r", bt($C_AZUL_PTS)." AS az", bt($C_ROJO_PTS)." AS ro"];
$sel[] = $C_AZUL_KD  ? bt($C_AZUL_KD)." AS azkd"   : "0 AS azkd";
$sel[] = $C_ROJO_KD  ? bt($C_ROJO_KD)." AS rokd"   : "0 AS rokd";
$sel[] = $C_AZUL_ADV ? bt($C_AZUL_ADV)." AS azad"  : "0 AS azad";
$sel[] = $C_ROJO_ADV ? bt($C_ROJO_ADV)." AS road"  : "0 AS road";
$sel[] = $C_OBS      ? bt($C_OBS)." AS obs"        : "'' AS obs";
$sel[] = $C_UPDATED  ? bt($C_UPDATED)." AS upd"    : "'' AS upd";

$sqlSel = "SELECT ".implode(',', $sel)." FROM `puntuaciones_jueces`
           WHERE ".bt($C_PELEA)."=? AND ".bt($C_JUEZ)."=?
           ORDER BY r ASC";
if($st=$conexion->prepare($sqlSel)){
  $st->bind_param('ii',$pelea_id,$juez_id);
  $st->execute();
  $st->bind_result($r,$az,$ro,$azkd,$rokd,$azad,$road,$obs,$upd);
  while($st->fetch()){
    $puntajes[] = [
      'round'=>$r, 'azul_puntos'=>$az, 'rojo_puntos'=>$ro,
      'azul_conteos'=>$azkd, 'rojo_conteos'=>$rokd,
      'azul_advertencias'=>$azad, 'rojo_advertencias'=>$road,
      'observaciones'=>$obs, 'updated_at'=>$upd
    ];
    $totalAz += (int)$az; $totalRo += (int)$ro;
  }
  $st->close();
}
$next_round = ($puntajes ? ((int)$puntajes[count($puntajes)-1]['round']+1) : 1);
if ($next_round > $rondasMax) $next_round = $rondasMax;

/* ===== UI ===== */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🧑‍⚖️ Puntuar — Pelea #<?= (int)$pelea_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{background:#0b1115;color:#e6eef4;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
    .wrap{max-width:980px;margin:3vh auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:16px}
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    input,select,button{font-family:inherit}
    input,select{padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer}
    .btn.gray{background:#1b2836;border-color:#2b3c4f}
    table{width:100%;border-collapse:collapse;margin-top:12px;font-size:14px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #1f2a33;font-size:12px}
    .muted{color:#9ecbff}
    .grid{display:grid;grid-template-columns:1fr;gap:16px}
    @media (min-width:980px){ .grid{grid-template-columns:1.2fr .8fr} }
    .hide{display:none}
    .group{border:1px dashed #2b3c4f;border-radius:10px;padding:8px}
    .group legend{font-size:12px;color:#9ecbff;padding:0 6px}
    .stack{display:flex;gap:10px;flex-wrap:wrap}
    .stack label{display:flex;gap:6px;align-items:center}
  </style>
</head>
<body>
<div class="wrap grid">
  <!-- IZQUIERDA: Un solo formulario -->
  <div class="card">
    <h2 style="margin:0 0 8px 0">🧑‍⚖️ Puntuar — Pelea #<?= (int)$pelea_id ?> · <?= h($azul_nom) ?> (Azul) vs <?= h($rojo_nom) ?> (Rojo)</h2>
    <div>Rondas configuradas: <b><?= (int)$rondasMax ?></b> — <span class="pill">Juez #<?= (int)$juez_id ?></span></div>
    <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>
    <?php if ($fallo): ?>
      <div class="ok">✅ <b>Fallo cargado:</b> <?= h($fallo['tipo']) ?> —
        <?php
          $gn = $fallo['ganador']==='azul'?'🔵 Azul':($fallo['ganador']==='rojo'?'🔴 Rojo':'⚖️ Sin ganador');
          echo $gn;
          $rf = (int)($fallo['round_fin'] ?? 0); if ($rf>0) echo ' · R'.$rf;
          $ts = (int)($fallo['tiempo_segundos'] ?? 0);
          if ($ts>0){ $mm = floor($ts/60); $ss = $ts%60; echo ' · '.$mm.':'.str_pad((string)$ss,2,'0',STR_PAD_LEFT); }
        ?>
        <?php if (!empty($fallo['observaciones'])): ?> · <i><?= h($fallo['observaciones']) ?></i><?php endif; ?>
        <div class="muted">Últ. modif: <?= h($fallo['updated_at'] ?? '') ?></div>
      </div>
    <?php endif; ?>

    <form method="post" action="tarjeta_puntuar.php?pelea_id=<?= (int)$pelea_id ?>&juez_id=<?= (int)$juez_id ?>" autocomplete="off" id="formUno">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="pelea_id" value="<?= (int)$pelea_id ?>">
      <input type="hidden" name="juez_id"  value="<?= (int)$juez_id ?>">

      <!-- Ronda -->
      <div class="row" style="margin-top:10px">
        <label>Round
          <input type="number" name="round" min="1" max="<?= (int)$rondasMax ?>" value="<?= (int)$next_round ?>" <?= $fallo?'disabled':'' ?>>
        </label>
        <label>Azul Pts
          <input type="number" name="azul_puntos" min="0" max="1000" value="10" <?= $fallo?'disabled':'' ?>>
        </label>
        <label>Rojo Pts
          <input type="number" name="rojo_puntos" min="0" max="1000" value="9" <?= $fallo?'disabled':'' ?>>
        </label>
        <label>A Conteos
          <input type="number" name="azul_conteos" min="0" max="99" value="0" <?= $fallo?'disabled':'' ?>>
        </label>
        <label>R Conteos
          <input type="number" name="rojo_conteos" min="0" max="99" value="0" <?= $fallo?'disabled':'' ?>>
        </label>
        <label>A Adv
          <input type="number" name="azul_advertencias" min="0" max="99" value="0" <?= $fallo?'disabled':'' ?>>
        </label>
        <label>R Adv
          <input type="number" name="rojo_advertencias" min="0" max="99" value="0" <?= $fallo?'disabled':'' ?>>
        </label>
        <label style="flex:1;min-width:260px">Obs
          <input type="text" name="observaciones" placeholder="Advertencias, penalidades, etc." <?= $fallo?'disabled':'' ?>>
        </label>
      </div>

      <!-- Checkbox para fallos -->
      <div class="row" style="margin-top:12px">
        <label style="display:flex;gap:8px;align-items:center">
          <input type="checkbox" name="finalizar" value="1" id="chkFin">
          <span>🏁 <b>Finalizar combate ahora</b></span>
        </label>
      </div>

      <!-- Bloque de datos de fallo (se muestra al tildar el checkbox) -->
      <?php
        $sel_tipo = $fallo['tipo'] ?? 'KO';
        $sel_g    = $fallo['ganador'] ?? 'azul';
      ?>
      <fieldset id="finBlock" class="group hide" style="margin-top:8px">
        <legend>Datos del fallo</legend>
        <div class="stack">
          <div>
            <div class="muted" style="margin-bottom:6px">Tipo de fallo</div>
            <label><input type="radio" name="tipo" value="KO"  <?= $sel_tipo==='KO'?'checked':'' ?>> KO</label>
            <label><input type="radio" name="tipo" value="KOT" <?= $sel_tipo==='KOT'?'checked':'' ?>> KOT</label>
            <label><input type="radio" name="tipo" value="DQ"  <?= $sel_tipo==='DQ'?'checked':'' ?>> DQ</label>
            <label><input type="radio" name="tipo" value="RTD" <?= $sel_tipo==='RTD'?'checked':'' ?>> RTD</label>
            <label><input type="radio" name="tipo" value="NC"  <?= $sel_tipo==='NC'?'checked':'' ?>> NC</label>
          </div>

          <div>
            <div class="muted" style="margin-bottom:6px">Ganador</div>
            <label><input type="radio" name="ganador" value="azul"    <?= $sel_g==='azul'?'checked':'' ?>> 🔵 Azul</label>
            <label><input type="radio" name="ganador" value="rojo"    <?= $sel_g==='rojo'?'checked':'' ?>> 🔴 Rojo</label>
            <label><input type="radio" name="ganador" value="ninguno" <?= $sel_g==='ninguno'?'checked':'' ?>> ⚖️ Sin ganador</label>
          </div>

          <label>Round fin
            <input type="number" name="round_fin" min="1" max="<?= (int)$rondasMax ?>" value="<?= (int)($fallo['round_fin'] ?? max(1,$next_round)) ?>">
          </label>

          <label>Tiempo (mm:ss)
            <?php
              $tval = '';
              if (!empty($fallo['tiempo_segundos'])) {
                $mm = floor(((int)$fallo['tiempo_segundos'])/60);
                $ss = ((int)$fallo['tiempo_segundos'])%60;
                $tval = $mm.':'.str_pad((string)$ss,2,'0',STR_PAD_LEFT);
              }
            ?>
            <input type="text" name="tiempo" placeholder="ej: 1:35" value="<?= h($tval) ?>">
          </label>

          <label style="flex:1;min-width:260px">Obs fallo
            <input type="text" name="observaciones_fallo" placeholder="Detalle KO/KOT/DQ/RTD/NC" value="<?= h($fallo['observaciones'] ?? '') ?>">
          </label>
        </div>
      </fieldset>

      <div class="row" style="margin-top:12px">
        <button class="btn" type="submit">Guardar (y finalizar si tildaste)</button>
      </div>
    </form>
  </div>

  <!-- DERECHA: Rondas cargadas -->
  <div class="card">
    <h3 style="margin:0 0 8px 0">📋 Rondas cargadas — <span class="pill">Total Azul <?= (int)$totalAz ?></span> • <span class="pill">Total Rojo <?= (int)$totalRo ?></span></h3>
    <?php if (!$puntajes): ?>
      <div>No cargaste rondas aún.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Round</th><th>Azul</th><th>Rojo</th><th>CA</th><th>CR</th><th>Adv A</th><th>Adv R</th><th>Obs</th><th>Últ. Modif</th></tr></thead>
        <tbody>
        <?php foreach($puntajes as $pu): ?>
          <tr>
            <td><?= (int)$pu['round'] ?></td>
            <td><?= (int)$pu['azul_puntos'] ?></td>
            <td><?= (int)$pu['rojo_puntos'] ?></td>
            <td><?= (int)$pu['azul_conteos'] ?></td>
            <td><?= (int)$pu['rojo_conteos'] ?></td>
            <td><?= (int)$pu['azul_advertencias'] ?></td>
            <td><?= (int)$pu['rojo_advertencias'] ?></td>
            <td><?= h($pu['observaciones'] ?? '') ?></td>
            <td><?= h($pu['updated_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
  (function(){
    const chk = document.getElementById('chkFin');
    const blk = document.getElementById('finBlock');
    if (!chk || !blk) return;
    function toggle(){ blk.classList.toggle('hide', !chk.checked); }
    chk.addEventListener('change', toggle);
    // Si venís con valores precargados (por ejemplo, hubo fallo), mostrarlos:
    if (<?= isset($fallo) && $fallo ? 'true' : 'false' ?>) {
      // no auto-tildo para evitar cerrar de nuevo, pero si querés dejar visible el bloque:
      // chk.checked = true; toggle();
    }
  })();
</script>
</body>
</html>
