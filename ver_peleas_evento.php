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
$C_EVENTO   = $pick(['evento_id','id_evento','evento']);
$C_ROJO     = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL     = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_FECHA    = $pick(['fecha','creado_en','created_at','created','fh_creacion']); // para orden

if (!$C_EVENTO || !$C_ROJO || !$C_AZUL) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">
          La tabla <b>peleas_evento</b> existe pero faltan columnas obligatorias.<br>
          Necesarias (cualquiera de los alias):<br>
          • Evento: <code>evento_id</code> / <code>id_evento</code> / <code>evento</code><br>
          • Rojo: <code>competidor_rojo_id</code> / <code>rojo_id</code> / <code>id_rojo</code> / <code>id_competidor_rojo</code> / <code>rojo</code><br>
          • Azul: <code>competidor_azul_id</code> / <code>azul_id</code> / <code>id_azul</code> / <code>id_competidor_azul</code> / <code>azul</code><br>
          Detectadas: <code>'.h(implode(', ', array_values($cols))).'</code>
        </div>';
  exit;
}

/* ========== Query de peleas ========== */
$colE = bt($C_EVENTO);
$colR = bt($C_ROJO);
$colA = bt($C_AZUL);
$colOrder = $C_FECHA ? 'p.'.bt($C_FECHA) : 'p.id';

$sql = "
SELECT 
  p.id AS pelea_id,
  cr.apellido AS r_apellido, cr.nombre AS r_nombre, cr.escuela_nombre AS r_escuela,
  cr.foto_competidor AS r_foto, cpr.nombre AS r_peso, dvr.nombre AS r_division, mr.nombre AS r_modalidad,
  ca.apellido AS a_apellido, ca.nombre AS a_nombre, ca.escuela_nombre AS a_escuela,
  ca.foto_competidor AS a_foto, cpa.nombre AS a_peso, dva.nombre AS a_division, ma.nombre AS a_modalidad
FROM peleas_evento p
JOIN competidores_evento cr ON p.$colR = cr.id
JOIN competidores_evento ca ON p.$colA = ca.id
LEFT JOIN categorias_peso_evento cpr ON cpr.id = cr.categoria_peso_id
LEFT JOIN divisiones_evento      dvr ON dvr.id = cr.division_id
LEFT JOIN modalidades_evento     mr  ON mr.id  = cr.modalidad_id
LEFT JOIN categorias_peso_evento cpa ON cpa.id = ca.categoria_peso_id
LEFT JOIN divisiones_evento      dva ON dva.id = ca.division_id
LEFT JOIN modalidades_evento     ma  ON ma.id  = ca.modalidad_id
WHERE p.$colE = ?
ORDER BY $colOrder DESC
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
    .contenedor{max-width:1100px;margin:0 auto;padding:14px;text-align:center}
    .table-wrap{width:100%;overflow-x:auto;margin-top:8px}
    table{width:100%;border-collapse:collapse;min-width:860px;margin:0 auto}
    th,td{border:1px solid #e7e7e7;padding:5px 6px;vertical-align:middle}
    th{background:#f6f7f9;font-size:13px}
    td{font-size:12.5px;line-height:1.1}
    .avatar{width:44px;height:44px;object-fit:cover;border-radius:8px}
    .pill{display:inline-block;padding:1px 6px;border-radius:999px;background:#eef5ff;color:#1e4fa1;font-size:11.5px}
    .vs{font-weight:700;text-align:center;font-size:16px;color:#666}
    .btn{display:inline-block;padding:7px 10px;border-radius:8px;border:0;cursor:pointer;text-decoration:none}
    .btn-primary{background:#1e88e5;color:#fff}
    .muted{color:#666;font-size:11.5px}
    .acciones{text-align:center}
  </style>
</head>
<body>
<div class="contenedor">
  <h2>📋 Peleas programadas — Evento #<?= (int)$evento_id ?></h2>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th colspan="4">Esquina Roja</th>
          <th class="vs">VS</th>
          <th colspan="4">Esquina Azul</th>
          <th class="acciones">Acciones</th>
        </tr>
        <tr>
          <th>Foto</th><th>Nombre</th><th>Info</th><th>Escuela</th>
          <th></th>
          <th>Foto</th><th>Nombre</th><th>Info</th><th>Escuela</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$peleas): ?>
        <tr><td colspan="10">No hay peleas programadas todavía.</td></tr>
      <?php else: foreach ($peleas as $p):
        $rFoto = !empty($p['r_foto']) ? $p['r_foto'] : $ph;
        $aFoto = !empty($p['a_foto']) ? $p['a_foto'] : $ph;
        $rInfo = trim(($p['r_peso'] ?? '-').' / '.($p['r_division'] ?? '-').' / '.($p['r_modalidad'] ?? '-'));
        $aInfo = trim(($p['a_peso'] ?? '-').' / '.($p['a_division'] ?? '-').' / '.($p['a_modalidad'] ?? '-'));
      ?>
        <tr>
          <td><img src="<?= h($rFoto) ?>" class="avatar" alt="Rojo"></td>
          <td><?= h($p['r_apellido'].' '.$p['r_nombre']) ?></td>
          <td><span class="pill"><?= h($rInfo) ?></span></td>
          <td class="muted"><?= h($p['r_escuela'] ?? '-') ?></td>

          <td class="vs">vs</td>

          <td><img src="<?= h($aFoto) ?>" class="avatar" alt="Azul"></td>
          <td><?= h($p['a_apellido'].' '.$p['a_nombre']) ?></td>
          <td><span class="pill"><?= h($aInfo) ?></span></td>
          <td class="muted"><?= h($p['a_escuela'] ?? '-') ?></td>

          <td class="acciones">
            <a class="btn btn-primary" href="combate_en_vivo.php?pelea_id=<?= (int)$p['pelea_id'] ?>">▶️ Iniciar</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
