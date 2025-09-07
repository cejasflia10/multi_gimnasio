<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($n){ return number_format((float)$n, 2, ',', '.'); }
function app_url(string $p=''): string {
  if (defined('APP_BASE_URL') && APP_BASE_URL) return rtrim(APP_BASE_URL,'/').'/'.ltrim($p,'/');
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
  return $scheme.'://'.$host.'/'.ltrim($p,'/');
}

$pedido_id = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;
if ($pedido_id <= 0) { http_response_code(400); exit('Falta pedido_id'); }

/* Pedido + evento */
$sql = "SELECT p.id, p.evento_id, p.comprador_nombre, p.comprador_email, p.comprador_tel,
               p.metodo_pago, p.total, p.comprobante_path, p.estado, p.created_at,
               e.titulo AS evento_titulo
        FROM pedidos p
        JOIN eventos_deportivos e ON e.id = p.evento_id
        WHERE p.id = ? LIMIT 1";
$st = $conexion->prepare($sql);
$st->bind_param('i',$pedido_id);
$st->execute();
$pedido = $st->get_result()->fetch_assoc();
$st->close();

if (!$pedido) { http_response_code(404); exit('Pedido no encontrado'); }

/* Resumen por tipo */
$tipos = [];
$q = $conexion->prepare("SELECT t.tipo_id, tt.nombre, tt.precio, COUNT(*) qty
                         FROM tickets t
                         JOIN tickets_tipos tt ON tt.id = t.tipo_id
                         WHERE t.pedido_id = ?
                         GROUP BY t.tipo_id, tt.nombre, tt.precio
                         ORDER BY tt.precio ASC");
$q->bind_param('i',$pedido_id);
$q->execute();
$r = $q->get_result();
while($row = $r->fetch_assoc()){ $tipos[] = $row; }
$q->close();

/* WhatsApp del organizador */
$conexion->query("CREATE TABLE IF NOT EXISTS eventos_contactos_config (
  evento_id INT PRIMARY KEY,
  whatsapp_admin VARCHAR(30) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (evento_id) REFERENCES eventos_deportivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$wa_admin = '';
if ($st = $conexion->prepare("SELECT whatsapp_admin FROM eventos_contactos_config WHERE evento_id=? LIMIT 1")){
  $st->bind_param('i',$pedido['evento_id']); $st->execute();
  $res = $st->get_result()->fetch_assoc(); $st->close();
  if ($res && !empty($res['whatsapp_admin'])) $wa_admin = preg_replace('/\D+/', '', $res['whatsapp_admin']);
}

/* Construir mensaje COMPLETO y luego codificar */
$lineas = [];
$lineas[] = "📲 *Nuevo pedido #{$pedido['id']}*";
$lineas[] = "*Evento:* {$pedido['evento_titulo']}";
$cliente = trim($pedido['comprador_nombre']);
if (!empty($pedido['comprador_email'])) $cliente .= " | ".$pedido['comprador_email'];
if (!empty($pedido['comprador_tel']))   $cliente .= " | ".$pedido['comprador_tel'];
$lineas[] = "*Cliente:* {$cliente}";
$lineas[] = "*Pago:* {$pedido['metodo_pago']}";
$lineas[] = "*Total:* $ ".money($pedido['total']);

if ($tipos){
  $lineas[] = "*Detalle:*";
  foreach($tipos as $t){
    $lineas[] = "- ".($t['nombre'] ?? ('Tipo '.$t['tipo_id']))." x {$t['qty']} ($ ".money($t['precio'])." c/u)";
  }
}

if (!empty($pedido['comprobante_path'])) {
  $lineas[] = "*Comprobante:* ".app_url($pedido['comprobante_path']);
}
$lineas[] = "▶️ Revisar: ".app_url('ver_ventas_evento.php?evento_id='.$pedido['evento_id']);

$mensaje_plano = implode("\n", $lineas);
$mensaje_cod   = rawurlencode($mensaje_plano);
$waLink        = $wa_admin ? ('https://wa.me/'.$wa_admin.'?text='.$mensaje_cod) : '';

/* URL comprobante absoluta para mostrar */
$comp_url = !empty($pedido['comprobante_path']) ? app_url($pedido['comprobante_path']) : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Pedido #<?= (int)$pedido['id'] ?> — Confirmación</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <style>
    :root{ --bg:#0a0a0a; --fg:#e6eef4; --brand:#d4af37; --card:#0f1720; --bd:#1f2a33; }
    *{box-sizing:border-box}
    html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:var(--brand);text-decoration:none}
    .wrap{max-width:780px;margin:22px auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.6rem .9rem;border-radius:10px;border:1px solid #333;background:#151515;color:var(--brand);font-weight:700}
    .btn.wa{background:#25D366;color:#062;border-color:#128C7E}
    .mut{color:#9ecbff}
    .row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    textarea{width:100%;min-height:120px;background:#0c131a;color:#dfe; border:1px solid #27455c;border-radius:8px;padding:8px}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>✅ Pedido recibido</h2>
    <p>Te avisamos por email cuando se acredite el pago.</p>

    <h3 style="margin:.4rem 0 0">Resumen</h3>
    <p>#<?= (int)$pedido['id'] ?> — <?= h($pedido['evento_titulo']) ?></p>
    <ul>
      <li><b>Cliente:</b> <?= h($pedido['comprador_nombre']) ?> — <?= h($pedido['comprador_email']) ?> <?php if($pedido['comprador_tel']): ?> — <?= h($pedido['comprador_tel']) ?><?php endif; ?></li>
      <li><b>Método:</b> <?= h($pedido['metodo_pago']) ?></li>
      <li><b>Total:</b> $ <?= money($pedido['total']) ?></li>
      <?php if ($comp_url): ?><li><b>Comprobante:</b> <a href="<?= h($comp_url) ?>" target="_blank" rel="noopener">ver archivo</a></li><?php endif; ?>
    </ul>

    <div class="row">
      <a class="btn" href="evento.php?id=<?= (int)$pedido['evento_id'] ?>">⬅ Volver al evento</a>
      <?php if ($waLink): ?>
        <a class="btn wa" href="<?= h($waLink) ?>" target="_blank" rel="noopener">🟢 Enviar por WhatsApp</a>
      <?php else: ?>
        <span class="mut">⚠️ Configurá el número del organizador para habilitar WhatsApp.</span>
      <?php endif; ?>
    </div>

    <?php if ($waLink): ?>
      <p class="mut" style="margin-top:10px"><small>Nota: WhatsApp no permite adjuntar archivos por link. Enviamos el texto con el <b>link al comprobante</b>.</small></p>
      <details style="margin-top:8px">
        <summary>Ver mensaje que se enviará</summary>
        <textarea readonly><?= h($mensaje_plano) ?></textarea>
      </details>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
