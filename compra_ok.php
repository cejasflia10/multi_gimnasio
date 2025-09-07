<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pedido_id = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;
if ($pedido_id<=0){ http_response_code(400); exit('Pedido inválido'); }

$st=$conexion->prepare("SELECT p.id, p.evento_id, p.total, p.estado, p.comprobante_path,
                               p.comprador_email, p.comprador_nombre,
                               e.titulo AS evento_titulo, p.created_at
                        FROM pedidos p
                        JOIN eventos_deportivos e ON e.id=p.evento_id
                        WHERE p.id=? LIMIT 1");
$st->bind_param('i',$pedido_id);
$st->execute();
$ped=$st->get_result()->fetch_assoc();
$st->close();
if (!$ped){ http_response_code(404); exit('Pedido no encontrado'); }

$ok_msg = $_SESSION['ok_msg'] ?? '';
$wa_link = $_SESSION['wa_link'] ?? null;
$wa_msg  = $_SESSION['wa_msg']  ?? null;
unset($_SESSION['ok_msg'], $_SESSION['wa_link'], $_SESSION['wa_msg']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Pedido #<?= (int)$ped['id'] ?> — <?= h($ped['evento_titulo']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#0b1115;--card:#0f1720;--bd:#1f2a33;--txt:#e6eef4;--mut:#bcd8ff;--ok:#0f251b;--okbd:#164b31;--bad:#2a1414;--badbd:#5e2626;--brand:#d4af37}
  body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
  .wrap{max-width:900px;margin:18px auto;padding:16px}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
  .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.56rem .9rem;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none;font-weight:600}
  .btn.gray{background:#1b2836;border-color:#2b3c4f;color:#ddd}
  .ok{background:var(--ok);border:1px solid var(--okbd);border-radius:10px;color:#b6f3d1;padding:10px}
  .bad{background:var(--bad);border:1px solid var(--badbd);border-radius:10px;color:#ffb4b4;padding:10px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>📄 Pedido #<?= (int)$ped['id'] ?> — <?= h($ped['evento_titulo']) ?></h2>
    <div style="color:var(--mut)">Estado: <b><?= h($ped['estado']) ?></b> · Total: $ <?= number_format((float)$ped['total'],2,',','.') ?> · <?= h($ped['created_at']) ?></div>

    <?php if($ok_msg): ?>
      <div class="ok" style="margin-top:10px"><?= h($ok_msg) ?></div>
    <?php endif; ?>

    <?php if($wa_link): ?>
      <div style="margin-top:8px" class="ok">
        <?= h($wa_msg ?: 'Avisá al organizador por WhatsApp:') ?>
        <div style="margin-top:6px">
          <a class="btn" target="_blank" rel="noopener" href="<?= h($wa_link) ?>">📲 Enviar por WhatsApp</a>
        </div>
      </div>
    <?php endif; ?>

    <div class="bad" style="margin-top:12px">
      Tu solicitud está <b>pendiente</b>. El organizador revisará tu comprobante.
      Te enviaremos las entradas (PDF con QR) a <b><?= h($ped['comprador_email']) ?></b> cuando se apruebe.
    </div>

    <div style="margin-top:10px">
      <?php if(!empty($ped['comprobante_path'])): ?>
        <span>Comprobante subido: </span>
        <a class="btn gray" href="<?= h($ped['comprobante_path']) ?>" target="_blank" rel="noopener">Ver</a>
      <?php else: ?>
        <span style="color:var(--mut)">No subiste comprobante.</span>
      <?php endif; ?>
    </div>

    <div style="margin-top:12px">
      <a class="btn gray" href="eventos_disponibles.php">← Volver a eventos</a>
    </div>
  </div>
</div>
</body>
</html>
