<?php
// tarjeta_puntuar.php — puntuación por rounds + cierre temprano (KO/KOT/RSC/IRC/ABANDONO/EMPATE/NC)
// La apertura/cierre de votación la controla "combate_en_vivo.php". Acá solo leemos ese estado.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }

function int_from_post(string $name, int $min, int $max): array {
  $v = isset($_POST[$name]) ? (int)$_POST[$name] : null;
  if ($v === null || $v < $min || $v > $max) {
    return [null, "Campo inválido: $name ($min..$max)."];
  }
  return [$v, null];
}
function method_label_to_enum(string $m): string {
  $m = strtoupper(trim($m));
  if ($m==='ABANDONO' || $m==='SURRENDER') return 'ABANDONO';
  if (in_array($m, ['KO','KOT','RSC','IRC','EMPATE','PTS','NC'], true)) return $m;
  return 'PTS';
}

$pelea_id = isset($_GET['pelea_id']) && is_numeric($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
$juez_id  = (int)($_SESSION['juez_id'] ?? ($_GET['juez_id'] ?? 0));

if ($pelea_id <= 0) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>pelea_id</b>.</div>'; exit; }
if ($juez_id  <= 0) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffe08a;background:#fff6da;color:#664d03;border-radius:8px;">No se detectó <b>juez_id</b>. Iniciá sesión como juez o pasá juez_id por GET.</div>'; exit; }

/* Detectar columnas de peleas_evento */
$cols = [];
$res = $conexion->query("SHOW COLUMNS FROM `peleas_evento`");
if (!$res) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'</div>'; exit; }
while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
$res->close();

$pick = function(array $cands) use ($cols){ foreach($cands as $c){ $lc=strtolower($c); if(isset($cols[$lc])) return $cols[$lc]; } return null; };

$C_EVENTO = $pick(['evento_id','id_evento','evento']);
$C_ROJO   = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL   = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_RONDAS = $pick(['rondas']);

if (!$C_EVENTO || !$C_ROJO || !$C_AZUL) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Faltan columnas obligatorias en <b>peleas_evento</b> (evento_id, competidor_rojo_id, competidor_azul_id).</div>';
  exit;
}

/* Traer info pelea + competidores */
$colE = bt($C_EVENTO);
$colR = bt($C_ROJO);
$colA = bt($C_AZUL);
$selRondas = $C_RONDAS ? "p.".bt($C_RONDAS)." AS rondas," : "NULL AS rondas,";

$sql = "
  SELECT
    p.id AS pelea_id, p.$colE AS evento_id, $selRondas
    p.$colR AS rojo_id, p.$colA AS azul_id,

    cr.apellido AS r_apellido, cr.nombre AS r_nombre, cr.escuela_nombre AS r_escuela,
    cr.foto_competidor AS r_foto, cr.edad AS r_edad,
    mr.nombre AS r_modalidad, dvr.nombre AS r_division, cpr.nombre AS r_peso,

    ca.apellido AS a_apellido, ca.nombre AS a_nombre, ca.escuela_nombre AS a_escuela,
    ca.foto_competidor AS a_foto, ca.edad AS a_edad,
    ma.nombre AS a_modalidad, dva.nombre AS a_division, cpa.nombre AS a_peso
  FROM `peleas_evento` p
  JOIN `competidores_evento` cr ON p.$colR = cr.id
  JOIN `competidores_evento` ca ON p.$colA = ca.id
  LEFT JOIN `modalidades_evento`     mr ON mr.id = cr.modalidad_id
  LEFT JOIN `divisiones_evento`      dvr ON dvr.id = cr.division_id
  LEFT JOIN `categorias_peso_evento` cpr ON cpr.id = cr.categoria_peso_id
  LEFT JOIN `modalidades_evento`     ma ON ma.id = ca.modalidad_id
  LEFT JOIN `divisiones_evento`      dva ON dva.id = ca.division_id
  LEFT JOIN `categorias_peso_evento` cpa ON cpa.id = ca.categoria_peso_id
  WHERE p.id = ?
  LIMIT 1
";
$st = $conexion->prepare($sql);
if (!$st) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Error preparando SQL: '.h($conexion->error).'</div>'; exit; }
$st->bind_param('i', $pelea_id);
$st->execute();
$st->bind_result(
  $X_pelea_id, $X_evento_id, $X_rondas,
  $X_rojo_id,  $X_azul_id,

  $r_apellido, $r_nombre, $r_escuela,
  $r_foto, $r_edad,
  $r_modalidad, $r_division, $r_peso,

  $a_apellido, $a_nombre, $a_escuela,
  $a_foto, $a_edad,
  $a_modalidad, $a_division, $a_peso
);
$ok = $st->fetch();
$st->close();
if (!$ok) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No se encontró la pelea.</div>';
  exit;
}

$info = [
  'pelea_id'=>$X_pelea_id, 'evento_id'=>$X_evento_id, 'rondas'=>$X_rondas,
  'r_apellido'=>$r_apellido, 'r_nombre'=>$r_nombre, 'r_escuela'=>$r_escuela,
  'r_foto'=>$r_foto, 'r_edad'=>$r_edad, 'r_modalidad'=>$r_modalidad,
  'r_division'=>$r_division, 'r_peso'=>$r_peso,
  'a_apellido'=>$a_apellido, 'a_nombre'=>$a_nombre, 'a_escuela'=>$a_escuela,
  'a_foto'=>$a_foto, 'a_edad'=>$a_edad, 'a_modalidad'=>$a_modalidad,
  'a_division'=>$a_division, 'a_peso'=>$a_peso
];

$rojo = trim(($info['r_apellido'] ?? '').' '.($info['r_nombre'] ?? ''));
$azul = trim(($info['a_apellido'] ?? '').' '.($info['a_nombre'] ?? ''));

$rondasEsperadas = (isset($info['rondas']) && (int)$info['rondas']>0) ? (int)$info['rondas'] : 3;
$incluir_menu = empty($_SESSION['__JUEZ_MODE__']);

/* ===== Resumen por mayoría (resultados_jueces ya enviados) ===== */
$cntAz=0; $cntRo=0; $cntEmp=0; $sumAz=0; $sumRo=0; $tarjetas=0;
if ($rs = $conexion->prepare("SELECT ganador, total_azul, total_rojo FROM resultados_jueces WHERE pelea_id=? AND estado='enviado'")) {
  $rs->bind_param('i',$pelea_id); $rs->execute(); $rs->bind_result($g,$ta,$tr);
  while($rs->fetch()){
    $tarjetas++; $sumAz += (int)$ta; $sumRo += (int)$tr;
    if ($g==='azul') $cntAz++; elseif ($g==='rojo') $cntRo++; else $cntEmp++;
  }
  $rs->close();
}
$mayoria = null;
if ($tarjetas>0){
  if ($cntAz>$cntRo && $cntAz>$cntEmp) $mayoria='azul';
  elseif ($cntRo>$cntAz && $cntRo>$cntEmp) $mayoria='rojo';
  else $mayoria='empate';
}
$resumen_txt = $tarjetas>0
  ? ('Tarjetas: AZUL '.$cntAz.' · ROJO '.$cntRo.' · EMP '.$cntEmp.' — Totales sumados: Azul '.$sumAz.' / Rojo '.$sumRo.' — Decisión por mayoría: '.strtoupper((string)$mayoria))
  : 'Aún no hay tarjetas cerradas de jueces.';

/* ===== Empate → +1 round solo en esa excepción ===== */
$rondasMax = $rondasEsperadas;
$ext_por_empate = false;
if ($mayoria === 'empate' && $tarjetas > 0) { $rondasMax = $rondasEsperadas + 1; $ext_por_empate = true; }

/* ===== Asegurar tabla resultados_jueces y ENUM NC ===== */
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
@$conexion->query("ALTER TABLE `resultados_jueces`
  MODIFY `metodo` ENUM('PTS','KO','KOT','RSC','SURRENDER','IRC','ABANDONO','EMPATE','NC') NOT NULL DEFAULT 'PTS'");

/* ===== Procesamiento POST ===== */
$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = $_POST['__accion__'] ?? '';

  if ($accion==='guardar_round') {
    $MAX_R = max(1, (int)$rondasMax);
    [$round, $e] = int_from_post('round', 1, $MAX_R); if ($e) $err=$e;

    [$azPts, $e2] = int_from_post('azul_puntos', 7, 10); if (!$err && $e2) $err=$e2;
    [$roPts, $e3] = int_from_post('rojo_puntos', 7, 10); if (!$err && $e3) $err=$e3;

    [$azKD,  $e4] = int_from_post('azul_conteos', 0, 3); if (!$err && $e4) $err=$e4;
    [$roKD,  $e5] = int_from_post('rojo_conteos', 0, 3); if (!$err && $e5) $err=$e5;

    [$azAdv, $e6] = int_from_post('azul_advertencias', 0, 3); if (!$err && $e6) $err=$e6;
    [$roAdv, $e7] = int_from_post('rojo_advertencias', 0, 3); if (!$err && $e7) $err=$e7;

    $obs = trim((string)($_POST['observaciones'] ?? ''));

    // ¿Ya cerrada la tarjeta por cantidad?
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
          // Recalcular totales y ver si cerrar (PTS)
          $totA=0; $totR=0; $det=[];
          if ($qp=$conexion->prepare("SELECT `round`,azul_puntos,rojo_puntos FROM `puntuaciones_jueces` WHERE pelea_id=? AND juez_id=? ORDER BY `round`")){
            $qp->bind_param('ii',$pelea_id,$juez_id); $qp->execute();
            if ($res=$qp->get_result()) { while($row=$res->fetch_assoc()){ $det[]=$row; $totA+=(int)$row['azul_puntos']; $totR+=(int)$row['rojo_puntos']; } }
            $qp->close();
          }
          $cargados = count($det);
          if ($cargados >= $MAX_R) {
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
            $msg="Round guardado. ✅ Tarjeta completa ($cargados/$MAX_R). Resultado registrado por PTS: $totA–$totR (".strtoupper($gan).").";
          } else {
            $msg="Round guardado. Progreso: $cargados/$MAX_R.";
          }
        } else { $err='No se pudo guardar el round.'; }
        $st->close();
      } else { $err='Error interno (prep).'; }
    }
  }

  if ($accion==='finalizar_antes') {
    // Cierre temprano con método
    $metodo = method_label_to_enum($_POST['metodo'] ?? 'PTS');
    $ganSel = strtolower(trim((string)($_POST['ganador'] ?? '')));
    if (in_array($metodo, ['EMPATE','NC'], true)) { $ganSel = 'empate'; }
    if (!in_array($ganSel, ['azul','rojo','empate'], true)) { $ganSel = 'empate'; }

    // Sumar lo ya cargado (por si hubo algunos rounds)
    $totA=0; $totR=0; $det=[];
    if ($qp=$conexion->prepare("SELECT `round`,azul_puntos,rojo_puntos FROM `puntuaciones_jueces` WHERE pelea_id=? AND juez_id=? ORDER BY `round`")){
      $qp->bind_param('ii',$pelea_id,$juez_id); $qp->execute();
      if ($res=$qp->get_result()) {
        while($row=$res->fetch_assoc()){ $det[]=$row; $totA+=(int)$row['azul_puntos']; $totR+=(int)$row['rojo_puntos']; }
      }
      $qp->close();
    }
    $round_stop = (int)($_POST['round_stop'] ?? (count($det) ? end($det)['round'] : 1));
    $obsFin = trim((string)($_POST['observaciones_final'] ?? ''));
    $obsFin = "Finalización temprana por {$metodo} en R{$round_stop}".($obsFin?": ".$obsFin:"");

    $checksum = hash('sha256', json_encode(['det'=>$det,'stop'=>$round_stop,'met'=>$metodo],JSON_UNESCAPED_UNICODE));
    $ins="INSERT INTO `resultados_jueces` (pelea_id,juez_id,total_azul,total_rojo,ganador,metodo,observaciones,detalle_checksum)
          VALUES (?,?,?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE total_azul=VALUES(total_azul), total_rojo=VALUES(total_rojo),
                                  ganador=VALUES(ganador), metodo=VALUES(metodo),
                                  observaciones=VALUES(observaciones),
                                  detalle_checksum=VALUES(detalle_checksum), enviado_at=CURRENT_TIMESTAMP, estado='enviado'";
    if ($sr=$conexion->prepare($ins)){
      $sr->bind_param('iiiissss',$pelea_id,$juez_id,$totA,$totR,$ganSel,$metodo,$obsFin,$checksum);
      if ($sr->execute()){ $msg = "✅ Tarjeta cerrada por finalización temprana: {$metodo} — Ganador: ".strtoupper($ganSel)."."; }
      else { $err = "No se pudo registrar la finalización."; }
      $sr->close();
    } else { $err = "Error interno (prep fin)."; }
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
$next_round = ($puntajes ? ((int)end($puntajes)['round']+1) : 1);
if ($next_round > $rondasEsperadas) $next_round = $rondasEsperadas;

/* ¿Ya hay resultado_jueces (tarjeta cerrada)? */
$tarjeta_cerrada = false; $metodo_existente = '';
if ($rj=$conexion->prepare("SELECT metodo FROM resultados_jueces WHERE pelea_id=? AND juez_id=? AND estado='enviado' LIMIT 1")){
  $rj->bind_param('ii',$pelea_id,$juez_id); $rj->execute(); $r=$rj->get_result();
  if ($r && $row=$r->fetch_assoc()){ $tarjeta_cerrada=true; $metodo_existente=(string)$row['metodo']; }
  $rj->close();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🧑‍⚖️ Puntuar — Pelea #<?= (int)$pelea_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="estilo_unificado.css">
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
    .badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#1a2633;border:1px solid #284058;font-size:12px}
  </style>
</head>
<body>
  <?php if ($incluir_menu) { @include __DIR__ . '/menu_eventos.php'; } ?>
  <div class="wrap">
    <div class="card">
      <h2 style="margin:0 0 8px 0">🧑‍⚖️ Puntuar — Pelea #<?= (int)$pelea_id ?> · <?= h($azul) ?> (Azul) vs <?= h($rojo) ?> (Rojo)</h2>
      <div class="subtle">Rondas configuradas: <b><?= (int)$rondasEsperadas ?></b><?= $ext_por_empate ? ' <span class="tag">+1 por EMPATE</span>' : '' ?> · Sistema: <span class="tag">Puntos 7–10</span></div>

      <?php if ($tarjeta_cerrada): ?>
        <div class="ok">✅ Tarjeta cerrada (método: <b><?= h($metodo_existente?:'PTS') ?></b>). Si hay error, avisá al supervisor.</div>
      <?php endif; ?>

      <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

      <!-- Estado de votación controlado por “Combate en vivo” -->
      <div class="row" style="align-items:center;margin-top:6px">
        <span id="voteBadge" class="badge">Votación: verificando…</span>
      </div>

      <!-- Form de round (habilitado solo si votación abierta y tarjeta no cerrada) -->
      <form method="post" action="" id="frmRound" novalidate style="margin-top:10px">
        <input type="hidden" name="__accion__" value="guardar_round">
        <div class="grid3">
          <div>
            <label>Round</label>
            <input type="number" name="round" min="1" max="<?= (int)$rondasMax ?>" step="1" value="<?= (int)$next_round ?>">
          </div>
          <div>
            <label>Puntos 🔵 Azul</label>
            <input type="number" name="azul_puntos" min="7" max="10" step="1" value="10">
          </div>
          <div>
            <label>Puntos 🔴 Rojo</label>
            <input type="number" name="rojo_puntos" min="7" max="10" step="1" value="9">
          </div>
        </div>

        <div class="grid3" style="margin-top:10px">
          <div>
            <label>Conteos a 🔵 Azul</label>
            <input type="number" name="azul_conteos" min="0" max="3" step="1" value="0">
          </div>
          <div>
            <label>Conteos a 🔴 Rojo</label>
            <input type="number" name="rojo_conteos" min="0" max="3" step="1" value="0">
          </div>
          <div>
            <label>Observaciones (opcional)</label>
            <input type="text" name="observaciones" placeholder="Advertencias, penalidades, cortes, etc.">
          </div>
        </div>

        <div class="grid2" style="margin-top:10px">
          <div>
            <label>Advertencias a 🔵 Azul</label>
            <input type="number" name="azul_advertencias" min="0" max="3" step="1" value="0">
          </div>
          <div>
            <label>Advertencias a 🔴 Rojo</label>
            <input type="number" name="rojo_advertencias" min="0" max="3" step="1" value="0">
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <button class="btn" type="submit" id="btnGuardar" <?= ($tarjeta_cerrada?'disabled':'') ?>>Guardar round</button>
          <a class="btn gray" href="panel_juez.php">Volver al Panel del Juez</a>
        </div>
        <div class="subtle" id="hintBloqueo" style="margin-top:6px">El formulario se habilita automáticamente cuando “Combate en vivo” abre la votación.</div>
      </form>
    </div>

    <div class="card" style="margin-top:16px">
      <h3 style="margin:0 0 8px 0">🏁 Cerrar pelea por finalización temprana</h3>
      <div class="subtle">Usar si la pelea termina antes de los <?= (int)$rondasEsperadas ?> rounds cargados.</div>

      <form method="post" action="" id="frmFinal" style="margin-top:8px">
        <input type="hidden" name="__accion__" value="finalizar_antes">

        <div class="grid3">
          <div>
            <label>Método</label>
            <select name="metodo" required>
              <option value="KO">KO</option>
              <option value="KOT">KOT</option>
              <option value="RSC">RSC</option>
              <option value="IRC">IRC</option>
              <option value="ABANDONO">Abandono / Surrender</option>
              <option value="EMPATE">Empate</option>
              <option value="NC">No Contest (NC)</option>
            </select>
          </div>
          <div>
            <label>Ganador</label>
            <select name="ganador" id="selGanador">
              <option value="azul">🔵 Azul (<?= h($azul) ?>)</option>
              <option value="rojo">🔴 Rojo (<?= h($rojo) ?>)</option>
              <option value="empate">⚖️ Empate / NC</option>
            </select>
          </div>
          <div>
            <label>Round de finalización</label>
            <input type="number" name="round_stop" min="1" max="<?= (int)$rondasMax ?>" step="1" value="<?= (int)max(1,$next_round) ?>">
          </div>
        </div>

        <label style="margin-top:8px">Observaciones (opcional)</label>
        <textarea name="observaciones_final" rows="2" placeholder="Detalle de la detención, lesión, abandono, etc."></textarea>

        <div class="row" style="margin-top:10px">
          <button class="btn soft" type="submit" <?= ($tarjeta_cerrada?'disabled':'') ?>>Cerrar tarjeta por finalización</button>
        </div>
        <div class="subtle" style="margin-top:6px">Esto registra tu resultado en <code>resultados_jueces</code> y cierra tu tarjeta, aunque no haya finalizado el número total de rounds.</div>
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
                <th>Azul Pts</th>
                <th>Rojo Pts</th>
                <th>Conteos A</th>
                <th>Conteos R</th>
                <th>Adv A</th>
                <th>Adv R</th>
                <th>Obs</th>
                <th>Últ. Modif</th>
              </tr>
            </thead>
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
                  <td><span class="subtle"><?= h($pu['updated_at'] ?? '') ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-top:16px">
      <h3 style="margin:0 0 8px 0">🧮 Resumen actual</h3>
      <div class="ok">
        <b><?= h($resumen_txt) ?></b><br>
        <span class="subtle">Este resumen muestra la mayoría de tarjetas ya cerradas por los jueces.</span>
      </div>
    </div>
  </div>

  <script>
    (function(){
      // ===== Solo dígitos y límites =====
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

      // ===== Ganador bloqueado si método EMPATE/NC =====
      const selMetodo  = document.querySelector('select[name="metodo"]');
      const selGanador = document.getElementById('selGanador');
      function syncWinnerDisable(){
        const m = (selMetodo.value||'').toUpperCase();
        if (m==='EMPATE' || m==='NC'){
          selGanador.value = 'empate';
          selGanador.disabled = true;
        }else{
          selGanador.disabled = false;
          if (selGanador.value==='empate') selGanador.value='azul';
        }
      }
      selMetodo.addEventListener('change', syncWinnerDisable);
      syncWinnerDisable();

      // ===== Votación controlada por Combate en vivo =====
      const peleaId = <?= (int)$pelea_id ?>;
      const formClosed = <?= $tarjeta_cerrada ? 'true' : 'false' ?>;
      const frm = document.getElementById('frmRound');
      const btnGuardar = document.getElementById('btnGuardar');
      const voteBadge = document.getElementById('voteBadge');
      const hintBloqueo = document.getElementById('hintBloqueo');

      function setFormEnabled(enabled){
        frm.querySelectorAll('input, textarea, select, button[type=submit]').forEach(el=>{
          if (formClosed) { el.disabled = true; return; }
          el.disabled = !enabled && (el.name!=='observaciones');
        });
        btnGuardar.disabled = !enabled || formClosed;
        voteBadge.textContent = 'Votación: ' + (enabled ? 'ABIERTA' : 'CERRADA');
        voteBadge.style.background = enabled ? '#0e2818' : '#1a2633';
        voteBadge.style.borderColor = enabled ? '#1e6a3f' : '#284058';
        hintBloqueo.textContent = enabled
          ? 'Descanso activo — podés puntuar este round.'
          : 'El formulario se habilita automáticamente cuando “Combate en vivo” abre la votación del round.';
      }

      async function tryFetchJson(url){
        try{
          const r = await fetch(url, {cache:'no-store'});
          if (r.ok) return await r.json();
        }catch(_){}
        return null;
      }

      async function pollVote(){
        if (formClosed){ setFormEnabled(false); return; }
        // Debe responder { ok:true, open:true/false, round:n }
        const data = await tryFetchJson('get_estado_votacion_round.php?pelea_id='+peleaId);
        if (data && data.ok){
          setFormEnabled(!!data.open);
          if (typeof data.round==='number'){
            const roundInp = frm.querySelector('input[name=round]');
            if (roundInp && !roundInp.disabled) roundInp.value = String(data.round);
          }
        } else {
          // Fallback: si no existe el endpoint, dejamos habilitado
          setFormEnabled(true);
          voteBadge.textContent = 'Votación: sin control (fallback)';
        }
      }

      setInterval(pollVote, 2000);
      pollVote();
    })();
  </script>
</body>
</html>
