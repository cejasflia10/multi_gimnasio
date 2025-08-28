<?php
/* ================= DEBUG (apagar en producción si querés) ================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);
/* ========================================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/conexion.php';
@include __DIR__.'/menu_horizontal.php'; // opcional

/* ---------- Conexión ---------- */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit("<div style='color:#ff6b6b;padding:12px;text-align:center'>❌ No hay conexión a la base de datos.</div>");
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 2, ',', '.'); }

/* ---------- Tablas de apoyo ---------- */
/* Historial de pagos de planes de cada gimnasio (sin FKs para evitar errores si no existen) */
$conexion->query("
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
    INDEX (gimnasio_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ---------- Acciones POST ---------- */

/* Registrar pago + extender vencimiento (robusto y seguro ante '0000-00-00') */
if (isset($_POST['act']) && $_POST['act'] === 'registrar_pago') {
  // Normalizaciones / validaciones
  $gymId = (int)($_POST['gimnasio_id'] ?? 0);

  // Fecha: si no viene válida YYYY-MM-DD, usar hoy
  $fecha_raw = trim($_POST['fecha_pago'] ?? '');
  $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw) ? $fecha_raw : date('Y-m-d');

  // Monto: acepta "1.234,56", "1234,56" o "1234.56"
  $monto_raw = trim($_POST['monto'] ?? '0');
  $monto_norm = str_replace(['.', ','], ['', '.'], $monto_raw);
  if (!is_numeric($monto_norm)) { $monto_norm = '0'; }
  $monto = (float)$monto_norm;

  $metodo = trim($_POST['metodo'] ?? 'Transferencia');
  $ref    = trim($_POST['referencia'] ?? '');
  $meses  = (int)($_POST['meses'] ?? 1); if ($meses < 0) $meses = 0;
  $obs    = trim($_POST['observaciones'] ?? '');

  if ($gymId <= 0) {
    $_SESSION['flash_err'] = "❌ Gimnasio inválido (ID vacío).";
    header("Location: panel_gimnasios.php"); exit;
  }
  if ($monto < 0) {
    $_SESSION['flash_err'] = "❌ Monto inválido.";
    header("Location: panel_gimnasios.php"); exit;
  }

  // Insert pago
  $stmt = $conexion->prepare("
    INSERT INTO gimnasios_pagos
      (gimnasio_id, fecha_pago, monto, metodo, referencia, meses, observaciones)
    VALUES (?,?,?,?,?,?,?)
  ");
  if (!$stmt) {
    $_SESSION['flash_err'] = "❌ No se pudo preparar INSERT pago: ".$conexion->error;
    header("Location: panel_gimnasios.php"); exit;
  }
  if (!$stmt->bind_param('isdssis', $gymId, $fecha, $monto, $metodo, $ref, $meses, $obs)) {
    $_SESSION['flash_err'] = "❌ Error bind_param INSERT pago: ".$stmt->error;
    $stmt->close(); header("Location: panel_gimnasios.php"); exit;
  }
  if (!$stmt->execute() || $stmt->affected_rows <= 0) {
    $_SESSION['flash_err'] = "❌ No se insertó el pago: ".$stmt->error;
    $stmt->close(); header("Location: panel_gimnasios.php"); exit;
  }
  $stmt->close();

  // Extender fecha_vencimiento si corresponde (evita 0000-00-00 con STR_TO_DATE + NULLIF(CONCAT...))
  if ($meses > 0) {
    $sqlUp = "
      UPDATE gimnasios
      SET fecha_vencimiento = DATE_ADD(
        CASE
          WHEN COALESCE(
                 STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d'),
                 DATE('1000-01-01')
               ) < CURDATE()
            THEN CURDATE()
          ELSE STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d')
        END, INTERVAL ? MONTH
      )
      WHERE id = ?
    ";
    $st2 = $conexion->prepare($sqlUp);
    if (!$st2) {
      $_SESSION['flash_err'] = "⚠️ Pago guardado, pero no se pudo actualizar vencimiento: ".$conexion->error;
      header("Location: panel_gimnasios.php"); exit;
    }
    if (!$st2->bind_param('ii', $meses, $gymId)) {
      $_SESSION['flash_err'] = "⚠️ Pago guardado, error bind_param en UPDATE de vencimiento: ".$st2->error;
      $st2->close(); header("Location: panel_gimnasios.php"); exit;
    }
    if (!$st2->execute()) {
      $_SESSION['flash_err'] = "⚠️ Pago guardado, pero falló UPDATE de vencimiento: ".$st2->error;
      $st2->close(); header("Location: panel_gimnasios.php"); exit;
    }
    $st2->close();
  }

  $_SESSION['flash_ok'] = "✅ Pago registrado".($meses>0 ? " y vencimiento +{$meses} mes/es" : "");
  header("Location: panel_gimnasios.php"); exit;
}

/* Cambiar estado del gimnasio */
if (isset($_POST['act']) && $_POST['act']==='cambiar_estado') {
  $gymId = (int)($_POST['gimnasio_id'] ?? 0);
  $estado = trim($_POST['estado'] ?? '');
  if ($gymId>0 && in_array($estado, ['activo','vencido','suspendido'], true)) {
    $st = $conexion->prepare("UPDATE gimnasios SET estado=? WHERE id=?");
    $st->bind_param('si', $estado, $gymId);
    $st->execute();
    $st->close();
    $_SESSION['flash_ok'] = "✅ Estado actualizado a: {$estado}";
  } else {
    $_SESSION['flash_err'] = "❌ Estado inválido.";
  }
  header("Location: panel_gimnasios.php"); exit;
}

/* Sincronizar permisos con plan (borra overrides y copia del plan) */
if (isset($_POST['act']) && $_POST['act']==='sync_plan') {
  $gymId = (int)($_POST['gimnasio_id'] ?? 0);
  // plan actual
  $planId = 0;
  if ($st = $conexion->prepare("SELECT plan_id FROM gimnasios WHERE id=? LIMIT 1")) {
    $st->bind_param('i', $gymId);
    $st->execute();
    $st->bind_result($planId);
    $st->fetch();
    $st->close();
  }
  if ($gymId>0 && $planId>0) {
    $conexion->query("DELETE FROM gimnasios_permisos WHERE gimnasio_id = {$gymId}");
    $sql = "
      INSERT INTO gimnasios_permisos (gimnasio_id, feature, enabled)
      SELECT ?, pp.feature, pp.enabled
      FROM plan_permisos pp
      WHERE pp.plan_id = ?
      ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)
    ";
    if ($st = $conexion->prepare($sql)) {
      $st->bind_param('ii', $gymId, $planId);
      $st->execute();
      $st->close();
    }
    if (function_exists('refresh_permissions')) { refresh_permissions($gymId); }
    $_SESSION['flash_ok'] = "♻️ Permisos sincronizados con el plan.";
  } else {
    $_SESSION['flash_err'] = "❌ No se pudo sincronizar (plan inexistente).";
  }
  header("Location: panel_gimnasios.php"); exit;
}

/* ---------- Filtros GET ---------- */
$q        = trim($_GET['q'] ?? '');
$estadoF  = trim($_GET['estado'] ?? '');
$planF    = (int)($_GET['plan_id'] ?? 0);
$vfrom    = trim($_GET['vfrom'] ?? '');
$vto      = trim($_GET['vto'] ?? '');
$porvF    = (int)($_GET['por_vencer_dias'] ?? 0); // ej. 15 => <= 15 días

/* ---------- Datos auxiliares: planes ---------- */
$planes_rs = $conexion->query("SELECT id, nombre FROM planes_gimnasio ORDER BY nombre");
$planes_map = [];
if ($planes_rs) {
  while ($r = $planes_rs->fetch_assoc()) {
    $planes_map[(int)$r['id']] = $r['nombre'];
  }
  $planes_rs->free();
}

/* ---------- Métricas ---------- */
$metrics = [
  'activos'     => 0,
  'vencidos'    => 0,
  'suspendidos' => 0,
  'por_vencer15'=> 0,
  'pagos30'     => 0.0,
];

$cntRs = $conexion->query("SELECT estado, COUNT(*) c FROM gimnasios GROUP BY estado");
if ($cntRs) {
  while ($r=$cntRs->fetch_assoc()) {
    $estado = $r['estado'] ?? '';
    $c = (int)$r['c'];
    if ($estado==='activo')     $metrics['activos']     = $c;
    if ($estado==='vencido')    $metrics['vencidos']    = $c;
    if ($estado==='suspendido') $metrics['suspendidos'] = $c;
  }
  $cntRs->free();
}

/* Por vencer en ≤15 días – usando STR_TO_DATE + NULLIF(CONCAT(...)) */
$pvRs = $conexion->query("
  SELECT COUNT(*) c
  FROM gimnasios
  WHERE STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') IS NOT NULL
    AND DATEDIFF(STR_TO_DATE(NULLIF(CONCAT(fecha_vencimiento),'0000-00-00'),'%Y-%m-%d'), CURDATE()) BETWEEN 0 AND 15
");
if ($pvRs && $row=$pvRs->fetch_assoc()) {
  $metrics['por_vencer15'] = (int)$row['c'];
  $pvRs->free();
}

/* Pagos últimos 30 días */
$pgRs = $conexion->query("
  SELECT COALESCE(SUM(monto),0) s
  FROM gimnasios_pagos
  WHERE fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
if ($pgRs && $row=$pgRs->fetch_assoc()) {
  $metrics['pagos30'] = (float)$row['s'];
  $pgRs->free();
}

/* ---------- Query listado con filtros (todas las fechas seguras) ---------- */
$where = [];
if ($q !== '') {
  $qesc = $conexion->real_escape_string($q);
  $where[] = "(g.nombre LIKE '%{$qesc}%' OR g.email LIKE '%{$qesc}%' OR g.telefono LIKE '%{$qesc}%')";
}
if (in_array($estadoF, ['activo','vencido','suspendido'], true)) {
  $where[] = "g.estado = '".$conexion->real_escape_string($estadoF)."'";
}
if ($planF > 0) {
  $where[] = "g.plan_id = {$planF}";
}
if ($vfrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $vfrom)) {
  $where[] = "STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') >= '".$conexion->real_escape_string($vfrom)."'";
}
if ($vto !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $vto)) {
  $where[] = "STR_TO_DATE(NULLIF(CONCAT(g.fecha_vencimiento),'0000-00-00'),'%Y-%m-%d') <= '".$conexion->real_escape_string($vto)."'";
}
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
    (
      SELECT COALESCE(SUM(monto),0) FROM gimnasios_pagos gp WHERE gp.gimnasio_id = g.id
    ) AS total_pagado
  FROM gimnasios g
  LEFT JOIN planes_gimnasio p ON p.id = g.plan_id
  {$cond}
  ORDER BY
    (g.estado='activo') DESC,
    (fv_date IS NULL),
    fv_date ASC,
    g.nombre ASC
";
$listado = $conexion->query($sql);
if (!$listado) { die("Error al listar: ".$conexion->error); }

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel de Gimnasios</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#000; --fg:gold; --card:#0f1115; --muted:#a0a7b4; --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444; --accent:#a00;
    --line:#2a2f3a;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:Arial,Helvetica,sans-serif}
  .wrap{max-width:1200px;margin:0 auto;padding:16px}
  h1{margin:10px 0 6px}
  .flash{padding:10px 12px;border-radius:8px;margin:10px 0}
  .ok{background:#052b18;color:#b7f7cf;border:1px solid #1f7848}
  .err{background:#2b0505;color:#ffb4b4;border:1px solid #782828}

  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:14px 0}
  .card{background:var(--card);padding:14px;border-radius:12px;border:1px solid var(--line)}
  .kpi{font-size:28px;font-weight:bold}
  .kpi-sub{color:var(--muted);font-size:12px;margin-top:4px}

  .filters{display:grid;grid-template-columns: repeat(6,1fr);gap:8px;margin:12px 0}
  .filters input,.filters select{padding:8px;border-radius:8px;border:1px solid var(--line);background:#111;color:var(--fg)}

  table{width:100%;border-collapse:collapse;margin-top:12px}
  th,td{border:1px solid var(--line);padding:8px;vertical-align:top}
  th{background:#141824}
  td .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px}
  .pill.ok{background:#063f2a;color:#b7f7cf;border:1px solid #1e7f56}
  .pill.warn{background:#3f2f06;color:#ffe79b;border:1px solid #7f651e}
  .pill.bad{background:#3f0606;color:#ffc2c2;border:1px solid #7f1e1e}
  details summary{cursor:pointer;color:#ddd}
  .btn{display:inline-block;padding:6px 10px;border-radius:8px;border:1px solid var(--line);background:#1a1f2b;color:#fff;text-decoration:none}
  .btn:hover{background:#21293a}
  .btn.red{background:#3a1212}
  .btn.gold{background:#503000}
  form.inline{display:inline}
  form input, form select, form textarea{width:100%;padding:8px;border-radius:8px;border:1px solid var(--line);background:#111;color:var(--fg);margin:4px 0}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
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
    <div class="card">
      <div class="kpi"><?= (int)$metrics['activos'] ?></div>
      <div class="kpi-sub">Activos</div>
    </div>
    <div class="card">
      <div class="kpi"><?= (int)$metrics['por_vencer15'] ?></div>
      <div class="kpi-sub">Por vencer ≤ 15 días</div>
    </div>
    <div class="card">
      <div class="kpi"><?= (int)$metrics['vencidos'] ?></div>
      <div class="kpi-sub">Vencidos</div>
    </div>
    <div class="card">
      <div class="kpi">$<?= money($metrics['pagos30']) ?></div>
      <div class="kpi-sub">Pagos últimos 30 días</div>
    </div>
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
    <input type="date" name="vfrom" value="<?= h($vfrom) ?>" placeholder="Venc. desde">
    <input type="date" name="vto"   value="<?= h($vto)   ?>" placeholder="Venc. hasta">
    <select name="por_vencer_dias">
      <option value="0">Por vencer en…</option>
      <option value="7"  <?= $porvF===7?'selected':'';  ?>>≤ 7 días</option>
      <option value="15" <?= $porvF===15?'selected':''; ?>>≤ 15 días</option>
      <option value="30" <?= $porvF===30?'selected':''; ?>>≤ 30 días</option>
    </select>
    <div style="grid-column:1 / -1">
      <button class="btn" type="submit">🔎 Filtrar</button>
      <a class="btn" href="panel_gimnasios.php">🧹 Limpiar</a>
      <a class="btn gold" href="agregar_gimnasio.php">➕ Agregar Gimnasio</a>
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
        <th>Pagado (hist.)</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while($g = $listado->fetch_assoc()):
        $dias = is_null($g['dias_restantes']) ? null : (int)$g['dias_restantes'];
        $fv   = $g['fv_date']; // fecha válida o NULL
        $vencTxt = '—';
        $pill = '<span class="pill">'.'</span>';
        if (!empty($fv)) {
          $vencTxt = date('d/m/Y', strtotime($fv));
          if ($dias === null) {
            $pill = '<span class="pill">'.'</span>';
          } elseif ($dias < 0) {
            $pill = '<span class="pill bad">Vencido '.abs($dias).'d</span>';
          } elseif ($dias <= 7) {
            $pill = '<span class="pill warn">≤ 7d</span>';
          } elseif ($dias <= 15) {
            $pill = '<span class="pill warn">≤ 15d</span>';
          } else {
            $pill = '<span class="pill ok">'.$dias.'d</span>';
          }
        }
      ?>
      <tr>
        <td>
          <div style="font-weight:bold"><?= h($g['nombre']) ?></div>
          <div style="color:#bbb; font-size:12px">Alias: <?= h($g['alias'] ?? '—') ?> | CUIT: <?= h($g['cuit'] ?? '—') ?></div>
        </td>
        <td><?= h($g['nombre_plan'] ?? '—') ?></td>
        <td>
          <?php
            $est = $g['estado'] ?? '';
            $badge = $est==='activo' ? 'ok' : ($est==='vencido' ? 'bad' : 'warn');
          ?>
          <span class="pill <?= $badge ?>"><?= h(ucfirst($est ?: '—')) ?></span>
        </td>
        <td>
          <div><?= $vencTxt ?></div>
          <div><?= $pill ?></div>
        </td>
        <td>
          <div><?= h($g['email'] ?? '—') ?></div>
          <div style="color:#bbb"><?= h($g['telefono'] ?? '—') ?></div>
        </td>
        <td>$<?= money($g['total_pagado']) ?></td>
        <td style="min-width:260px">
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
                      <option>Transferencia</option>
                      <option>Efectivo</option>
                      <option>Débito</option>
                      <option>Crédito</option>
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

                  <div style="margin-top:10px">
                    <a class="btn" href="editar_gimnasio.php?id=<?= (int)$g['id'] ?>">✏️ Editar</a>
                    <a class="btn" href="renovar_gimnasio.php?id=<?= (int)$g['id'] ?>">🔁 Renovar</a>
                  </div>
                </div>
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
