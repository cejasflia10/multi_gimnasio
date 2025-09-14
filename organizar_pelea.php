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

/* ============== Utilidades SQL ============== */
function bt($col){ return '`'.str_replace('`','``',$col).'`'; }

function pe_get_cols(mysqli $db){
  $res = $db->query("SHOW COLUMNS FROM peleas_evento");
  if (!$res) { return ['error' => 'No se pudo leer columnas de peleas_evento: '.$db->error]; }
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

/* columnas dinámicas de categorias_evento */
function cat_get_cols(mysqli $db){
  $res = $db->query("SHOW COLUMNS FROM categorias_evento");
  if (!$res) { return ['error' => 'No se pudo leer columnas de categorias_evento: '.$db->error]; }
  $cols = [];
  while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
  $pick = function(array $cands) use ($cols){
    foreach ($cands as $c) { $lc = strtolower($c); if (isset($cols[$lc])) return $cols[$lc]; }
    return null;
  };
  return [
    'id'        => $pick(['id','categoria_id']),
    'nombre'    => $pick(['nombre','categoria','clase','weight_class','titulo','title','descripcion']),
    'peso_min'  => $pick(['peso_min','min_peso','desde','min','minimo','peso_minimo']),
    'peso_max'  => $pick(['peso_max','max_peso','hasta','max','maximo','peso_maximo']),
    'genero'    => $pick(['genero','sexo','gender']),
    'edad_min'  => $pick(['edad_min','edad_desde','min_edad','desde_edad']),
    'edad_max'  => $pick(['edad_max','edad_hasta','max_edad','hasta_edad']),
    '_all'      => array_values($cols),
  ];
}

$pe_cols = pe_get_cols($conexion);
if (isset($pe_cols['error'])) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Error: '.h($pe_cols['error']).'</div>'; 
  exit;
}
if (!$pe_cols['evento'] || !$pe_cols['rojo'] || !$pe_cols['azul']) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">
        La tabla <b>peleas_evento</b> existe, pero faltan las 3 columnas obligatorias.
        <br>Detectadas: <code>'.h(implode(', ', $pe_cols['_all'])).'</code></div>';
  exit;
}

$cat_cols = cat_get_cols($conexion);
if (isset($cat_cols['error'])) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Error: '.h($cat_cols['error']).'</div>';
  exit;
}
if (!$cat_cols['id']) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">'.
       'La tabla <b>categorias_evento</b> no tiene columna <code>id</code> detectable. Columnas: <code>'.h(implode(', ', $cat_cols['_all'])).'</code>'.
       '</div>';
  exit;
}

/* ====== Helpers de presentación ====== */
function fmt_kg($n){
  if ($n === null || $n === '' ) return null;
  if (is_numeric($n)) $n = (float)$n;
  return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
}
function label_peso_cat($row){
  // Usa ct_peso_min/ct_peso_max/ct_nombre/ct_genero/ct_edad_min/ct_edad_max si existen
  $min = isset($row['ct_peso_min']) ? fmt_kg($row['ct_peso_min']) : null;
  $max = isset($row['ct_peso_max']) ? fmt_kg($row['ct_peso_max']) : null;
  $nom = trim((string)($row['ct_nombre'] ?? ''));
  $gen = trim((string)($row['ct_genero'] ?? ''));
  $eMin = isset($row['ct_edad_min']) ? (string)$row['ct_edad_min'] : '';
  $eMax = isset($row['ct_edad_max']) ? (string)$row['ct_edad_max'] : '';

  $peso = '-';
  if ($min !== null && $max !== null && $min !== '' && $max !== '')       $peso = $min.'–'.$max.' kg';
  elseif ($min !== null && $min !== '')                                   $peso = $min.' kg';
  elseif ($max !== null && $max !== '')                                   $peso = $max.' kg';
  elseif ($nom !== '')                                                    $peso = $nom;

  $suf = [];
  if ($gen !== '') $suf[] = $gen;
  if ($eMin !== '' || $eMax !== '') $suf[] = trim($eMin.'–'.$eMax);
  return $peso.( $suf ? ' ('.implode(', ', $suf).')' : '' );
}

/* =========================
   POST: Guardar pelea(s)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $formato = $_POST['formato'] ?? 'simple';
  $rondas  = isset($_POST['rondas']) && is_numeric($_POST['rondas']) ? (int)$_POST['rondas'] : 3;
  $obsBase = isset($_POST['observaciones']) ? trim((string)$_POST['observaciones']) : '';
  if ($rondas < 1 || $rondas > 12) { $rondas = 3; }

  $pairs = []; // [rojo_id, azul_id, obs_suffix]
  $todos = [];

  if ($formato === 'simple') {
    $rojo_id = isset($_POST['rojo_id']) && is_numeric($_POST['rojo_id']) ? (int)$_POST['rojo_id'] : 0;
    $azul_id = isset($_POST['azul_id']) && is_numeric($_POST['azul_id']) ? (int)$_POST['azul_id'] : 0;
    if ($rojo_id <= 0 || $azul_id <= 0) { flash_err('Seleccioná ambas esquinas (1 vs 1).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if ($rojo_id === $azul_id) { flash_err('No podés elegir al mismo competidor en ambas esquinas.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $todos = [$rojo_id, $azul_id];
    $pairs[] = [$rojo_id, $azul_id, ''];

  } elseif ($formato === 'triangular') {
    $tri_r = isset($_POST['tri_rojo_id']) ? (int)$_POST['tri_rojo_id'] : 0;
    $tri_a = isset($_POST['tri_azul_id']) ? (int)$_POST['tri_azul_id'] : 0;
    $tri_l = isset($_POST['tri_libre_id']) ? (int)$_POST['tri_libre_id'] : 0;

    if ($tri_r<=0 || $tri_a<=0 || $tri_l<=0) { flash_err('Completá los 3 slots: Rojo (SF), Azul (SF) y Libre.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if (count(array_unique([$tri_r,$tri_a,$tri_l])) !== 3) { flash_err('Los 3 competidores deben ser distintos en Triangular.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

    $todos = [$tri_r,$tri_a,$tri_l];
    $pairs[] = [$tri_r, $tri_a, ' (Triangular - Semifinal)'];

  } else { // super4
    $sf1r = isset($_POST['sf1_rojo_id']) ? (int)$_POST['sf1_rojo_id'] : 0;
    $sf1a = isset($_POST['sf1_azul_id']) ? (int)$_POST['sf1_azul_id'] : 0;
    $sf2r = isset($_POST['sf2_rojo_id']) ? (int)$_POST['sf2_rojo_id'] : 0;
    $sf2a = isset($_POST['sf2_azul_id']) ? (int)$_POST['sf2_azul_id'] : 0;

    if ($sf1r<=0 || $sf1a<=0 || $sf2r<=0 || $sf2a<=0) { flash_err('Completá los 4 slots de semifinales (SF1 y SF2).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if (count(array_unique([$sf1r,$sf1a,$sf2r,$sf2a])) !== 4) { flash_err('No repitas competidores entre las semifinales (Super 4).'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

    $todos = [$sf1r,$sf1a,$sf2r,$sf2a];
    $pairs[] = [$sf1r, $sf1a, ' (Super 4 - SF1)'];
    $pairs[] = [$sf2r, $sf2a, ' (Super 4 - SF2)'];
  }

  // Verificar pertenencia al evento
  if ($todos) {
    $place = implode(',', array_fill(0, count($todos), '?'));
    $types = str_repeat('i', count($todos) + 1);
    $sql = "SELECT COUNT(*) AS c FROM competidores_evento WHERE evento_id = ? AND id IN ($place)";
    $st = $conexion->prepare($sql);
    if (!$st) { flash_err('SQL prepare (verificar pertenencia): '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $bind = []; $bind[] = $types; $ev_copy = $evento_id; $bind[] = &$ev_copy;
    foreach ($todos as $i=>&$v) { $bind[] = &$v; }
    call_user_func_array([$st,'bind_param'],$bind);
    $st->execute();
    $cOk = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    if ($cOk !== count($todos)) { flash_err('Algún competidor no pertenece al evento.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
  }

  // Chequear duplicadas
  foreach ($pairs as [$r,$a]) {
    $sql = "SELECT 1 FROM peleas_evento
            WHERE ".bt($pe_cols['evento'])." = ?
              AND ((".bt($pe_cols['rojo'])." = ? AND ".bt($pe_cols['azul'])." = ?) OR (".bt($pe_cols['rojo'])." = ? AND ".bt($pe_cols['azul'])." = ?))
            LIMIT 1";
    $st = $conexion->prepare($sql);
    if (!$st) { flash_err('SQL prepare (verificar duplicadas): '.$conexion->error); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $st->bind_param('iiiii', $evento_id, $r, $a, $a, $r);
    $st->execute(); $dupe = $st->get_result(); $st->close();
    if ($dupe && $dupe->num_rows > 0) { flash_err('Alguna pelea ya existe en este evento.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
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

      $cols_bt = array_map('bt', $cols);
      $ph = implode(',', array_fill(0, count($cols_bt), '?'));
      $sql = "INSERT INTO peleas_evento (".implode(',', $cols_bt).") VALUES ($ph)";
      $st = $conexion->prepare($sql);
      if (!$st) throw new Exception('SQL prepare (insert pelea): '.$conexion->error);

      $bind = []; $bind[] = $types;
      foreach ($vals as $k=>&$v) { $bind[] = &$v; }
      call_user_func_array([$st, 'bind_param'], $bind);
      if (!$st->execute()) { $err = $st->error; $st->close(); throw new Exception('No se pudo guardar una pelea: '.$err); }
      $st->close();
    }
    $conexion->commit();
  } catch (Throwable $e) {
    $conexion->rollback();
    flash_err($e->getMessage());
    header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
  }

  $creadas = count($pairs);
  $txtFmt = ($formato==='simple' ? '1 vs 1' : ($formato==='triangular' ? 'Triangular (SF + Libre)' : 'Super 4 (semifinales)'));
  flash_ok("Se crearon $creadas pelea(s) — formato $txtFmt.");
  header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id);
  exit;
}

/* ====== Catálogos (para filtros básicos) ====== */
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas_evento ORDER BY nombre");
$modalidades = $conexion->query("SELECT id, nombre FROM modalidades_evento ORDER BY nombre");
$divisiones  = $conexion->query("SELECT id, nombre FROM divisiones_evento ORDER BY id");

/* ====== SELECT dinámicos para categorias_evento ====== */
$selNombre = $cat_cols['nombre']   ? "ct.".bt($cat_cols['nombre'])."   AS ct_nombre"    : "NULL AS ct_nombre";
$selPmin   = $cat_cols['peso_min'] ? "ct.".bt($cat_cols['peso_min'])." AS ct_peso_min"  : "NULL AS ct_peso_min";
$selPmax   = $cat_cols['peso_max'] ? "ct.".bt($cat_cols['peso_max'])." AS ct_peso_max"  : "NULL AS ct_peso_max";
$selGenero = $cat_cols['genero']   ? "ct.".bt($cat_cols['genero'])."   AS ct_genero"    : "NULL AS ct_genero";
$selEmin   = $cat_cols['edad_min'] ? "ct.".bt($cat_cols['edad_min'])." AS ct_edad_min"  : "NULL AS ct_edad_min";
$selEmax   = $cat_cols['edad_max'] ? "ct.".bt($cat_cols['edad_max'])." AS ct_edad_max"  : "NULL AS ct_edad_max";

/* “Categoría de Peso (desde categorías_evento)” – usamos todas las filas como opciones */
$pesos = [];
$cat_rs = $conexion->query("SELECT ct.".bt($cat_cols['id'])." AS ct_id, $selNombre, $selPmin, $selPmax, $selGenero, $selEmin, $selEmax FROM categorias_evento ct ORDER BY ct.".bt($cat_cols['id']));
if ($cat_rs) {
  while($t = $cat_rs->fetch_assoc()){
    $label = label_peso_cat($t);
    $pesos[] = ['id'=>(int)$t['ct_id'], 'nombre'=>$label];
  }
}

/* “Categoría Técnica” = mismas filas (si querés diferenciarlas visualmente) */
$categorias = $conexion->query("SELECT ct.".bt($cat_cols['id'])." AS ct_id, $selNombre, $selPmin, $selPmax, $selGenero, $selEmin, $selEmax FROM categorias_evento ct ORDER BY ct.".bt($cat_cols['id']));

/* ====== Filtros (por ID) ====== */
$f_disciplina_id        = (isset($_GET['disciplina_id'])        && is_numeric($_GET['disciplina_id']))        ? (int)$_GET['disciplina_id']        : null;
$f_modalidad_id         = (isset($_GET['modalidad_id'])         && is_numeric($_GET['modalidad_id']))         ? (int)$_GET['modalidad_id']         : null;
$f_division_id          = (isset($_GET['division_id'])          && is_numeric($_GET['division_id']))          ? (int)$_GET['division_id']          : null;
$f_categoria_peso_id    = (isset($_GET['categoria_peso_id'])    && is_numeric($_GET['categoria_peso_id']))    ? (int)$_GET['categoria_peso_id']    : null;
$f_categoria_tecnica_id = (isset($_GET['categoria_tecnica_id']) && is_numeric($_GET['categoria_tecnica_id'])) ? (int)$_GET['categoria_tecnica_id'] : null;

/* ====== Buscar competidores (peso desde categorias_evento) ====== */
$sql = "
SELECT
  ce.id,
  ce.apellido, ce.nombre, ce.dni, ce.edad,
  ce.foto_competidor, ce.escuela_logo, ce.escuela_nombre,
  d.nombre  AS disciplina,
  m.nombre  AS modalidad,
  dv.nombre AS division,
  ct.".bt($cat_cols['id'])." AS ct_id,
  $selNombre,
  $selPmin,
  $selPmax,
  $selGenero,
  $selEmin,
  $selEmax
FROM competidores_evento ce
LEFT JOIN disciplinas_evento d  ON d.id  = ce.disciplina_id
LEFT JOIN modalidades_evento m  ON m.id  = ce.modalidad_id
LEFT JOIN divisiones_evento dv ON dv.id = ce.division_id
LEFT JOIN categorias_evento ct  ON ct.".bt($cat_cols['id'])." = ce.categoria_tecnica_id
WHERE ce.evento_id = ?
";
$types = 'i';
$params = [$evento_id];

if (!is_null($f_disciplina_id))        { $sql .= " AND ce.disciplina_id = ?";         $types.='i'; $params[]=$f_disciplina_id; }
if (!is_null($f_modalidad_id))         { $sql .= " AND ce.modalidad_id = ?";          $types.='i'; $params[]=$f_modalidad_id; }
if (!is_null($f_division_id))          { $sql .= " AND ce.division_id = ?";           $types.='i'; $params[]=$f_division_id; }

/* Filtro “Categoría de Peso (desde Categoría)” -> coincide con ce.categoria_tecnica_id */
if (!is_null($f_categoria_peso_id))    { $sql .= " AND ce.categoria_tecnica_id = ?";  $types.='i'; $params[]=$f_categoria_peso_id; }

/* Filtro “Categoría Técnica” (mismo id) */
if (!is_null($f_categoria_tecnica_id)) { $sql .= " AND ce.categoria_tecnica_id = ?";  $types.='i'; $params[]=$f_categoria_tecnica_id; }

$sql .= " ORDER BY ct.".bt($cat_cols['id']).", dv.nombre, ce.apellido, ce.nombre";

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
    .slot-grid { display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; }
    .slot-grid .full { grid-column: 1 / -1; }
    .muted { color:#475569; font-size:13px; }
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
      <label>Categoría de Peso (desde Categoría)</label>
      <select name="categoria_peso_id">
        <option value="">Todas</option>
        <?php foreach ($pesos as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ($f_categoria_peso_id===(int)$p['id'])?'selected':'' ?>>
            <?= h($p['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="muted">* Sale de <b>categorias_evento</b> (min–max kg, género y edades).</small>
    </div>
    <div>
      <label>Categoría Técnica</label>
      <select name="categoria_tecnica_id">
        <option value="">Todas</option>
        <?php if ($categorias): while($ct = $categorias->fetch_assoc()):
              $lbl = label_peso_cat($ct);
        ?>
          <option value="<?= (int)$ct['ct_id'] ?>" <?= ($f_categoria_tecnica_id===(int)$ct['ct_id'])?'selected':'' ?>>
            <?= h($lbl) ?>
          </option>
        <?php endwhile; endif; ?>
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
      <div>
        <label>Rondas</label>
        <input type="number" name="rondas" id="rondas_input" min="1" max="12" value="3">
      </div>
      <div>
        <label>Observaciones</label>
        <input type="text" name="observaciones" placeholder="(opcional)">
      </div>
    </div>

    <!-- SLOTS DINÁMICOS -->
    <div id="slots-container" class="slot-grid" style="margin-bottom:10px;">
      <!-- Se completa vía JS -->
    </div>

    <div class="cols" style="grid-template-columns: 1fr auto; align-items:center;">
      <div class="muted">
        <b>Triangular:</b> SF (Rojo vs Azul) + <u>Libre</u> (espera al ganador).<br>
        <b>Super 4:</b> SF1 y SF2. La final queda libre y se arma luego con ganadores.
      </div>
      <div>
        <button type="submit" class="btn-primary" id="btn-guardar">✅ Confirmar y Agregar pelea(s)</button>
        <a class="btn-secondary" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?>">📋 Ver / Editar / Eliminar peleas</a>
      </div>
    </div>

    <!-- LISTADO INFORMATIVO -->
    <div class="table-wrap" style="margin-top:12px;">
      <table>
        <thead>
          <tr>
            <th>Foto</th>
            <th>Apellido y Nombre</th>
            <th>DNI</th>
            <th>Edad</th>
            <th>Disciplina</th>
            <th>Modalidad</th>
            <th>Categoría</th>
            <th>Peso (min–max kg)</th>
            <th>División</th>
            <th>Escuela</th>
            <th>Logo</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$competidores): ?>
          <tr><td colspan="11">No hay competidores con esos filtros.</td></tr>
        <?php else: foreach ($competidores as $c):
          $srcFoto = !empty($c['foto_competidor']) ? $c['foto_competidor'] : $placeholderFoto;
          $srcLogo = !empty($c['escuela_logo'])    ? $c['escuela_logo']    : $placeholderLogo;
          $catLbl  = label_peso_cat([
            'ct_peso_min'=>$c['ct_peso_min'] ?? null,
            'ct_peso_max'=>$c['ct_peso_max'] ?? null,
            'ct_nombre'  =>$c['ct_nombre']   ?? '',
            'ct_genero'  =>$c['ct_genero']   ?? '',
            'ct_edad_min'=>$c['ct_edad_min'] ?? '',
            'ct_edad_max'=>$c['ct_edad_max'] ?? '',
          ]);
          $pesoSolo = (function($min,$max){
            $min = fmt_kg($min); $max = fmt_kg($max);
            if ($min && $max) return $min.'–'.$max.' kg';
            if ($min) return $min.' kg';
            if ($max) return $max.' kg';
            return '-';
          })($c['ct_peso_min'] ?? null, $c['ct_peso_max'] ?? null);
        ?>
          <tr>
            <td><img src="<?= h($srcFoto) ?>" class="avatar" alt="Foto"></td>
            <td><?= h($c['apellido'].' '.$c['nombre']) ?></td>
            <td><?= h($c['dni']) ?></td>
            <td><?= h($c['edad']) ?></td>
            <td><span class="pill"><?= h($c['disciplina'] ?? '-') ?></span></td>
            <td><?= h($c['modalidad'] ?? '-') ?></td>
            <td><?= h($catLbl) ?></td>
            <td><?= h($pesoSolo) ?></td>
            <td><?= h($c['division'] ?? '-') ?></td>
            <td><?= h($c['escuela_nombre'] ?? '-') ?></td>
            <td><img src="<?= h($srcLogo) ?>" class="logo" alt="Logo"></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<script>
  // ==== Helpers UI ====
  const formatoSel = document.getElementById('formato');
  const slots = document.getElementById('slots-container');
  const btn = document.getElementById('btn-guardar');

  // Opciones de competidores (label incluye peso min–max desde categorias_evento)
  const optionsHTML = (function(){
    const opts = [`<option value="">Seleccioná competidor…</option>`];
    <?php foreach ($competidores as $c):
      $min = isset($c['ct_peso_min']) ? fmt_kg($c['ct_peso_min']) : '';
      $max = isset($c['ct_peso_max']) ? fmt_kg($c['ct_peso_max']) : '';
      $peso = ($min && $max) ? "$min–$max kg" : (($min||$max) ? (($min?:$max)+' kg') : ($c['ct_nombre'] ?? '-'));
      $label = trim(($c['apellido'].' '.$c['nombre']).' — '.$peso.' / '.($c['division']??'-').' / '.($c['modalidad']??'-'));
    ?>
      opts.push(`<option value="<?= (int)$c['id'] ?>"><?= h($label) ?></option>`);
    <?php endforeach; ?>
    return opts.join('');
  })();

  function selectTpl(name, label){
    return `
      <div>
        <label>${label}</label>
        <select name="${name}" class="slot-select">
          ${optionsHTML}
        </select>
      </div>
    `;
  }

  function renderSlots(){
    const fmt = formatoSel.value;
    let html = '';

    if (fmt === 'simple'){
      html = `
        ${selectTpl('rojo_id', 'Rincón Rojo')}
        ${selectTpl('azul_id', 'Rincón Azul')}
      `;
    } else if (fmt === 'triangular'){
      html = `
        ${selectTpl('tri_rojo_id', 'Triangular — SF (Rincón Rojo)')}
        ${selectTpl('tri_azul_id', 'Triangular — SF (Rincón Azul)')}
        <div class="full" style="height:0;"></div>
        ${selectTpl('tri_libre_id', 'Triangular — Libre (espera la final)')}
      `;
    } else { // super4
      html = `
        <div class="full" style="font-weight:600;">Semifinal 1</div>
        ${selectTpl('sf1_rojo_id', 'SF1 — Rincón Rojo')}
        ${selectTpl('sf1_azul_id', 'SF1 — Rincón Azul')}

        <div class="full" style="font-weight:600;margin-top:6px;">Semifinal 2</div>
        ${selectTpl('sf2_rojo_id', 'SF2 — Rincón Rojo')}
        ${selectTpl('sf2_azul_id', 'SF2 — Rincón Azul')}

        <div class="full muted" style="margin-top:6px;">Final (Rincón Rojo) — libre, se define luego con ganador SF1/SF2</div>
        <div class="full muted" style="margin-top:-6px;">Final (Rincón Azul) — libre, se define luego con ganador SF1/SF2</div>
      `;
    }

    slots.innerHTML = html;
    attachUniqueLogic();
    validar();
  }

  // Evitar repetir el mismo competidor en varios selects
  function attachUniqueLogic(){
    const selects = Array.from(document.querySelectorAll('.slot-select'));
    function refreshDisables(){
      const used = new Set(selects.map(s => s.value).filter(v => v));
      selects.forEach(sel => {
        const cur = sel.value;
        Array.from(sel.options).forEach(opt => {
          if (!opt.value) return;
          opt.disabled = (opt.value !== cur) && used.has(opt.value);
        });
      });
    }
    selects.forEach(sel => sel.addEventListener('change', () => { refreshDisables(); validar(); }));
    refreshDisables();
  }

  function validar(){
    if (!btn) return;
    const fmt = formatoSel.value;

    function getVal(name){ const s = document.querySelector(`[name="${name}"]`); return s ? parseInt(s.value||'0') : 0; }

    if (fmt === 'simple'){
      const r = getVal('rojo_id'), a = getVal('azul_id');
      if (!r || !a || r===a){ btn.disabled = true; btn.title="Elegí Rojo y Azul distintos."; return; }
      btn.disabled = false; btn.title = "";
      return;
    }
    if (fmt === 'triangular'){
      const r = getVal('tri_rojo_id'), a = getVal('tri_azul_id'), l = getVal('tri_libre_id');
      const all = [r,a,l].filter(Boolean);
      const ok = r && a && l && (new Set(all).size===3);
      btn.disabled = !ok;
      btn.title = ok ? "" : "Elegí 3 competidores distintos (Rojo, Azul y Libre).";
      return;
    }
    // super4
    const v = ['sf1_rojo_id','sf1_azul_id','sf2_rojo_id','sf2_azul_id'].map(getVal).filter(Boolean);
    const ok = v.length===4 && (new Set(v).size===4);
    btn.disabled = !ok;
    btn.title = ok ? "" : "Completá SF1 y SF2 con 4 competidores distintos.";
  }

  formatoSel.addEventListener('change', renderSlots);
  renderSlots(); // inicial
</script>
</body>
</html>
