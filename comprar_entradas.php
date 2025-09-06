<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id<=0){ http_response_code(400); exit('Falta evento_id'); }

/* Evento */
$st=$conexion->prepare("SELECT id,titulo,fecha,hora,lugar,flyer FROM eventos_deportivos WHERE id=? LIMIT 1");
$st->bind_param('i',$evento_id); $st->execute(); $ev=$st->get_result()->fetch_assoc(); $st->close();
if (!$ev){ http_response_code(404); exit('Evento no encontrado'); }

/* Config pagos */
$conexion->query("CREATE TABLE IF NOT EXISTS eventos_pagos_config(
  evento_id INT PRIMARY KEY,
  alias_bancario VARCHAR(120) NULL,
  titular_banco  VARCHAR(200) NULL,
  banco_nombre   VARCHAR(200) NULL,
  nota TEXT NULL,
  habilitar_online  TINYINT(1) NOT NULL DEFAULT 1,
  habilitar_taquilla TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY(evento_id) REFERENCES eventos_deportivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$cfg=['alias_bancario'=>null,'titular_banco'=>null,'banco_nombre'=>null,'nota'=>null,'habilitar_online'=>1];
$st=$conexion->prepare("SELECT * FROM eventos_pagos_config WHERE evento_id=?");
$st->bind_param('i',$evento_id); $st->execute(); $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); } $st->close();
if ((int)$cfg['habilitar_online']!==1){ http_response_code(403); exit('Ventas online deshabilitadas para este evento.'); }

/* Tipos disponibles ONLINE */
$sql="SELECT id,nombre,precio,stock_disponible,max_por_compra FROM tickets_tipos
      WHERE evento_id=? AND visible=1 AND canal IN('online','todos') ORDER BY precio ASC, id ASC";
$st=$conexion->prepare($sql); $st->bind_param('i',$evento_id); $st->execute();
$tipos=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Comprar entradas — <?= h($ev['titulo']) ?></title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:880px;margin:18px auto;padding:14px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:14px}
    .muted{color:#9ecbff}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:820px){.grid{grid-template-columns:1fr}}
    input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
    .btn{padding:12px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer}
    .tipo{display:flex;align-items:center;justify-content:space-between;border:1px solid #243140;border-radius:10px;padding:10px;margin:8px 0}
    .precio{color:#b6f3d1}
    .alert{margin:8px 0;padding:10px;border-radius:10px}
    .ok{background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 6px">🎫 Comprar entradas — <?= h($ev['titulo']) ?></h2>
    <div class="muted">📅 <?= h($ev['fecha']) ?> · ⏰ <?= h(substr((string)$ev['hora'],0,5)) ?> · 📍 <?= h($ev['lugar']) ?></div>
    <?php if(isset($_SESSION['flash_error'])): ?><div class="alert bad"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>
    <?php if(isset($_SESSION['ok_msg'])): ?><div class="alert ok"><?= h($_SESSION['ok_msg']); unset($_SESSION['ok_msg']); ?></div><?php endif; ?>

    <?php if(!$tipos): ?>
      <p class="muted" style="margin-top:10px">No hay tipos de entrada disponibles online por ahora.</p>
    <?php else: ?>
      <form action="comprar_entradas_post.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">

        <h3 style="margin:10px 0 6px">Datos del comprador</h3>
        <div class="grid">
          <div><label>Nombre y apellido</label><input name="nombre" required></div>
          <div><label>Email</label><input name="email" type="email" required></div>
          <div><label>Teléfono</label><input name="tel"></div>
          <div>
            <label>Método de pago</label>
            <select name="metodo_pago" required>
              <option value="transferencia">Transferencia/Depósito</option>
              <option value="efectivo">Efectivo (en punto físico)</option>
              <option value="tarjeta">Tarjeta</option>
            </select>
          </div>
        </div>

        <h3 style="margin:14px 0 6px">Seleccioná tus entradas</h3>
        <?php foreach($tipos as $t): ?>
          <div class="tipo">
            <div>
              <div><strong><?= h($t['nombre']) ?></strong></div>
              <small class="muted">Máx/compra: <?= (int)$t['max_por_compra'] ?> · Stock disp.: <?= (int)$t['stock_disponible'] ?></small>
            </div>
            <div>
              <span class="precio">$<?= number_format((float)$t['precio'],2,',','.') ?></span>
              <input type="number" name="qty[<?= (int)$t['id'] ?>]" min="0" value="0" style="width:90px;margin-left:8px">
            </div>
          </div>
        <?php endforeach; ?>

        <h3 style="margin:14px 0 6px">Comprobante del pago</h3>
        <p class="muted" style="margin:0 0 6px">
          Transferí a <b><?= h($cfg['alias_bancario'] ?: '—') ?></b> (<?= h($cfg['banco_nombre'] ?: 'Banco') ?>, Titular: <?= h($cfg['titular_banco'] ?: '—') ?>)
          y subí el comprobante. Tu solicitud quedará <b>pendiente</b> hasta que el organizador la apruebe. Luego recibirás tu <b>QR</b> y el <b>PDF</b> por correo.
        </p>
        <input type="file" name="comprobante" accept=".pdf,image/*">

        <div style="margin-top:12px">
          <button class="btn" type="submit">✅ Enviar solicitud y comprobante</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
