<?php
// puntuar_pelea.php — permite puntuar aunque la pelea NO esté asignada al juez
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== BYPASS para cualquier guard de asignación que tengas ===== */
$_SESSION['__JUEZ_MODE__']        = 1; // modo juez
$_SESSION['__ALLOW_UNASSIGNED__'] = 1; // permitir no asignadas
$_SESSION['__BYPASS_ASIG__']      = 1; // flag alternativo por si otro guard lo usa
if (!defined('BYPASS_ASIGNACION')) define('BYPASS_ASIGNACION', true);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$juez_id  = (int)($_SESSION['juez_id'] ?? 0);
$pelea_id = isset($_GET['pelea_id']) && ctype_digit($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($juez_id<=0){ header('Location: login_juez.php?err='.urlencode('Iniciá sesión.')); exit; }
if ($pelea_id<=0){ exit('❌ Falta pelea_id.'); }

/* === NO exigimos asignación. (Si tenés un include que chequea, con las flags arriba no debe cortar) === */
$mostrar_aviso_no_asignada = false;
if (isset($_GET['mostrar_aviso']) && $_GET['mostrar_aviso']=='1') $mostrar_aviso_no_asignada = true;

/* === Asegurar tablas de almacenamiento === */
$conexion->query("CREATE TABLE IF NOT EXISTS `puntuaciones_jueces` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pelea_id` INT NOT NULL,
  `juez_id` INT NOT NULL,
  `round` INT NOT NULL,
  `azul_puntos` INT NOT NULL,
  `rojo_puntos` INT NOT NULL,
  `observaciones` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_pelea_juez_round` (`pelea_id`,`juez_id`,`round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

/* === Guardado / Envío === */
$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = $_POST['__accion__'] ?? '';
  if ($accion==='guardar_round') {
    $round = (int)($_POST['round'] ?? 0);
    $az    = (int)($_POST['azul_puntos'] ?? 0);
    $ro    = (int)($_POST['rojo_puntos'] ?? 0);
    $obs   = trim((string)($_POST['observaciones'] ?? ''));
    if ($round<=0) {
      $err='Round inválido.';
    } else {
      $sql="INSERT INTO `puntuaciones_jueces` (pelea_id,juez_id,`round`,azul_puntos,rojo_puntos,observaciones)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE azul_puntos=VALUES(azul_puntos), rojo_puntos=VALUES(rojo_puntos), observaciones=VALUES(observaciones)";
      if ($st=$conexion->prepare($sql)) {
        $st->bind_param('iiiiss',$pelea_id,$juez_id,$round,$az,$ro,$obs);
        if ($st->execute()) $msg='Round guardado.'; else $err='No se pudo guardar el round.';
        $st->close();
      } else { $err='Error interno (prep).'; }
    }
  }
  if ($accion==='enviar_resultado') {
    $totA=0; $totR=0; $det=[];
    if ($st=$conexion->prepare("SELECT `round`,azul_puntos,rojo_puntos FROM `puntuaciones_jueces` WHERE pelea_id=? AND juez_id=? ORDER BY `round`")){
      $st->bind_param('ii',$pelea_id,$juez_id); $st->execute();
      $r=$st->get_result(); while($row=$r->fetch_assoc()){ $det[]=$row; $totA+=(int)$row['azul_puntos']; $totR+=(int)$row['rojo_puntos']; }
      $st->close();
    }
    if (!$det){
      $err='Cargá al menos un round antes de enviar el resultado.';
    } else {
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

/* === Info visual opcional de la pelea (nombres de esquinas) === */
$azul='AZUL'; $rojo='ROJO';
$hasPe = $conexion->query("SHOW TABLES LIKE 'peleas_evento'");
if ($hasPe && $hasPe->num_rows) {
  $colA = null; $colR = null;
  foreach(['azul_nombre','competidor_a'] as $c){ $r=$conexion->query("SHOW COLUMNS FROM `peleas_evento` LIKE '$c'"); if($r && $r->num_rows) { $colA=$c; break; } }
  foreach(['rojo_nombre','competidor_b'] as $c){ $r=$conexion->query("SHOW COLUMNS FROM `peleas_evento` LIKE '$c'"); if($r && $r->num_rows) { $colR=$c; break; } }
  if ($colA || $colR) {
    $sql="SELECT ".($colA?"$colA AS a":"NULL AS a").",".($colR?"$colR AS r":"NULL AS r")." FROM `peleas_evento` WHERE id=? LIMIT 1";
    if ($st=$conexion->prepare($sql)){
      $st->bind_param('i',$pelea_id); $st->execute();
      if ($res=$st->get_result()->fetch_assoc()){
        if (!empty($res['a'])) $azul = trim($res['a']);
        if (!empty($res['r'])) $rojo = trim($res['r']);
      }
      $st->close();
    }
  }
}

/* === Traer rounds ya cargados para tabla === */
$puntajes=[]; $totalAz=0; $totalRo=0;
if ($st=$conexion->prepare("SELECT `round`,azul_puntos,rojo_puntos,observaciones,updated_at FROM `puntuaciones_jueces` WHERE pelea_id=? AND juez_id=? ORDER BY `round` ASC")){
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
    .wrap{max-width:740px;margin:6vh auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:16px}
    .muted{color:#9ecbff}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    input,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer}
    .btn.gray{background:#1b2836;border-color:#2b3c4f}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left;font-size:14px}
    th{color:#9ecbff}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
    .quick{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
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

      <?php if ($mostrar_aviso_no_asignada): ?>
        <div class="bad">⚠️ Nota: esta pelea no está asignada a tu usuario, pero se permite puntuar.</div>
      <?php endif; ?>

      <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

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
        <div class="quick">
          <button type="button" class="btn" onclick="setScore(10,9)">10–9 Azul</button>
          <button type="button" class="btn" onclick="setScore(9,10)">9–10 Rojo</button>
          <button type="button" class="btn" onclick="setScore(10,8)">10–8 Azul</button>
          <button type="button" class="btn" onclick="setScore(8,10)">8–10 Rojo</button>
          <button type="button" class="btn gray" onclick="setScore(10,10)">10–10</button>
        </div>
        <label style="margin-top:8px">Observaciones (opcional)</label>
        <textarea name="observaciones" rows="2" placeholder="Advertencias, penalidades…"></textarea>
        <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap">
