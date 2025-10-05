<?php
// profesor_seguimiento.php — Panel profesor: ver historial de alumnos y comentar
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_horizontal.php'; // o tu menú de profesor

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$rol         = $_SESSION['rol'] ?? '';

if ($gimnasio_id<=0 || (!$profesor_id && $rol!=='admin')) {
  http_response_code(403);
  exit('Acceso restringido.');
}

/* Comentario del profesor */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_coment'])) {
  $log_id = (int)$_POST['log_id'];
  $coment = trim($_POST['comentario'] ?? '');
  if ($log_id>0 && $coment!=='') {
    $stmt = $conexion->prepare("INSERT INTO rutina_log_comentarios (log_id, profesor_id, comentario) VALUES (?,?,?)");
    $pid = $profesor_id ?: null;
    $stmt->bind_param('iis', $log_id, $pid, $coment);
    $stmt->execute(); $stmt->close();
  }
  header('Location: '.$_SERVER['REQUEST_URI']); exit;
}

/* Filtros */
$q = trim($_GET['q'] ?? '');                  // búsqueda por alumno (nombre/DNI o id)
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');
if ($desde==='' && $hasta==='') {
  // por defecto últimos 14 días
  $desde = date('Y-m-d', strtotime('-14 days'));
  $hasta = date('Y-m-d');
}

/* Resolver clientes */
$clientes = [];
$cliente_id = 0;
if ($q!=='') {
  if (ctype_digit($q)) {
    $cliente_id = (int)$q;
  } else {
    // Intento buscar por nombre en tabla clientes si existe
    $hasClientes = $conexion->query("SHOW TABLES LIKE 'clientes'")->num_rows>0;
    if ($hasClientes) {
      $stmt = $conexion->prepare("SELECT id, CONCAT_WS(' ', nombre, apellido) AS nom FROM clientes WHERE (nombre LIKE CONCAT('%',?,'%') OR apellido LIKE CONCAT('%',?,'%') OR dni LIKE CONCAT('%',?,'%')) AND gimnasio_id=? ORDER BY nom LIMIT 20");
      $stmt->bind_param('sssi', $q,$q,$q,$gimnasio_id);
      $stmt->execute(); $r=$stmt->get_result();
      while($row=$r->fetch_assoc()) $clientes[]=$row;
      $stmt->close();
      if (count($clientes)===1) $cliente_id = (int)$clientes[0]['id'];
    }
  }
}

/* Query historial */
$params = [$gimnasio_id, $desde, $hasta];
$types  = 'iss';
$where  = "WHERE rl.gimnasio_id=? AND DATE(rl.creada_en) BETWEEN ? AND ?";

if ($cliente_id>0) { $where .= " AND rl.cliente_id=?"; $params[]=$cliente_id; $types.='i'; }

$sql = "
  SELECT rl.*, m.nombre AS maquina_nombre
  FROM rutina_logs rl
  LEFT JOIN maquinas_gym m ON m.id=rl.maquina_id
  $where
  ORDER BY rl.creada_en DESC
  LIMIT 500
";
$stmt = $conexion->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Traer comentarios por lote */
$ids = array_column($logs,'id');
$comentarios = [];
if ($ids) {
  $in = implode(',', array_map('intval',$ids));
  $res = $conexion->query("SELECT * FROM rutina_log_comentarios WHERE log_id IN ($in) ORDER BY creada_en ASC");
  while($c=$res->fetch_assoc()) { $comentarios[$c['log_id']][]=$c; }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Seguimiento de alumnos — Profesor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --bg:#0b1220; --card:#0f172a; --muted:#94a3b8; --line:#1f2937; --acc:#22d3ee; --ink:#e5e7eb; }
    body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--ink); }
    .wrap{ max-width:1100px; margin:0 auto; padding:18px; }
    .card{ background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px; margin-bottom:16px; }
    .row{ display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; }
    @media (max-width: 900px){ .row{ grid-template-columns: 1fr; } }
    input,select,textarea{ width:100%; padding:10px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#e5e7eb; }
    button{ background:var(--acc); border:0; color:#111; font-weight:800; padding:10px 14px; border-radius:10px; cursor:pointer; }
    .muted{ color:var(--muted); font-size:.9rem; }
    .list .item{ border:1px solid var(--line); border-radius:14px; padding:12px; margin-bottom:10px; background:#0f172a; }
    .pill{ display:inline-block; padding:3px 8px; border-radius:999px; background:#1f2937; font-size:.8rem; margin-right:6px }
    .small{ font-size:.9rem; color:#cbd5e1; }
    .steps{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px }
    .chip{ background:#1e293b; padding:4px 8px; border-radius:999px; font-size:.85rem }
    .comments{ background:#0b1220; border:1px dashed #334155; padding:10px; border-radius:10px; margin-top:10px }
    .comment{ padding:6px 8px; border-bottom:1px solid #1f2937 }
    .comment:last-child{ border-bottom:0 }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Seguimiento de alumnos</h1>
    <div class="card">
      <form method="get">
        <div class="row">
          <div>
            <label>Alumno (ID o nombre/DNI)</label>
            <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Ej: 123 o Juan / 30123456">
            <?php if ($q!=='' && !$cliente_id && !empty($clientes)): ?>
              <div class="muted" style="margin-top:6px">
                Coincidencias:
                <?php foreach ($clientes as $c): ?>
                  <a href="?q=<?php echo (int)$c['id']; ?>&desde=<?php echo h($desde); ?>&hasta=<?php echo h($hasta); ?>" style="color:#22d3ee"><?php echo h($c['nom']); ?> (#<?php echo (int)$c['id']; ?>)</a>&nbsp;
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div>
            <label>Desde</label>
            <input type="date" name="desde" value="<?php echo h($desde); ?>">
          </div>
          <div>
            <label>Hasta</label>
            <input type="date" name="hasta" value="<?php echo h($hasta); ?>">
          </div>
        </div>
        <div style="margin-top:10px"><button type="submit">Buscar</button></div>
      </form>
      <div class="muted" style="margin-top:8px">Filtrado por gimnasio #<?php echo (int)$gimnasio_id; ?>.</div>
    </div>

    <div class="card list">
      <h2 style="margin:0 0 10px">Resultados</h2>
      <?php if (!$logs): ?>
        <div class="muted">Sin registros para los filtros dados.</div>
      <?php else: foreach ($logs as $L):
        $idx = json_decode($L['pasos_hechos_json'] ?? '[]', true) ?: [];
        $nivel = $L['nivel'];
      ?>
        <div class="item">
          <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap">
            <div>
              <div><strong><?php echo h($L['maquina_nombre'] ?? ('Máquina #'.$L['maquina_id'])); ?></strong></div>
              <div class="small">Cliente #<?php echo (int)$L['cliente_id']; ?> • <?php echo h($nivel); ?> • <?php echo h($L['creada_en']); ?></div>
            </div>
            <div>
              <?php if ($L['rpe']): ?><span class="pill">RPE <?php echo (int)$L['rpe']; ?></span><?php endif; ?>
              <?php if ($L['tiempo_min']): ?><span class="pill"><?php echo (int)$L['tiempo_min']; ?> min</span><?php endif; ?>
            </div>
          </div>

          <?php if ($idx): ?>
            <div class="steps">
              <?php foreach ($idx as $i): ?>
                <span class="chip">Paso #<?php echo (int)$i+1; ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (trim((string)$L['notas_cliente'])!==''): ?>
            <div class="small" style="margin-top:8px"><em>Notas del alumno:</em> <?php echo nl2br(h($L['notas_cliente'])); ?></div>
          <?php endif; ?>

          <div class="comments">
            <div><strong>Comentarios del profesor</strong></div>
            <div style="margin:6px 0">
              <?php if (!empty($comentarios[$L['id']])): foreach ($comentarios[$L['id']] as $C): ?>
                <div class="comment">
                  <div class="small"><?php echo h($C['creada_en']); ?> <?php if ($C['profesor_id']) echo '• Prof. #'.(int)$C['profesor_id']; ?></div>
                  <div><?php echo nl2br(h($C['comentario'])); ?></div>
                </div>
              <?php endforeach; else: ?>
                <div class="small muted">Sin comentarios aún.</div>
              <?php endif; ?>
            </div>
            <form method="post" style="margin-top:6px">
              <input type="hidden" name="log_id" value="<?php echo (int)$L['id']; ?>">
              <textarea name="comentario" rows="2" placeholder="Escribí una corrección/observación…" required></textarea>
              <div style="margin-top:6px"><button name="add_coment" value="1">Agregar comentario</button></div>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</body>
</html>
