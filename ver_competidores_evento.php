<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_eventos.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* =========================
   Helpers
   ========================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($col){ return '`'.str_replace('`','``',$col).'`'; }
function flash_err($msg){ $_SESSION['flash_error'] = $msg; }
function flash_ok($msg){ $_SESSION['flash_ok'] = $msg; }
function fmt_kg($n){
  if ($n===null || $n==='') return '—';
  $n = (float)$n;
  return rtrim(rtrim(number_format($n,2,'.',''), '0'), '.').' kg';
}

/* ====== Avatar estilo Messenger (SVG Data URI) ====== */
function iniciales_de($nombre = '', $apellido = '', $fallback = '?', $max = 2){
  $txt = trim($nombre.' '.$apellido);
  if ($apellido === '' && $nombre === '' && $txt === '') return $fallback;
  if ($apellido === '' && $nombre !== '' && strpos($nombre,' ') !== false) {
    $partes = preg_split('~\s+~u', trim($nombre));
  } else {
    $partes = preg_split('~\s+~u', trim($txt));
  }
  $ini = '';
  foreach ($partes as $p){
    if ($p === '') continue;
    $ini .= mb_strtoupper(mb_substr($p,0,1));
    if (mb_strlen($ini) >= $max) break;
  }
  return $ini !== '' ? $ini : $fallback;
}
function _hash_str($s){
  $h = 0; $len = mb_strlen($s);
  for ($i=0;$i<$len;$i++){
    $code = uniord(mb_substr($s,$i,1));
    $h = ($h*31 + $code) & 0x7fffffff;
  }
  return $h;
}
function uniord($c){
  $h = ord($c[0]);
  if ($h <= 0x7F) return $h;
  if ($h < 0xC2) return null;
  if ($h <= 0xDF) return ($h & 0x1F) << 6 | (ord($c[1]) & 0x3F);
  if ($h <= 0xEF) return ($h & 0x0F) << 12 | (ord($c[1]) & 0x3F) << 6 | (ord($c[2]) & 0x3F);
  return ($h & 0x07) << 18 | (ord($c[1]) & 0x3F) << 12 | (ord($c[2]) & 0x3F) << 6 | (ord($c[3]) & 0x3F);
}
function pick_gradient($key){
  $pairs = [
    ['#60a5fa','#2563eb'], ['#34d399','#059669'], ['#f472b6','#db2777'], ['#f59e0b','#d97706'],
    ['#a78bfa','#7c3aed'], ['#f87171','#ef4444'], ['#22d3ee','#06b6d4'], ['#93c5fd','#3b82f6'],
  ];
  $h = _hash_str($key); $idx = $h % count($pairs);
  return $pairs[$idx];
}
function avatar_svg_data_uri($key, $initials, $size = 60, $rounded = true){
  list($c1,$c2) = pick_gradient($key);
  $rx = $rounded ? $size/2 : min(12, $size/4);
  $fontSize = (mb_strlen($initials) >= 3) ? round($size*0.34) : round($size*0.42);
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'">
    <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="'.$c1.'"/><stop offset="100%" stop-color="'.$c2.'"/></linearGradient></defs>
    <rect x="0" y="0" width="'.$size.'" height="'.$size.'" rx="'.$rx.'" fill="url(#g)"/>
    <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle"
      fill="#fff" font-family="system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif"
      font-size="'.$fontSize.'" font-weight="700" letter-spacing="0.5">'.$initials.'</text></svg>';
  return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
}

/* CSRF */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf_token'];
function csrf_ok($t){ return !empty($_SESSION['csrf_token']) && !empty($t) && hash_equals($_SESSION['csrf_token'], $t); }

/* evento_id */
$evento_id = (int)($_GET['evento_id'] ?? $_POST['evento_id'] ?? $_SESSION['evento_id_actual'] ?? 0);
if ($evento_id <= 0) { http_response_code(400); exit('❌ Falta evento_id'); }
$_SESSION['evento_id_actual'] = $evento_id;

/* =========================
   Detección columnas peleas_evento (para bloqueo de borrado y marcas)
   ========================= */
function pe_get_cols(mysqli $db){
  $res = $db->query("SHOW COLUMNS FROM peleas_evento");
  if (!$res) return null;
  $cols = [];
  while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
  $pick = function(array $cands) use ($cols){
    foreach ($cands as $c) { $lc=strtolower($c); if (isset($cols[$lc])) return $cols[$lc]; }
    return null;
  };
  return [
    'evento' => $pick(['evento_id','id_evento','evento']),
    'rojo'   => $pick(['rojo_id','id_rojo','competidor_rojo_id','id_competidor_rojo','rojo']),
    'azul'   => $pick(['azul_id','id_azul','competidor_azul_id','id_competidor_azul','azul']),
  ];
}

/* =========================
   POST: Eliminar competidor
   ========================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion'] ?? '') === 'eliminar_comp') {
  $token = $_POST['csrf'] ?? '';
  $comp_id = (int)($_POST['comp_id'] ?? 0);
  if (!csrf_ok($token)) { flash_err('CSRF inválido.'); header('Location: ver_competidores_evento.php?evento_id='.$evento_id); exit; }
  if ($comp_id <= 0) { flash_err('ID de competidor inválido.'); header('Location: ver_competidores_evento.php?evento_id='.$evento_id); exit; }

  // Verificar pertenencia
  $st = $conexion->prepare("SELECT 1 FROM competidores_evento WHERE id=? AND evento_id=? LIMIT 1");
  if (!$st) { flash_err('Error al validar pertenencia: '.$conexion->error); header('Location: ver_competidores_evento.php?evento_id='.$evento_id); exit; }
  $st->bind_param('ii', $comp_id, $evento_id);
  $st->execute(); $own = $st->get_result(); $st->close();
  if (!$own || $own->num_rows===0) {
    flash_err('El competidor no existe o no pertenece a este evento.');
    header('Location: ver_competidores_evento.php?evento_id='.$evento_id); exit;
  }

  // Bloquear si está en peleas
  $pe = pe_get_cols($conexion);
  if ($pe && $pe['evento'] && $pe['rojo'] && $pe['azul']) {
    $sqlRef = "SELECT 1 FROM peleas_evento
               WHERE ".bt($pe['evento'])."=?
                 AND (".bt($pe['rojo'])."=? OR ".bt($pe['azul'])."=?)
               LIMIT 1";
    $st = $conexion->prepare($sqlRef);
    if ($st) {
      $st->bind_param('iii', $evento_id, $comp_id, $comp_id);
      $st->execute(); $ref = $st->get_result(); $st->close();
      if ($ref && $ref->num_rows>0) {
        flash_err('No se puede eliminar: el competidor ya está en una pelea de este evento.');
        header('Location: ver_competidores_evento.php?evento_id='.$evento_id); exit;
      }
    }
  }

  $del = $conexion->prepare("DELETE FROM competidores_evento WHERE id=? AND evento_id=?");
  if (!$del) { flash_err('No se pudo preparar la eliminación: '.$conexion->error); header('Location: ver_competidores_evento.php?evento_id='.$evento_id); exit; }
  $del->bind_param('ii', $comp_id, $evento_id);
  if ($del->execute() && $del->affected_rows>0) flash_ok('Competidor eliminado correctamente.');
  else flash_err('No se pudo eliminar el competidor (puede no existir).');
  $del->close();
  header('Location: ver_competidores_evento.php?evento_id='.$evento_id); exit;
}

/* =========================
   Catálogos
   ========================= */
$disciplinas         = $conexion->query("SELECT id, nombre FROM disciplinas_evento ORDER BY id");
$modalidades         = $conexion->query("SELECT id, nombre FROM modalidades_evento ORDER BY id");
$divisiones          = $conexion->query("SELECT id, nombre FROM divisiones_evento ORDER BY id");
$categorias_tecnicas = $conexion->query("SELECT id, codigo, descripcion FROM categorias_tecnicas_evento ORDER BY id");

/* =========================
   Filtros
   ========================= */
$f_disciplina_id        = (isset($_GET['disciplina_id'])        && is_numeric($_GET['disciplina_id']))        ? (int)$_GET['disciplina_id']        : null;
$f_modalidad_id         = (isset($_GET['modalidad_id'])         && is_numeric($_GET['modalidad_id']))         ? (int)$_GET['modalidad_id']         : null;
$f_division_id          = (isset($_GET['division_id'])          && is_numeric($_GET['division_id']))          ? (int)$_GET['division_id']          : null;
$f_categoria_tecnica_id = (isset($_GET['categoria_tecnica_id']) && is_numeric($_GET['categoria_tecnica_id'])) ? (int)$_GET['categoria_tecnica_id'] : null;

/* =========================
   Columna de peso (opcional)
   ========================= */
$peso_col = null;
$colRes = $conexion->query("SHOW COLUMNS FROM competidores_evento");
if ($colRes) {
  $have=[]; while($r=$colRes->fetch_assoc()){ $have[strtolower($r['Field'])] = $r['Field']; }
  foreach (['peso_kg','peso','peso_decl','kg','peso_competidor','weight_kg','weight'] as $cand){
    $lc = strtolower($cand);
    if (isset($have[$lc])) { $peso_col = $have[$lc]; break; }
  }
}

/* =========================
   Consulta (versión robusta, una sola vez)
   ========================= */
$cols = [
  'ce.id',
  'ce.apellido',
  'ce.nombre',
  'ce.dni',
  'ce.edad',
  'ce.foto_competidor',
  'ce.escuela_logo',
  'ce.escuela_nombre',
  'd.nombre  AS disciplina',
  'm.nombre  AS modalidad',
  'dv.nombre AS division',
  'ct.codigo AS categoria_tecnica_codigo',
  'ct.descripcion AS categoria_tecnica_desc'
];

if (!empty($peso_col)) {
  $cols[] = 'ce.' . bt($peso_col) . ' AS peso_declarado';
} else {
  $cols[] = 'NULL AS peso_declarado';
}

$sql  = "SELECT \n  " . implode(",\n  ", $cols) . "\n";
$sql .= "FROM competidores_evento ce
LEFT JOIN disciplinas_evento         d  ON d.id  = ce.disciplina_id
LEFT JOIN modalidades_evento         m  ON m.id  = ce.modalidad_id
LEFT JOIN divisiones_evento          dv ON dv.id = ce.division_id
LEFT JOIN categorias_tecnicas_evento ct ON ct.id = ce.categoria_tecnica_id
WHERE ce.evento_id = ?
";

$types  = 'i';
$params = [$evento_id];

if (!is_null($f_disciplina_id))        { $sql .= " AND ce.disciplina_id = ?";        $types.='i'; $params[]=$f_disciplina_id; }
if (!is_null($f_modalidad_id))         { $sql .= " AND ce.modalidad_id = ?";         $types.='i'; $params[]=$f_modalidad_id; }
if (!is_null($f_division_id))          { $sql .= " AND ce.division_id = ?";          $types.='i'; $params[]=$f_division_id; }
if (!is_null($f_categoria_tecnica_id)) { $sql .= " AND ce.categoria_tecnica_id = ?"; $types.='i'; $params[]=$f_categoria_tecnica_id; }

$sql .= " ORDER BY ce.apellido, ce.nombre";

$st = $conexion->prepare($sql);
if (!$st) {
  http_response_code(500);
  exit('❌ SQL prepare: '.$conexion->error."\n\nSQL:\n".$sql);
}

/* bind dinámico */
$refs = []; $refs[] = &$types; foreach ($params as $k => &$v) { $refs[] = &$v; }
call_user_func_array([$st,'bind_param'], $refs);

$st->execute();
$res = $st->get_result();
$competidores = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

/* =========================
   MAPA: competidores que YA TIENEN PELEA
   ========================= */
$tienePelea = [];
$pe = pe_get_cols($conexion);
if ($pe && $pe['evento'] && $pe['rojo'] && $pe['azul']) {
  $sqlPe = "SELECT DISTINCT comp_id FROM (
              SELECT ".bt($pe['rojo'])." AS comp_id
              FROM peleas_evento
              WHERE ".bt($pe['evento'])."=?
              UNION
              SELECT ".bt($pe['azul'])." AS comp_id
              FROM peleas_evento
              WHERE ".bt($pe['evento'])."=?
            ) t
            WHERE comp_id IS NOT NULL";
  $stPe = $conexion->prepare($sqlPe);
  if ($stPe) {
    $stPe->bind_param('ii', $evento_id, $evento_id);
    if ($stPe->execute()) {
      $rPe = $stPe->get_result();
      while($row = $rPe->fetch_assoc()){
        $cid = (int)$row['comp_id'];
        if ($cid > 0) $tienePelea[$cid] = true;
      }
    }
    $stPe->close();
  }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ver Competidores del Evento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor { max-width: 1200px; margin: 0 auto; padding: 16px; }
    .alert { padding:10px 12px;border-radius:8px;margin-bottom:12px }
    .alert.error{background:#fdecea;color:#b71c1c;border:1px solid #f5c6cb}
    .alert.ok{background:#e6f4ea;color:#0f5132;border:1px solid #badbcc}
    form .filters { display:grid; grid-template-columns:repeat(4,minmax(160px,1fr)); gap:12px; align-items:end; margin-bottom:16px; }
    form label { font-weight:600; font-size:14px; }
    form select, form button { width:100%; padding:8px 10px; border:1px solid #dcdcdc; border-radius:8px; }
    form button { cursor:pointer; }
    @media (max-width:900px){ form .filters{ grid-template-columns:repeat(2,1fr);} }
    @media (max-width:600px){ form .filters{ grid-template-columns:1fr; } form button{ padding:12px; } }
    .table-wrap { width:100%; overflow-x:auto; }
    .tabla { width:100%; border-collapse:collapse; min-width:1040px; }
    .tabla th, .tabla td { border:1px solid #e7e7e7; padding:8px 10px; vertical-align:middle; }
    .tabla th { background:#f6f7f9; text-align:left; }
    .avatar { width:60px; height:60px; object-fit:cover; border-radius:50%; }
    .logo   { width:60px; height:60px; object-fit:cover; border-radius:14px; }
    @media (max-width:680px){
      .tabla, .tabla thead, .tabla tbody, .tabla th, .tabla td, .tabla tr { display:block; }
      .tabla thead { position:absolute; left:-9999px; top:-9999px; }
      .tabla tr { border:1px solid #e7e7e7; border-radius:10px; margin-bottom:12px; background:#fff; }
      .tabla td { border:none; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; gap:12px; padding:10px 12px; }
      .tabla td:last-child{ border-bottom:none; }
      .tabla td::before { content: attr(data-label); font-weight:600; min-width:42%; }
      .avatar { width:72px; height:72px; }
      .logo { width:72px; height:72px; border-radius:16px; }
    }
    .btn { display:inline-block; padding:8px 10px; border-radius:8px; text-decoration:none; border:1px solid #dcdcdc; background:#fff; color:#0f172a; }
    .btn:hover { background:#f2f4f7; }
    .btn-primary { background:#1e88e5; color:#fff; border:0; }
    .btn-primary:hover { background:#166fbe; }
    .btn-danger { background:#dc2626; color:#fff; border:0; }
    form.inline { display:inline; }
    .actions a, .actions button { margin-right:6px; margin-bottom:6px; }

    /* Marca de competidores con pelea */
    .tabla tr.tr-pelea { background:#ecfdf5; } /* verde muy suave */
    .badge-pelea {
      display:inline-block;
      margin-top:4px;
      padding:2px 6px;
      font-size:11px;
      border-radius:999px;
      background:#16a34a;
      color:#fff;
      font-weight:600;
    }
  </style>
</head>
<body>
<div class="contenedor">
  <h2>👊 Competidores del Evento #<?= (int)$evento_id ?></h2>

  <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert error"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['flash_ok'])): ?>
    <div class="alert ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
  <?php endif; ?>

  <p style="font-size:13px;margin-top:4px;margin-bottom:10px;">
    <span class="badge-pelea">✔ Tiene pelea</span> = competidor que ya está en una pelea cargada en <code>ver_peleas_evento.php</code>
    (útil para saber qué deslindes imprimir primero).
  </p>

  <form method="GET">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <div class="filters">
      <div>
        <label>Disciplina</label>
        <select name="disciplina_id">
          <option value="">Todas</option>
          <?php while($d = $disciplinas->fetch_assoc()): ?>
            <option value="<?= (int)$d['id'] ?>" <?= (!is_null($f_disciplina_id) && $f_disciplina_id==(int)$d['id'])?'selected':'' ?>>
              <?= h($d['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label>Modalidad</label>
        <select name="modalidad_id">
          <option value="">Todas</option>
          <?php while($m = $modalidades->fetch_assoc()): ?>
            <option value="<?= (int)$m['id'] ?>" <?= (!is_null($f_modalidad_id) && $f_modalidad_id==(int)$m['id'])?'selected':'' ?>>
              <?= h($m['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label>Categoría Técnica</label>
        <select name="categoria_tecnica_id">
          <option value="">Todas</option>
          <?php while($ct = $categorias_tecnicas->fetch_assoc()): ?>
            <option value="<?= (int)$ct['id'] ?>" <?= (!is_null($f_categoria_tecnica_id) && $f_categoria_tecnica_id==(int)$ct['id'])?'selected':'' ?>>
              <?= h(($ct['codigo'] ?? '').(isset($ct['descripcion']) && $ct['descripcion']!=='' ? ' - '.$ct['descripcion'] : '')) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label>División</label>
        <select name="division_id">
          <option value="">Todas</option>
          <?php while($dv = $divisiones->fetch_assoc()): ?>
            <option value="<?= (int)$dv['id'] ?>" <?= (!is_null($f_division_id) && $f_division_id==(int)$dv['id'])?'selected':'' ?>>
              <?= h($dv['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div><button type="submit" class="btn">🔍 Filtrar</button></div>
    </div>
  </form>

  <p style="margin-bottom:12px">
    <a class="btn btn-primary" href="agregar_competidor_evento.php?evento_id=<?= (int)$evento_id ?>">➕ Agregar competidor</a>
    <a class="btn btn-primary" href="agregar_competidor_min.php?evento_id=<?= (int)$evento_id ?>&return=ver_competidores_evento.php">⚡ Carga rápida</a>
  </p>

  <?php if (!$competidores): ?>
    <p>No hay competidores que coincidan con los filtros.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th>Foto</th>
            <th>Apellido y Nombre</th>
            <th>DNI</th>
            <th>Edad</th>
            <th>Disciplina</th>
            <th>Modalidad</th>
            <th>Cat. Técnica</th>
            <th>Peso (decl.)</th>
            <th>División</th>
            <th>Escuela</th>
            <th>Logo</th>
            <th>Deslinde</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($competidores as $c):
            // Foto (o avatar)
            $nombreCompleto = trim(($c['nombre'] ?? '').' '.($c['apellido'] ?? ''));
            $iniUser = iniciales_de($c['nombre'] ?? '', $c['apellido'] ?? '');
            $srcFoto = !empty($c['foto_competidor'])
                        ? $c['foto_competidor']
                        : avatar_svg_data_uri($nombreCompleto, $iniUser, 60, true);

            // Logo escuela (o avatar cuadrado)
            $escNom = trim((string)($c['escuela_nombre'] ?? ''));
            $iniEsc = iniciales_de($escNom, '', 'E', 2);
            $srcLogo = !empty($c['escuela_logo'])
                        ? $c['escuela_logo']
                        : avatar_svg_data_uri($escNom !== '' ? $escNom : 'Escuela', $iniEsc, 60, false);

            // Técnica label
            $catTec  = trim(($c['categoria_tecnica_codigo'] ?? '').' - '.($c['categoria_tecnica_desc'] ?? ''));
            $catTec  = ($catTec === ' - ')? '-' : $catTec;

            // Peso declarado (formateado)
            $pesoDecl = fmt_kg($c['peso_declarado'] ?? null);

            $enPelea = !empty($tienePelea[(int)$c['id']]);
          ?>
            <tr class="<?= $enPelea ? 'tr-pelea' : '' ?>">
              <td data-label="Foto"><img class="avatar" src="<?= h($srcFoto) ?>" alt="Foto"></td>
              <td data-label="Apellido y Nombre">
                <?= h($c['apellido']) ?> <?= h($c['nombre']) ?>
                <?php if ($enPelea): ?>
                  <div><span class="badge-pelea">✔ Tiene pelea</span></div>
                <?php endif; ?>
              </td>
              <td data-label="DNI"><?= h($c['dni']) ?></td>
              <td data-label="Edad"><?= h($c['edad']) ?></td>
              <td data-label="Disciplina"><?= h($c['disciplina'] ?? '-') ?></td>
              <td data-label="Modalidad"><?= h($c['modalidad'] ?? '-') ?></td>
              <td data-label="Cat. Técnica"><?= h($catTec) ?></td>
              <td data-label="Peso (decl.)"><?= h($pesoDecl) ?></td>
              <td data-label="División"><?= h($c['division'] ?? '-') ?></td>
              <td data-label="Escuela"><?= h($c['escuela_nombre'] ?? '-') ?></td>
              <td data-label="Logo"><img class="logo" src="<?= h($srcLogo) ?>" alt="Logo"></td>

              <td data-label="Deslinde">
                <?php if ($enPelea): ?>
                  <a class="btn btn-primary" href="waiver_print_competidor.php?id=<?= (int)$c['id'] ?>&evento_id=<?= (int)$evento_id ?>" target="_blank">🧾 PDF</a>
                <?php else: ?>
                  <a class="btn" href="waiver_print_competidor.php?id=<?= (int)$c['id'] ?>&evento_id=<?= (int)$evento_id ?>" target="_blank">🧾 PDF</a>
                <?php endif; ?>
              </td>

              <td class="actions" data-label="Acciones">
                <a class="btn" href="editar_competidor_evento.php?evento_id=<?= (int)$evento_id ?>&id=<?= (int)$c['id'] ?>">✏️ Editar</a>
                <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar a <?= h($c['apellido'].' '.$c['nombre']) ?> del evento?');">
                  <input type="hidden" name="accion" value="eliminar_comp">
                  <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                  <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
                  <input type="hidden" name="comp_id" value="<?= (int)$c['id'] ?>">
                  <button type="submit" class="btn btn-danger">🗑️ Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p style="margin-top:14px">
    <a class="btn btn-primary" href="agregar_competidor_evento.php?evento_id=<?= (int)$evento_id ?>">➕ Agregar competidor</a>
    <a class="btn btn-primary" href="agregar_competidor_min.php?evento_id=<?= (int)$evento_id ?>&return=ver_competidores_evento.php">⚡ Carga rápida</a>
  </p>
</div>
</body>
</html>
