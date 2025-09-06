<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['__JUEZ_MODE__'] = 1; // BYPASS guard organizador para páginas del juez

require_once __DIR__.'/conexion.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$evento_id = (int)($_SESSION['evento_id_actual'] ?? 0);
$err = isset($_GET['err']) ? (string)$_GET['err'] : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ingreso de Juez</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;background:#0b1115;color:#e6eef4}
    .wrap{max-width:420px;margin:8vh auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:18px}
    h1{margin:0 0 10px 0;font-size:22px}
    label{display:block;margin:8px 0 4px;color:#9ecbff}
    input{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
    .btn{margin-top:12px;width:100%;padding:11px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer}
    .note{color:#8bb3d9;margin:6px 0}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
    .muted{color:#8bb3d9;font-size:13px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Ingreso de Juez</h1>

      <?php if ($evento_id>0): ?>
        <div class="note">Evento activo: <b>#<?= (int)$evento_id ?></b></div>
      <?php else: ?>
        <div class="note">No hay evento activo en la sesión. Se usará el juez más reciente con ese DNI.</div>
      <?php endif; ?>

      <?php if ($err): ?><div class="bad">❌ <?= htmlspecialchars($err, ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

      <form method="post" action="login_juez_procesar.php" autocomplete="off">
        <label>DNI del juez</label>
        <input name="dni" inputmode="numeric" pattern="[0-9]{6,12}" maxlength="12" required placeholder="Sólo números (ej: 12345678)">
        <button class="btn" type="submit">Ingresar</button>
      </form>

      <p class="muted">Ingresá tu DNI para entrar a tu panel y ver tu tarjeta.</p>
    </div>
  </div>
</body>
</html>
