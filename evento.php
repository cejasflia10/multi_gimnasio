<?php
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0) { http_response_code(400); exit('Evento inválido'); }

$hoy = date('Y-m-d H:i:s');

$st=$conexion->prepare("SELECT * FROM eventos_publicos WHERE id=? AND estado='publicado' AND (venta_desde IS NULL OR venta_desde<=?) AND (venta_hasta IS NULL OR venta_hasta>=?) LIMIT 1");
$st->bind_param('iss',$id,$hoy,$hoy);
$st->execute(); $ev=$st->get_result()->fetch_assoc(); $st->close();
if (!$ev) { http_response_code(404); exit('Evento no disponible'); }

$st=$conexion->prepare("SELECT id,nombre,precio,stock_disponible,max_por_compra FROM tickets_tipos WHERE evento_id=? ORDER BY precio ASC");
$st->bind_param('i',$id); $st->execute(); $tipos=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= h($ev['nombre']) ?> — Entradas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#0b1115;--card:#0f1720;--bd:#1f2a33;--txt:#e6eef4;--mut:#bcd8ff;--btn:#0e7ad1}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:980px;margin:0 auto;padding:16px}
    .hdr{display:grid;grid-template-columns:280px 1fr;gap:14px}
    @media(max-width:760px){.hdr{grid-template-columns:1fr}}
    .poster{background:#111a24;border:1px solid var(--bd);border-radius:12px;overflow:hidden}
    .poster img{width:100%;display:block}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px;margin-top:14px}
    .mut{color:var(--mut)}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    th,td{border-bottom:1px solid #1c2a36;padding:8px;text-align:left}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .btn{background:var(--btn);color:#fff;padding:10px 14px;border-radius:10px;border:1px solid #27455c;text-decoration:none;cursor:pointer}
    input,select{padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:var(--txt)}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hdr">
      <div class="poster"><?php if (!empty($ev['banner_url'])): ?><img src="<?= h($ev['banner_url']) ?>" alt="Poster"><?php endif; ?></div>
      <div>
        <h1 style="margin:0 0 6px"><?= h($ev['nombre']) ?></h1>
        <div class="mut"><?= h(date('d/m/Y', strtotime($ev['fecha'])) . ($ev['hora']?' · '.substr($ev['hora'],0,5):'')) ?></div>
        <div class="mut" style="margin-top:4px"><?= h($ev['lugar'] . ($ev['direccion']?' — '.$ev['direccion']:'') . ($ev['ciudad']?' · '.$ev['ciudad']:'')) ?></div>
        <?php if (!empty($ev['descripcion'])): ?>
          <div class="card"><?= nl2br(h($ev['descripcion'])) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h3 style="margin:0 0 6px">Entradas</h3>
      <?php if (!$tipos): ?>
        <div class="mut">No hay tipos de entradas configurados aún.</div>
      <?php else: ?>
      <form method="post" action="crear_pedido.php" id="frmCompra">
        <input type="hidden" name="evento_id" value="<?= (int)$ev['id'] ?>">
        <table>
          <thead><tr><th>Tipo</th><th>Precio</th><th>Disponibles</th><th>Cantidad</th></tr></thead>
          <tbody>
            <?php foreach($tipos as $t): ?>
            <tr>
              <td><?= h($t['nombre']) ?></td>
              <td>$ <?= number_format((float)$t['precio'],2,',','.') ?></td>
              <td><?= (int)$t['stock_disponible'] ?></td>
              <td>
                <select name="qty[<?= (int)$t['id'] ?>]">
                  <?php
                    $max = min((int)$t['stock_disponible'], (int)$t['max_por_compra']);
                    for($i=0;$i<=$max;$i++){ echo '<option value="'.$i.'">'.$i.'</option>'; }
                  ?>
                </select>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="row" style="margin-top:10px">
          <input type="text" name="nombre" placeholder="Tu nombre y apellido" required>
          <input type="email" name="email" placeholder="Tu e-mail" required>
          <input type="tel" name="tel" placeholder="Teléfono (opcional)">
        </div>

        <div style="margin-top:10px">
          <button class="btn" type="submit">Comprar</button>
          <a class="btn" style="background:#1b2836;border-color:#2b3c4f" href="eventos_disponibles.php">Volver</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
