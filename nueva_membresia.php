<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

// === Planes / Clientes / Adicionales ===
$planes      = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = {$gimnasio_id}");
$clientes    = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = {$gimnasio_id}");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = {$gimnasio_id}");

$clientes_array = [];
while ($c = $clientes->fetch_assoc()) { $clientes_array[] = $c; }

// === Profesores (opcional) ===
$profes = [];
$tbl_prof = $conexion->query("SHOW TABLES LIKE 'profesores'");
if ($tbl_prof && $tbl_prof->num_rows > 0) {
    $has_activo = $conexion->query("SHOW COLUMNS FROM profesores LIKE 'activo'");
    $cond_act   = ($has_activo && $has_activo->num_rows > 0) ? "AND activo=1" : "";
    $q = $conexion->query("
        SELECT id, nombre, apellido
        FROM profesores
        WHERE gimnasio_id = {$gimnasio_id} {$cond_act}
        ORDER BY apellido, nombre
    ");
    while ($q && $r = $q->fetch_assoc()) { $profes[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>

<script src="fullscreen.js"></script>
<script>
// Calcular automáticamente al cargar la página
window.addEventListener('DOMContentLoaded', () => {
    calcularTotal();
    // Sincronizar rangos personalizados con las fechas del plan
    const fi = document.getElementById('fecha_inicio');
    const fv = document.getElementById('fecha_vencimiento');
    const pf = document.getElementById('pers_desde');
    const ph = document.getElementById('pers_hasta');
    if (fi && fv && pf && ph) {
        pf.value = fi.value || pf.value;
        ph.value = fv.value || ph.value;
    }
    actualizarBloquePersonalizados();
});

function actualizarTotalVisible() {
    const total = document.getElementById('total_pagar');
    const span = document.getElementById('total_visible');
    if (total && span) { span.textContent = total.value; }
}
setInterval(actualizarTotalVisible, 500);
</script>

<body>
<div class="contenedor">

<?php if (isset($_GET['exito']) && $_GET['exito'] == 1): ?>
<div style="background-color: #0f0; color: black; padding: 10px; text-align: center; font-weight: bold; border-radius: 6px;">
    ✅ Membresía cargada correctamente
</div>
<script>
    setTimeout(() => { window.location.href = "nueva_membresia.php"; }, 2500);
</script>
<?php endif; ?>

<div class="container">
    <h1>Registrar Nueva Membresía</h1>

    <!-- IMPORTANTE: el onsubmit valida pagos y empaqueta turnos personalizados en JSON -->
    <form method="POST" action="guardar_membresia.php" onsubmit="return prepararEnvio()">
        <label>Buscar Cliente (DNI, nombre o apellido):</label>
        <input type="text" id="buscador_cliente" list="clientes" required oninput="buscarCliente()">
        <input type="hidden" name="cliente_id" id="cliente_id">
        <datalist id="clientes">
            <?php foreach ($clientes_array as $c): ?>
                <option data-id="<?= (int)$c['id'] ?>" value="<?= htmlspecialchars($c['apellido']) ?>, <?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['dni']) ?>)"></option>
            <?php endforeach; ?>
        </datalist>

        <label>Plan:</label>
        <select name="plan_id" id="plan" required onchange="cargarDatosPlan()">
            <option value="">Seleccionar plan</option>
            <?php foreach ($planes as $p): ?>
                <option value="<?= (int)$p['id'] ?>"
                        data-precio="<?= htmlspecialchars($p['precio']) ?>"
                        data-clases="<?= (int)$p['clases_disponibles'] ?>"
                        data-duracion="<?= (int)$p['duracion_meses'] ?>">
                    <?= htmlspecialchars($p['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div style="max-height: 500px; overflow-y: auto; display:none;">
            <table><!-- (Reservado) --></table>
        </div>

        <label>Precio del Plan:</label>
        <input type="text" name="precio" id="precio" readonly>

        <label>Clases Disponibles:</label>
        <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>

        <label>Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" id="fecha_inicio" required value="<?= date('Y-m-d') ?>" onchange="sincronizarRangos(); calcularVencimiento();">

        <label>Fecha de Vencimiento:</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>

        <label>Planes Adicionales:</label>
        <div id="lista_adicionales">
            <?php while ($a = $adicionales->fetch_assoc()): ?>
                <label style="display:block; margin:2px 0;">
                    <input type="checkbox" name="adicionales[]" value="<?= (int)$a['id'] ?>" data-precio="<?= htmlspecialchars($a['precio']) ?>" onchange="calcularTotal()">
                    <?= htmlspecialchars($a['nombre']) ?> ($<?= number_format((float)$a['precio'], 2, ',', '.') ?>)
                </label>
            <?php endwhile; ?>
        </div>

        <label>Otros Pagos:</label>
        <input type="number" name="otros_pagos" id="otros_pagos" value="0" step="0.01" oninput="calcularTotal()">

        <label>Descuento:</label>
        <select id="descuento" name="descuento" onchange="calcularTotal()">
            <option value="0">Sin descuento</option>
            <option value="10">10%</option>
            <option value="15">15%</option>
            <option value="25">25%</option>
            <option value="50">50%</option>
        </select>

        <label>Total a Pagar:</label>
        <input type="text" name="total_pagar" id="total_pagar" readonly>
        <p style="margin-top:5px; color: gold;">Total actual: <span id="total_visible" style="font-weight: bold;"></span></p>

        <h3>💳 Formas de Pago</h3>
        <div>
            <label>💵 Efectivo: </label>
            <input type="number" step="0.01" min="0" name="pago_efectivo" value="0"><br>
            <label>🏦 Transferencia: </label>
            <input type="number" step="0.01" min="0" name="pago_transferencia" value="0"><br>
            <label>💳 Débito: </label>
            <input type="number" step="0.01" min="0" name="pago_debito" value="0"><br>
            <label>💳 Crédito: </label>
            <input type="number" step="0.01" min="0" name="pago_credito" value="0"><br>
            <label>📒 Cuenta Corriente (Deuda): </label>
            <input type="number" step="0.01" min="0" name="pago_cuenta_corriente" value="0"><br>
        </div>

        <h4>Total abonado: $<span id="total_abonado">0.00</span></h4>

        <!-- ============================= -->
        <!--    TURNOS PERSONALIZADOS      -->
        <!-- ============================= -->
        <hr style="margin:16px 0; opacity:.4;">
        <h2 style="margin-bottom:6px;">🗓️ Turnos personalizados (opcional)</h2>

        <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
            <input type="checkbox" id="pers_habilitar" onchange="actualizarBloquePersonalizados()">
            Generar turnos fijos para este cliente
        </label>

        <div id="bloque_personalizados" style="display:none; border:1px solid #444; border-radius:8px; padding:12px;">
            <!-- Profesor global (aplica a todos los días seleccionados) -->
            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
                <div>
                    <label>Profesor (aplica a todos los días):</label>
                    <select id="profesor_id">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach (($profes ?? []) as $pr): ?>
                            <option value="<?= (int)$pr['id'] ?>">
                                <?= htmlspecialchars(($pr['apellido'] ?? '').', '.($pr['nombre'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="opacity:.7; display:block;">Si tu vista de horarios filtra por profesor, asignalo aquí.</small>
                </div>
            </div>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
                <div>
                    <label>Desde:</label>
                    <input type="date" id="pers_desde" value="<?= date('Y-m-d') ?>" onchange="validarRangoPers()">
                </div>
                <div>
                    <label>Hasta:</label>
                    <input type="date" id="pers_hasta" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" onchange="validarRangoPers()">
                </div>
            </div>

            <p style="margin:6px 0 10px;">Seleccioná días y horario (uno por día):</p>

            <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:10px;">
                <?php
                $dias = [
                    ['label'=>'Lunes','dow'=>1],
                    ['label'=>'Martes','dow'=>2],
                    ['label'=>'Miércoles','dow'=>3],
                    ['label'=>'Jueves','dow'=>4],
                    ['label'=>'Viernes','dow'=>5],
                    ['label'=>'Sábado','dow'=>6],
                    ['label'=>'Domingo','dow'=>0],
                ];
                foreach ($dias as $d):
                ?>
                <div style="border:1px dashed #555; border-radius:10px; padding:8px;">
                    <label style="display:block;">
                        <input type="checkbox" class="pers_dia_chk" data-dow="<?= (int)$d['dow'] ?>" onchange="toggleHora(this)">
                        <?= htmlspecialchars($d['label']) ?>
                    </label>
                    <label style="display:block; margin-top:6px; opacity:.8;">Hora:</label>
                    <input type="time" class="pers_hora" data-dow="<?= (int)$d['dow'] ?>" disabled>
                </div>
                <?php endforeach; ?>
            </div>

            <input type="hidden" name="turnos_json" id="turnos_json">
            <small style="display:block; margin-top:8px; opacity:.8;">
                Este JSON se procesará en <b>guardar_membresia.php</b> para crear los registros de turnos fijos
                (p. ej. en <code>clientes_fijos</code> o <code>turnos_personalizados</code>), incluyendo el <b>profesor</b> si la tabla lo admite.
            </small>
        </div>
        <!-- /TURNOS PERSONALIZADOS -->

        <button type="submit" style="margin-top:14px;">Guardar Membresía</button>
    </form>
</div>

<script>
const clientes = <?= json_encode($clientes_array, JSON_UNESCAPED_UNICODE) ?>;

function buscarCliente() {
    const input = document.getElementById('buscador_cliente').value.toLowerCase();
    const cliente = clientes.find(c => `${c.apellido}, ${c.nombre} (${c.dni})`.toLowerCase() === input);
    document.getElementById('cliente_id').value = cliente ? cliente.id : '';
}

function cargarDatosPlan() {
    const plan = document.getElementById('plan');
    const selected = plan.options[plan.selectedIndex];
    const precio = selected?.getAttribute('data-precio') || '';
    const clases = selected?.getAttribute('data-clases') || '';
    const duracion = selected?.getAttribute('data-duracion') || '';

    document.getElementById('precio').value = precio;
    document.getElementById('clases_disponibles').value = clases;
    calcularVencimiento();
    calcularTotal();
}

function calcularVencimiento() {
    const plan = document.getElementById('plan');
    const duracion = plan.options[plan.selectedIndex]?.getAttribute('data-duracion');
    const fechaInicio = document.getElementById('fecha_inicio').value;
    if (!duracion || !fechaInicio) return;

    const fecha = new Date(fechaInicio);
    fecha.setMonth(fecha.getMonth() + parseInt(duracion));

    const mes = (fecha.getMonth() + 1).toString().padStart(2, '0');
    const dia = fecha.getDate().toString().padStart(2, '0');
    const anio = fecha.getFullYear();

    const fv = `${anio}-${mes}-${dia}`;
    document.getElementById('fecha_vencimiento').value = fv;

    // si hay personalizados habilitados, acompasamos el rango
    const ph = document.getElementById('pers_hasta');
    if (ph) ph.value = fv;
}

function calcularTotal() {
    const precioPlan = parseFloat(document.getElementById('precio').value) || 0;
    const otros      = parseFloat(document.getElementById('otros_pagos').value) || 0;
    const descuento  = parseFloat(document.getElementById('descuento').value) || 0;
    let totalAdicionales = 0;

    document.querySelectorAll('#lista_adicionales input[type="checkbox"]:checked').forEach(cb => {
        totalAdicionales += parseFloat(cb.getAttribute('data-precio')) || 0;
    });

    const totalBruto = precioPlan + totalAdicionales + otros;
    const totalFinal = totalBruto - (totalBruto * descuento / 100);

    document.getElementById('total_pagar').value = totalFinal.toFixed(2);
    actualizarTotalAbonadoLive();
}

function actualizarTotalAbonadoLive(){
    const efectivo        = parseFloat(document.querySelector('[name=pago_efectivo]')?.value) || 0;
    const transferencia   = parseFloat(document.querySelector('[name=pago_transferencia]')?.value) || 0;
    const debito          = parseFloat(document.querySelector('[name=pago_debito]')?.value) || 0;
    const credito         = parseFloat(document.querySelector('[name=pago_credito]')?.value) || 0;
    const cuenta_corriente= parseFloat(document.querySelector('[name=pago_cuenta_corriente]')?.value) || 0;

    const total = efectivo + transferencia + debito + credito + cuenta_corriente;
    const tgt = document.getElementById('total_abonado');
    if (tgt) tgt.innerText = total.toFixed(2);
}

// inputs de pago => live update
document.addEventListener('input', (ev)=>{
    if (ev.target && ev.target.matches('input[type=number], select')) {
        actualizarTotalAbonadoLive();
    }
});

// ---------- Personalizados UI ----------
function actualizarBloquePersonalizados(){
    const chk = document.getElementById('pers_habilitar');
    const bloque = document.getElementById('bloque_personalizados');
    bloque.style.display = chk && chk.checked ? 'block' : 'none';
}

function toggleHora(chk){
    const dow = chk.getAttribute('data-dow');
    const hora = document.querySelector('.pers_hora[data-dow="'+dow+'"]');
    if (hora) {
        hora.disabled = !chk.checked;
        if (!chk.checked) hora.value = '';
    }
}

function sincronizarRangos(){
    const fi = document.getElementById('fecha_inicio');
    const fv = document.getElementById('fecha_vencimiento');
    const pf = document.getElementById('pers_desde');
    const ph = document.getElementById('pers_hasta');
    if (pf && fi) pf.value = fi.value;
    if (ph && fv) ph.value = fv.value;
}

function validarRangoPers(){
    const pf = document.getElementById('pers_desde');
    const ph = document.getElementById('pers_hasta');
    if (pf.value && ph.value && pf.value > ph.value){
        alert('El rango de personalizados es inválido: "Desde" no puede ser mayor que "Hasta".');
        ph.value = pf.value;
    }
}

// Empaqueta el JSON con días/horas y profesor elegido
function buildTurnosJSON(){
    const enabled = document.getElementById('pers_habilitar')?.checked;
    if (!enabled) return '[]';

    const desde = document.getElementById('pers_desde')?.value || '';
    const hasta = document.getElementById('pers_hasta')?.value || '';
    const profesorSel = document.getElementById('profesor_id');
    const profesor_id = profesorSel && profesorSel.value ? parseInt(profesorSel.value) : null;

    const out = [];
    const labels = {0:'Domingo',1:'Lunes',2:'Martes',3:'Miércoles',4:'Jueves',5:'Viernes',6:'Sábado'};

    document.querySelectorAll('.pers_dia_chk:checked').forEach(chk=>{
        const dow  = parseInt(chk.getAttribute('data-dow'));
        const hora = document.querySelector('.pers_hora[data-dow="'+dow+'"]')?.value || '';
        if (hora){
            out.push({
                dia: labels[dow] ?? ('DOW '+dow),
                dow: dow,
                hora: hora,
                desde: desde,
                hasta: hasta,
                profesor_id: profesor_id // <-- clave para aparecer en "Horarios" si filtra por profe
            });
        }
    });

    return JSON.stringify(out);
}

// ---------- Validaciones ----------
function validarPagos() {
    // Usamos el total final calculado
    const total_plan = parseFloat(document.getElementById('total_pagar').value) || 0;

    const efectivo        = parseFloat(document.querySelector('[name=pago_efectivo]').value) || 0;
    const transferencia   = parseFloat(document.querySelector('[name=pago_transferencia]').value) || 0;
    const debito          = parseFloat(document.querySelector('[name=pago_debito]').value) || 0;
    const credito         = parseFloat(document.querySelector('[name=pago_credito]').value) || 0;
    const cuenta_corriente= parseFloat(document.querySelector('[name=pago_cuenta_corriente]').value) || 0;

    const total_pagado = efectivo + transferencia + debito + credito + cuenta_corriente;

    if (total_pagado < total_plan) {
        const diferencia = total_plan - total_pagado;
        return confirm(`⚠️ Se pagaron $${total_pagado.toFixed(2)} de $${total_plan.toFixed(2)}.\nSe registrará una deuda de $${diferencia.toFixed(2)} en cuenta corriente. ¿Desea continuar?`);
    }

    if (total_pagado > total_plan) {
        alert(`❌ El total abonado ($${total_pagado.toFixed(2)}) supera el total a pagar ($${total_plan.toFixed(2)}). Corrija los valores.`);
        return false;
    }

    return true;
}

// Hook principal del submit
function prepararEnvio(){
    calcularTotal(); // asegurar total actualizado
    if (!validarPagos()) return false;

    // empaquetar turnos personalizados
    const json = buildTurnosJSON();
    const tgt = document.getElementById('turnos_json');
    if (tgt) tgt.value = json;

    // Validación rápida: si habilitó personalizados, debe haber al menos un día con hora
    if (document.getElementById('pers_habilitar')?.checked) {
        const arr = JSON.parse(json || '[]');
        if (!arr.length){
            alert('Seleccionaste "Generar turnos fijos" pero no elegiste ningún día/horario.');
            return false;
        }
    }
    return true;
}
</script>
</div>
</body>
</html>
