<?php
// evento.php — Vista pública de un evento + formulario de compra
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; } return false;
}

$evento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($evento_id<=0){ http_response_code(400); exit('Evento inválido'); }

/* Traemos datos del evento */
$st=$conexion->prepare("SELECT id, titulo, descripcion, fecha, hora, lugar, flyer, video FROM eventos_deportivos WHERE id=? LIMIT 1");
$st->bind_param('i',$evento_id); $st->execute(); $evento=$st->get_result()->fetch_assoc(); $st->close();
if (!$evento){ http_response_code(404); exit('Evento no encontrado'); }

/* Config de pagos (habilitar_online) */
$cfg=['habilitar_online'=>1,'alias_bancario'=>null,'titular_banco'=>null,'banco_nombre'=>null,'nota'=>null];
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
$st=$conexion->prepare("SELECT alias_bancario,titular_banco,banco_nombre,habilitar_online,nota FROM eventos_pagos_config WHERE evento_id=?");
$st->bind_param('i',$evento_id); $st->execute(); $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); } $st->close();

/* Tipos de entrada con stock */
$cols_extra = has_col($conexion,'tickets_tipos','visible') ? ', visible' : '';
$sql="SELECT id, nombre, precio, stock_disponible, max_por_compra $cols_extra
      FROM tickets_tipos WHERE evento_id=? ORDER BY precio ASC, id ASC";
$st=$conexion->prepare($sql);
$st->bind_param('i',$evento_id);
$st->execute();
$tipos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* Reglas de disponibilidad */
$hay_stock = false;
$tipos_mostrables = [];
foreach($tipos as $t){
  $visible_ok = !isset($t['visible']) || (int)$t['visible']===1;
  $stock_ok   = (int)$t['stock_disponible'] > 0;
  if ($visible_ok) { $tipos_mostrables[] = $t; }
  if ($visible_ok && $stock_ok) { $hay_stock = true; }
}
$online_habilitado = (int)$cfg['habilitar_online'] === 1;

/* Si la venta online está deshabilitada, mostramos aviso claro */
$bloqueado = !$online_habilitado;

/* Mensajes flash */
$flash_err = $_SESSION['flash_error'] ?? '';
$flash_ok  = $_SESSION['ok_msg'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['ok_msg']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= h($evento['titulo']) ?> — Entradas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#0b1115; --card:#0f1720; --bd:#1f2a33; --tx:#e6eef4; --mut:#9ecbff; --btn:#0e7ad1;
      --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1; --badbg:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--tx);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:980px;margin:18px auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}
    h1{margin:0 0 6px}
    .muted{color:var(--mut)}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:var(--btn);color:#fff;text-decoration:none;cursor:pointer}
    input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:var(--tx)}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #1c2a36;padding:8px;text-align:left}
    th{color:var(--mut)}
    .qty{display:flex;gap:8px;align-items:center}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #3b4b5a;font-size:12px;margin-right:6px}
  </style>
</head>
<body>
  <div class="wrap">
    <div style="margin-bottom:10px"><a class="btn" style="background:#1b2836;border-color:#2b3c4f" href="eventos_disponibles.php">← Eventos disponibles</a></div>

    <div class="grid">
      <div>
        <div class="card">
          <h1><?= h($evento['titulo']) ?></h1>
          <div class="muted">
            <b>Fecha:</b> <?= h($evento['fecha']) ?> · <b>Hora:</b> <?= h($evento['hora']) ?> · <b>Lugar:</b> <?= h($evento['lugar']) ?>
          </div>
          <?php if(!empty($evento['descripcion'])): ?>
            <p style="margin-top:8px"><?= nl2br(h($evento['descripcion'])) ?></p>
          <?php endif; ?>
          <?php if(!empty($evento['flyer'])): ?>
            <div style="margin-top:10px"><img src="<?= h($evento['flyer']) ?>" alt="Flyer" style="max-width:100%;border-radius:10px;border:1px solid #263341"></div>
          <?php endif; ?>
        </div>

        <div class="card" style="margin-top:12px">
          <h3 style="margin:0 0 6px">Entradas</h3>

          <?php if($flash_err): ?><div class="bad"><?= h($flash_err) ?></div><?php endif; ?>
          <?php if($flash_ok):  ?><div class="ok"><?= h($flash_ok)  ?></div><?php endif; ?>

          <?php if ($bloqueado): ?>
            <div class="bad">🚫 Las ventas online para este evento están deshabilitadas por el organizador.</div>
          <?php elseif (!$tipos_mostrables): ?>
            <div class="bad">🚫 Aún no hay tipos de entrada configurados.</div>
          <?php elseif (!$hay_stock): ?>
            <div class="bad">🚫 No hay stock disponible en este momento.</div>
          <?php else: ?>
            <form method="post" action="crear_pedido.php" id="frmCompra">
              <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
              <table>
                <thead>
                  <tr><th>Tipo</th><th>Precio</th><th>Stock</th><th style="width:140px">Cantidad</th></tr>
                </thead>
                <tbody>
                <?php foreach($tipos_mostrables as $t):
                  $stock = (int)$t['stock_disponible']; $max = max(0,(int)($t['max_por_compra'] ?? 0)); ?>
                  <tr>
                    <td><?= h($t['nombre']) ?></td>
                    <td>$ <?= number_format((float)$t['precio'],2,',','.') ?></td>
                    <td><?= $stock>0 ? $stock : 'Agotado' ?></td>
                    <td>
                      <?php if ($stock>0): ?>
                        <select name="qty[<?= (int)$t['id'] ?>]">
                          <?php
                            $lim = $max>0 ? min($max,$stock) : $stock;
                            for($i=0;$i<=$lim;$i++): ?>
                              <option value="<?= $i ?>"><?= $i ?></option>
                          <?php endfor; ?>
                        </select>
                      <?php else: ?>
                        <span class="pill">0</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>

              <div class="card" style="margin-top:10px">
                <div class="pill">Online: <?= $online_habilitado?'ON':'OFF' ?></div>
                <?php if(!empty($cfg['alias_bancario'])): ?>
                  <div style="margin-top:6px"><b>Alias bancario:</b> <?= h($cfg['alias_bancario']) ?>
                  <?php if(!empty($cfg['titular_banco'])): ?> — Titular: <?= h($cfg['titular_banco']) ?><?php endif; ?>
                  <?php if(!empty($cfg['banco_nombre'])): ?> — Banco: <?= h($cfg['banco_nombre']) ?><?php endif; ?></div>
                <?php endif; ?>
                <?php if(!empty($cfg['nota'])): ?>
                  <div style="margin-top:6px"><?= nl2br(h($cfg['nota'])) ?></div>
                <?php endif; ?>
              </div>

              <div class="card" style="margin-top:10px">
                <h4 style="margin:0 0 8px">Tus datos</h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                  <div><label>Nombre y apellido</label><input type="text" name="nombre" required></div>
                  <div><label>Email</label><input type="email" name="email" required></div>
                </div>
                <div style="margin-top:8px"><label>Teléfono (opcional)</label><input type="text" name="tel"></div>
              </div>

              <div style="margin-top:10px">
                <button class="btn" type="submit">Comprar</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <div class="card">
          <h3 style="margin:0 0 6px">Información</h3>
          <div><b>Fecha:</b> <?= h($evento['fecha']) ?> — <b>Hora:</b> <?= h($evento['hora']) ?></div>
          <div style="margin-top:4px"><b>Lugar:</b> <?= h($evento['lugar']) ?></div>
          <?php if(!empty($evento['video'])): ?>
            <div style="margin-top:8px">
              <a class="btn" href="<?= h($evento['video']) ?>" target="_blank">🎥 Video</a>
            </div>
          <?php endif; ?>
        </div>

        <div class="card" style="margin-top:12px">
          <h3 style="margin:0 0 6px">Ayuda</h3>
          <p class="muted">Tras la compra vas a poder <b>descargar tu entrada en PDF</b> con un QR por <b>número de venta</b>. Ese QR se valida una sola vez en el acceso.</p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
