<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['evento_usuario_id'])) { header('Location: login_evento.php'); exit; }

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : (int)($_POST['evento_id'] ?? 0);
if ($evento_id<=0){ http_response_code(400); exit('evento_id requerido'); }

$conexion->query("CREATE TABLE IF NOT EXISTS eventos_pagos_config (
  evento_id INT PRIMARY KEY,
  alias_bancario VARCHAR(120) NULL,
  titular_banco  VARCHAR(200) NULL,
  banco_nombre   VARCHAR(200) NULL,
  habilitar_online  TINYINT(1) NOT NULL DEFAULT 1,
  habilitar_taquilla TINYINT(1) NOT NULL DEFAULT 1,
  nota TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (evento_id) REFERENCES eventos_deportivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* POST: guardar */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $alias = trim((string)($_POST['alias_bancario'] ?? ''));
  $tit   = trim((string)($_POST['titular_banco'] ?? ''));
  $bco   = trim((string)($_POST['banco_nombre'] ?? ''));
  $onl   = isset($_POST['habilitar_online']) ? 1 : 0;
  $taq   = isset($_POST['habilitar_taquilla']) ? 1 : 0;
  $nota  = trim((string)($_POST['nota'] ?? ''));

  $sql="INSERT INTO eventos_pagos_config (evento_id,alias_bancario,titular_banco,banco_nombre,habilitar_online,habilitar_taquilla,nota)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE alias_bancario=VALUES(alias_bancario), titular_banco=VALUES(titular_banco),
                                banco_nombre=VALUES(banco_nombre), habilitar_online=VALUES(habilitar_online),
                                habilitar_taquilla=VALUES(habilitar_taquilla), nota=VALUES(nota)";
  $st=$conexion->prepare($sql);
  $st->bind_param('isssiis',$evento_id,$alias,$tit,$bco,$onl,$taq,$nota);
  $st->execute(); $st->close();

  $_SESSION['flash_ok']='Formas de pago actualizadas.';
  header('Location: config_pagos_evento.php?evento_id='.$evento_id);
  exit;
}

/* GET: leer valores */
$cfg = ['alias_bancario'=>'','titular_banco'=>'','banco_nombre'=>'','habilitar_online'=>1,'habilitar_taquilla'=>1,'nota'=>''];
$st=$conexion->prepare("SELECT * FROM eventos_pagos_config WHERE evento_id=?");
$st->bind_param('i',$evento_id); $st->execute(); $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); } $st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Configurar pagos — Evento #<?= (int)$evento_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:860px;margin:20px auto;padding:16px}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:14px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:720px){.row{grid-template-columns:1fr}}
    label{font-size:14px;color:#cfe7ff}
    input,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none;cursor:pointer}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
  </style>
</head>
<body>
  <div class="wrap">
    <h2 style="margin:0 0 10px">💳 Formas de pago — Evento #<?= (int)$evento_id ?></h2>
    <?php if(!empty($_SESSION['flash_ok'])): ?><div class="ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div><?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
      <div class="card">
        <div class="row">
          <div>
            <label>Alias bancario (CBU/ALIAS)</label>
            <input type="text" name="alias_bancario" value="<?= h((string)$cfg['alias_bancario']) ?>" placeholder="ALIAS.EJEMPLO.BANCO">
          </div>
          <div>
            <label>Titular</label>
            <input type="text" name="titular_banco" value="<?= h((string)$cfg['titular_banco']) ?>">
          </div>
          <div>
            <label>Banco</label>
            <input type="text" name="banco_nombre" value="<?= h((string)$cfg['banco_nombre']) ?>">
          </div>
          <div>
            <label>Habilitar ventas online</label><br>
            <input type="checkbox" name="habilitar_online" <?= ((int)$cfg['habilitar_online']===1?'checked':'') ?>> Online
          </div>
          <div>
            <label>Habilitar ventas en taquilla</label><br>
            <input type="checkbox" name="habilitar_taquilla" <?= ((int)$cfg['habilitar_taquilla']===1?'checked':'') ?>> Taquilla
          </div>
        </div>
        <label style="display:block;margin-top:10px">Nota (se muestra en confirmación/página de pago)</label>
        <textarea name="nota" rows="3" placeholder="Ej.: Enviar comprobante de transferencia a ..."><?= h((string)$cfg['nota']) ?></textarea>

        <div style="margin-top:10px">
          <button class="btn" type="submit">Guardar</button>
          <a class="btn" style="background:#1b2836;border-color:#2b3c4f" href="ver_evento.php?id=<?= (int)$evento_id ?>">Volver</a>
        </div>
      </div>
    </form>
  </div>
</body>
</html>
