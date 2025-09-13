<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_horizontal.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = {$id} AND gimnasio_id = {$gimnasio_id}")->fetch_assoc();
if (!$membresia) { http_response_code(404); exit("Membresía no encontrada."); }

$cliente_id = (int)$membresia['cliente_id'];
$cliente = $conexion->query("SELECT * FROM clientes WHERE id = {$cliente_id}")->fetch_assoc();

$planes = $conexion->query("SELECT id, nombre, precio, clases_disponibles, duracion_meses
                            FROM planes WHERE gimnasio_id = {$gimnasio_id} ORDER BY nombre ASC");

$adicionales = $conexion->query("SELECT id, nombre, precio
                                 FROM planes_adicionales
                                 WHERE gimnasio_id = {$gimnasio_id}
                                 ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Renovar Membresía</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor{max-width:960px;margin:20px auto;padding:16px;background:#fff;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.08)}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    label{font-weight:600;margin-top:8px;display:block}
    input[type="number"],input[type="date"],select{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px}
    .row{margin:10px 0}
    .boton-verde{background:#2e7d32;color:#fff;border:0;padding:10px 16px;border-radius:8px;cursor:pointer}
    .boton-volver{display:inline-block;margin-left:8px;padding:10px 16px;border-radius:8px;background:#e0e0e0;text-decoration:none;color:#212121}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;background:#f5f5f5;margin:4px 6px 0 0;border:1px solid #e0e0e0}
    .totales{margin-top:18px;padding:12px;border-radius:10px;background:#f9fafb;border:1px solid #e5e7eb}
  </style>
</head>
<body>
<div class="contenedor">
  <h2>♻️ Renovar Membresía</h2>

  <form action="guardar_renovacion.php" method="POST">
    <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
    <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">

    <p><strong>Cliente:</strong> <?= h(($cliente['apellido'] ?? '').', '.($cliente['nombre'] ?? '')) ?></p>

    <div class="grid">
      <div class="row">
        <label for="plan_id">Seleccionar Plan:</label>
        <select name="plan_id" id="plan_id" required>
          <option value="">-- Seleccionar --</option>
          <?php while($plan = $planes->fetch_assoc()): ?>
            <option value="<?= (int)$plan['id'] ?>"
              data-precio="<?= h($plan['precio']) ?>"
              data-clases="<?= (int)$plan['clases_disponibles'] ?>"
              data-duracion="<?= (int)($plan['duracion_meses'] ?: 1) ?>">
              <?= h($plan['nombre']) ?> - $<?= number_format((float)$plan['precio'], 2) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="row">
        <label for="fecha_inicio">Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
      </div>

      <div class="row">
        <label for="fecha_vencimiento">Fecha de Vencimiento:</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly required>
      </div>

      <div class="row">
        <label for="clases_disponibles">Clases Disponibles:</label>
        <input type="number" name="clases_disponibles" id="clases_disponibles" readonly required>
      </div>

      <div class="row">
        <label for="precio">Precio del plan:</label>
        <input type="number" name="precio" id="precio" readonly required>
      </div>

      <div class="row">
        <label for="otros_pagos">Otros cargos (matrícula/credencial/etc.):</label>
        <input type="number" name="otros_pagos" id="otros_pagos" value="0" step="0.01">
      </div>
    </div>

    <input type="hidden" name="duracion_meses" id="duracion_meses" value="1">

    <div class="row">
      <h3>Descuento</h3>
      <span class="pill"><label><input type="radio" name="descuento" value="0" checked> 0%</label></span>
      <span class="pill"><label><input type="radio" name="descuento" value="10"> 10%</label></span>
      <span class="pill"><label><input type="radio" name="descuento" value="15"> 15%</label></span>
      <span class="pill"><label><input type="radio" name="descuento" value="25"> 25%</label></span>
      <span class="pill"><label><input type="radio" name="descuento" value="50"> 50%</label></span>
    </div>

    <div class="row">
      <h3>Adicionales</h3>
      <?php if ($adicionales && $adicionales->num_rows > 0): ?>
        <?php while ($ad = $adicionales->fetch_assoc()): ?>
          <label class="pill">
            <input type="checkbox"
                   class="adicional"
                   name="adicionales[]"
                   value="<?= (int)$ad['id'] ?>"
                   data-precio="<?= h($ad['precio']) ?>">
            <?= h($ad['nombre']) ?> — $<?= number_format((float)$ad['precio'], 2) ?>
          </label>
        <?php endwhile; ?>
      <?php else: ?>
        <p>No hay adicionales configurados.</p>
      <?php endif; ?>
    </div>

    <div class="row">
      <h3>Formas de Pago (hoy)</h3>
      <div class="grid">
        <div>
          <label>Efectivo:</label>
          <input type="number" name="pago_efectivo" id="pago_efectivo" value="0" step="0.01">
        </div>
        <div>
          <label>Transferencia:</label>
          <input type="number" name="pago_transferencia" id="pago_transferencia" value="0" step="0.01">
        </div>
        <div>
          <label>Débito:</label>
          <input type="number" name="pago_debito" id="pago_debito" value="0" step="0.01">
        </div>
        <div>
          <label>Crédito:</label>
          <input type="number" name="pago_credito" id="pago_credito" value="0" step="0.01">
        </div>
        <div>
          <label>A cuenta corriente (cargar a DEBE):</label>
          <input type="number" name="pago_cuenta_corriente" id="pago_cuenta_corriente" value="0" step="0.01">
        </div>
      </div>
    </div>

    <div class="totales">
      <h3 id="total_a_pagar">💰 Total a Pagar: $0.00</h3>
      <div id="total_abonado_text">Abonado hoy: $0.00</div>
      <div id="remanente_text">Remanente (deuda/saldo a favor): $0.00</div>
    </div>

    <br>
    <button type="submit" class="boton-verde">Guardar Renovación</button>
    <a href="ver_membresias.php" class="boton-volver">Cancelar</a>
  </form>
</div>

<script>
function toNum(s){ if(!s) return 0; s = (""+s).replace(/\s+/g,"").replace(",","."); return parseFloat(s)||0; }

function actualizarDatosPlan() {
  const sel = document.getElementById('plan_id');
  const opt = sel && sel.selectedOptions && sel.selectedOptions[0] ? sel.selectedOptions[0] : null;
  if (!opt) return;
  const precio = toNum(opt.dataset.precio || 0);
  const clases = parseInt(opt.dataset.clases || 0);
  const dur    = parseInt(opt.dataset.duracion || 1);

  document.getElementById('precio').value = precio.toFixed(2);
  document.getElementById('clases_disponibles').value = isNaN(clases) ? 0 : clases;
  document.getElementById('duracion_meses').value = isNaN(dur) ? 1 : dur;

  const fi = new Date(document.getElementById('fecha_inicio').value);
  if (!isNaN(fi)) {
    const fv = new Date(fi);
    fv.setMonth(fv.getMonth() + (isNaN(dur) ? 1 : dur));
    const yyyy = fv.getFullYear();
    const mm = String(fv.getMonth()+1).padStart(2,'0');
    const dd = String(fv.getDate()).padStart(2,'0');
    document.getElementById('fecha_vencimiento').value = `${yyyy}-${mm}-${dd}`;
  }

  actualizarTotales();
}

function actualizarTotales() {
  const precioPlan  = toNum(document.getElementById('precio').value);
  const otrosCargos = toNum(document.getElementById('otros_pagos').value);

  let sumAdic = 0;
  document.querySelectorAll('.adicional:checked').forEach(chk=>{
    sumAdic += toNum(chk.dataset.precio || 0);
  });

  const descPct = toNum(document.querySelector('input[name="descuento"]:checked')?.value || 0);
  const subtotal = precioPlan + otrosCargos + sumAdic;
  const total = Math.max(0, subtotal - (subtotal * (descPct/100)));

  // pagos hoy (no incluye CC porque es carga a DEBE)
  const abonado = toNum(pago_efectivo.value) + toNum(pago_transferencia.value) + toNum(pago_debito.value) + toNum(pago_credito.value);
  const remanente = total - abonado - 0; // CC manual se suma en backend como DEBE aparte

  document.getElementById('total_a_pagar').textContent = "💰 Total a Pagar: $" + total.toFixed(2);
  document.getElementById('total_abonado_text').textContent = "Abonado hoy: $" + abonado.toFixed(2);
  document.getElementById('remanente_text').textContent = "Remanente (deuda/saldo a favor): $" + remanente.toFixed(2);
}

document.getElementById('plan_id').addEventListener('change', actualizarDatosPlan);
document.getElementById('fecha_inicio').addEventListener('change', actualizarDatosPlan);

['otros_pagos','pago_efectivo','pago_transferencia','pago_debito','pago_credito','pago_cuenta_corriente']
  .forEach(id => document.getElementById(id).addEventListener('input', actualizarTotales));

document.querySelectorAll('input[name="descuento"]').forEach(r => r.addEventListener('change', actualizarTotales));
document.querySelectorAll('.adicional').forEach(chk => chk.addEventListener('change', actualizarTotales));

window.addEventListener('DOMContentLoaded', actualizarDatosPlan);
</script>
</body>
</html>
