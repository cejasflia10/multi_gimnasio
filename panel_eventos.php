<?php
/* ============================================================
   panel_eventos.php — Panel principal
   - Requiere login de eventos (evento_usuario_id)
   - Si hay gimnasio_id filtra; si no, muestra todos
   - Acceso principal al "link del evento": ver_evento.php?id=ID
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- Guardia de sesión ---------- */
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'panel_eventos.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}

/* ---------- Conexión ---------- */
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ---------- Menú ---------- */
@include __DIR__ . '/menu_eventos.php';

/* ---------- Query de eventos ---------- */
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id > 0) {
  $st = $conexion->prepare("
      SELECT id, titulo, fecha, hora, lugar, flyer, video
      FROM eventos_deportivos
      WHERE gimnasio_id = ?
      ORDER BY fecha DESC
  ");
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $resultado = $st->get_result();
} else {
  $resultado = $conexion->query("
      SELECT id, titulo, fecha, hora, lugar, flyer, video
      FROM eventos_deportivos
      ORDER BY fecha DESC
  ");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Eventos Deportivos</title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    table { width:100%; border-collapse:collapse }
    th, td { padding:.55rem; border-bottom:1px solid #ddd; text-align:left; vertical-align:middle }
    thead th { background:#111; color:gold }
    .boton { display:inline-block; padding:.45rem .7rem; background:#111; color:gold; text-decoration:none; border-radius:6px }
    .contenedor { max-width:1100px; margin:20px auto }
  </style>
</head>
<body>
<div class="contenedor">
  <h2>🏆 Panel de Eventos Deportivos</h2>

  <div style="margin:10px 0 16px">
    <a href="crear_evento.php" class="boton">➕ Nuevo Evento</a>
  </div>

  <?php if (!$resultado): ?>
    <div style="color:#ff6b6b">⚠️ No se pudo obtener la lista de eventos.</div>
  <?php elseif ($resultado->num_rows === 0): ?>
    <div>No hay eventos cargados.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Título (link del evento)</th>
          <th>Fecha</th>
          <th>Hora</th>
          <th>Lugar</th>
          <th>Flyer</th>
          <th>Video</th>
          <th>Ingresar</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($e = $resultado->fetch_assoc()): $eid = (int)$e['id']; ?>
          <tr>
            <td>
              <!-- Título enlaza a la vista general del evento -->
              <a href="ver_evento.php?id=<?= $eid ?>"><?= h($e['titulo']) ?></a>
            </td>
            <td><?= h($e['fecha']) ?></td>
            <td><?= h($e['hora']) ?></td>
            <td><?= h($e['lugar']) ?></td>
            <td>
              <?php if (!empty($e['flyer'])): ?>
                <a href="<?= h($e['flyer']) ?>" target="_blank">📷 Ver</a>
              <?php else: ?>❌<?php endif; ?>
            </td>
            <td>
              <?php if (!empty($e['video'])): ?>
                <a href="<?= h($e['video']) ?>" target="_blank">▶️ Ver</a>
              <?php else: ?>❌<?php endif; ?>
            </td>
            <td>
              <!-- Botón principal para "entrar" al evento (misma vista general) -->
              <a class="boton" href="ver_evento.php?id=<?= $eid ?>">Entrar</a>
            </td>
            <td>
              <a href="editar_evento.php?id=<?= $eid ?>">✏️</a>
              <a href="eliminar_evento.php?id=<?= $eid ?>" onclick="return confirm('¿Eliminar evento?')">🗑️</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>
