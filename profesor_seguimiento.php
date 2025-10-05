<?php
// profesor_seguimiento.php — Seguimiento de alumnos (profesor)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_horizontal.php'; // o tu menú del profesor

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id   = (int)($_SESSION['gimnasio_id'] ?? 0);
$profesor_id   = (int)($_SESSION['profesor_id'] ?? 0) ?: null; // opcional
if ($gimnasio_id <= 0) { http_response_code(403); exit('Acceso restringido.'); }

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function db_prepare(mysqli $cx, string $sql): mysqli_stmt {
  $stmt = $cx->prepare($sql);
  if (!$stmt) { echo "<pre>SQL ERROR\n".$cx->error."\n".$sql."</pre>"; exit; }
  return $stmt;
}
// Normaliza pasos por nivel usando fallback
function normalize_levels(?array $byLevel, ?array $fallback): array {
  $fallback = is_array($fallback)&&$fallback ? $fallback : ['Series x repeticiones','Descanso 60–90s','Registrar carga'];
  $L = ['principiante','medio','avanzado']; $out=[];
  foreach($L as $k){ $v = $byLevel[$k] ?? null; if (!is_array($v)||!$v) $v=$fallback; $out[$k]=array_values(array_map('strval',$v)); }
  return $out;
}

/* Comentario del profesor */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_comment'], $_POST['log_id'])) {
  $log_id = (int)$_POST['log_id'];
  $coment = trim($_POST['comentario'] ?? '');
  if ($coment !== '') {
    // validación de pertenencia al gimnasio
    $chk = db_prepare($conexion, "SELECT l.id FROM rutina_logs l WHERE l.id=? LIMIT 1");
    $chk->bind_param('i', $log_id); $chk->execute();
    $own = $chk->get_result()->fetch_assoc(); $chk->close();
    if ($own) {
      $stmt = db_prepare($conexion, "INSERT INTO rutina_log_comentarios (log_id, profesor_id, comentario) VALUES (?,?,?)");
      if ($profesor_id) { $stmt->bind_param('iis', $log_id, $profesor_id, $coment); }
      else { $null = null; $stmt->bind_param('iis', $log_id, $null, $coment); }
      $stmt->execute(); $stmt->close();
      $_SESSION['flash_prof'] = '✅ Comentario agregado.';
    }
  }
  $back = $_SERVER['HTTP_REFERER'] ?? 'profesor_seguimiento.php';
  header('Location: '.$back); exit;
}

/* Búsqueda de clientes del gimnasio */
$q = trim($_GET['q'] ?? '');
$sel_cliente = (int)($_GET['c'] ?? 0);

$clientes = [];
if ($q !== '') {
  // Buscar por nombre/apellido/dni
  $w = "%$q%";
  $stmt = db_prepare($conexion, "
    SELECT id, COALESCE(NULLIF(CONCAT_WS(' ', nombre, apellido), ''), nombre, apellido, CAST(dni AS CHAR)) AS nombre,
           dni
    FROM clientes
    WHERE gimnasio_id = ? AND (
      nombre LIKE ? OR apellido LIKE ? OR CAST(dni AS CHAR) LIKE ?
    )
    ORDER BY nombre ASC
    LIMIT 50
  ");
  $stmt->bind_param('isss', $gimnasio_id, $w, $w, $w);
  $stmt->execute();
  $res = $stmt->get_result();
  while($r = $res->fetch_assoc()) $clientes[] = $r;
  $stmt->close();
}

/* Si hay cliente seleccionado, traer historial */
$logs = [];
$rutinas_cache = []; // maquina_id => ['general'=>[], 'niveles'=>[]]
$comentarios = [];   // log_id => [ ... ]

if ($sel_cliente > 0) {
  // Historial del cliente (últimos 120 registros)
  $stmt = db_prepare($conexion, "
    SELECT l.*, m.nombre AS maquina_nombre
    FROM rutina_logs l
    JOIN maquinas_gym m ON m.id = l.maquina_id
    WHERE l.gimnasio_id = ? AND l.cliente_id = ?
    ORDER BY l.creada_en DESC
    LIMIT 120
  ");
  $stmt->bind_param('ii', $gimnasio_id, $sel_cliente);
  $stmt->execute();
  $res = $stmt->get_result();
  $machine_ids = [];
  while($r = $res->fetch_assoc()){
    $logs[] = $r;
    $machine_ids[(int)$r['maquina_id']] = true;
  }
  $stmt->close();

  if ($machine_ids) {
    $ids = implode(',', array_map('intval', array_keys($machine_ids)));
    $sql = "SELECT maquina_id, pasos_json, pasos_por_nivel_json FROM rutinas_maquina WHERE maquina_id IN ($ids)";
    $res = $conexion->query($sql);
    while($r = $res->fetch_assoc()){
      $rutinas_cache[(int)$r['maquina_id']] = [
        'general' => json_decode($r['pasos_json'] ?: '[]', true) ?: [],
        'niveles' => json_decode($r['pasos_por_nivel_json'] ?: 'null', true) ?: null,
      ];
    }

    // Comentarios por lote
    $log_ids = implode(',', array_map('intval', array_column($logs,'id')));
    if ($log_ids!=='') {
      $res = $conexion->query("SELECT * FROM rutina_log_comentarios WHERE log_id IN ($log_ids) ORDER BY creada_en ASC");
      while($c = $res->fetch_assoc()){ $comentarios[(int)$c['log_id']][] = $c; }
    }
  }
}

// Flash
$flash = $_SESSION['flash_prof'] ?? null; unset($_SESSION['flash_prof']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Seguimiento de alumnos — Profesor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --bg:#0b1220; --card:#0f172a; --mut:#94a3b8; --line:#1f2937; --acc:#22d3ee; --ok:#22c55e; }
    *{ box-sizing:border-box }
    body{ margin:0; background:var(--bg); color:#e5e7eb; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
    .wrap{ max-width:1100px; margin:0 auto; padding:20px; }
    h1{ margin:6px 0 14px; font-size:1.6rem; }
    .card{ background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px; margin-bottom:16px; }
    .row{ display:grid; grid-template-columns: 1fr auto; gap:10px }
    @media (max-width:700px){ .row{ grid-template-columns:1fr; } }
    input[type="text"]{ width:100%; padding:10px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#e5e7eb; }
    button, .btn{ background:var(--acc); border:0; color:#111; font-weight:800; padding:10px 14px; border-radius:10px; cursor:pointer; text-decoration:none; display:inline-block; }
    .mut{ color:var(--mut) }
    .badge{ display:inline-block; padding:3px 8px; border-radius:999px; background:#1f2937; font-size:.8rem; }
    .tag{ display:inline-block; padding:3px 8px; border-radius:6px; background:#111827; border:1px solid #334155; font-size:.78rem; margin-right:6px }
    .steps{ margin-top:6px; padding-left:14px; }
    .steps li{ margin:2px 0; }
    textarea{ width:100%; padding:10px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#e5e7eb; }
    .small{ font-size:.9rem }
    .ok{ color:var(--ok) }
    .grid2{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    @media (max-width:900px){ .grid2{ grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>📈 Seguimiento de alumnos</h1>

    <?php if ($flash): ?>
      <div class="card" style="border:1px solid var(--acc)"><?php echo h($flash); ?></div>
    <?php endif; ?>

    <div class="card">
      <form method="get" class="row" action="profesor_seguimiento.php">
        <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Buscar por nombre o DNI…">
        <button type="submit">Buscar</button>
      </form>
      <?php if ($q !== ''): ?>
        <div class="mut small" style="margin-top:8px">Resultados de: <strong><?php echo h($q); ?></strong></div>
        <?php if ($clientes): ?>
          <ul style="list-style:none;padding:0;margin-top:10px">
            <?php foreach ($clientes as $c): ?>
              <li style="padding:8px 0;border-bottom:1px solid #1f2937">
                <a class="btn" href="?q=<?php echo urlencode($q); ?>&c=<?php echo (int)$c['id']; ?>">Ver historial</a>
                <span style="margin-left:8px"><strong><?php echo h($c['nombre']); ?></strong></span>
                <?php if (!empty($c['dni'])): ?><span class="mut"> — DNI: <?php echo h($c['dni']); ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="mut">Sin coincidencias.</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if ($sel_cliente > 0): ?>
      <div class="card">
        <h2 style="margin:0 0 10px">Historial del alumno</h2>
        <?php if (empty($logs)): ?>
          <div class="mut">No hay registros todavía.</div>
        <?php else: ?>
          <div class="grid2">
          <?php foreach ($logs as $L):
            $niv = $L['nivel'];
            $pasos_idx = json_decode($L['pasos_hechos_json'] ?? '[]', true) ?: [];
            $mid = (int)$L['maquina_id'];

            $general = $rutinas_cache[$mid]['general'] ?? [];
            $porNivel = $rutinas_cache[$mid]['niveles'] ?? null;
            $niveles = normalize_levels($porNivel, $general);
            $pasos_nivel = $niveles[$niv] ?? $general;

            // prepara lista de pasos marcados
            $done_texts = [];
            foreach ($pasos_idx as $i) {
              if (isset($pasos_nivel[$i])) $done_texts[] = $pasos_nivel[$i];
            }

            $my_comments = $comentarios[(int)$L['id']] ?? [];
          ?>
            <div class="card" style="margin:0">
              <div class="small mut"><?php echo h($L['creada_en']); ?></div>
              <div style="margin:4px 0 6px">
                <span class="badge"><?php echo h($L['maquina_nombre']); ?></span>
                <span class="tag">Nivel: <?php echo h(ucfirst($L['nivel'])); ?></span>
                <?php if ($L['rpe']): ?><span class="tag">RPE: <?php echo (int)$L['rpe']; ?></span><?php endif; ?>
                <?php if ($L['tiempo_min']): ?><span class="tag">Tiempo: <?php echo (int)$L['tiempo_min']; ?> min</span><?php endif; ?>
              </div>
              <?php if (!empty($L['notas_cliente'])): ?>
                <div class="small" style="margin:6px 0"><strong>Notas del cliente:</strong> <?php echo nl2br(h($L['notas_cliente'])); ?></div>
              <?php endif; ?>

              <?php if ($done_texts): ?>
                <div class="small" style="margin-top:8px"><strong>Pasos realizados:</strong></div>
                <ul class="steps">
                  <?php foreach ($done_texts as $txt): ?>
                    <li>✔️ <?php echo h($txt); ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="mut small" style="margin-top:8px">Sin pasos marcados.</div>
              <?php endif; ?>

              <?php if ($my_comments): ?>
                <div class="small" style="margin-top:8px"><strong>Comentarios del profesor</strong></div>
                <ul class="steps">
                  <?php foreach ($my_comments as $cm): ?>
                    <li>🗒️ <?php echo nl2br(h($cm['comentario'])); ?> <span class="mut">— <?php echo h($cm['creada_en']); ?></span></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <form method="post" class="small" style="margin-top:10px">
                <input type="hidden" name="add_comment" value="1">
                <input type="hidden" name="log_id" value="<?php echo (int)$L['id']; ?>">
                <label>Agregar comentario</label>
                <textarea name="comentario" rows="2" placeholder="Observaciones, correcciones, progresión sugerida…" required></textarea>
                <div style="margin-top:8px"><button type="submit">Guardar</button></div>
              </form>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
