<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ventas_productos_mod.php");
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$total = (float)($_POST['total'] ?? 0);
$cliente_id = (int)($_POST['cliente_id'] ?? 0);
$cliente_temporal = isset($_POST['cliente_temporal']) ? 1 : 0;
$tipo_venta = $_POST['tipo_venta'] ?? '';
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

// Productos enviados como arrays
$productos  = $_POST['producto_nombre'] ?? [];
$precios    = $_POST['precio'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formas de Pago</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
      .contenedor{max-width:680px;margin:auto;padding:16px}
      .fila{display:grid;grid-template-columns:1fr 1fr;gap:10px}
      .info{background:#0f1115;color:#f1f5f9;border-radius:12px;padding:12px;margin:12px 0}
      .muted{opacity:.8}
      input[type=number]{width:100%}
      .totales{display:flex;justify-content:space-between;align-items:center}
      .aviso{color:#ef4444;margin-top:6px}
      .ok{color:#22c55e}
      button{padding:10px 14px;border-radius:10px;border:1px solid #334155;background:#1e293b;color:#fff;cursor:pointer}
      button:hover{background:#243143}
    </style>
</head>
<body>
<div class="contenedor">
    <h2>💳 Formas de Pago</h2>

    <div class="info">
      <div class="totales">
        <strong>Total original:</strong>
        <span>$ <span id="total_original"><?= number_format($total, 2, ',', '.') ?></span></span>
      </div>
    </div>

    <!-- IFRAME oculto: recibe la respuesta con el comprobante -->
    <iframe name="dl_iframe" style="display:none" title="descarga"></iframe>

    <form id="fpago" method="POST" action="guardar_venta_productos.php" target="dl_iframe" onsubmit="return previoSubmit();">
        <!-- Datos ocultos -->
        <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
        <input type="hidden" name="cliente_temporal" value="<?= $cliente_temporal ?>">
        <input type="hidden" name="total_original" id="total_original_val" value="<?= $total ?>">
        <input type="hidden" name="tipo_venta" value="<?= h($tipo_venta) ?>">
        <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">

        <!-- Totales calculados que necesita el backend -->
        <input type="hidden" name="total_con_descuento" id="total_con_descuento" value="<?= $total ?>">
        <input type="hidden" name="descuento_porcentaje" id="descuento_porcentaje" value="0">
        <input type="hidden" name="pagado_total" id="pagado_total" value="0">
        <input type="hidden" name="saldo_cuenta_corriente" id="saldo_cuenta_corriente" value="0">
        <input type="hidden" name="vuelto" id="vuelto" value="0">
        <!-- Señal para que el backend sepa que abrimos comprobante en iframe -->
        <input type="hidden" name="modo_descarga" value="iframe">
        <!-- Señal de a dónde volver (por si querés usarla en el backend) -->
        <input type="hidden" name="redir_after" value="ventas_productos_mod.php">

        <?php
        for ($i = 0; $i < count($productos); $i++) {
            echo '<input type="hidden" name="producto_nombre[]" value="' . h($productos[$i]) . '">';
            echo '<input type="hidden" name="precio[]" value="' . h($precios[$i]) . '">';
            echo '<input type="hidden" name="cantidad[]" value="' . h($cantidades[$i]) . '">';
        }
        ?>

        <label for="descuento">Descuento:</label>
        <select id="descuento" name="descuento" onchange="recalcularTotal()" required>
            <option value="0">Sin descuento</option>
            <option value="10">10%</option>
            <option value="15">15%</option>
            <option value="20">20%</option>
        </select>

        <div class="info muted">
          <small>El descuento se aplica sobre el total y se recalcula automáticamente el resto.</small>
        </div>

        <label>Pagos (ingresa lo recibido):</label>
        <div class="fila">
          <input type="number" id="pago_efectivo" name="pago_efectivo" placeholder="💵 Efectivo" step="0.01" min="0" oninput="recalcularCCyVuelto()">
          <input type="number" id="pago_transferencia" name="pago_transferencia" placeholder="🏦 Transferencia" step="0.01" min="0" oninput="recalcularCCyVuelto()">
        </div>
        <div class="fila">
          <input type="number" id="pago_debito" name="pago_debito" placeholder="💳 Débito" step="0.01" min="0" oninput="recalcularCCyVuelto()">
          <input type="number" id="pago_credito" name="pago_credito" placeholder="💳 Crédito" step="0.01" min="0" oninput="recalcularCCyVuelto()">
        </div>

        <!-- Cuenta corriente visible (readonly) -->
        <label for="pago_cuenta_corriente">📒 Cuenta Corriente (Deuda generada)</label>
        <input type="number" id="pago_cuenta_corriente" step="0.01" min="0" value="0" readonly>

        <div class="info">
          <div class="totales">
            <strong>Total con descuento:</strong>
            <span>$ <span id="total_descuento"><?= number_format($total, 2, ',', '.') ?></span></span>
          </div>
          <div class="totales">
            <span>Pagado ahora:</span>
            <span>$ <span id="pagado_ahora">0.00</span></span>
          </div>
          <div class="totales">
            <span>Cuenta Corriente a generar:</span>
            <span class="ok">$ <span id="cc_a_generar">0.00</span></span>
          </div>
          <div class="totales" id="seccion_vuelto" style="display:none;">
            <span>Vuelto:</span>
            <span>$ <span id="vuelto_mostrar">0.00</span></span>
          </div>
          <div class="aviso" id="aviso_validacion" style="display:none;"></div>
        </div>

        <br>
        <button type="submit">✅ Finalizar y Generar Comprobante</button>
        <div class="muted" id="msg_post" style="display:none;margin-top:8px">Generando comprobante… te redirigimos a Ventas.</div>
    </form>
</div>

<script>
function toNumber(v){
  const x = parseFloat(String(v).replace(',', '.'));
  return isNaN(x) ? 0 : x;
}

function valoresPagos(){
  const ef   = toNumber(document.getElementById('pago_efectivo').value);
  const trf  = toNumber(document.getElementById('pago_transferencia').value);
  const deb  = toNumber(document.getElementById('pago_debito').value);
  const cred = toNumber(document.getElementById('pago_credito').value);
  const pagado = +(ef + trf + deb + cred).toFixed(2);
  return {ef, trf, deb, cred, pagado};
}

function actualizarUIyHidden(total){
  const {pagado} = valoresPagos();
  let restante = +(total - pagado).toFixed(2);
  if (Math.abs(restante) < 0.01) restante = 0; // tolerancia

  let cc = 0, vuelto = 0;
  if (restante > 0) { cc = restante; vuelto = 0; }
  else if (restante < 0) { cc = 0; vuelto = Math.abs(restante); }

  // UI
  document.getElementById('pagado_ahora').textContent   = pagado.toFixed(2);
  document.getElementById('cc_a_generar').textContent   = cc.toFixed(2);
  document.getElementById('pago_cuenta_corriente').value= cc.toFixed(2);
  document.getElementById('vuelto_mostrar').textContent = vuelto.toFixed(2);
  document.getElementById('seccion_vuelto').style.display = (vuelto > 0.009) ? 'flex' : 'none';

  // HIDDEN para backend
  document.getElementById('pagado_total').value          = pagado.toFixed(2);
  document.getElementById('saldo_cuenta_corriente').value= cc.toFixed(2);
  document.getElementById('vuelto').value                = vuelto.toFixed(2);

  // Aviso
  const aviso = document.getElementById('aviso_validacion');
  if (vuelto > 0.009) {
    aviso.style.display = 'block';
    aviso.textContent = 'Atención: hay excedente. Se registrará vuelto y NO se generará saldo negativo en cuenta corriente.';
  } else {
    aviso.style.display = 'none';
    aviso.textContent = '';
  }
}

function recalcularTotal() {
    const original = toNumber(document.getElementById('total_original_val').value);
    const desc = toNumber(document.getElementById('descuento').value);
    const total_desc = +(original - (original * (desc / 100))).toFixed(2);

    document.getElementById('total_descuento').textContent = total_desc.toFixed(2);
    document.getElementById('total_con_descuento').value   = total_desc.toFixed(2);
    document.getElementById('descuento_porcentaje').value  = desc.toFixed(0);

    actualizarUIyHidden(total_desc);
}

function recalcularCCyVuelto(){
    const total = toNumber(document.getElementById('total_con_descuento').value);
    actualizarUIyHidden(total);
}

function validarPago() {
    const total  = toNumber(document.getElementById('total_con_descuento').value);
    const pagado = toNumber(document.getElementById('pagado_total').value);
    const cc     = toNumber(document.getElementById('saldo_cuenta_corriente').value);
    const vuelto = toNumber(document.getElementById('vuelto').value);

    // pagado + cc - vuelto == total (con tolerancia)
    const suma = +(pagado + cc - vuelto).toFixed(2);
    return Math.abs(suma - total) <= 0.01;
}

function previoSubmit(){
  // recalcular por si algo quedó pendiente
  recalcularCCyVuelto();
  if (!validarPago()){
    alert("⚠️ La suma de pagos + cuenta corriente debe coincidir con el total.");
    return false;
  }

  // Mensaje y redirección automática a Ventas
  document.getElementById('msg_post').style.display = 'block';
  setTimeout(function(){
    window.location.href = 'ventas_productos_mod.php?ok=1';
  }, 1100);

  return true; // enviamos al iframe (descarga comprobante)
}

// Inicializa cálculos al cargar
document.addEventListener('DOMContentLoaded', () => {
  recalcularTotal();
});
</script>
</body>
</html>
