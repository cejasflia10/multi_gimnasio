<?php
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function table_exists(mysqli $db, string $t): bool {
  $t=$db->real_escape_string($t);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function excerpt($txt, $len=140){
  $s = trim((string)$txt);
  if ($s==='' ) return '';
  if (mb_strlen($s,'UTF-8') <= $len) return $s;
  return rtrim(mb_substr($s,0,$len,'UTF-8')).'…';
}

/* =========================
   1) Intento: eventos_deportivos
   ========================= */
$eventos = [];
$hoy = date('Y-m-d H:i:s');

if (table_exists($conexion,'eventos_deportivos')) {
  $where = ["1=1"]; // no bloqueamos si no hay flags
  $types = '';
  $vals  = [];

  // si existe columna 'estado', mostramos publicados/activos
  if (has_col($conexion,'eventos_deportivos','estado')) {
    $where[] = "estado IN ('publicado','activo')";
  }
  if (has_col($conexion,'eventos_deportivos','visible')) {
    $where[] = "visible=1";
  }
  if (has_col($conexion,'eventos_deportivos','venta_desde')) {
    $where[] = "(venta_desde IS NULL OR venta_desde <= ?)";
    $types  .= 's'; $vals[] = $hoy;
  }
  if (has_col($conexion,'eventos_deportivos','venta_hasta')) {
    $where[] = "(venta_hasta IS NULL OR venta_hasta >= ?)";
    $types  .= 's'; $vals[] = $hoy;
  }

  $ord = "ORDER BY fecha ASC";
  if (has_col($conexion,'eventos_deportivos','hora')) { $ord = "ORDER BY fecha ASC, hora ASC"; }

  // mapeamos columnas a una forma común (nombre/descripcion/fecha/hora/lugar/ciudad/banner_url,id)
  $sql = "SELECT id, titulo AS nombre, descripcion,
                 fecha, hora, lugar,
                 NULL AS ciudad, flyer AS banner_url
          FROM eventos_deportivos
          WHERE ".implode(' AND ', $where)." {$ord}";
  $st = $conexion->prepare($sql);
  if ($types!==''){
    $bind = [$types]; foreach ($vals as $k=>&$v){ $bind[]=&$v; }
    call_user_func_array([$st,'bind_param'],$bind);
  }
  $st->execute();
  $res = $st->get_result();
  if ($res) { $eventos = $res->fetch_all(MYSQLI_ASSOC); }
  $st->close();
}

/* =========================
   2) Fallback: eventos_publicos (si estaba vacío)
   ========================= */
if (!$eventos && table_exists($conexion,'eventos_publicos')) {
  $where = ["estado='publicado'"];
  $types = '';
  $vals  = [];
  if (has_col($conexion,'eventos_publicos','venta_desde')) {
    $where[]="(venta_desde IS NULL OR venta_desde <= ?)";
    $types.='s'; $vals[]=$hoy;
  }
  if (has_col($conexion,'eventos_publicos','venta_hasta')) {
    $where[]="(venta_hasta IS NULL OR venta_hasta >= ?)";
    $types.='s'; $vals[]=$hoy;
  }
  $sql = "SELECT id, nombre, descripcion, fecha, hora, lugar, ciudad, banner_url
          FROM eventos_publicos
          WHERE ".implode(' AND ',$where)."
          ORDER BY fecha ASC, hora ASC";
  $st=$conexion->prepare($sql);
  if ($types!==''){
    $bind = [$types]; foreach ($vals as $k=>&$v){ $bind[]=&$v; }
    call_user_func_array([$st,'bind_param'],$bind);
  }
  $st->execute();
  $res=$st->get_result(); 
  $eventos = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $st->close();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Eventos disponibles</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <style>
    :root{
      --bg:#0b1115; --card:#0f1720; --bd:#1f2a33; --txt:#e6eef4; --mut:#bcd8ff;
      --btn:#0e7ad1; --btnbd:#27455c; --brand:#d4af37;
    }
    *{box-sizing:border-box}
    html,body{margin:0;background:var(--bg);color:var(--txt);
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:inherit;text-decoration:none}
    a:focus,button:focus{outline:2px dashed var(--brand); outline-offset:2px}

    .wrap{max-width:1100px;margin:0 auto;padding:16px}
    h1{margin:10px 0 14px}

    .grid{
      display:grid;
      grid-template-columns:repeat(3, minmax(0,1fr));
      gap:14px;
    }
    @media(max-width:980px){ .grid{grid-template-columns:repeat(2, minmax(0,1fr));} }
    @media(max-width:620px){ .grid{grid-template-columns:1fr;} }

    .card{
      position:relative; background:var(--card); border:1px solid var(--bd);
      border-radius:14px; overflow:hidden; display:flex; flex-direction:column;
      transition:transform .12s ease, box-shadow .12s ease;
    }
    .card:hover{ transform:translateY(-2px); box-shadow:0 8px 18px rgba(0,0,0,.35); }
    .thumb{ width:100%; aspect-ratio:16/9; object-fit:cover; background:#0a0f15; display:block }
    .cnt{ padding:12px; display:flex; flex-direction:column; gap:6px; flex:1 }
    .mut{ color:var(--mut); font-size:.92rem }
    .title{ margin:2px 0 0; font-size:1.05rem; font-weight:700; line-height:1.25 }
    .desc{ color:#d9e6f2; font-size:.95rem }
    .loc{ color:#b7c7d8; font-size:.9rem }
    .cta{
      align-self:flex-start; margin-top:auto;
      background:var(--btn); color:#fff; padding:10px 14px; border-radius:10px;
      border:1px solid var(--btnbd);
    }
    .noimg{ display:grid; place-items:center; color:#9db7d1; font-weight:600; letter-spacing:.3px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>🎟️ Eventos disponibles</h1>

    <?php if (!$eventos): ?>
      <p class="mut">No hay eventos a la venta por el momento.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach($eventos as $e):
          $nombre = $e['nombre'] ?? '';
          $desc   = $e['descripcion'] ?? '';
          $fecha  = $e['fecha'] ?? '';
          $hora   = $e['hora'] ?? '';
          $lugar  = trim(($e['lugar'] ?? '').(($e['ciudad'] ?? '') ? ' · '.$e['ciudad'] : ''));
          $banner = $e['banner_url'] ?? '';
          $fechaTxt = $fecha ? date('d/m/Y', strtotime((string)$fecha)).($hora ? ' · '.substr((string)$hora,0,5) : '') : '';
          $dest = 'evento.php?id='.(int)$e['id'];
        ?>
        <a class="card" href="<?= h($dest) ?>" aria-label="Ver <?= h($nombre) ?>">
          <?php if ($banner): ?>
            <img class="thumb" loading="lazy" src="<?= h($banner) ?>" alt="<?= h($nombre) ?>">
          <?php else: ?>
            <div class="thumb noimg">SIN IMAGEN</div>
          <?php endif; ?>

          <div class="cnt">
            <?php if ($fechaTxt): ?><div class="mut"><?= h($fechaTxt) ?></div><?php endif; ?>
            <div class="title"><?= h($nombre) ?></div>
            <?php if ($desc): ?><div class="desc"><?= h(excerpt($desc, 140)) ?></div><?php endif; ?>
            <?php if ($lugar): ?><div class="loc">📍 <?= h($lugar) ?></div><?php endif; ?>
            <span class="cta">Ver y comprar</span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
