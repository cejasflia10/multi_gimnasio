<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_eventos.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($col){ return '`'.str_replace('`','``', $col).'`'; }

/* ========== evento_id del contexto ========== */
$evento_id = (int)($_GET['evento_id'] ?? $_SESSION['evento_id_actual'] ?? $_SESSION['evento_id'] ?? 0);
if ($evento_id <= 0) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">
          Falta <b>evento_id</b>. Volvé desde el evento.
        </div>';
  exit;
}
$_SESSION['evento_id_actual'] = $evento_id;

/* ========== Detectar columnas de peleas_evento ========== */
$cols = [];
$res = $conexion->query("SHOW COLUMNS FROM peleas_evento");
if (!$res) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">
          No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'
        </div>';
  exit;
}
while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
$pick = function(array $cands) use ($cols){
  foreach ($cands as $c) { $lc = strtolower($c); if (isset($cols[$lc])) return $cols[$lc]; }
  return null;
};
$C_ID       = $pick(['id','pelea_id','id_pelea']);
$C_EVENTO   = $pick(['evento_id','id_evento','evento']);
$C_ROJO     = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL     = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_RONDAS   = $pick(['rondas','rounds']);
$C_OBS      = $pick(['observaciones','obs','comentarios','comentario','nota']);
$C_FECHA    = $pick(['fecha','creado_en','created_at','created','fh_creacion']); // fallback para orden inicial

if (!$C_EVENTO || !$C_ROJO || !$C_AZUL) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">
          La tabla <b>peleas_evento</b> existe pero faltan columnas obligatorias (evento/rojo/azul).
        </div>';
  exit;
}

/* ========== Acciones (SOLO eliminar acá) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion   = $_POST['accion'] ?? '';
  $pelea_id = isset($_POST['pelea_id']) && is_numeric($_POST['pelea_id']) ? (int)$_POST['pelea_id'] : 0;

  // Verificar pertenencia al evento
  if ($pelea_id > 0) {
    $sqlChk = "SELECT 1 FROM peleas_evento WHERE ".bt($C_EVENTO)." = ? AND ".bt($C_ID ?: 'id')." = ? LIMIT 1";
    $st = $conexion->prepare($sqlChk);
    if ($st) {
      $st->bind_param('ii', $evento_id, $pelea_id);
      $st->execute();
      $ok = $st->get_result()->num_rows === 1;
      $st->close();
      if (!$ok) { $pelea_id = 0; }
    } else { $pelea_id = 0; }
  }

  if ($accion === 'delete' && $pelea_id > 0) {
    $sqlD = "DELETE FROM peleas_evento WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID ?: 'id')."=? LIMIT 1";
    $st = $conexion->prepare($sqlD);
    if ($st) {
      $st->bind_param('ii', $evento_id, $pelea_id);
      $st->execute(); $st->close();
    }
    header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id); exit;
  }
}

/* ========== Listado de peleas ========== */
$colE = bt($C_EVENTO); $colR = bt($C_ROJO); $colA = bt($C_AZUL);
$orderBy = $C_FECHA ? ('p.'.bt($C_FECHA)) : 'p.'.bt($C_ID ?: 'id');
$selectRondas = $C_RONDAS ? (', p.'.bt($C_RONDAS).' AS rondas') : ', NULL AS rondas';
$selectObs    = $C_OBS    ? (', p.'.bt($C_OBS).' AS observaciones') : ', NULL AS observaciones';

/* Traemos además la categoría técnica de cada competidor para el orden secundario */
$sql = "
SELECT 
  p.".bt($C_ID ?: 'id')." AS pelea_id
  $selectRondas
  $selectObs,
  cr.apellido AS r_apellido, cr.nombre AS r_nombre, cr.escuela_nombre AS r_escuela,
  cr.foto_competidor AS r_foto, cpr.nombre AS r_peso, dvr.nombre AS r_division, mr.nombre AS r_modalidad,
  ctr.codigo AS r_cat_cod, ctr.descripcion AS r_cat_desc, ctr.id AS r_cat_id,

  ca.apellido AS a_apellido, ca.nombre AS a_nombre, ca.escuela_nombre AS a_escuela,
  ca.foto_competidor AS a_foto, cpa.nombre AS a_peso, dva.nombre AS a_division, ma.nombre AS a_modalidad,
  cta.codigo AS a_cat_cod, cta.descripcion AS a_cat_desc, cta.id AS a_cat_id
FROM peleas_evento p
JOIN competidores_evento cr ON p.$colR = cr.id
JOIN competidores_evento ca ON p.$colA = ca.id
LEFT JOIN categorias_peso_evento     cpr ON cpr.id = cr.categoria_peso_id
LEFT JOIN divisiones_evento          dvr ON dvr.id = cr.division_id
LEFT JOIN modalidades_evento         mr  ON mr.id  = cr.modalidad_id
LEFT JOIN categorias_tecnicas_evento ctr ON ctr.id = cr.categoria_tecnica_id

LEFT JOIN categorias_peso_evento     cpa ON cpa.id = ca.categoria_peso_id
LEFT JOIN divisiones_evento          dva ON dva.id = ca.division_id
LEFT JOIN modalidades_evento         ma  ON ma.id  = ca.modalidad_id
LEFT JOIN categorias_tecnicas_evento cta ON cta.id = ca.categoria_tecnica_id

WHERE p.$colE = ?
/* Orden preliminar estable (por fecha/ID) para que el usort sea determinístico */
ORDER BY $orderBy ASC
";
$st = $conexion->prepare($sql);
if (!$st) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Error preparando la lista de peleas: '.h($conexion->error).'</div>';
  exit;
}
$st->bind_param('i', $evento_id);
$st->execute();
$peleas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* ====== Clasificación y ordenamiento según reglas ====== */
function norm($s){ return mb_strtolower(trim((string)$s), 'UTF-8'); }

/**
 * Retorna [bloque_prioridad, etiqueta_bloque]
 * Bloques (menor número = antes):
 * 1: Exhibiciones Boxeo
 * 2: Exhibiciones Lowkick
 * 3: Amateur Boxeo
 * 4: Amateur Lowkick
 * 5: Amateur K1
 * 6: ProAm
 * 7: K1
 * 8: MMA
 * 9: Pro
 * 99: Otros / sin clasificar
 */
function clasificar_bloque(array $row): array {
  $modR = norm($row['r_modalidad'] ?? '');
  $modA = norm($row['a_modalidad'] ?? '');
  $obs  = norm($row['observaciones'] ?? '');
  $mod  = $modR.' '.$modA.' '.$obs;

  $isExhib = (bool)preg_match('/exhib/i', $mod);
  $isAma   = (bool)preg_match('/amateur|amat\b/i', $mod);
  $isProAm = (bool)preg_match('/pro[\s\-]?am/i', $mod);
  $isPro   = (bool)preg_match('/\bpro\b|prof(esional)?/i', $mod);

  $isK1    = (bool)preg_match('/\bk1\b/i', $mod);
  $isMMA   = (bool)preg_match('/\bmma\b/i', $mod);
  $isBox   = (bool)preg_match('/box|boxeo/i', $mod);
  $isLow   = (bool)preg_match('/low[\s\-]?kick/i', $mod);

  // Exhibiciones primero
  if ($isExhib && $isBox) return [1, 'Exhibición Boxeo'];
  if ($isExhib && $isLow) return [2, 'Exhibición Lowkick'];

  // Amateur (por modalidad/obs)
  if ($isAma && $isBox)   return [3, 'Amateur Boxeo'];
  if ($isAma && $isLow)   return [4, 'Amateur Lowkick'];
  if ($isAma && $isK1)    return [5, 'Amateur K1'];

  // ProAm
  if ($isProAm)           return [6, 'ProAm'];

  // K1 / MMA "abiertos" (no marcar como amateur explícito)
  if ($isK1)              return [7, 'K1'];
  if ($isMMA)             return [8, 'MMA'];

  // Pro (genérico o por marca)
  if ($isPro)             return [9, 'Pro'];

  return [99, 'Otros'];
}

/* Clave de orden por categoría técnica */
function clave_tecnica(array $row): array {
  // Preferimos el código (alfanumérico), luego la descripción, luego el ID
  $cod = $row['r_cat_cod'] ?? '';
  $desc= $row['r_cat_desc'] ?? '';
  $id  = $row['r_cat_id'] ?? null;

  // Si en rojo está vacío, probamos azul
  if ($cod === '' && ($row['a_cat_cod'] ?? '') !== '') $cod = $row['a_cat_cod'];
  if ($desc === '' && ($row['a_cat_desc'] ?? '') !== '') $desc = $row['a_cat_desc'];
  if (is_null($id) && !is_null($row['a_cat_id'] ?? null)) $id = $row['a_cat_id'];

  $codNorm  = strtoupper(trim((string)$cod));
  $descNorm = strtoupper(trim((string)$desc));
  $idVal    = (int)($id ?? 0);

  // Construimos una tupla comparable
  if ($codNorm !== '')   return [0, $codNorm, $idVal, $descNorm];
  if ($descNorm !== '')  return [1, $descNorm, $idVal, ''];
  return [2, '', $idVal, ''];
}

/* Ordenamos en PHP según (bloque, técnica, id_pelea) */
usort($peleas, function($A, $B){
  [$b1a] = clasificar_bloque($A);
  [$b1b] = clasificar_bloque($B);
  if ($b1a !== $b1b) return $b1a <=> $b1b;

  $ta = clave_tecnica($A);
  $tb = clave_tecnica($B);
  if ($ta !== $tb) return $ta <=> $tb;

  // estable: por pelea_id asc
  return ((int)$A['pelea_id']) <=> ((int)$B['pelea_id']);
});

/* Enumeración final */
$enumerado = [];
$contador = 1;
foreach ($peleas as $p) {
  $p['_n'] = $contador++;
  [$prio, $lbl] = clasificar_bloque($p);
  $p['_bloque_lbl'] = $lbl;
  $enumerado[] = $p;
}

$peleas = $enumerado;
$ph = 'assets/placeholder-user.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🥊 Peleas del Evento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor{max-width:1150px;margin:0 auto;padding:14px;}
    .toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:10px}
    .btn{display:inline-block;padding:7px 10px;border-radius:10px;border:0;cursor:pointer;text-decoration:none}
    .btn-primary{background:#1e88e5;color:#fff}
    .btn-secondary{background:#e9ecef;color:#0f172a}
    .btn-danger{background:#d32f2f;color:#fff}
    .btn-mini{padding:4px 8px;font-size:11px;border-radius:6px}
    .btn-xxs{padding:3px 6px;font-size:10.5px;border-radius:6px}
    .table-wrap{width:100%;overflow-x:auto;margin-top:6px}
    table{width:100%;border-collapse:collapse;min-width:1040px}
    th,td{border:1px solid #e7e7e7;padding:6px 8px;vertical-align:middle}
    th{background:#f6f7f9;font-size:13px}
    td{font-size:12.5px}
    .avatar{width:44px;height:44px;object-fit:cover;border-radius:8px}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#eef5ff;color:#1e4fa1;font-size:12px}
    .muted{color:#666;font-size:11.5px}
    .acciones{text-align:center;white-space:nowrap}
    .vs{font-weight:700;text-align:center;color:#666}
    .row-actions{display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:wrap}
    .bloque{font-size:11px;color:#475569}
    .num{font-weight:700}
  </style>
</head>
<body>
<div class="contenedor">
  <div class="toolbar">
    <h2 style="margin:0">📋 Peleas programadas — Evento #<?= (int)$evento_id ?></h2>
    <div>
      <a class="btn btn-mini btn-secondary" href="organizar_pelea.php?evento_id=<?= (int)$evento_id ?>">➕ Nueva pelea</a>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Bloque</th>
          <th colspan="4">Esquina Roja</th>
          <th class="vs">VS</th>
          <th colspan="4">Esquina Azul</th>
          <th>Rondas</th>
          <th>Obs.</th>
          <th class="acciones">Acciones</th>
        </tr>
        <tr>
          <th></th><th></th>
          <th>Foto</th><th>Nombre</th><th>Info</th><th>Escuela</th>
          <th></th>
          <th>Foto</th><th>Nombre</th><th>Info</th><th>Escuela</th>
          <th></th>
          <th></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$peleas): ?>
        <tr><td colspan="14">No hay peleas programadas todavía.</td></tr>
      <?php else: foreach ($peleas as $p):
        $rFoto = !empty($p['r_foto']) ? $p['r_foto'] : $ph;
        $aFoto = !empty($p['a_foto']) ? $p['a_foto'] : $ph;
        $rInfo = trim(($p['r_peso'] ?? '-').' / '.($p['r_division'] ?? '-').' / '.($p['r_modalidad'] ?? '-'));
        $aInfo = trim(($p['a_peso'] ?? '-').' / '.($p['a_division'] ?? '-').' / '.($p['a_modalidad'] ?? '-'));
        $rondasVal = isset($p['rondas']) && is_numeric($p['rondas']) ? (int)$p['rondas'] : 3;
        $obsVal = (string)($p['observaciones'] ?? '');
      ?>
        <tr>
          <td class="num"><?= (int)$p['_n'] ?></td>
          <td class="bloque"><?= h($p['_bloque_lbl']) ?></td>

          <td><img src="<?= h($rFoto) ?>" class="avatar" alt="Roja"></td>
          <td><?= h($p['r_apellido'].' '.$p['r_nombre']) ?></td>
          <td><span class="pill"><?= h($rInfo) ?></span></td>
          <td class="muted"><?= h($p['r_escuela'] ?? '-') ?></td>

          <td class="vs">vs</td>

          <td><img src="<?= h($aFoto) ?>" class="avatar" alt="Azul"></td>
          <td><?= h($p['a_apellido'].' '.$p['a_nombre']) ?></td>
          <td><span class="pill"><?= h($aInfo) ?></span></td>
          <td class="muted"><?= h($p['a_escuela'] ?? '-') ?></td>

          <td><?= (int)$rondasVal ?></td>
          <td><?= h($obsVal) ?></td>
          <td class="acciones">
            <div class="row-actions">
              <a class="btn btn-xxs btn-primary" title="Editar"
                 href="editar_pelea.php?evento_id=<?= (int)$evento_id ?>&pelea_id=<?= (int)$p['pelea_id'] ?>">✏️ Editar</a>
              <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta pelea? Esta acción no se puede deshacer.');">
                <input type="hidden" name="pelea_id" value="<?= (int)$p['pelea_id'] ?>">
                <input type="hidden" name="accion" value="delete">
                <button type="submit" class="btn btn-xxs btn-danger" title="Eliminar">🗑️ Eliminar</button>
              </form>
              <a class="btn btn-xxs btn-secondary" title="Iniciar en vivo"
                 href="combate_en_vivo.php?evento_id=<?= (int)$evento_id ?>&pelea_id=<?= (int)$p['pelea_id'] ?><?= $C_RONDAS ? '&rondas='.(int)$rondasVal : '' ?>">▶️ Iniciar</a>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
