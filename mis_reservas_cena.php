<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0) { header('Location: login.php'); exit; }

/* Acción: marcar pagado (simples) */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pagar_id'])) {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(400); exit('❌ CSRF inválido'); }

  $rid = (int)$_POST['pagar_id'];
  // Traer reserva
  $stmt = $conexion->prepare("SELECT id, total, monto_pagado, estado_pago FROM cenas_reservas WHERE id=? AND cliente_id=? AND gimnasio_id=? LIMIT 1");
  $stmt->bind_param('iii', $rid, $cliente_id, $gimnasio_id);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($r && $r['estado_pago']!=='pagado') {
    $nuevo_monto = (float)$r['total'];
    $stmt = $conexion->prepare("UPDATE cenas_reservas SET estado_pago='pagado', monto_pagado=? , metodo_pago='efectivo' WHERE id=?");
    $stmt->bind_param('di', $nuevo_monto, $rid);
    $stmt->execute();
    $stmt->close();
  }
  header('Location: mis_reservas_cena.php?pagado=1'); exit;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

/* Listar reservas del cliente */
$stmt = $conexion->prepare("
  SELECT r.id, r.cantidad, r.total, r.estado_reserva, r.estado_pago, r.monto_pagado, r.creado_en,
         e.titulo, e.fecha, e.hora, e.lugar, e.precio_cubierto, e.sena_minima
  FROM cenas_reservas r
  JOIN cenas_eventos e ON e.id = r.evento_id
  WHERE r.gimnasio_id=? AND r.cliente_id=?
  ORDER BY r.creado_en DESC
");
$stmt->bind_param('ii', $gimnasio_id, $cliente_id);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mis reservas de cena</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0f1320;color:#fff;margin:0}
    .wrap{max-width:980px;margin:24px auto;padding:16px}
    .card{background:#141a2a;border:1px solid #222a40;border-radius:14px;padding:20px;box-shadow:0 6px 26px rgba(0,0,0,.25);margin-bottom:16px}
    .h1{font-size:26px;margin:0 0 16px}
    .tag{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;border:1px solid rgba(255,255,255,.15)}
    .ok{color:#34d399;border-color:#34d399}
    .warn{color:#fbbf24;border-color:#fbbf24}
    .danger{color:#ff6b6b;border-color:#ff6b6b}
    .btn{padding:10px 12px;border-radius:10px;border:0;background:#3b82f6;color:#fff;cursor:pointer;font-weight:700}
    .muted{color:#c9d1e1}
    a{color:#93c5fd}
  </style>
</head>
<body>
  <div class="wrap">
    <h1 class="h1">🍽️ Mis reservas — Cena de Fin de Año</h1>
    <p><a href="cena_fin_anio.php">← Volver</a></p>
    <?php if (empty($rows)): ?>
      <div class="card"><p class="muted">Aún no tenés reservas.</p></div>
    <?php else: foreach($rows as $r): 
      $fecha = date('d/m/Y', strtotime($r['fecha']));
      $hora  = substr($r['hora'],0,5);
      $tagClass = $r['estado_pago']==='pagado' ? 'ok' : ($r['estado_pago']==='sena' ? 'warn' : 'danger');
    ?>
      <div class="card">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
          <div>
            <strong><?=htmlspecialchars($r['titulo'])?></strong><br>
            <span class="muted">📅 <?=$fecha?> — ⏰ <?=$hora?> — 📍 <?=htmlspecialchars($r['lugar'])?></span>
          </div>
          <div><span class="tag <?=$tagClass?>">Pago: <?=strtoupper($r['estado_pago'])?></span></div>
        </div>
        <p style="margin:10px 0 0">
          Cantidad: <strong><?=$r['cantidad']?></strong> — Total: <strong>$<?=number_format($r['total'],2,',','.')?></strong><br>
          Pagado: <strong>$<?=number_format($r['monto_pagado'],2,',','.')?></strong>
        </p>
        <?php if($r['estado_pago']!=='pagado'): ?>
          <form method="post" style="margin-top:10px">
            <input type="hidden" name="csrf" value="<?=$csrf?>">
            <input type="hidden" name="pagar_id" value="<?=$r['id']?>">
            <button class="btn" type="submit">Completar pago (marcar pagado)</button>
          </form>
        <?php endif; ?>
        <p class="muted" style="margin-top:10px">Reserva creada el <?=date('d/m/Y H:i', strtotime($r['creado_en']))?></p>
      </div>
    <?php endforeach; endif; ?>
  </div>
</body>
</html>
