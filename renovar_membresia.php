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
  <!-- Usa SOLO tu CSS unificado -->
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
  <h2>♻️ Renovar Membresía</h2>

  <form action="guardar_renovacion.php" method="POST" id="formRenovar">
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
              <?= h($plan['nombre']) ?> — $<?= number_format((float)$plan['precio'], 2, ',', '.') ?>
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
        <input type="number" name="precio" id="precio" readonly required step="0.01">
      </div>

      <div class="row">
        <label for="otros_pagos">Otros cargos (matrícula/credencial/etc.):</label>
        <input type="number" name="otros_pagos" id="otros_pagos" value="0" step="0.01">
      </div>
    </div>

    <input type="hidden" name="duracion_meses" id="duracion_meses" value="1">

    <div class="row">
      <h3>Descuento</h3>
      <label class="pill"><input type="radio" name="descuento" value="0" checked> 0%</label>
      <label class="pill"><input type="radio" name="descuento" value="10"> 10%</label>
      <label class="pill"><input type="radio" name="descuento" value="15"> 15%</label>
      <label class="pill"><input type="radio" name="descuento" value="25"> 25%</label>
      <label class="pill"><input type="radio" name="descuento" value="50"> 50%</label>
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
            <?= h($ad['nombre']) ?> — $<?= number_format((float)$ad['precio'], 2, ',', '.') ?>
          </label>
        <?php endwhile; ?>
      <?php else: ?>
        <p>No hay adicionales configurados.</p>
      <?php endif; ?>
    </div>

    <!-- ===== Formas de Pago (HOY) ===== -->
    <div class="row">
      <h3>Formas de Pago (hoy)</h3>
      <div class="grid">
        <div class="row">
          <label for="pago_efectivo">Efectivo:</label>
          <input type="number" name="pago_efectivo" id="pago_efectivo" value="0" step="0.01">
        </div>
        <div class="row">
          <label for="pago_transferencia">Transferencia:</label>
          <input type="number" name="pago_transferencia" id="pago_transferencia" value="0" step="0.01">
        </div>
        <div class="row">
          <label for="pago_debito">Débito:</label>
          <input type="number" name="pago_debito" id="pago_debito" value="0" step="0.01">
        </div>
        <div class="row">
          <label for="pago_credito">Crédito:</label>
          <input type="number" name="pago_credito" id="pago_credito" value="0" step="0.01">
        </div>
        <div class="row">
          <label for="pago_cuenta_corriente">A cuenta corriente (cargar a DEBE):</label>
          <input type="number" name="pago_cuenta_corriente" id="pago_cuenta_corriente" value="0" step="0.01">
        </div>
      </div>
    </div>

    <div class="totales">
      <h3 id="total_a_pagar">💰 Total a Pagar: $0,00</h3>
      <div id="total_abonado_text">Abonado hoy: $0,00</div>
      <div id="remanente_text">Remanente (deuda/saldo a favor): $0,00</div>
    </div>

    <br>
    <button type="submit" class="boton-verde" id="btnGuardar" disabled>Guardar Renovación</button>
    <a href="ver_membresias.php" class="boton-volver">Cancelar</a>
  </form>
</div>

<script>
(function(){
  const $  = (id)=>document.getElementById(id);
  const q  = (sel)=>document.querySelector(sel);
  const qa = (sel)=>Array.from(document.querySelectorAll(sel));

  const elPlan        = $('plan_id');
  const elFechaInicio = $('fecha_inicio');
  const elFechaVto    = $('fecha_vencimiento');
  const elClases      = $('clases_disponibles');
  const elPrecio      = $('precio');
  const elOtros       = $('otros_pagos');
  const elDuracion    = $('duracion_meses');

  // pagos (restaurados)
  const elEf   = $('pago_efectivo');
  const elTrf  = $('pago_transferencia');
  const elDeb  = $('pago_debito');
  const elCred = $('pago_credito');
  const elCC   = $('pago_cuenta_corriente');

  const elTotalH3 = $('total_a_pagar');
  const elAbonado = $('total_abonado_text');
  const elReman   = $('remanente_text');
  const btnGuardar= $('btnGuardar');

  function toNum(s){
    if(!s) return 0;
    s = (""+s).replace(/\s+/g,"").replace(",",".");
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }

  function addMonthsKeepingDay(date, months){
    const d = new Date(date.getTime());
    const day = d.getDate();
    d.setMonth(d.getMonth() + months);
    if (d.getDate() < day) d.setDate(0); // último día del mes
    return d;
  }

  function actualizarDatosPlan(){
    const opt = elPlan && elPlan.selectedOptions && elPlan.selectedOptions[0] ? elPlan.selectedOptions[0] : null;

    btnGuardar.disabled = !opt || !elPlan.value;

    if (!opt) {
      elPrecio.value = '';
      elClases.value = '';
      elDuracion.value = '1';
      elFechaVto.value = '';
      actualizarTotales();
      return;
    }

    const precio = toNum(opt.dataset.precio || 0);
    const clases = parseInt(opt.dataset.clases || 0);
    const dur    = parseInt(opt.dataset.duracion || 1);

    elPrecio.value   = precio.toFixed(2);
    elClases.value   = isNaN(clases) ? 0 : clases;
    elDuracion.value = isNaN(dur) ? 1 : dur;

    const fi = new Date(elFechaInicio.value);
    if (!isNaN(fi.getTime())) {
      const fv = addMonthsKeepingDay(fi, (isNaN(dur)?1:dur));
      const yyyy = fv.getFullYear();
      const mm = String(fv.getMonth()+1).padStart(2,'0');
      const dd = String(fv.getDate()).padStart(2,'0');
      elFechaVto.value = `${yyyy}-${mm}-${dd}`;
    } else {
      elFechaVto.value = '';
    }

    actualizarTotales();
  }

  function actualizarTotales(){
    const precioPlan  = toNum(elPrecio.value);
    const otrosCargos = toNum(elOtros.value);

    let sumAdic = 0;
    qa('.adicional:checked').forEach(chk=>{
      sumAdic += toNum(chk.dataset.precio || 0);
    });

    const rDesc   = q('input[name="descuento"]:checked');
    const descPct = toNum(rDesc ? rDesc.value : 0);

    const subtotal = precioPlan + otrosCargos + sumAdic;
    const total    = Math.max(0, subtotal - (subtotal * (descPct/100)));

    const abonado  = toNum(elEf?.value || 0)
                   + toNum(elTrf?.value || 0)
                   + toNum(elDeb?.value || 0)
                   + toNum(elCred?.value || 0);

    const remanente= total - abonado; // CC se registra en backend como DEBE

    if (elTotalH3) elTotalH3.textContent = "💰 Total a Pagar: $" + total.toFixed(2);
    if (elAbonado) elAbonado.textContent = "Abonado hoy: $" + abonado.toFixed(2);
    if (elReman)   elReman.textContent   = "Remanente (deuda/saldo a favor): $" + remanente.toFixed(2);
  }

  // Listeners principales
  elPlan.addEventListener('change', actualizarDatosPlan);
  elFechaInicio.addEventListener('change', actualizarDatosPlan);

  [elOtros, elEf, elTrf, elDeb, elCred, elCC].forEach(el => {
    if (el) el.addEventListener('input', actualizarTotales);
  });

  qa('input[name="descuento"]').forEach(r => r.addEventListener('change', actualizarTotales));
  qa('.adicional').forEach(chk => chk.addEventListener('change', actualizarTotales));

  // Inicializar
  window.addEventListener('DOMContentLoaded', ()=>{
    actualizarDatosPlan();
    actualizarTotales();
  });
})();
</script>
</body>
</html>
