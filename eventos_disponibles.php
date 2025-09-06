<?php
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$hoy = date('Y-m-d H:i:s');
$sql = "SELECT id,nombre,descripcion,fecha,hora,lugar,ciudad,banner_url
        FROM eventos_publicos
        WHERE estado='publicado'
          AND (venta_desde IS NULL OR venta_desde <= ?)
          AND (venta_hasta IS NULL OR venta_hasta >= ?)
        ORDER BY fecha ASC, hora ASC";
$st=$conexion->prepare($sql);
$st->bind_param('ss',$hoy,$hoy);
$st->execute();
$res=$st->get_result(); $eventos=$res?$res->fetch_all(MYSQLI_ASSOC):[];
$st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Eventos disponibles</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#0b1115;--card:#0f1720;--bd:#1f2a33;--txt:#e6eef4;--mut:#bcd8ff;--btn:#0e7ad1}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1080px;margin:0 auto;padding:16px}
    h1{margin:16px 0}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
    @media(max-width:980px){.grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:640px){.grid{grid-template-columns:1fr}}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;overflow:hidden}
    .card img{width:100%;aspect-ratio:16/9;object-fit:cover;background:#111a24}
    .cnt{padding:12px}
    .mut{color:var(--mut);font-size:13px}
    .btn{display:inline-block;margin-top:8px;background:var(--btn);color:#fff;padding:10px 14px;border-radius:10px;text-decoration:none;border:1px solid #27455c}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>🎟️ Eventos disponibles</h1>
    <?php if (!$eventos): ?>
      <div class="mut">No hay eventos a la venta por el momento.</div>
    <?php else: ?>
      <div class="grid">
        <?php foreach($eventos as $e): ?>
        <div class="card">
          <?php if (!empty($e['banner_url'])): ?>
            <img src="<?= h($e['banner_url']) ?>" alt="<?= h($e['nombre']) ?>">
          <?php endif; ?>
          <div class="cnt">
            <div class="mut"><?= h(date('d/m/Y', strtotime($e['fecha'])) . ( $e['hora'] ? ' · '.substr($e['hora'],0,5) : '' )) ?></div>
            <h3 style="margin:6px 0 6px"><?= h($e['nombre']) ?></h3>
            <div class="mut"><?= h($e['lugar'] . ( $e['ciudad']? ' · '.$e['ciudad'] : '')) ?></div>
            <a class="btn" href="evento.php?id=<?= (int)$e['id'] ?>">Ver y comprar</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
