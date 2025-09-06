<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { die('Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$evento_id = isset($_GET['evento_id'])?(int)$_GET['evento_id']: (int)($_POST['evento_id']??0);
if ($evento_id<=0) die('Falta evento_id');

/* Tabla */
$conexion->query("CREATE TABLE IF NOT EXISTS `eventos_cobros`(
  `evento_id` INT PRIMARY KEY,
  `alias_cbu` VARCHAR(120) NULL,
  `cuenta_destino` VARCHAR(200) NULL,
  `instrucciones` TEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $alias = trim((string)($_POST['alias_cbu']??''));
  $cuenta= trim((string)($_POST['cuenta_destino']??''));
  $inst  = trim((string)($_POST['instrucciones']??''));
  $st=$conexion->prepare("INSERT INTO eventos_cobros(evento_id,alias_cbu,cuenta_destino,instrucciones)
                          VALUES(?,?,?,?)
                          ON DUPLICATE KEY UPDATE alias_cbu=VALUES(alias_cbu),
                                                  cuenta_destino=VALUES(cuenta_destino),
                                                  instrucciones=VALUES(instrucciones)");
  $st->bind_param('isss',$evento_id,$alias,$cuenta,$inst);
  $st->execute(); $st->close();
  $_SESSION['ok_msg']='Cobros actualizados.';
  header('Location: config_cobros_evento.php?evento_id='.$evento_id); exit;
}

/* Cargar valores */
$row = [];
if ($st=$conexion->prepare("SELECT alias_cbu,cuenta_destino,instrucciones FROM eventos_cobros WHERE evento_id=?")){
  $st->bind_param('i',$evento_id); $st->execute(); $row=$st->get_result()->fetch_assoc() ?: []; $st->close();
}
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><title>Config Cobros</title>
<style>body{font-family:system-ui;margin:20px} input,textarea{width:100%;padding:8px;margin:6px 0} .btn{padding:9px 14px;background:#0e7ad1;border:0;color:#fff;border-radius:8px}</style>
</head><body>
<h3>💳 Configurar cobros — Evento #<?= (int)$evento_id ?></h3>
<?php if(!empty($_SESSION['ok_msg'])){ echo '<div style="background:#e6ffed;border:1px solid #8de4a1;padding:8px;border-radius:8px">'.h($_SESSION['ok_msg']).'</div>'; unset($_SESSION['ok_msg']); } ?>
<form method="post">
  <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
  <label>Alias / CBU / CVU</label>
  <input name="alias_cbu" value="<?= h($row['alias_cbu']??'') ?>" placeholder="alias.cbu@banco o CBU/CVU">
  <label>Cuenta destino (Titular/Banco)</label>
  <input name="cuenta_destino" value="<?= h($row['cuenta_destino']??'') ?>" placeholder="Juan Pérez — Banco X — Caja Ahorro ...">
  <label>Instrucciones visibles en el checkout</label>
  <textarea name="instrucciones" rows="4" placeholder="Enviá el comprobante adjunto..."><?= h($row['instrucciones']??'') ?></textarea>
  <p><button class="btn" type="submit">Guardar</button></p>
</form>
<p><a href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a></p>
</body></html>
