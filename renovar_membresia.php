<?php
/* ============================================================================
   renovar_membresia.php — SOLO FORMULARIO (muestra datos y manda a guardar_renovacion.php)
   - GET: muestra cliente fijo y planes/adicionales del gimnasio
   - POST REAL: lo hace guardar_renovacion.php
============================================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/menu_horizontal.php';

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

@$conexion->set_charset('utf8mb4');
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);

$DEBUG = isset($_GET['debug']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function table_has(mysqli $db,string $t){
  $t=$db->real_escape_string($t);
  $r=$db->query("SHOW TABLES LIKE '$t'");
  return $r && $r->num_rows>0;
}
function col_has(mysqli $db,string $t,string $c){
  $t=$db->real_escape_string($t);
  $c=$db->real_escape_string($c);
  $r=$db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows>0;
}
function gym_col(mysqli $db,string $t){
  if (col_has($db,$t,'gimnasio_id')) return 'gimnasio_id';
  if (col_has($db,$t,'id_gimnasio')) return 'id_gimnasio';
  return null;
}

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin BD');
}

$RUTA_M = 'ver_membresias.php';
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id<=0) {
  http_response_code(401);
  exit('❌ Falta gimnasio en sesión');
}

$MEM_GYM = gym_col($conexion,'membresias');
if (!$MEM_GYM){
  http_response_code(500);
  exit('❌ La tabla membresias no tiene gimnasio_id ni id_gimnasio');
}
$PLA_GYM = gym_col($conexion,'planes');
$CLI_GYM = gym_col($conexion,'clientes');

/* =======================
   Resolver CLIENTE (GET)
======================= */
$cliente = null;
$cliente_id   = (int)($_GET['cliente_id'] ?? $_GET['cliente'] ?? $_GET['c'] ?? 0);
$dni          = isset($_GET['dni']) ? trim((string)$_GET['dni']) : '';
$membresia_id = (int)($_GET['membresia_id'] ?? $_GET['mid'] ?? $_GET['m'] ?? $_GET['id'] ?? 0);

if ($cliente_id>0){
  $cond = "id={$cliente_id}";
  if ($CLI_GYM) $cond .= " AND `$CLI_GYM`={$gimnasio_id}";
  $rs = $conexion->query("SELECT id, apellido, nombre, dni FROM clientes WHERE $cond LIMIT 1");
  if ($rs && $rs->num_rows) $cliente = $rs->fetch_assoc();
}elseif($dni!==''){
  $dniE = $conexion->real_escape_string($dni);
  $cond = "dni='{$dniE}'";
  if ($CLI_GYM) $cond .= " AND `$CLI_GYM`={$gimnasio_id}";
  $rs = $conexion->query("SELECT id, apellido, nombre, dni FROM clientes WHERE $cond LIMIT 1");
  if ($rs && $rs->num_rows) $cliente = $rs->fetch_assoc();
}elseif($membresia_id>0){
  $rsM = $conexion->query("SELECT cliente_id FROM membresias WHERE id={$membresia_id} LIMIT 1");
  if ($rsM && $rsM->num_rows){
    $cid = (int)$rsM->fetch_assoc()['cliente_id'];
    $cond = "id={$cid}";
    if ($CLI_GYM) $cond .= " AND `$CLI_GYM`={$gimnasio_id}";
    $rsC = $conexion->query("SELECT id, apellido, nombre, dni FROM clientes WHERE $cond LIMIT 1");
    if ($rsC && $rsC->num_rows) $cliente = $rsC->fetch_assoc();
  }
}

if (!$cliente) {
  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Renovar Membresía</title>
    <link rel="stylesheet" href="estilo_unificado.css">
  </head>
  <body>
  <div class="contenedor">
    <div class="container">
      <h1>Renovar Membresía</h1>
      <div class="alerta-error">❌ No se pudo identificar el cliente. Entrá desde el listado (botón Renovar).</div>
      <div style="margin-top:12px;">
        <a class="btn-primario" href="<?= h($RUTA_M) ?>">Volver</a>
      </div>
    </div>
  </div>
  </body>
  </html>
  <?php
  exit;
}

/* =======================
   GET: cargar planes/adics
======================= */
$planes = [];
$condPlan = $PLA_GYM ? "WHERE `$PLA_GYM`={$gimnasio_id}" : "";
$rsP = $conexion->query("SELECT id,nombre,precio,clases_disponibles,duracion_meses FROM planes $condPlan ORDER BY nombre");
while($rsP && $r=$rsP->fetch_assoc()){ $planes[]=$r; }

$adics = [];
if (table_has($conexion,'planes_adicionales')){
  $PAD_GYM = gym_col($conexion,'planes_adicionales');
  $condAd = $PAD_GYM ? "WHERE `$PAD_GYM`={$gimnasio_id}" : "";
  $rsA = $conexion->query("SELECT id,nombre,precio FROM planes_adicionales $condAd ORDER BY nombre");
  while($rsA && $a=$rsA->fetch_assoc()){ $adics[]=$a; }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Renovar Membresía</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="estilo_unificado.css">
  <script src="fullscreen.js"></script>
</head>
<body>
<div class="contenedor">
  <div class="container">
    <h1>Renovar Membresía</h1>

    <!-- Cliente fijo (sin buscador) -->
    <label>Cliente</label>
    <input type="text" value="<?= h(($cliente['apellido']??'').', '.($cliente['nombre']??'').' ('.($cliente['dni']??'-').')') ?>" readonly>

    <!-- AHORA EL FORMULARIO APUNTA A guardar_renovacion.php -->
    <form method="POST" action="guardar_renovacion.php" onsubmit="return prepararEnvio()">
      <input type="hidden" name="cliente_id" value="<?= (int)($cliente['id'] ?? 0) ?>">
      <!-- membresía que se está renovando (para desactivar / cc) -->
      <input type="hidden" name="membresia_id" value="<?= (int)$membresia_id ?>">

      <label>Plan</label>
      <select name="plan_id" id="plan" required onchange="cargarDatosPlan()">
        <option value="">Seleccionar plan</option>
        <?php foreach($planes as $p): ?>
          <option value="<?= (int)$p['id'] ?>"
                  data-precio="<?= h($p['precio']) ?>"
                  data-clases="<?= (int)$p['clases_disponibles'] ?>"
                  data-duracion="<?= (int)$p['duracion_meses'] ?>">
            <?= h($p['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Precio del Plan</label>
      <input type="text" name="precio" id="precio" readonly>

      <label>Clases Disponibles</label>
      <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>

      <label>Fecha de Inicio</label>
      <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= date('Y-m-d') ?>" required onchange="calcularVencimiento()">

      <label>Fecha de Vencimiento</label>
      <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>

      <label>Planes Adicionales</label>
      <div id="lista_adicionales" class="lista-adicionales">
        <?php foreach($adics as $a): ?>
          <label class="check-line">
            <input type="checkbox" name="adicionales[]" value="<?= (int)$a['id'] ?>" data-precio="<?= h($a['precio']) ?>" onchange="calcularTotal()">
            <?= h($a['nombre']) ?> ($<?= number_format((float)$a['precio'],2,',','.') ?>)
          </label>
        <?php endforeach; ?>
      </div>

      <div class="fila-3">
        <div>
          <label>Otros Pagos</label>
          <input type="text" inputmode="decimal" name="otros_pagos" id="otros_pagos" value="0" oninput="calcularTotal()">
        </div>
        <div>
          <label>Descuento</label>
          <select id="descuento" name="descuento" onchange="calcularTotal()">
            <option value="0">Sin descuento</option>
            <option value="10">10%</option>
            <option value="15">15%</option>
            <option value="25">25%</option>
            <option value="50">50%</option>
          </select>
        </div>
        <div>
          <label>Total a Pagar</label>
          <input type="text" name="total_pagar" id="total_pagar" readonly>
          <p class="total-visible">Total actual: <span id="total_visible">0.00</span></p>
        </div>
      </div>

      <h3>💳 Formas de Pago</h3>
      <div class="fila-3">
        <div>
          <label>💵 Efectivo</label>
          <input type="text" inputmode="decimal" name="pago_efectivo" value="0" oninput="actualizarTotalAbonadoLive()">
        </div>
        <div>
          <label>🏦 Transferencia</label>
          <input type="text" inputmode="decimal" name="pago_transferencia" value="0" oninput="actualizarTotalAbonadoLive()">
        </div>
        <div>
          <label>💳 Débito</label>
          <input type="text" inputmode="decimal" name="pago_debito" value="0" oninput="actualizarTotalAbonadoLive()">
        </div>
      </div>
      <div class="fila-3">
        <div>
          <label>💳 Crédito</label>
          <input type="text" inputmode="decimal" name="pago_credito" value="0" oninput="actualizarTotalAbonadoLive()">
        </div>
        <div>
          <label>📒 Cuenta Corriente (Deuda)</label>
          <input type="text" inputmode="decimal" name="pago_cuenta_corriente" value="0" oninput="actualizarTotalAbonadoLive()">
        </div>
        <div class="total-abonado">
          Total abonado: $<span id="total_abonado">0.00</span>
        </div>
      </div>

      <!-- OPCIONAL: duplicar total para respaldo en guardar_renovacion -->
      <input type="hidden" name="monto_pago" id="monto_pago_hidden" value="0">
      <input type="hidden" name="monto_pagado" id="monto_pagado_hidden" value="0">
      <input type="hidden" name="total_pagado" id="total_pagado_hidden" value="0">

      <div class="acciones-form">
        <button type="submit" class="btn-primario">Renovar</button>
        <a href="<?= h($RUTA_M) ?>" class="btn-secundario">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
function toNum(raw){
  if (raw === undefined || raw === null) return 0;
  let s = String(raw).trim().replace(/\u00A0|\s/g, '');
  if (!s) return 0;
  const lastDot = s.lastIndexOf('.');
  const lastCom = s.lastIndexOf(',');
  if (lastDot === -1 && lastCom === -1) return Number(s)||0;

  let decSep, thouSep;
  if (lastDot !== -1 && lastCom !== -1) {
    decSep  = (lastDot > lastCom) ? '.' : ',';
    thouSep = (decSep === '.') ? ',' : '.';
  } else if (lastDot !== -1) {
    decSep = '.';
  } else {
    decSep = ',';
  }
  if (thouSep) s = s.split(thouSep).join('');
  if (decSep !== '.') s = s.replace(decSep, '.');
  const n = Number(s);
  return isNaN(n) ? 0 : n;
}

function cargarDatosPlan(){
  const opt = document.querySelector('#plan option:checked');
  const precio = opt?.getAttribute('data-precio') || '0';
  const clases = opt?.getAttribute('data-clases') || '0';
  document.getElementById('precio').value = precio;
  document.getElementById('clases_disponibles').value = clases;
  calcularVencimiento();
  calcularTotal();
}

function calcularVencimiento(){
  const fi = document.getElementById('fecha_inicio').value;
  const opt = document.querySelector('#plan option:checked');
  const dur = parseInt(opt?.getAttribute('data-duracion') || '0', 10);
  if (!fi || !dur) return;
  const d = new Date(fi); d.setMonth(d.getMonth()+dur);
  const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), da=String(d.getDate()).padStart(2,'0');
  document.getElementById('fecha_vencimiento').value = `${y}-${m}-${da}`;
}

function calcularTotal(){
  const precio = toNum(document.getElementById('precio').value);
  const otros  = toNum(document.getElementById('otros_pagos').value);
  const desc   = toNum(document.getElementById('descuento').value);
  let adics=0;
  document.querySelectorAll('#lista_adicionales input[type="checkbox"]:checked').forEach(cb=>{
    adics += toNum(cb.getAttribute('data-precio'));
  });
  const bruto = precio + adics + otros;
  const total = Math.max(0, bruto - (bruto * desc / 100));
  document.getElementById('total_pagar').value = total.toFixed(2);
  document.getElementById('total_visible').textContent = total.toFixed(2);
  actualizarTotalAbonadoLive();
}

function actualizarTotalAbonadoLive(){
  const n = name => toNum(document.querySelector(`[name=${name}]`)?.value);
  const t = n('pago_efectivo')+n('pago_transferencia')+n('pago_debito')+n('pago_credito')+n('pago_cuenta_corriente');
  const tgt = document.getElementById('total_abonado'); if (tgt) tgt.textContent = t.toFixed(2);

  // duplico a los hidden viejos para que guardar_renovacion pueda usarlos de respaldo
  document.getElementById('monto_pago_hidden').value   = t.toFixed(2);
  document.getElementById('monto_pagado_hidden').value = t.toFixed(2);
  document.getElementById('total_pagado_hidden').value = t.toFixed(2);
}

function validarPagos(){
  const total = toNum(document.getElementById('total_pagar').value);
  const n = name => toNum(document.querySelector(`[name=${name}]`)?.value);
  const pagado = n('pago_efectivo')+n('pago_transferencia')+n('pago_debito')+n('pago_credito')+n('pago_cuenta_corriente');
  const T = Math.round(total*100)/100, P = Math.round(pagado*100)/100;
  if (P > T){ alert(`❌ El abonado (${P.toFixed(2)}) supera el total (${T.toFixed(2)}).`); return false; }
  if (P < T){ const dif = T - P; return confirm(`⚠️ Se registrará deuda por $${dif.toFixed(2)}. ¿Continuar?`); }
  return true;
}

function prepararEnvio(){
  calcularTotal();
  return validarPagos();
}

window.addEventListener('DOMContentLoaded', ()=>{
  calcularTotal();
});
</script>
</body>
</html>
