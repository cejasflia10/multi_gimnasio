<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/conexion.php';
require_once __DIR__.'/permiso.php';
guardia_permiso();
@include __DIR__.'/menu_horizontal.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit("<div style='color:#ff6b6b;padding:12px;text-align:center'>❌ No hay conexión a la base de datos.</div>");
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 2, ',', '.'); }

/* ====== Utilidades seguras de esquema ====== */
function ensure_unique_index(mysqli $db, string $table, string $indexName, array $cols): void {
  $t = $db->real_escape_string($table);
  $i = $db->real_escape_string($indexName);
  $exists = false;
  if ($res = $db->query("SHOW INDEX FROM `$t` WHERE Key_name = '$i'")) {
    $exists = ($res->num_rows > 0);
    $res->free();
  }
  if (!$exists) {
    $colsEsc = array_map(fn($c)=>"`".str_replace("`","``",$c)."`", $cols);
    @ $db->query("ALTER TABLE `$t` ADD UNIQUE KEY `$i` (".implode(',', $colsEsc).")");
  }
}

function ensure_gimnasios_pagos_table(mysqli $db): void {
  @ $db->query("
    CREATE TABLE IF NOT EXISTS gimnasios_pagos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      gimnasio_id INT NOT NULL,
      fecha_pago DATE NOT NULL,
      monto DECIMAL(12,2) NOT NULL DEFAULT 0,
      metodo VARCHAR(32) NOT NULL DEFAULT 'Transferencia',
      referencia VARCHAR(128) DEFAULT NULL,
      meses INT NOT NULL DEFAULT 1,
      observaciones TEXT,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (gimnasio_id),
      INDEX (fecha_pago)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
}

/* ====== Asegurar índices/tablas sin romper en cada carga ====== */
ensure_unique_index($conexion, 'plan_permisos',      'uq_plan_feature', ['plan_id','feature']);
ensure_unique_index($conexion, 'gimnasios_permisos', 'uq_gym_feature',  ['gimnasio_id','feature']);
ensure_gimnasios_pagos_table($conexion);

/* ===== Acciones ===== */
if (isset($_POST['act']) && $_POST['act'] === 'registrar_pago') {
  $gymId = (int)($_POST['gimnasio_id'] ?? 0);
  $fecha_raw = trim($_POST['fecha_pago'] ?? '');
  $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw) ? $fecha_raw : date('Y-m-d');

  $monto_raw = trim($_POST['monto'] ?? '0');
  // Normaliza: "1.234,56" -> "1234.56"
  $monto_norm = str_replace(['.', ','], ['', '.'], $monto_raw);
  if (!is_numeric($monto_norm)) { $monto_norm = '0'; }
  $monto = (float)$monto_norm;

  $metodo = trim($_POST['metodo'] ?? 'Transferencia');
  $ref    = trim($_POST['referencia'] ?? '');
  $meses  = max(0, (int)($_POST['meses'] ?? 1));
  $obs    = trim($_POST['observaciones'] ?? '');

  if ($gymId <= 0) { $_SESSION['flash_err']="❌ Gimnasio inválido."; header("Location: panel_gimnasios.php"); exit; }
  if ($monto < 0)  { $_SESSION['flash_err']="❌ Monto inválido.";   header("Location: panel_gimnasios.php"); exit; }

  $conexion->begin_transaction();
  try {
    $stmt = $conexion->prepare("
      INSERT INTO gimnasios_pagos (gimnasio_id, fecha_pago, monto, metodo, referencia, meses, observaciones)
      VALUES (?,?,?,?,?,?,?)
    ");
    if (!$stmt) throw new Exception("Prep pagos: ".$conexion->error);
    // tipos: i s d s s i s
    if (!$stmt->bind_param('isdssis', $gymId, $fecha, $monto, $metodo, $ref, $meses, $obs)) throw new Exception("Bind pagos: ".$stmt->error);
    if (!$stmt->execute()) throw new Exception("Exec pagos: ".$stmt->error);
    $stmt->close();

    if ($meses > 0) {
      $sqlUp = "
        UPDATE gimnasios
        SET fecha_vencimiento = DATE_ADD(
          CASE
            WHEN COALESCE(STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d'), DATE('1000-01-01')) < CURDATE()
              THEN CURDATE()
            ELSE STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d')
          END, INTERVAL ? MONTH
        )
        WHERE id = ?
      ";
      $st2 = $conexion->prepare($sqlUp);
      if (!$st2) throw new Exception("Prep venc: ".$conexion->error);
      if (!$st2->bind_param('ii', $meses, $gymId)) throw new Exception("Bind venc: ".$st2->error);
      if (!$st2->execute()) throw new Exception("Exec venc: ".$st2->error);
      $st2->close();
    }

    $conexion->commit();
    $_SESSION['flash_ok'] = "✅ Pago registrado".($meses>0 ? " y vencimiento +{$meses} mes/es" : "");
  } catch (Throwable $e) {
    $conexion->rollback();
    $_SESSION['flash_err'] = "❌ No se pudo guardar: ".h($e->getMessage());
  }
  header("Location: panel_gimnasios.php"); exit;
}

if (isset($_POST['act']) && $_POST['act']==='cambiar_estado') {
  $gymId = (int)($_POST['gimnasio_id'] ?? 0);
  $estado = trim($_POST['estado'] ?? '');
  if ($gymId>0 && in_array($estado, ['activo','vencido','suspendido'], true)) {
    $st = $conexion->prepare("UPDATE gimnasios SET estado=? WHERE id=?");
    $st->bind_param('si', $estado, $gymId);
    $st->execute(); $st->close();
    $_SESSION['flash_ok'] = "✅ Estado actualizado a: {$estado}";
  } else {
    $_SESSION['flash_err'] = "❌ Estado inválido.";
  }
  header("Location: panel_gimnasios.php"); exit;
}

if (isset($_POST['act']) && $_POST['act']==='sync_plan') {
  $gymId = (int)($_POST['gimnasio_id'] ?? 0);
  $planId = 0;
  if ($st = $conexion->prepare("SELECT plan_id FROM gimnasios WHERE id=? LIMIT 1")) {
    $st->bind_param('i', $gymId);
    $st->execute(); $st->bind_result($planId); $st->fetch(); $st->close();
  }
  if ($gymId>0 && $planId>0) {
    // Limpiar overrides y copiar plan
    if (!$conexion->query("DELETE FROM gimnasios_permisos WHERE gimnasio_id = {$gymId}")) {
      $_SESSION['flash_err'] = "❌ No se pudo limpiar overrides: ".$conexion->error;
      header("Location: panel_gimnasios.php"); exit;
    }
    $sql = "
      INSERT INTO gimnasios_permisos (gimnasio_id, feature, enabled)
      SELECT ?, pp.feature, pp.enabled
      FROM plan_permisos pp
      WHERE pp.plan_id = ?
      ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)
    ";
    if ($st = $conexion->prepare($sql)) { $st->bind_param('ii', $gymId, $planId); $st->execute(); $st->close(); }
    if (function_exists('refresh_permissions')) { refresh_permissions($gymId); }
    $_SESSION['flash_ok'] = "♻️ Permisos sincronizados con el plan.";
  } else {
    $_SESSION['flash_err'] = "❌ No se pudo sincronizar (plan inexistente).";
  }
  header("Location: panel_gimnasios.php"); exit;
}

/* ===== Guardar overrides (TRI-ESTADO) ===== */
if (isset($_POST['act']) && $_POST['act']==='save_perms') {
  $gymId = (int)($_POST['gimnasio_id'] ?? 0);
  $state = isset($_POST['state']) && is_array($_POST['state']) ? $_POST['state'] : [];

  if ($gymId <= 0) { $_SESSION['flash_err'] = "❌ Gimnasio inválido."; header("Location: panel_gimnasios.php"); exit; }

  // normalizar
  $clean = [];
  foreach ($state as $feat => $val) {
    $f = substr(trim((string)$feat), 0, 64);
    $v = in_array($val, ['inherit','on','off'], true) ? $val : 'inherit';
    $clean[$f] = $v;
  }

  $conexion->begin_transaction();
  try {
    $del = $conexion->prepare("DELETE FROM gimnasios_permisos WHERE gimnasio_id=?");
    $del->bind_param('i', $gymId);
    if (!$del->execute()) throw new Exception($del->error);
    $del->close();

    $ins = $conexion->prepare("INSERT INTO gimnasios_permisos (gimnasio_id, feature, enabled) VALUES (?,?,?)
                               ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)");
    foreach ($clean as $f => $v) {
      if ($v === 'inherit') continue;
      $en = ($v === 'on') ? 1 : 0;
      $ins->bind_param('isi', $gymId, $f, $en);
      if (!$ins->execute()) throw new Exception($ins->error);
    }
    $ins->close();

    $conexion->commit();
    if (function_exists('refresh_permissions')) refresh_permissions($gymId);
    $_SESSION['flash_ok'] = "✅ Permisos guardados (gym #{$gymId}).";
  } catch (Throwable $e) {
    $conexion->rollback();
    $_SESSION['flash_err'] = "❌ Error guardando permisos: ".$e->getMessage();
  }
  header("Location: panel_gimnasios.php"); exit;
}

/* ===== Filtros ===== */
$q        = trim($_GET['q'] ?? '');
$estadoF  = trim($_GET['estado'] ?? '');
$planF    = (int)($_GET['plan_id'] ?? 0);
$vfrom    = trim($_GET['vfrom'] ?? '');
$vto      = trim($_GET['vto'] ?? '');
$porvF    = (int)($_GET['por_vencer_dias'] ?? 0);
$featCount = trim($_GET['feat_count'] ?? 'membresias');

/* planes */
$planes_rs = $conexion->query("SELECT id, nombre FROM planes_gimnasio ORDER BY nombre");
$planes_map = [];
if ($planes_rs) { while ($r = $planes_rs->fetch_assoc()) { $planes_map[(int)$r['id']] = $r['nombre']; } $planes_rs->free(); }

/* ============ KPIs (por fecha real) ============ */
$metrics = ['activos'=>0,'vencidos'=>0,'suspendidos'=>0,'por_vencer15'=>0,'pagos30'=>0.0,'feat_on'=>0];

/* Suspendidos (por estado explícito) */
if ($r = $conexion->query("SELECT COUNT(*) c FROM gimnasios WHERE estado='suspendido'")) {
  if ($row = $r->fetch_assoc()) $metrics['suspendidos'] = (int)$row['c'];
  $r->free();
}

/* Activos por fecha (no suspendidos) */
$activosSql = "
  SELECT COUNT(*) c
  FROM gimnasios
  WHERE estado <> 'suspendido'
    AND STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') IS NOT NULL
    AND STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') >= CURDATE()
";
/* Vencidos por fecha (no suspendidos) */
$vencidosSql = "
  SELECT COUNT(*) c
  FROM gimnasios
  WHERE estado <> 'suspendido'
    AND STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') IS NOT NULL
    AND STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') < CURDATE()
";
/* Por vencer en 15 días (no suspendidos) */
$porVencerSql = "
  SELECT COUNT(*) c
  FROM gimnasios
  WHERE estado <> 'suspendido'
    AND STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') IS NOT NULL
    AND DATEDIFF(STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d'), CURDATE()) BETWEEN 0 AND 15
";
foreach ([['activos',$activosSql], ['vencidos',$vencidosSql], ['por_vencer15',$porVencerSql]] as [$k,$sqlTmp]) {
  if ($res = $conexion->query($sqlTmp)) {
    if ($row = $res->fetch_assoc()) $metrics[$k] = (int)$row['c'];
    $res->free();
  }
}

/* Pagos últimos 30 días */
if ($pgRs = $conexion->query("SELECT COALESCE(SUM(monto),0) s FROM gimnasios_pagos WHERE fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")) {
  if ($row=$pgRs->fetch_assoc()) $metrics['pagos30'] = (float)$row['s'];
  $pgRs->free();
}

/* KPI de feature efectiva ON */
$featCountEsc = $conexion->real_escape_string($featCount);
$kpiSql = "
  SELECT COUNT(*) c FROM (
    SELECT g.id, COALESCE(gp.enabled, pp.enabled, 0) AS en
    FROM gimnasios g
    LEFT JOIN plan_permisos pp ON pp.plan_id = g.plan_id AND pp.feature = '{$featCountEsc}'
    LEFT JOIN gimnasios_permisos gp ON gp.gimnasio_id = g.id AND gp.feature = '{$featCountEsc}'
  ) x WHERE en = 1
";
if ($kpiRs = $conexion->query($kpiSql)) {
  if ($r=$kpiRs->fetch_assoc()) $metrics['feat_on'] = (int)$r['c'];
  $kpiRs->free();
}

/* ============ Listado ============ */
$where = [];
if ($q !== '') { $qesc = $conexion->real_escape_string($q); $where[] = "(g.nombre LIKE '%{$qesc}%' OR g.email LIKE '%{$qesc}%' OR g.telefono LIKE '%{$qesc}%')"; }
if (in_array($estadoF, ['activo','vencido','suspendido'], true)) { $where[] = "g.estado = '".$conexion->real_escape_string($estadoF)."'"; }
if ($planF > 0) { $where[] = "g.plan_id = {$planF}"; }
if ($vfrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $vfrom)) { $where[] = "STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') >= '".$conexion->real_escape_string($vfrom)."'"; }
if ($vto !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $vto))   { $where[] = "STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') <= '".$conexion->real_escape_string($vto)."'"; }
if ($porvF > 0) {
  $where[] = "STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') IS NOT NULL
              AND DATEDIFF(STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d'), CURDATE()) BETWEEN 0 AND {$porvF}";
}
$cond = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$sql = "
  SELECT
    g.*,
    p.nombre AS nombre_plan,
    STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') AS fv_date,
    DATEDIFF(STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d'), CURDATE()) AS dias_restantes,
    /* Pagos acumulados históricos */
    (SELECT COALESCE(SUM(monto),0) FROM gimnasios_pagos gp WHERE gp.gimnasio_id = g.id) AS total_pagado,
    /* Último pago */
    (SELECT MAX(fecha_pago) FROM gimnasios_pagos gp2 WHERE gp2.gimnasio_id = g.id) AS ultima_fecha_pago,
    /* Pagos últimos 12 meses */
    (SELECT COALESCE(SUM(monto),0) FROM gimnasios_pagos gp3 
      WHERE gp3.gimnasio_id = g.id AND gp3.fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    ) AS pagos_12m,
    /* Estado computado por fecha (prioriza suspendido) */
    CASE
      WHEN g.estado = 'suspendido' THEN 'suspendido'
      WHEN STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') IS NULL THEN 'vencido'
      WHEN STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') < CURDATE() THEN 'vencido'
      ELSE 'activo'
    END AS estado_computado
  FROM gimnasios g
  LEFT JOIN planes_gimnasio p ON p.id = g.plan_id
  {$cond}
  ORDER BY 
    (estado_computado='activo') DESC,
    (fv_date IS NULL), 
    fv_date ASC, 
    g.nombre ASC
";
$listado = $conexion->query($sql);
if (!$listado) { die("Error al listar: ".$conexion->error); }

/* helpers permisos */
function fetch_effective_enable(mysqli $db, int $gymId, int $planId): array {
  $eff = [];
  $sql = "
    SELECT f.feature, COALESCE(gp.enabled, pp.enabled, 0) AS effective,
           pp.enabled AS plan_enabled, gp.enabled AS gym_enabled
    FROM (
      SELECT feature FROM plan_permisos WHERE plan_id = ?
      UNION
      SELECT feature FROM gimnasios_permisos WHERE gimnasio_id = ?
    ) f
    LEFT JOIN plan_permisos pp ON pp.plan_id=? AND pp.feature=f.feature
    LEFT JOIN gimnasios_permisos gp ON gp.gimnasio_id=? AND gp.feature=f.feature
  ";
  $st = $db->prepare($sql);
  $st->bind_param('iiii',$planId,$gymId,$planId,$gymId);
  if ($st->execute()) {
    $rs = $st->get_result();
    while($r = $rs->fetch_assoc()){
      $feat = $r['feature'];
      $eff[$feat] = [
        'effective' => (int)$r['effective'],
        'plan'      => is_null($r['plan_enabled']) ? null : (int)$r['plan_enabled'],
        'override'  => is_null($r['gym_enabled'])  ? null : (int)$r['gym_enabled'],
      ];
    }
  }
  $st->close();
  if (function_exists('_perm_map')) {
    foreach (array_values(_perm_map()) as $feat) {
      if (!isset($eff[$feat])) $eff[$feat] = ['effective'=>0,'plan'=>null,'override'=>null];
    }
  }
  ksort($eff);
  return $eff;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel de Gimnasios</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#000; --fg:gold; --card:#0f1115; --muted:#a0a7b4; --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444;
    --line:#2a2f3a;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:Arial,Helvetica,sans-serif}
  .wrap{max-width:1200px;margin:0 auto;padding:16px}
  h1{margin:10px 0 6px}
  .flash{padding:10px 12px;border-radius:8px;margin:10px 0}
  .ok{background:#052b18;color:#b7f7cf;border:1px solid #1f7848}
  .err{background:#2b0505;color:#ffb4b4;border:1px solid #782828}

  .grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:14px 0}
  .card{background:var(--card);padding:14px;border-radius:12px;border:1px solid var(--line)}
  .kpi{font-size:28px;font-weight:bold}
  .kpi-sub{color:var(--muted);font-size:12px;margin-top:4px}

  .filters{display:grid;grid-template-columns: repeat(7,1fr);gap:8px;margin:12px 0}
  .filters input,.filters select{padding:8px;border-radius:8px;border:1px solid var(--line);background:#111;color:var(--fg)}

  table{width:100%;border-collapse:collapse;margin-top:12px}
  th,td{border:1px solid var(--line);padding:8px;vertical-align:top}
  th{background:#141824}
  td .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px}
  .pill.ok{background:#063f2a;color:#b7f7cf;border:1px solid #1e7f56}
  .pill.warn{background:#3f2f06;color:#ffe79b;border:1px solid #7f651e}
  .pill.bad{background:#3f0606;color:#ffc2c2;border:1px solid #7f1e1e}
  .chip{display:inline-block;padding:2px 6px;border-radius:6px;font-size:11px;border:1px solid var(--line);margin-left:6px;background:#18202f;color:#cbd5e1}
  .chip.on{background:#10341f;color:#b7f7cf;border-color:#1e7f56}
  .chip.off{background:#321313;color:#ffc2c2;border-color:#7f1e1e}
  details summary{cursor:pointer;color:#ddd}
  .btn{display:inline-block;padding:6px 10px;border-radius:8px;border:1px solid var(--line);background:#1a1f2b;color:#fff;text-decoration:none}
  .btn:hover{background:#21293a}
  form input, form select, form textarea{width:100%;padding:8px;border-radius:8px;border:1px solid var(--line);background:#111;color:var(--fg);margin:4px 0}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .grid-perms{display:grid;grid-template-columns:1fr;gap:6px}
  .perm-item{display:grid;grid-template-columns: 160px 1fr;align-items:center;gap:8px;padding:8px;border:1px solid var(--line);border-radius:8px;background:#121722}
  @media (max-width:1200px){ .grid{grid-template-columns:repeat(4,1fr)} .filters{grid-template-columns: repeat(3,1fr)} }
  @media (max-width:980px){
    .grid{grid-template-columns:repeat(2,1fr)}
    .filters{grid-template-columns: repeat(2,1fr)}
    .row{grid-template-columns:1fr}
  }
</style>
<script>
  function confirmSync(gym, plan){ return confirm(`¿Sincronizar permisos del gimnasio #${gym} con su plan? Se perderán overrides.`); }
</script>
</head>
<body>
<div class="wrap">

  <h1>📊 Panel de Gimnasios</h1>

  <?php if (!empty($_SESSION['flash_ok'])): ?>
    <div class="flash ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_err'])): ?>
    <div class="flash err"><?= h($_SESSION['flash_err']); unset($_SESSION['flash_err']); ?></div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="grid">
    <div class="card"><div class="kpi"><?= (int)($metrics['activos'] ?? 0) ?></div><div class="kpi-sub">Activos</div></div>
    <div class="card"><div class="kpi"><?= (int)($metrics['por_vencer15'] ?? 0) ?></div><div class="kpi-sub">Por vencer ≤ 15 días</div></div>
    <div class="card"><div class="kpi"><?= (int)($metrics['vencidos'] ?? 0) ?></div><div class="kpi-sub">Vencidos</div></div>
    <div class="card"><div class="kpi">$<?= money($metrics['pagos30']) ?></div><div class="kpi-sub">Pagos últimos 30 días</div></div>
    <div class="card"><div class="kpi"><?= (int)$metrics['feat_on'] ?></div><div class="kpi-sub">Gimnasios con “<?= h($featCount) ?>” habilitado</div></div>
  </div>

  <!-- Filtros -->
  <form method="GET" class="filters">
    <input type="text" name="q" placeholder="Buscar por nombre, email, teléfono" value="<?= h($q) ?>">
    <select name="estado">
      <option value="">Estado</option>
      <option value="activo"     <?= $estadoF==='activo'?'selected':''; ?>>Activo</option>
      <option value="vencido"    <?= $estadoF==='vencido'?'selected':''; ?>>Vencido</option>
      <option value="suspendido" <?= $estadoF==='suspendido'?'selected':''; ?>>Suspendido</option>
    </select>
    <select name="plan_id">
      <option value="0">Plan</option>
      <?php foreach ($planes_map as $pid => $pname): ?>
        <option value="<?= (int)$pid ?>" <?= $planF===$pid?'selected':''; ?>><?= h($pname) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="vfrom" value="<?= h($vfrom) ?>">
    <input type="date" name="vto"   value="<?= h($vto)   ?>">
    <select name="por_vencer_dias">
      <option value="0">Por vencer en…</option>
      <option value="7"  <?= $porvF===7?'selected':'';  ?>>≤ 7 días</option>
      <option value="15" <?= $porvF===15?'selected':''; ?>>≤ 15 días</option>
      <option value="30" <?= $porvF===30?'selected':''; ?>>≤ 30 días</option>
    </select>
    <select name="feat_count" title="Feature para KPI (efectiva)">
      <?php if (function_exists('_perm_map')) { $allFeatures = array_unique(array_values(_perm_map())); sort($allFeatures);
        foreach ($allFeatures as $F): ?>
          <option value="<?= h($F) ?>" <?= $featCount===$F?'selected':''; ?>>KPI: <?= h($F) ?></option>
        <?php endforeach;
      } ?>
      <option value="membresias" <?= $featCount==='membresias'?'selected':''; ?>>KPI: membresias</option>
    </select>
    <div style="grid-column:1 / -1">
      <button class="btn" type="submit">🔎 Filtrar</button>
      <a class="btn" href="panel_gimnasios.php">🧹 Limpiar</a>
      <a class="btn" href="agregar_gimnasio.php">➕ Agregar Gimnasio</a>
    </div>
  </form>

  <!-- Listado -->
  <table>
    <thead>
      <tr>
        <th>Gimnasio</th>
        <th>Plan</th>
        <th>Estado</th>
        <th>Vencimiento</th>
        <th>Contacto</th>
        <th>Pagos (hist.)</th>
        <th>Último pago</th>
        <th>Pagos 12m</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while($g = $listado->fetch_assoc()):
        $dias = is_null($g['dias_restantes']) ? null : (int)$g['dias_restantes'];
        $fv   = $g['fv_date'];
        $vencTxt = '—'; $pill = '<span class="pill"></span>';
        if (!empty($fv)) {
          $vencTxt = date('d/m/Y', strtotime($fv));
          if     ($dias === null) { $pill = '<span class="pill"></span>'; }
          elseif ($dias < 0)      { $pill = '<span class="pill bad">Vencido '.abs($dias).'d</span>'; }
          elseif ($dias <= 7)     { $pill = '<span class="pill warn">≤ 7d</span>'; }
          elseif ($dias <= 15)    { $pill = '<span class="pill warn">≤ 15d</span>'; }
          else                    { $pill = '<span class="pill ok">'.$dias.'d</span>'; }
        }

        $estComp = $g['estado_computado'] ?? ($g['estado'] ?? '');
        $al_dia  = ($estComp==='activo' && $dias !== null && $dias >= 0);
        $planId  = (int)($g['plan_id'] ?? 0);
        $gymId   = (int)$g['id'];
        $effMap  = fetch_effective_enable($conexion, $gymId, $planId);
      ?>
      <tr>
        <td>
          <div style="font-weight:bold"><?= h($g['nombre']) ?></div>
          <div style="color:#bbb; font-size:12px">
            Alias: <?= h($g['alias'] ?? '—') ?> | CUIT: <?= h($g['cuit'] ?? '—') ?>
            <?= $al_dia ? '<span class="chip on">Al día</span>' : '<span class="chip off">Con deuda/por vencer</span>' ?>
          </div>
        </td>
        <td><?= h($g['nombre_plan'] ?? '—') ?></td>
        <td>
          <?php $badge = $estComp==='activo' ? 'ok' : ($estComp==='vencido' ? 'bad' : 'warn'); ?>
          <span class="pill <?= $badge ?>"><?= h(ucfirst($estComp ?: '—')) ?></span>
        </td>
        <td><div><?= $vencTxt ?></div><div><?= $pill ?></div></td>
        <td>
          <div><?= h($g['email'] ?? '—') ?></div>
          <div style="color:#bbb"><?= h($g['telefono'] ?? '—') ?></div>
        </td>
        <td>$<?= money($g['total_pagado']) ?></td>
        <td><?= $g['ultima_fecha_pago'] ? h(date('d/m/Y', strtotime($g['ultima_fecha_pago']))) : '—' ?></td>
        <td>$<?= money($g['pagos_12m']) ?></td>
        <td style="min-width:360px">
          <details>
            <summary>⚙️ Acciones</summary>
            <div class="card" style="margin-top:8px">
              <div class="row">
                <div>
                  <div style="font-weight:bold;margin-bottom:4px">Registrar pago</div>
                  <form method="POST">
                    <input type="hidden" name="act" value="registrar_pago">
                    <input type="hidden" name="gimnasio_id" value="<?= (int)$g['id'] ?>">
                    <label>Fecha</label>
                    <input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>">
                    <label>Monto</label>
                    <input type="text" name="monto" placeholder="0,00">
                    <label>Método</label>
                    <select name="metodo">
                      <option>Transferencia</option><option>Efectivo</option>
                      <option>Débito</option><option>Crédito</option>
                    </select>
                    <label>Referencia</label>
                    <input type="text" name="referencia" placeholder="Comprobante/alias">
                    <label>Extender (meses)</label>
                    <input type="number" name="meses" value="1" min="0">
                    <label>Observaciones</label>
                    <textarea name="observaciones" placeholder="Notas internas"></textarea>
                    <button class="btn" type="submit">💾 Guardar pago</button>
                  </form>
                </div>

                <div>
                  <div style="font-weight:bold;margin-bottom:4px">Cambiar estado</div>
                  <form method="POST">
                    <input type="hidden" name="act" value="cambiar_estado">
                    <input type="hidden" name="gimnasio_id" value="<?= (int)$g['id'] ?>">
                    <select name="estado">
                      <option value="activo"     <?= ($g['estado']==='activo')?'selected':''; ?>>Activo</option>
                      <option value="vencido"    <?= ($g['estado']==='vencido')?'selected':''; ?>>Vencido</option>
                      <option value="suspendido" <?= ($g['estado']==='suspendido')?'selected':''; ?>>Suspendido</option>
                    </select>
                    <button class="btn" type="submit">🔄 Actualizar</button>
                  </form>

                  <div style="font-weight:bold;margin:10px 0 4px">Permisos / Plan</div>
                  <form method="POST" onsubmit="return confirmSync(<?= (int)$g['id'] ?>, '<?= h($g['nombre_plan'] ?? '') ?>')">
                    <input type="hidden" name="act" value="sync_plan">
                    <input type="hidden" name="gimnasio_id" value="<?= (int)$g['id'] ?>">
                    <button class="btn" type="submit">♻️ Sincronizar con plan</button>
                  </form>
                </div>
              </div>

              <!-- ===== Editor TRI-ESTADO ===== -->
              <div style="margin-top:14px">
                <div style="font-weight:bold;margin-bottom:6px">Editar permisos del gimnasio</div>
                <form method="POST">
                  <input type="hidden" name="act" value="save_perms">
                  <input type="hidden" name="gimnasio_id" value="<?= (int)$g['id'] ?>">

                  <div class="grid-perms">
                    <?php
                      foreach ($effMap as $feat => $meta):
                        $label = ucwords(str_replace('_',' ', $feat));
                        $pln = $meta['plan'];        // null|0|1
                        $ovr = $meta['override'];    // null|0|1
                        $eff = $meta['effective'];   // 0|1
                        $selected = 'inherit';
                        if ($ovr === 1) $selected = 'on';
                        if ($ovr === 0) $selected = 'off';
                        $chipPlan = is_null($pln) ? '<span class="chip">Plan: —</span>' : ('<span class="chip '.($pln? 'on':'off').'">Plan: '.($pln?'ON':'OFF').'</span>');
                        $chipEff  = '<span class="chip '.($eff? 'on':'off').'">Efectivo: '.($eff?'ON':'OFF').'</span>';
                    ?>
                    <div class="perm-item">
                      <div><strong><?= h($label) ?></strong> <?= $chipPlan ?> <?= $chipEff ?></div>
                      <div>
                        <select name="state[<?= h($feat) ?>]">
                          <option value="inherit" <?= $selected==='inherit'?'selected':''; ?>>Heredar del plan</option>
                          <option value="on"      <?= $selected==='on'?'selected':''; ?>>Forzar ON (override)</option>
                          <option value="off"     <?= $selected==='off'?'selected':''; ?>>Forzar OFF (bloquear)</option>
                        </select>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>

                  <div style="margin-top:8px">
                    <button class="btn" type="submit">💾 Guardar permisos</button>
                  </div>
                </form>
              </div>
              <!-- ===== FIN editor ===== -->

              <div style="margin-top:10px">
                <a class="btn" href="editar_gimnasio.php?id=<?= (int)$g['id'] ?>">✏️ Editar</a>
                <a class="btn" href="renovar_gimnasio.php?id=<?= (int)$g['id'] ?>">🔁 Renovar</a>
              </div>
            </div>
          </details>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

</div>
</body>
</html>
