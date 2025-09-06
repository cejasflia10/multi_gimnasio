<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Guardia de sesión con return_to */
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'config_pagos_evento.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : (int)($_POST['evento_id'] ?? 0);
if ($evento_id<=0){ http_response_code(400); exit('evento_id requerido'); }

/* Traer título del evento (opcional, para cabecera) */
$ev = null;
if ($st=$conexion->prepare("SELECT titulo FROM eventos_deportivos WHERE id=? LIMIT 1")){
  $st->bind_param('i',$evento_id); $st->execute();
  $ev = $st->get_result()->fetch_assoc();
  $st->close();
}
$titulo = $ev['titulo'] ?? ('Evento #'.$evento_id);

/* Tabla de configuración (migración suave) */
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
        ON DUPLICATE KEY UPDATE
          alias_bancario=VALUES(alias_bancario),
          titular_banco=VALUES(titular_banco),
          banco_nombre=VALUES(banco_nombre),
          habilitar_online=VALUES(habilitar_online),
          habilitar_taquilla=VALUES(habilitar_taquilla),
          nota=VALUES(nota)";
  if ($st=$conexion->prepare($sql)) {
    $st->bind_param('isssiis',$evento_id,$alias,$tit,$bco,$onl,$taq,$nota);
    $st->execute(); $st->close();
    $_SESSION['flash_ok']='Formas de pago actualizadas.';
  } else {
    $_SESSION['flash_ok']='⚠️ No se pudo guardar la configuración (prepare error).';
  }

  header('Location: config_pagos_evento.php?evento_id='.$evento_id);
  exit;
}

/* GET: valores actuales */
$cfg = ['alias_bancario'=>'','titular_banco'=>'','banco_nombre'=>'','habilitar_online'=>1,'habilitar_taquilla'=>1,'nota'=>''];
if ($st=$conexion->prepare("SELECT * FROM eventos_pagos_config WHERE evento_id=? LIMIT 1")){
  $st->bind_param('i',$evento_id); $st->execute();
  $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); }
  $st->close();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Configurar pagos — <?= h($titulo) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <style>
    :root{
      --bg:#0a0a0a; --fg:#e6eef4; --mut:#c9c9c9; --brand:#d4af37;
      --card:#0f1720; --bd:#1f2a33; --line:#222;
      --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1;
    }
    html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:var(--brand);text-decoration:none}
    a:focus,button:focus,input:focus,textarea:focus{outline:2px dashed var(--brand); outline-offset:2px}

    .wrap{max-width:860px;margin:20px auto;padding:16px}
    .header{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
    .btn{
      display:inline-flex;align-items:center;gap:.45rem;
      padding:.58rem .9rem;border-radius:10px;border:1px solid var(--line);
      background:#151515;color:var(--brand);text-decoration:none;font-weight:600;cursor:pointer
    }
    .btn.gray{background:#1b1b1b;color:#ddd}
    .btn.primary{background:#0e7ad1;border-color:#27455c;color:#fff}

    .pill{display:inline-block;padding:.25rem .6rem;border-radius:999px;border:1px solid #3b3b3b;font-size:.85rem;color:#ddd}

    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:720px){.row{grid-template-columns:1fr}}

    label{font-size:.92rem;color:#cfe7ff;display:block;margin-bottom:6px}
    input,textarea{
      width:100%;padding:10px;border-radius:10px;border:1px solid var(--line);
      background:#111a24;color:var(--fg)
    }
    textarea{resize:vertical}
    .switch{display:flex;gap:8px;align-items:center}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
  </style>
</head>
<body>
  <div class="wrap">
    <?php @include __DIR__.'/menu_eventos.php'; ?>

    <div class="header">
      <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
      <span class="pill">Evento #<?= (int)$evento_id ?></span>
      <span class="pill"><?= h($titulo) ?></span>
    </div>

    <h2 style="margin:0 0 10px">💳 Formas de pago — <?= h($titulo) ?></h2>
    <?php if(!empty($_SESSION['flash_ok'])): ?>
      <div class="ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">

      <div class="card">
        <div class="row">
          <div>
            <label for="alias_bancario">Alias bancario (CBU/ALIAS)</label>
            <input id="alias_bancario" type="text" name="alias_bancario" value="<?= h((string)$cfg['alias_bancario']) ?>" placeholder="ALIAS.EJEMPLO.BANCO" autocomplete="off" autofocus>
          </div>
          <div>
            <label for="titular_banco">Titular</label>
            <input id="titular_banco" type="text" name="titular_banco" value="<?= h((string)$cfg['titular_banco']) ?>">
          </div>
          <div>
            <label for="banco_nombre">Banco</label>
            <input id="banco_nombre" type="text" name="banco_nombre" value="<?= h((string)$cfg['banco_nombre']) ?>">
          </div>
          <div class="switch">
            <input id="habilitar_online" type="checkbox" name="habilitar_online" <?= ((int)$cfg['habilitar_online']===1?'checked':'') ?>>
            <label for="habilitar_online" style="margin:0">Habilitar ventas <b>online</b></label>
          </div>
          <div class="switch">
            <input id="habilitar_taquilla" type="checkbox" name="habilitar_taquilla" <?= ((int)$cfg['habilitar_taquilla']===1?'checked':'') ?>>
            <label for="habilitar_taquilla" style="margin:0">Habilitar ventas en <b>taquilla</b></label>
          </div>
        </div>

        <label for="nota" style="margin-top:10px">Nota (se muestra en confirmación/página de pago)</label>
        <textarea id="nota" name="nota" rows="3" placeholder="Ej.: Enviar comprobante de transferencia a ..."><?= h((string)$cfg['nota']) ?></textarea>

        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap">
          <button class="btn primary" type="submit">Guardar</button>
          <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">Volver</a>
        </div>
      </div>
    </form>
  </div>
</body>
</html>
