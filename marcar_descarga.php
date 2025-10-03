<?php
// Marca una rutina como "descargada" para el cliente actual.
// Se llama con sendBeacon/FormData desde el click del botón.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id<=0 || $gimnasio_id<=0) { http_response_code(401); echo json_encode(['ok'=>0,'err'=>'sesion']); exit; }

$rutina_id = (int)($_POST['rutina_id'] ?? 0);
if ($rutina_id<=0) { http_response_code(400); echo json_encode(['ok'=>0,'err'=>'param']); exit; }

// Verificar pertenencia
$ok=false;
if ($st=$conexion->prepare("SELECT 1 FROM rutinas_clientes WHERE id=? AND cliente_id=? AND gimnasio_id=? LIMIT 1")) {
  $st->bind_param('iii',$rutina_id,$cliente_id,$gimnasio_id);
  $st->execute(); $st->store_result(); $ok=$st->num_rows>0; $st->close();
}
if (!$ok) { http_response_code(403); echo json_encode(['ok'=>0,'err'=>'no-own']); exit; }

// Upsert en rutinas_vistas con descargado_en = ahora
if ($st2=$conexion->prepare("INSERT INTO rutinas_vistas (cliente_id, rutina_id, visto_en, descargado_en)
  VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
  ON DUPLICATE KEY UPDATE descargado_en=CURRENT_TIMESTAMP")) {
  $st2->bind_param('ii',$cliente_id,$rutina_id);
  $st2->execute(); $st2->close();
  // 204 para beacon (sin cuerpo)
  http_response_code(204); exit;
}

http_response_code(500); echo json_encode(['ok'=>0,'err'=>'db']);
