<?php
// ver_mis_pagos.php — Listado de pagos del cliente con estilos/menú unificados
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0) { header('Location: login.php'); exit; }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function table_exists(mysqli $cx, string $name): bool {
  $n = $cx->real_escape_string($name);
  $rs = $cx->query("SHOW TABLES LIKE '{$n}'");
  return ($rs instanceof mysqli_result) && $rs->num_rows > 0;
}
function column_exists(mysqli $cx, string $table, string $col): bool {
  if (!table_exists($cx, $table)) return false;
  $t = $cx->real_escape_string($table);
  $c = $cx->real_escape_string($col);
  $rs = $cx->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return ($rs instanceof mysqli_result) && $rs->num_rows > 0;
}

/* Query segura: usa gimnasio_id si existe en membresias */
$pagos = [];
$err   = '';
if (table_exists($conexion, 'membresias')) {
  $hasGym = column_exists($conexion, 'membresias', 'gimnasio_id');
  $sql = "SELECT fecha_inicio, fecha_vencimiento, total, metodo_pago
          FROM membresias
          WHERE cliente_id = ? " . ($hasGym ? "AND gimnasio_id = ? " : "") . "
          ORDER BY fecha_inicio DESC";
  if ($st = $conexion->prepare($sql)) {
    if ($hasGym) { $st->bind_param('ii', $cliente_id, $gimnasio_id); }
    else         { $st->bind_param('i',  $cliente_id); }
    if ($st->execute()) {
      $rs = $st->get_result();
      if ($rs instanceof mysqli_result) {
        while($r = $rs->fetch_assoc()) $pagos[] = $r;
      }
    } else {
      $err = 'No se pudieron obtener los pagos (ejecución).';
    }
    $st->close();
  } else {
    $err = 'No se pudieron obtener los pagos (prepare).';
  }
} else {
  $err = 'No existe la tabla de membresías.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>💳 Mis Pagos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Hoja de estilo unificada -->
  <link rel="stylesheet" href="/multi_gimnasio/estilo_unificado.css?v=20251006">
  <style>
    /* ===== Overrides para que SIEMPRE se vean las letras del menú ===== */
    .mc-top, .mc-top *, .mc-drawer *, .mc-tabs *, .mc-item, .mc-item * {
      -webkit-text-fill-color: currentColor !important;
      background: none !important;
      -webkit-background-clip: initial !important;
      background-clip: initial !important;
    }
    .mc-top{ background:#111 !important; border-bottom:1px solid #444 !important; }
    .mc-bar .mc-title{ color: gold !important; font-weight: 800 !important; }
    .mc-bar .mc-link{ color: gold !important; }
    .mc-bar .mc-btn{ background:#ffd600 !important; color:#000 !important; }
    .mc-bar .mc-link:hover{ background:#222 !important; }

    .mc-item{ background:#222 !important; border:1px solid #444 !important; color:gold !important; }
    .mc-item:hover{ background:#333 !important; }

    .mc-tabs{ background:#111 !important; border-top:1px solid #444 !important; }
    .mc-tabs a{ color: gold !important; }
    .mc-tabs a.active{ background:#333 !important; border-color:#444 !important; color:#fff !important; }

    /* Estilos locales mínimos */
    .contenedor{ max-width: 900px; margin: 20px auto; }
    table{ width:100%; border-collapse: collapse; margin-top: 12px; }
    th, td{ padding: 12px; border-bottom: 1px solid #444; text-align:left; color:#fff; }
    th{ background:#333; }
    tr:hover{ background:#2a2a2a; }
    .note{ background:#111; color: gold; padding:10px; border:1px solid gold; border-radius:8px; margin:10px 0; text-align:center; }
  </style>
</head>
<body>

<?php include __DIR__ . '/menu_cliente.php'; ?>

<div class="contenedor">
  <h2>💳 Mis Pagos</h2>

  <?php if ($err !== ''): ?>
    <div class="note"><?= h($err) ?></div>
  <?php endif; ?>

  <?php if (!empty($pagos)): ?>
    <table>
      <thead>
        <tr>
          <th>Fecha Inicio</th>
          <th>Fecha Vencimiento</th>
          <th>Total</th>
          <th>Método de Pago</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pagos as $fila): ?>
          <tr>
            <td><?= h($fila['fecha_inicio'] ?? '—') ?></td>
            <td><?= h($fila['fecha_vencimiento'] ?? '—') ?></td>
            <td>$<?= number_format((float)($fila['total'] ?? 0), 2, ',', '.') ?></td>
            <td><?= h($fila['metodo_pago'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php elseif ($err === ''): ?>
    <p style="text-align:center;">No hay pagos registrados.</p>
  <?php endif; ?>
</div>
</body>
</html>
