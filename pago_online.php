<?php
// pago_online.php — Carga de comprobante con planes/adicionales (estilo/menú unificado)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id === 0 || $gimnasio_id === 0) { die('Acceso denegado.'); }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ok_ext(string $name): bool {
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return in_array($ext, ['jpg','jpeg','png','pdf'], true);
}
function ok_mime(string $tmp): bool {
  $f = @finfo_open(FILEINFO_MIME_TYPE);
  if (!$f) return true; // si no está disponible, no bloqueamos
  $m = (string)@finfo_file($f, $tmp);
  @finfo_close($f);
  if ($m === '') return true;
  return in_array($m, [
    'image/jpeg','image/png','application/pdf'
  ], true);
}

/* CSRF simple */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$mensaje = '';

/* === Cargar catálogo de planes y adicionales del gimnasio === */
$planes = [];
if ($st = $conexion->prepare("SELECT id, nombre, precio FROM planes WHERE gimnasio_id = ? ORDER BY nombre")){
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $res = $st->get_result();
  while($r = $res->fetch_assoc()) $planes[] = $r;
  $st->close();
}
$adicionales = [];
if ($st = $conexion->prepare("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = ? ORDER BY nombre")){
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $res = $st->get_result();
  while($r = $res->fetch_assoc()) $adicionales[] = $r;
  $st->close();
}

/* Alias de transferencia */
$alias = '';
if ($st = $conexion->prepare("SELECT alias FROM gimnasios WHERE id = ? LIMIT 1")){
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $st->bind_result($alias);
  $st->fetch();
  $st->close();
}

/* === POST: subir comprobante === */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    $mensaje = '❌ Sesión expirada. Recargá la página.';
  } else {
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $monto   = (float)($_POST['monto']   ?? 0);
    $fecha   = date('Y-m-d H:i:s');

    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
      $cod = (int)($_FILES['comprobante']['error'] ?? -1);
      $mensaje = "❌ Error al recibir el archivo (código {$cod}).";
    } else {
      $ruta_tmp = $_FILES['comprobante']['tmp_name'];
      $archivo  = (string)$_FILES['comprobante']['name'];
      $tam      = (int)$_FILES['comprobante']['size'];

      if ($tam <= 0 || $tam > 10 * 1024 * 1024) {
        $mensaje = "❌ El archivo supera el tamaño permitido (máx. 10 MB).";
      } elseif (!ok_ext($archivo)) {
        $mensaje = "❌ Formato no permitido. Solo JPG, PNG o PDF.";
      } elseif (!ok_mime($ruta_tmp)) {
        $mensaje = "❌ El tipo de archivo no es válido.";
      }

      // Carpeta de destino: ./comprobantes (pública)
      $relDir = 'comprobantes';
      $absDir = __DIR__ . DIRECTORY_SEPARATOR . $relDir;
      if (empty($mensaje) && !is_dir($absDir)) {
        if (!@mkdir($absDir, 0775, true)) $mensaje = "❌ No se pudo crear la carpeta de destino.";
      }
      if (empty($mensaje) && !is_writable($absDir)) {
        $mensaje = "❌ La carpeta no es escribible: {$absDir}";
      }

      if (empty($mensaje)) {
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($archivo));
        $nombre_final = 'g'.$gimnasio_id.'_c'.$cliente_id.'_'.uniqid('', true).'_'.$base;

        $ruta_publica = $relDir . '/' . $nombre_final;                 // p.ej. comprobantes/archivo.pdf
        $ruta_fisica  = $absDir . DIRECTORY_SEPARATOR . $nombre_final; // ruta real en disco

        // Adicionales seleccionados -> JSON
        $adicionales_sel = (isset($_POST['adicionales']) && is_array($_POST['adicionales']))
          ? array_map('intval', $_POST['adicionales'])
          : [];
        $adicionales_json = json_encode($adicionales_sel, JSON_UNESCAPED_UNICODE);

        if (!is_uploaded_file($ruta_tmp) || !@move_uploaded_file($ruta_tmp, $ruta_fisica)) {
          error_log('Fallo move_uploaded_file hacia: ' . $ruta_fisica);
          $mensaje = "❌ No se pudo guardar el comprobante en el servidor.";
        } else {
          $sql = "INSERT INTO pagos_pendientes 
                    (cliente_id, gimnasio_id, plan_id, monto, archivo_comprobante, fecha_envio, estado, adicionales)
                  VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)";
          if ($st = $conexion->prepare($sql)) {
            // tipos: i i i d s s s
            $st->bind_param(
              'iiidsss',
              $cliente_id,
              $gimnasio_id,
              $plan_id,
              $monto,
              $ruta_publica,
              $fecha,
              $adicionales_json
            );
            if ($st->execute()) {
              $mensaje = "✅ Comprobante enviado correctamente. Será validado en breve.";
            } else {
              @unlink($ruta_fisica);
              $mensaje = "❌ Error al guardar el registro en la base de datos.";
            }
            $st->close();
          } else {
            @unlink($ruta_fisica);
            $mensaje = "❌ Error interno (prepare).";
          }
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>💳 Pago Online</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Estilo unificado -->
  <link rel="stylesheet" href="/multi_gimnasio/estilo_unificado.css?v=20251006">
  <style>
    /* Overrides para visibilidad del menú (texto siempre visible) */
    .mc-top, .mc-top *, .mc-drawer *, .mc-tabs *, .mc-item, .mc-item *{
      -webkit-text-fill-color: currentColor !important;
      background: none !important;
      -webkit-background-clip: initial !important;
      background-clip: initial !important;
    }
    .mc-top{ background:#111 !important; border-bottom:1px solid #444 !important; }
    .mc-bar .mc-title{ color: gold !important; font-weight:800 !important; }
    .mc-bar .mc-link{ color: gold !important; }
    .mc-bar .mc-btn{ background:#ffd600 !important; color:#000 !important; }
    .mc-item{ background:#222 !important; border:1px solid #444 !important; color:gold !important; }
    .mc-item:hover{ background:#333 !important; }

    /* Página */
    .contenedor{ max-width: 700px; margin: 20px auto; }
    form label{ display:block; margin-top:10px; }
    select, input[type="file"]{
      width:100%; padding:12px; margin:8px 0;
      background:#1c1c1c; border:1px solid #444; border-radius:8px; color:#fff;
    }
    .mensaje{ background:#111; color:gold; padding:10px; border:1px solid gold; border-radius:8px; margin-top:12px; text-align:center; }
    button{
      width:100%; padding:12px; background:#ffd600; color:#000; border:0; border-radius:8px;
      font-weight:800; cursor:pointer; margin-top:12px;
    }
    .tot{ font-weight:800; color:#fff; margin:8px 0; }
    .alias{ text-align:center; color:gold; font-size:18px; margin: 16px 0; }
    .alias strong{ color:#fff; }
  </style>
  <script>
    function calcularTotal() {
      let total = 0;
      const planSel = document.getElementById('plan_id');
      if (planSel && planSel.value !== "0") {
        total += parseFloat(planSel.selectedOptions[0].dataset.precio || 0);
      }
      document.querySelectorAll('input[name="adicionales[]"]:checked').forEach(el=>{
        total += parseFloat(el.dataset.precio || 0);
      });
      document.getElementById('monto').value = total.toFixed(2);
      document.getElementById('total_mostrar').innerText = "$" + total.toFixed(2);
    }
    document.addEventListener('DOMContentLoaded', calcularTotal);
  </script>
</head>
<body>

<?php include __DIR__ . '/menu_cliente.php'; ?>

<div class="contenedor">
  <h1>💳 Pago por Transferencia</h1>

  <div class="alias">
    💰 Alias para transferencia:<br>
    <strong><?= h($alias ?: 'No disponible') ?></strong>
  </div>

  <form method="POST" enctype="multipart/form-data" oninput="calcularTotal()">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

    <label>Seleccioná un plan:</label>
    <select name="plan_id" id="plan_id">
      <option value="0" data-precio="0">-- Solo adicionales --</option>
      <?php foreach($planes as $p): ?>
        <option value="<?= (int)$p['id'] ?>" data-precio="<?= h($p['precio']) ?>">
          <?= h($p['nombre']) ?> - $<?= number_format((float)$p['precio'], 2, ',', '.') ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php if (!empty($adicionales)): ?>
      <label>Seleccioná adicionales (opcionales):</label>
      <?php foreach($adicionales as $a): ?>
        <label style="display:flex;gap:8px;align-items:center;margin:6px 0">
          <input type="checkbox" name="adicionales[]" value="<?= (int)$a['id'] ?>" data-precio="<?= h($a['precio']) ?>">
          <span><?= h($a['nombre']) ?> ($<?= number_format((float)$a['precio'], 2, ',', '.') ?>)</span>
        </label>
      <?php endforeach; ?>
    <?php endif; ?>

    <p class="tot">Total a pagar: <span id="total_mostrar">$0.00</span></p>
    <input type="hidden" name="monto" id="monto" value="0">

    <label>Comprobante (imagen o PDF):</label>
    <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required>

    <button type="submit">Enviar Comprobante</button>
  </form>

  <?php if ($mensaje): ?>
    <p class="mensaje"><?= h($mensaje) ?></p>
  <?php endif; ?>
</div>
</body>
</html>
