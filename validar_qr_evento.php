<?php
// validar_qr_evento.php — valida token por 1ra vez y marca como USADO
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$token = preg_replace('/[^a-f0-9]/','', strtolower((string)($_GET['token'] ?? '')));
if ($token==='' || strlen($token)!==40) { http_response_code(400); exit('Token inválido'); }

// Leemos el pedido por token
$st=$conexion->prepare("SELECT p.*, e.nombre AS evento, e.fecha, e.hora, e.lugar
                        FROM pedidos p
                        JOIN eventos_publicos e ON e.id=p.evento_id
                        WHERE p.qr_token=? LIMIT 1");
$st->bind_param('s',$token);
$st->execute(); $ped=$st->get_result()->fetch_assoc(); $st->close();

if (!$ped){ http_response_code(404); exit('No existe el token'); }

$yaUsado = ($ped['qr_status']==='usado');
if (!$yaUsado) {
  // Marcamos como usado AHORA (primera validación)
  $now = date('Y-m-d H:i:s');
  $up=$conexion->prepare("UPDATE pedidos SET qr_status='usado', qr_used_at=? WHERE id=? AND qr_status='activo'");
  $up->bind_param('si',$now,$ped['id']);
  $up->execute();
  $primeraVez = $up->affected_rows>0;
  $up->close();

  // (opcional) marcar todos los tickets del pedido como usados también:
  // $conexion->query("UPDATE tickets SET status='usado', used_at=NOW() WHERE pedido_id=".(int)$ped['id']);

  $yaUsado = !$primeraVez ? true : false; // si no afectó filas, ya estaba usado
}

// Vista minimal para staff
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Validación de entrada</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:720px;margin:0 auto;padding:16px}
    .ok{background:#0f251b;border:1px solid #164b31;padding:12px;border-radius:12px;color:#b6f3d1}
    .bad{background:#2a1414;border:1px solid #5e2626;padding:12px;border-radius:12px;color:#ffb4b4}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:14px;margin-top:12px}
    .meta{color:#9ecbff}
  </style>
</head>
<body>
  <div class="wrap">
    <h2>🔎 Validación de entrada</h2>

    <?php if ($yaUsado): ?>
      <div class="bad">🚫 Entrada YA VALIDADA<?php if(!empty($ped['qr_used_at'])): ?> (<?= h($ped['qr_used_at']) ?>)<?php endif; ?>.</div>
    <?php else: ?>
      <div class="ok">✅ Entrada VALIDADA. Permitir ingreso.</div>
    <?php endif; ?>

    <div class="card">
      <div><b>Evento:</b> <?= h($ped['evento']) ?></div>
      <div class="meta"><?= h(date('d/m/Y', strtotime($ped['fecha'])) . ($ped['hora']?' · '.substr($ped['hora'],0,5):'')) ?> — <?= h($ped['lugar']) ?></div>
      <div style="margin-top:6px"><b>Nro. de venta:</b> <?= sprintf('PED-%06d',(int)$ped['id']) ?></div>
      <div><b>Comprador:</b> <?= h($ped['comprador_nombre']) ?> — <?= h($ped['comprador_email']) ?></div>
    </div>
  </div>
</body>
</html>
