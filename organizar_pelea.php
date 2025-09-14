<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_eventos.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function flash_err($msg){ $_SESSION['flash_error'] = $msg; }
function flash_ok($msg){ $_SESSION['flash_ok'] = $msg; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ====== evento_id del contexto ====== */
$evento_id = (int)($_GET['evento_id'] ?? $_SESSION['evento_id_actual'] ?? 0);
if ($evento_id <= 0) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">
          Falta <b>evento_id</b>. Abrí esta pantalla desde el evento (botón “Organizar peleas”).
        </div>';
  exit;
}
$_SESSION['evento_id_actual'] = $evento_id;

/* ==============
   Utilidades SQL
   ============== */
function pe_get_cols(mysqli $db){
  $res = $db->query("SHOW COLUMNS FROM peleas_evento");
  if (!$res) {
    return ['error' => 'No se pudo leer columnas de peleas_evento: '.$db->error];
  }
  $cols = [];
  while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r; }
  $find = function(array $cands) use ($cols){
    foreach ($cands as $c) if (isset($cols[strtolower($c)])) return $c;
    return null;
  };
  $map = [
    'id'      => $find(['id','pelea_id','id_pelea']),
    'evento'  => $find(['evento_id','id_evento','evento']),
    'rojo'    => $find(['rojo_id','id_rojo','competidor_rojo_id','id_competidor_rojo','rojo']),
    'azul'    => $find(['azul_id','id_azul','competidor_azul_id','id_competidor_azul','azul']),
    'rondas'  => $find(['rondas','rounds']),
    'obs'     => $find(['observaciones','obs','comentarios','comentario','nota']),
    'estado'  => $find(['estado','status']),
    'fecha'   => $find(['fecha','creado_en','created_at','created','fh_creacion']),
  ];
  $map['_all'] = array_keys($cols);
  return $map;
}
function bt($col){ return '`'.str_replace('`','``',$col).'`'; } // backtick seguro

$pe_cols = pe_get_cols($conexion);
if (isset($pe_cols['error'])) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">'
     . 'Error: '.h($pe_cols['error'])
     . '</div>';
  exit;
}
if (!$pe_cols['evento'] || !$pe_cols['rojo'] || !$pe_cols['azul']) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">'
     . 'La tabla <b>peleas_evento</b> existe, pero no encuentro las columnas obligatorias.<br>'
     . 'Necesito 3 columnas (con cualquiera de estos nombres):<br>'
     . '<ul>'
     . '<li>Evento: <code>evento_id</code> o <code>id_evento</code> o <code>evento</code></li>'
     . '<li>Rojo: <code>rojo_id</code> o <code>id_rojo</code> o <code>competidor_rojo_id</code> o <code>id_competidor_rojo</code> o <code>rojo</code></li>'
     . '<li>Azul: <code>azul_id</code> o <code>id_azul</code> o <code>competidor_azul_id</code> o <code>id_competidor_azul</code> o <code>azul</code></li>'
     . '</ul>'
     . 'Columnas detectadas: <code>'.h(implode(', ', $pe_cols['_all'])).'</code>'
     . '</div>';
  exit;
}

/* =========================
   POST: Guardar pelea(s)
   Formatos soportados:
   - simple: 1 vs 1 (usa rojo_id y azul_id)
   - triangular: 3 competidores, todos contra todos (3 peleas)
   - super4: 4 competidores, semifinales (2 peleas). La final se crea luego.
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $formato = $_POST['formato'] ?? 'simple';
  $rondas  = isset($_POST['rondas']) && is_numeric($_POST['rondas']) ? (int)$_POST['rondas'] : 3;
  $obsBase = isset($_POST['observaciones']) ? trim((string)$_POST['observaciones']) : '';
  if ($rondas < 1 || $rondas > 12) { $rondas = 3; }

  $colE = bt($pe_cols['evento']); $colR = bt($pe_cols['rojo']); $colA = bt($pe_cols['azul']);
  $pairs = []; // lista de [rojo_id, azul_id, obs_suffix]

  if ($formato === 'simple') {
    $rojo_id = isset($_POST['rojo_id']) && is_numeric($_POST['rojo_id']) ? (int)$_POST['rojo_id'] : 0;
    $azul_id = isset($_POST['azul_id']) && is_numeric($_POST['azul_id']) ? (int)$_POST['azul_id'] : 0;
    if ($rojo_id <= 0 || $azul_id <= 0) { flash_err('Seleccioná ambas esquinas (formato 1 vs 1).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if ($rojo_id === $azul_id) { flash_err('No podés elegir al mismo competidor en ambas esquinas.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

    // verificar que pertenezcan al evento
    $sql = "SELECT COUNT(*) AS c FROM competidores_evento WHERE evento_id = ? AND (id = ? OR id = ?)";
    $st = $conexion->prepare($sql);
    if (!$st) { flash_err('SQL prepare (verificar competidores): '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $st->bind_param('iii', $evento_id, $rojo_id, $azul_id);
    $st->execute();
    $okCount = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    if ($okCount !== 2) { flash_err('Los competidores no pertenecen al evento.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

    $pairs[] = [$rojo_id, $azul_id, ''];

  } else {
    // Triangular / Super 4: recibir ids_seleccionados[]
    $ids_sel = array_map('intval', $_POST['ids_seleccionados'] ?? []);
    $ids_sel = array_values(array_unique(array_filter($ids_sel, fn($v)=>$v>0)));
    $necesarios = ($formato === 'triangular') ? 3 : 4;
    if (count($ids_sel) !== $necesarios) {
      flash_err('Debés seleccionar exactamente '.$necesarios.' competidores para el formato elegido.');
      header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
    }

    // verificar que todos pertenecen al evento
    $place = implode(',', array_fill(0, count($ids_sel), '?'));
    $types = str_repeat('i', count($ids_sel) + 1);
    $sql = "SELECT COUNT(*) AS c FROM competidores_evento WHERE evento_id = ? AND id IN ($place)";
    $st = $conexion->prepare($sql);
    if (!$st) { flash_err('SQL prepare (verificar grupo): '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    // bind dinámico
    $bind = []; $bind[] = $types; $ev_copy = $evento_id; $bind[] = &$ev_copy;
    foreach ($ids_sel as $i=>&$v) { $bind[] = &$v; }
    call_user_func_array([$st,'bind_param'],$bind);
    $st->execute();
    $cOk = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    if ($cOk !== count($ids_sel)) {
      flash_err('Algún competidor seleccionado no pertenece al evento.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
    }

    if ($formato === 'triangular') {
      // A-B, B-C, A-C en el orden de selección
      $A=$ids_sel[0]; $B=$ids_sel[1]; $C=$ids_sel[2];
      $pairs[] = [$A,$B,' (Triangular 1/3)'];
      $pairs[] = [$B,$C,' (Triangular 2/3)'];
      $pairs[] = [$A,$C,' (Triangular 3/3)'];
    } else { // super4
      // Semifinales en el orden de selección: (1 vs 2) y (3 vs 4)
      $pairs[] = [$ids_sel[0], $ids_sel[1], ' (Super 4 - SF1)'];
      $pairs[] = [$ids_sel[2], $ids_sel[3], ' (Super 4 - SF2)'];
      // La final se crea luego con ganadores desde "combate_en_vivo" o flujo posterior.
    }
  }

  // Validar duplicados (ninguna de las peleas puede existir ya)
  foreach ($pairs as [$r,$a]) {
    $sql = "SELECT 1 FROM peleas_evento
            WHERE $colE = ?
              AND (($colR = ? AND $colA = ?) OR ($colR = ? AND $colA = ?))
            LIMIT 1";
    $st = $conexion->prepare($sql);
    if (!$st) { flash_err('SQL prepare (verificar duplicadas): '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $st->bind_param('iiiii', $evento_id, $r, $a, $a, $r);
    $st->execute();
    $dupe = $st->get_result();
    $st->close();
    if ($dupe && $dupe->num_rows > 0) {
      flash_err('Alguna pelea ya está programada para este evento. Revisá los cruces.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
    }
  }

  // INSERT en transacción
  $conexion->begin_transaction();
  try {
    foreach ($pairs as [$r,$a,$obsSuf]) {
      $cols = [$pe_cols['evento'], $pe_cols['rojo'], $pe_cols['azul']];
      $vals = [$evento_id, $r, $a];
      $types = 'iii';

      if ($pe_cols['rondas']) { $cols[] = $pe_cols['rondas']; $vals[] = $rondas; $types .= 'i'; }
      if ($pe_cols['obs'])    { $cols[] = $pe_cols['obs'];    $vals[] = trim($obsBase.$obsSuf); $types .= 's'; }
      // if ($pe_cols['estado']) { $cols[] = $pe_cols['estado']; $vals[] = 'programada'; $types .= 's'; }

      $cols_bt = array_map('bt', $cols);
      $ph = implode(',', array_fill(0, count($cols_bt), '?'));
      $sql = "INSERT INTO peleas_evento (".implode(',', $cols_bt).") VALUES ($ph)";
      $st = $conexion->prepare($sql);
      if (!$st) throw new Exception('SQL prepare (insert pelea): '.$conexion->error);

      $bind = []; $bind[] = $types;
      foreach ($vals as $k=>&$v) { $bind[] = &$v; }
      call_user_func_array([$st, 'bind_param'], $bind);
      if (!$st->execute()) { $err = $st->error; $st->close(); throw new Exception('No se pudo guardar una de las peleas: '.$err); }
      $st->close();
    }
    $conexion->commit();
  } catch (Throwable $e) {
    $conexion->rollback();
    flash_err($e->getMessage());
    header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
  }

  // Redirigir a ver peleas (editar / eliminar / enviar a vivo se hace allí)
  $creadas = count($pairs);
  flash_ok("Se crearon $creadas pelea(s) en formato ".($formato==='simple'?'1 vs 1':($formato==='triangular'?'Triangular':'Super 4')).".");
  header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id);
  exit;
}

/* ====== Catálogos (para filtros) ====== */
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas_evento ORDER BY nombre");
$modalidades = $conexion->query("SELECT id, nombre FROM modalidades_evento ORDER BY nombre");
$divisiones  = $conexion->query("SELECT id, nombre FROM divisiones_evento ORDER BY id");
$pesos       = $conexion->query("SELECT id, nombre FROM categorias_peso_evento ORDER BY nombre");
$tecnicas    = $conexion->query("SELECT id, codigo, descripcion FROM categorias_tecnicas_evento ORDER BY id");

/* ====== Filtros (por ID) ====== */
$f_disciplina_id        = (isset($_GET['disciplina_id'])        && is_numeric($_GET['disciplina_id']))        ? (int)$_GET['disciplina_id']        : null;
$f_modalidad_id         = (isset($_GET['modalidad_id'])         && is_numeric($_GET['modalidad_id']))         ? (int)$_GET['modalidad_id']         : null;
$f_division_id          = (isset($_GET['division_id'])          && is_numeric($_GET['division_id']))          ? (int)$_GET['division_id']          : null;
$f_categoria_peso_id    = (isset($_GET['categoria_peso_id'])    && is_numeric($_GET['categoria_peso_id']))    ? (int)$_GET['categoria_peso_id']    : null;
$f_categoria_tecnica_id = (isset($_GET['categoria_tecnica_id']) && is_numeric($_GET['categoria_tecnica_id'])) ? (int)$_GET['categoria_tecnica_id'] : null;

/* ====== Buscar competidores del evento (con filtros) ====== */
$sql = "
SELECT
  ce.id,
  ce.apellido, ce.nombre, ce.dni, ce.edad,
  ce.foto_competidor, ce.escuela_logo, ce.escuela_nombre,
  d.nombre  AS disciplina,
  m.nombre  AS modalidad,
  dv.nombre AS division,
  cp.nombre AS categoria_peso,
  ct.codigo AS categoria_tecnica_codigo,
  ct.descripcion AS categoria_tecnica_desc
FROM competidores_evento ce
LEFT JOIN disciplinas_evento         d  ON d.id  = ce.disciplina_id
LEFT JOIN modalidades_evento         m  ON m.id  = ce.modalidad_id
LEFT JOIN divisiones_evento          dv ON dv.id = ce.division_id
LEFT JOIN categorias_peso_evento     cp ON cp.id = ce.categoria_peso_id
LEFT JOIN categorias_tecnicas_evento ct ON ct.id = ce.categoria_tecnica_id
WHERE ce.evento_id = ?
";
$types = 'i';
$params = [$evento_id];

if (!is_null($f_disciplina_id))        { $sql .= " AND ce.disciplina_id = ?";         $types.='i'; $params[]=$f_disciplina_id; }
if (!is_null($f_modalidad_id))         { $sql .= " AND ce.modalidad_id = ?";          $types.='i'; $params[]=$f_modalidad_id; }
if (!is_null($f_division_id))          { $sql .= " AND ce.division_id = ?";           $types.='i'; $params[]=$f_division_id; }
if (!is_null($f_categoria_peso_id))    { $sql .= " AND ce.categoria_peso_id = ?";     $types.='i'; $params[]=$f_categoria_peso_id; }
if (!is_null($f_categoria_tecnica_id)) { $sql .= " AND ce.categoria_tecnica_id = ?";  $types.='i'; $params[]=$f_categoria_tecnica_id; }

$sql .= " ORDER BY cp.nombre, dv.nombre, ct.id, ce.apellido, ce.nombre";

$st = $conexion->prepare($sql);
if (!$st) { http_response_code(500); exit('❌ SQL prepare (listar competidores): '.$conexion->error); }
$refs = [];
foreach ($params as $k=>&$v) { $refs[$k] = &$v; }
array_unshift($refs, $types);
call_user_func_array([$st,'bind_param'], $refs);
$st->execute();
$res = $st->get_result();
$competidores = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

$placeholderFoto = 'assets/placeholder-user.png';
$placeholderLogo = 'assets/placeholder-logo.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Organizar Peleas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor { max-width: 1200px; margin: 0 auto; padding: 16px; }
    .alert { padding:10px 12px;border-radius:8px;margin-bottom:12px }
    .alert.error{background:#fdecea;color:#b71c1c;border:1px solid #f5c6cb}
    .alert.ok{background:#e6f4ea;color:#0f5132;border:1px solid #badbcc}
    .filters { display:grid; grid-template-columns: repeat(6, minmax(160px,1fr)); gap:12px; align-items:end; margin-bottom: 14px; }
    @media (max-width: 1000px){ .filters{ grid-template-columns: repeat(3,1fr);} }
    @media (max-width: 640px){ .filters{ grid-template-columns: 1fr;} }
    label { font-weight:600; font-size:14px; }
    select, button, input[type=number], input[type=text] { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:8px; }
    .table-wrap { width:100%; overflow-x:auto; }
    table { width:100%; border-collapse:collapse; min-width: 980px; }
    th, td { border:1px solid #e7e7e7; padding:8px 10px; vertical-align:middle; }
    th { background:#f6f7f9; text-align:left; }
    .avatar { width:50px; height:50px; object-fit:cover; border-radius:8px; }
    .logo   { width:50px; height:50px; object-fit:contain; }
    .cols { display:grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    @media (max-width: 880px){ .cols{ grid-template-columns: 1fr; } }
    .btn-primary { background:#1e88e5; color:#fff; border:0; padding:10px 14px; border-radius:10px; cursor:pointer; }
    .btn-primary:disabled{ opacity:.6; cursor:not-allowed; }
    .btn-secondary{background:#e9ecef;color:#0f172a;border:0;padding:10px 14px;border-radius:10px;cursor:pointer;text-decoration:none;display:inline-block}
    .note { font-size:13px; color:#555; }
    .pill { display:inline-block; padding:2px 8px; border-radius:999px; background:#eef5ff; color:#1e4fa1; font-size:12px; }
    .toolbar { display:flex; gap:8px; justify-content:space-between; align-items:center; margin: 6px 0 12px; flex-wrap: wrap; }
    .hidden{display:none}
    .sel-info{font-size:13px;color:#333}
  </style>
</head>
<body>
<div class="contenedor">
  <h2>🥊 Organización de Peleas — Evento #<?= (int)$evento_id ?></h2>

  <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert error"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['flash_ok'])): ?>
    <div class="alert ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
  <?php endif; ?>

  <!-- Filtros -->
  <form method="GET" class="filters">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <div>
      <label>Disciplina</label>
      <select name="disciplina_id">
        <option value="">Todas</option>
        <?php while($d = $disciplinas->fetch_assoc()): ?>
          <option value="<?= (int)$d['id'] ?>" <?= ($f_disciplina_id===(int)$d['id'])?'selected':'' ?>><?= h($d['nombre']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>Modalidad</label>
      <select name="modalidad_id">
        <option value="">Todas</option>
        <?php while($m = $modalidades->fetch_assoc()): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ($f_modalidad_id===(int)$m['id'])?'selected':'' ?>><?= h($m['nombre']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>División</label>
      <select name="division_id">
        <option value="">Todas</option>
        <?php while($dv = $divisiones->fetch_assoc()): ?>
          <option value="<?= (int)$dv['id'] ?>" <?= ($f_division_id===(int)$dv['id'])?'selected':'' ?>><?= h($dv['nombre']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>Categoría de Peso</label>
      <select name="categoria_peso_id">
        <option value="">Todas</option>
        <?php while($cp = $pesos->fetch_assoc()): ?>
          <option value="<?= (int)$cp['id'] ?>" <?= ($f_categoria_peso_id===(int)$cp['id'])?'selected':'' ?>><?= h($cp['nombre']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>Categoría Técnica</label>
      <select name="categoria_tecnica_id">
        <option value="">Todas</option>
        <?php while($ct = $tecnicas->fetch_assoc()): ?>
          <option value="<?= (int)$ct['id'] ?>" <?= ($f_categoria_tecnica_id===(int)$ct['id'])?'selected':'' ?>>
            <?= h(($ct['codigo'] ?? '').(isset($ct['descripcion']) ? ' - '.$ct['descripcion'] : '')) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <button type="submit" class="btn-primary">🔍 Buscar</button>
    </div>
  </form>

  <!-- CREACIÓN DE PELEA(S) -->
  <form method="POST" action="" id="form-bout">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">

    <div class="cols" style="margin:12px 0;">
      <div>
        <label>Formato</label>
        <select name="formato" id="formato">
          <option value="simple">1 vs 1</option>
          <option value="triangular">Triangular (3 competidores)</option>
          <option value="super4">Super 4 (cuadrangular)</option>
        </select>
      </div>
      <div id="blk-simple-rojo">
        <label>Esquina Roja</label>
        <select name="rojo_id" id="rojo_id">
          <option value="">Seleccioná competidor…</option>
          <?php foreach ($competidores as $c): ?>
            <option value="<?= (int)$c['id'] ?>">
              <?= h($c['apellido'].' '.$c['nombre'].' — '.$c['categoria_peso'].' / '.$c['division'].' / '.$c['modalidad']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="blk-simple-azul">
        <label>Esquina Azul</label>
        <select name="azul_id" id="azul_id">
          <option value="">Seleccioná competidor…</option>
          <?php foreach ($competidores as $c): ?>
            <option value="<?= (int)$c['id'] ?>">
              <?= h($c['apellido'].' '.$c['nombre'].' — '.$c['categoria_peso'].' / '.$c['division'].' / '.$c['modalidad']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="cols" style="margin:6px 0 8px;">
      <div>
        <label>Rondas</label>
        <input type="number" name="rondas" id="rondas_input" min="1" max="12" value="3">
      </div>
      <div>
        <label>Observaciones</label>
        <input type="text" name="observaciones" placeholder="(opcional)">
      </div>
      <div id="sel-counter" class="hidden" style="align-self:end;">
        <span class="sel-info">Seleccionados: <b id="sel-count">0</b> / <span id="sel-need">0</span></span>
      </div>
    </div>

    <!-- BOTONERA ARRIBA DEL LISTADO -->
    <div class="toolbar">
      <button type="submit" class="btn-primary" id="btn-guardar">✅ Confirmar y Agregar pelea(s)</button>
      <a class="btn-secondary" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?>">📋 Ver / Editar / Eliminar peleas</a>
    </div>

    <!-- LISTADO SOLO DE COMPETIDORES (SIN RINCÓN ROJO/AZUL) + casillas de selección para triangular/super4 -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th id="th-elegir" class="hidden">Elegir</th>
            <th>Foto</th>
            <th>Apellido y Nombre</th>
            <th>DNI</th>
            <th>Edad</th>
            <th>Disciplina</th>
            <th>Modalidad</th>
            <th>Cat. Técnica</th>
            <th>Peso</th>
            <th>División</th>
            <th>Escuela</th>
            <th>Logo</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$competidores): ?>
          <tr><td colspan="12">No hay competidores con esos filtros.</td></tr>
        <?php else: foreach ($competidores as $c):
          $srcFoto = !empty($c['foto_competidor']) ? $c['foto_competidor'] : $placeholderFoto;
          $srcLogo = !empty($c['escuela_logo'])    ? $c['escuela_logo']    : $placeholderLogo;
          $catTec  = trim(($c['categoria_tecnica_codigo'] ?? '').' - '.($c['categoria_tecnica_desc'] ?? ''));
        ?>
          <tr>
            <td class="td-elegir hidden"><input type="checkbox" class="chk-sel" name="ids_seleccionados[]" value="<?= (int)$c['id'] ?>"></td>
            <td><img src="<?= h($srcFoto) ?>" class="avatar" alt="Foto"></td>
            <td><?= h($c['apellido'].' '.$c['nombre']) ?></td>
            <td><?= h($c['dni']) ?></td>
            <td><?= h($c['edad']) ?></td>
            <td><span class="pill"><?= h($c['disciplina'] ?? '-') ?></span></td>
            <td><?= h($c['modalidad'] ?? '-') ?></td>
            <td><?= h($catTec !== ' - ' ? $catTec : '-') ?></td>
            <td><?= h($c['categoria_peso'] ?? '-') ?></td>
            <td><?= h($c['division'] ?? '-') ?></td>
            <td><?= h($c['escuela_nombre'] ?? '-') ?></td>
            <td><img src="<?= h($srcLogo) ?>" class="logo" alt="Logo"></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <p class="note" style="margin:10px 0 0;">
      Tip: filtrá por <b>peso</b> y <b>división</b> para emparejar mejor.
      <br>Formato <b>Triangular</b>: crea A vs B, B vs C y A vs C. —
      <b>Super 4</b>: crea sólo semifinales (1 vs 2 y 3 vs 4) en el orden de selección.
    </p>
  </form>
</div>

<script>
  const formatoSel = document.getElementById('formato');
  const rojo = document.getElementById('rojo_id');
  const azul = document.getElementById('azul_id');
  const blkSimpleR = document.getElementById('blk-simple-rojo');
  const blkSimpleA = document.getElementById('blk-simple-azul');
  const btn  = document.getElementById('btn-guardar');

  const thElegir = document.getElementById('th-elegir');
  const tdElegirCells = document.querySelectorAll('.td-elegir');
  const chks = document.querySelectorAll('.chk-sel');
  const selCounter = document.getElementById('sel-counter');
  const selCount = document.getElementById('sel-count');
  const selNeed = document.getElementById('sel-need');

  function setHidden(el, hide){ if (!el) return; el.classList[hide ? 'add':'remove']('hidden'); }
  function countChecked(){ let n=0; chks.forEach(c=>{ if(c.checked) n++; }); return n; }

  function needByFormat(fmt){
    if (fmt === 'triangular') return 3;
    if (fmt === 'super4') return 4;
    return 0;
  }

  function updateUI(){
    const fmt = formatoSel.value;
    const need = needByFormat(fmt);

    // Mostrar/ocultar selects simple
    setHidden(blkSimpleR, need !== 0);
    setHidden(blkSimpleA, need !== 0);

    // Requeridos en simple
    if (need === 0) { rojo?.setAttribute('required','required'); azul?.setAttribute('required','required'); }
    else { rojo?.removeAttribute('required'); azul?.removeAttribute('required'); }

    // Mostrar/ocultar columna Elegir y contador
    setHidden(thElegir, need === 0);
    tdElegirCells.forEach(td=> setHidden(td, need === 0));
    setHidden(selCounter, need === 0);
    selNeed.textContent = need.toString();

    // Si vuelvo a simple, borro selecciones para que no se envíen ocultas
    if (need === 0){
      chks.forEach(c => { c.checked = false; c.disabled = false; });
      selCount.textContent = '0';
    }

    // Limitar selección máxima (triangular/super4)
    chks.forEach(ch => ch.disabled = false);
    const current = countChecked();
    selCount.textContent = current.toString();

    if (need > 0 && current > need){
      // desmarcar extras arbitrariamente
      let n = current - need;
      for (const c of chks) { if (n<=0) break; if (c.checked){ c.checked = false; n--; } }
    }
    // Deshabilitar restantes si ya se alcanzó el máximo
    if (need > 0 && countChecked() >= need) {
      chks.forEach(ch => { if (!ch.checked) ch.disabled = true; });
    } else {
      chks.forEach(ch => ch.disabled = false);
    }

    validar();
  }

  function validar(){
    if (!btn) return;
    const fmt = formatoSel.value;
    const need = needByFormat(fmt);
    if (need === 0){
      // simple: no permitir mismo competidor
      if (rojo && azul && rojo.value && azul.value && rojo.value === azul.value) {
        btn.disabled = true;
        btn.title = "No podés elegir al mismo competidor en ambas esquinas.";
        return;
      }
      btn.disabled = false; btn.title = "";
    } else {
      const current = countChecked();
      if (current !== need) {
        btn.disabled = true;
        btn.title = "Seleccioná exactamente " + need + " competidores para "+(fmt==='triangular'?'Triangular':'Super 4')+".";
        return;
      }
      btn.disabled = false; btn.title = "";
    }
  }

  chks.forEach(ch=>{
    ch.addEventListener('change', ()=>{
      const need = needByFormat(formatoSel.value);
      let current = countChecked();
      selCount.textContent = current.toString();
      if (need > 0 && current >= need) {
        chks.forEach(c => { if (!c.checked) c.disabled = true; });
      } else {
        chks.forEach(c => c.disabled = false);
      }
      validar();
    });
  });
  formatoSel.addEventListener('change', updateUI);
  rojo?.addEventListener('change', validar);
  azul?.addEventListener('change', validar);

  // Init
  updateUI();
</script>
</body>
</html>
