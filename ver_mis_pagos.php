<?php
// ver_mis_pagos.php — Listado de pagos usando MENÚ REUSABLE (idéntico a panel/ver_turnos)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0 || $gimnasio_id <= 0) { header('Location: cliente_acceso.php'); exit; }

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

/* Traer pagos (usa gimnasio_id si existe en membresias) */
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
      if ($rs instanceof mysqli_result) while($r = $rs->fetch_assoc()) $pagos[] = $r;
    } else { $err = 'No se pudieron obtener los pagos (ejecución).'; }
    $st->close();
  } else { $err = 'No se pudieron obtener los pagos (prepare).'; }
} else { $err = 'No existe la tabla de membresías.'; }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>💳 Mis Pagos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* ================== ESTILOS BASE (idénticos al panel) ================== */
    :root{
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg);
      color:var(--fg);
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .container{ max-width:1100px; margin:0 auto; padding:16px 16px 48px; }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }
    .card{ padding:18px }
    .muted{ color:var(--muted) }

    /* ================== TABLA PAGOS (responsive) ================== */
    table{ width:100%; border-collapse:collapse; }
    thead th{ text-align:left; padding:.7rem; border-bottom:1px solid var(--border); background:rgba(255,255,255,.03); }
    tbody td{ padding:.7rem; border-bottom:1px solid var(--border); }
    tr:hover td{ background:rgba(255,255,255,.03); }
    .chip{ display:inline-block; padding:.2rem .5rem; border-radius:999px; border:1px solid var(--border); background:#0f1115; font-size:.85rem; }

    @media (max-width:660px){
      thead{ display:none; }
      table tr{ display:block; border:1px solid var(--border); border-radius:12px; padding:.6rem; margin-bottom:.6rem; }
      table td{ display:block; border-bottom:none; padding:.35rem 0; }
      table td[data-lbl]::before{ content: attr(data-lbl) ": "; font-weight:700; opacity:.85; margin-right:4px; }
    }

    .alert{ padding:10px 12px; border-radius:12px; border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.12); color:#fecaca; }
  </style>
</head>
<body>

  <?php
    // ===== Menú REUSABLE (idéntico al resto de pantallas) =====
    require_once __DIR__.'/menu_cliente.php';
    render_menu_cliente('pagos'); // pestaña activa: Mis Pagos
  ?>

  <div class="container">
    <section class="glass card">
      <h2>💳 Mis Pagos</h2>

      <?php if ($err !== ''): ?>
        <div class="alert"><?= h($err) ?></div>
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
                <td data-lbl="Inicio"><?= h($fila['fecha_inicio'] ?? '—') ?></td>
                <td data-lbl="Vencimiento"><?= h($fila['fecha_vencimiento'] ?? '—') ?></td>
                <td data-lbl="Total">$<?= number_format((float)($fila['total'] ?? 0), 2, ',', '.') ?></td>
                <td data-lbl="Método"><span class="chip"><?= h($fila['metodo_pago'] ?? '—') ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php elseif ($err === ''): ?>
        <p class="muted" style="text-align:center;margin:0">No hay pagos registrados.</p>
      <?php endif; ?>
    </section>
  </div>

</body>
</html>
