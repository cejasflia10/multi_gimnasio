<?php
/* ============================================================
   panel_eventos.php — Panel principal (responsive)
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

/* ---------- Menú (opcional) ---------- */
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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    :root{
      --bg:#0b0b0b;
      --fg:#f6f6f6;
      --muted:#c9c9c9;
      --brand:#d4af37; /* dorado */
      --card:#151515;
      --line:#222;
      --danger:#ff6b6b;
    }
    html,body{background:#0a0a0a;color:var(--fg); margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}
    a{color:var(--brand); text-decoration:none}
    a:hover{opacity:.9}
    .contenedor{max-width:1100px; margin:20px auto; padding:0 12px}
    h2{margin:8px 0 16px; font-weight:700}

    .toolbar{
      display:flex; gap:10px; align-items:center; justify-content:space-between;
      margin:10px 0 16px;
    }
    .btn{
      display:inline-flex; align-items:center; gap:.45rem;
      padding:.6rem .9rem; background:#111; color:var(--brand);
      border-radius:10px; border:1px solid var(--line);
      font-weight:600;
    }
    .btn:active{transform:translateY(1px)}
    .help{
      color:var(--muted); font-size:.9rem
    }

    /* ====== Tabla (desktop) ====== */
    .tabla-wrap{ width:100%; overflow-x:auto; }
    table{ width:100%; border-collapse:collapse; min-width:780px; }
    thead th{
      position:sticky; top:0; background:#111; color:var(--brand);
      text-align:left; padding:.7rem .65rem; border-bottom:1px solid var(--line); z-index:1;
    }
    td{ padding:.6rem .65rem; border-bottom:1px solid var(--line); vertical-align:middle; }
    td a.btn-mini{
      display:inline-flex; align-items:center; padding:.4rem .6rem;
      background:#111; color:var(--brand); border-radius:8px; border:1px solid var(--line);
      font-size:.9rem; font-weight:600;
    }
    .acciones a{ margin-right:.45rem; font-size:1.05rem }

    /* Hover zebra en desktop */
    @media (hover:hover){
      tbody tr:hover{ background:#101010 }
    }

    /* ====== Cards (mobile) ======
       En móviles escondemos thead y convertimos cada fila en card con etiquetas */
    @media (max-width: 820px){
      .tabla-wrap{overflow:visible}
      table{border-collapse:separate; border-spacing:0 12px; min-width:0}
      thead{display:none}
      tbody tr{
        display:block; background:var(--card); border:1px solid var(--line);
        border-radius:14px; padding:10px 10px 6px; box-shadow:0 1px 0 rgba(255,255,255,.04) inset;
      }
      tbody td{
        display:flex; justify-content:space-between; gap:12px;
        padding:.55rem .3rem; border-bottom:0; font-size:.98rem;
      }
      tbody td::before{
        content:attr(data-label);
        color:var(--muted); min-width:40%;
      }
      /* título ocupa todo el ancho, más grande */
      td[data-key="titulo"]{
        display:block; font-size:1.05rem; font-weight:700; padding-top:.2rem; padding-bottom:.4rem;
      }
      td[data-key="titulo"]::before{ content:"" }
      /* acciones en fila */
      td[data-key="acciones"], td[data-key="ingresar"]{
        display:flex; gap:10px; align-items:center; justify-content:flex-start;
      }
      td[data-key="ingresar"] .btn-mini{flex:0 0 auto}
      .acciones a{font-size:1.15rem}
    }

    /* Accesibilidad foco */
    a:focus{ outline:2px dashed var(--brand); outline-offset:2px }
  </style>
</head>
<body>
  <div class="contenedor">
    <h2>🏆 Panel de Eventos Deportivos</h2>

    <div class="toolbar">
      <a href="crear_evento.php" class="btn">➕ Nuevo Evento</a>
      <div class="help">Tip: tocá el título para abrir el evento.</div>
    </div>

    <?php if (!$resultado): ?>
      <div style="color:var(--danger)">⚠️ No se pudo obtener la lista de eventos.</div>
    <?php elseif ($resultado->num_rows === 0): ?>
      <div>No hay eventos cargados.</div>
    <?php else: ?>
      <div class="tabla-wrap" role="region" aria-label="Listado de eventos" tabindex="0">
        <table>
          <thead>
            <tr>
              <th scope="col">Título (link del evento)</th>
              <th scope="col">Fecha</th>
              <th scope="col">Hora</th>
              <th scope="col">Lugar</th>
              <th scope="col">Flyer</th>
              <th scope="col">Video</th>
              <th scope="col">Ingresar</th>
              <th scope="col">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($e = $resultado->fetch_assoc()): $eid = (int)$e['id']; ?>
            <tr>
              <td data-key="titulo" data-label="Título">
                <a href="ver_evento.php?id=<?= $eid ?>"><?= h($e['titulo']) ?></a>
              </td>
              <td data-label="Fecha"><?= h($e['fecha']) ?></td>
              <td data-label="Hora"><?= h($e['hora']) ?></td>
              <td data-label="Lugar"><?= h($e['lugar']) ?></td>
              <td data-label="Flyer">
                <?php if (!empty($e['flyer'])): ?>
                  <a class="btn-mini" href="<?= h($e['flyer']) ?>" target="_blank" rel="noopener">📷 Ver</a>
                <?php else: ?>❌<?php endif; ?>
              </td>
              <td data-label="Video">
                <?php if (!empty($e['video'])): ?>
                  <a class="btn-mini" href="<?= h($e['video']) ?>" target="_blank" rel="noopener">▶️ Ver</a>
                <?php else: ?>❌<?php endif; ?>
              </td>
              <td data-key="ingresar" data-label="Ingresar">
                <a class="btn-mini" href="ver_evento.php?id=<?= $eid ?>">Entrar</a>
              </td>
              <td class="acciones" data-key="acciones" data-label="Acciones">
                <a href="editar_evento.php?id=<?= $eid ?>" aria-label="Editar">✏️</a>
                <a href="eliminar_evento.php?id=<?= $eid ?>" onclick="return confirm('¿Eliminar evento?')" aria-label="Eliminar">🗑️</a>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
