<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/auth_roles.php';
require_login();

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión BD.'); }
@mysqli_report(MYSQLI_REPORT_OFF); @$conexion->set_charset('utf8mb4');

$role = user_role();
$uid  = user_id();

/* ========= QUERY SEGÚN ROL ========= */
/* Estructura asumida: eventos_deportivos(id, titulo, fecha, hora, lugar, flyer, video)
   Si tus nombres difieren, cambiá SOLO el SELECT de abajo.
*/

if ($role === ROL_ORGANIZADOR) {
  // ORG ve todos
  $sql = "SELECT id, titulo, fecha, hora, lugar, flyer, video
          FROM eventos_deportivos
          ORDER BY fecha DESC, hora DESC";
  $st = $conexion->prepare($sql);
} else {
  // STAFF/JUEZ: solo asignados
  $sql = "SELECT e.id, e.titulo, e.fecha, e.hora, e.lugar, e.flyer, e.video
          FROM eventos_deportivos e
          JOIN evento_asignaciones a ON a.evento_id = e.id
          WHERE a.user_id = ?
          ORDER BY e.fecha DESC, e.hora DESC";
  $st = $conexion->prepare($sql);
  if (!$st) { http_response_code(500); exit('❌ Error SQL (prepare): '.$conexion->error); }
  $st->bind_param('i', $uid);
}

if (!$st) { http_response_code(500); exit('❌ Error SQL (prepare): '.$conexion->error); }
if (!$st->execute()) { http_response_code(500); exit('❌ Error SQL (execute): '.$st->error); }
$res = $st->get_result();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel de Eventos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{margin:0;background:#0a0a0a;color:#f6f6f6;font-family:system-ui,Segoe UI,Roboto}
  .wrap{max-width:1100px;margin:18px auto;padding:0 12px}
  a{color:#d4af37;text-decoration:none}
  .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem .8rem;background:#111;color:#d4af37;border:1px solid #222;border-radius:10px}
  table{width:100%;border-collapse:collapse;min-width:780px}
  th,td{padding:.6rem .65rem;border-bottom:1px solid #222}
  thead th{position:sticky;top:0;background:#111;color:#d4af37;text-align:left}
  @media (max-width:820px){
    table{border-collapse:separate;border-spacing:0 10px;min-width:0}
    thead{display:none}
    tbody tr{display:block;background:#151515;border:1px solid #222;border-radius:14px;padding:8px 10px}
    tbody td{display:flex;justify-content:space-between;gap:10px;border:0}
    tbody td::before{content:attr(data-label);color:#c9c9c9}
  }
</style>
</head>
<body>
<div class="wrap">
  <h2>🏆 Panel de Eventos</h2>
  <div style="margin:10px 0 16px;display:flex;gap:10px;align-items:center;justify-content:space-between">
    <a class="btn" href="crear_evento.php">➕ Nuevo Evento</a>
    <small style="color:#c9c9c9">Rol: <?=htmlspecialchars($role)?> · Usuario: <?=$uid?></small>
  </div>

  <?php if (!$res || $res->num_rows===0): ?>
    <div>No hay eventos para mostrar.</div>
  <?php else: ?>
    <div style="overflow:auto">
      <table>
        <thead>
          <tr>
            <th>Título</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Lugar</th>
            <th>Flyer</th>
            <th>Video</th>
            <th>Ingresar</th>
            <?php if ($role===ROL_ORGANIZADOR): ?><th>Acciones</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php while($e = $res->fetch_assoc()): $id=(int)$e['id']; ?>
          <tr>
            <td data-label="Título"><a href="ver_evento.php?id=<?=$id?>"><?=htmlspecialchars($e['titulo']??'')?></a></td>
            <td data-label="Fecha"><?=htmlspecialchars($e['fecha']??'')?></td>
            <td data-label="Hora"><?=htmlspecialchars($e['hora']??'')?></td>
            <td data-label="Lugar"><?=htmlspecialchars($e['lugar']??'')?></td>
            <td data-label="Flyer">
              <?php if (!empty($e['flyer'])): ?>
                <a class="btn" href="<?=htmlspecialchars($e['flyer'])?>" target="_blank" rel="noopener">📷 Ver</a>
              <?php else: ?>❌<?php endif; ?>
            </td>
            <td data-label="Video">
              <?php if (!empty($e['video'])): ?>
                <a class="btn" href="<?=htmlspecialchars($e['video'])?>" target="_blank" rel="noopener">▶️ Ver</a>
              <?php else: ?>❌<?php endif; ?>
            </td>
            <td data-label="Ingresar"><a class="btn" href="ver_evento.php?id=<?=$id?>">Entrar</a></td>
            <?php if ($role===ROL_ORGANIZADOR): ?>
              <td data-label="Acciones">
                <a class="btn" href="editar_evento.php?id=<?=$id?>">✏️</a>
                <a class="btn" href="eliminar_evento.php?id=<?=$id?>" onclick="return confirm('¿Eliminar evento?')">🗑️</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
