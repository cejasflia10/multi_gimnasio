<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';
require __DIR__ . '/menu_horizontal.php';

$id = (int)($_GET['id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($id <= 0 || $gimnasio_id <= 0) { http_response_code(400); die('Datos inválidos'); }

$sql = "
  SELECT m.*, c.apellido, c.nombre, c.dni, p.nombre AS plan_nombre
  FROM membresias m
  JOIN clientes c ON c.id = m.cliente_id
  JOIN planes   p ON p.id = m.plan_id
  WHERE m.id = {$id} AND m.gimnasio_id = {$gimnasio_id}
  LIMIT 1
";
$membresia = $conexion->query($sql)->fetch_assoc();
if (!$membresia) { die('Membresía no encontrada'); }

$cliente_id = (int)$membresia['cliente_id'];

/* =========================
   Cargar profesores (opcional)
   ========================= */
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

/* =========================
   Cargar PERSONALIZADOS existentes
   Busca en clientes_fijos o turnos_personalizados
   ========================= */
$pers = [];
$fuente = null;
foreach (['clientes_fijos','turnos_personalizados'] as $cand) {
  $t = $conexion->query("SHOW TABLES LIKE '".$conexion->real_escape_string($cand)."'");
  if ($t && $t->num_rows > 0) { $fuente = $cand; break; }
}
if ($fuente) {
  // columnas detectadas
  $col_prof = null;
  foreach (['profesor_id','profe_id','instructor_id','entrenador_id'] as $cp) {
    $qq = $conexion->query("SHOW COLUMNS FROM `$fuente` LIKE '".$conexion->real_escape_string($cp)."'");
    if ($qq && $qq->num_rows > 0) { $col_prof = $cp; break; }
  }
  $has_gim = $conexion->query("SHOW COLUMNS FROM `$fuente` LIKE 'gimnasio_id'")->num_rows > 0;
  $has_mem = $conexion->query("SHOW COLUMNS FROM `$fuente` LIKE 'membresia_id'")->num_rows > 0;
  $has_cli = $conexion->query("SHOW COLUMNS FROM `$fuente` LIKE 'cliente_id'")->num_rows > 0;

  // armar WHERE
  $w = [];
  if ($has_gim) $w[] = "gimnasio_id = {$gimnasio_id}";
  if ($has_mem) $w[] = "membresia_id = {$id}";
  elseif ($has_cli) $w[] = "cliente_id = {$cliente_id}";
  $where = $w ? ("WHERE ".implode(" AND ", $w)) : "";

  $cols = "dow,hora,desde,hasta";
  if ($col_prof) $cols = "$col_prof,$cols";

  $sqlp = "SELECT $cols FROM `$fuente` $where ORDER BY desde, dow, hora";
  $rp = $conexion->query($sqlp);
  if ($rp) {
    while ($row = $rp->fetch_assoc()) {
      $pers[] = [
        'profesor_id' => $col_prof ? (int)$row[$col_prof] : null,
        'dow'         => isset($row['dow']) ? (int)$row['dow'] : null,
        'hora'        => (string)($row['hora'] ?? ''),
        'desde'       => (string)($row['desde'] ?? ''),
        'hasta'       => (string)($row['hasta'] ?? ''),
      ];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Membresía</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{background:#000;color:gold;font-family:system-ui,Arial,sans-serif}
    .contenedor{max-width:900px;margin:0 auto;padding:16px}
    .bloque{background:#111;border:1px solid #444;border-radius:10px;padding:14px;margin-bottom:14px}
    label{display:block;margin:10px 0 6px}
    input,select{width:100%;padding:10px;border-radius:8px;border:1px solid #555;background:#0c0c0c;color:#ddd}
    .solo-lectura{opacity:.7}
    .ayuda{font-size:12px;color:#9aa}
    .acciones{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
    .btn{padding:10px 14px;border:none;border-radius:8px;font-weight:700;cursor:pointer}
    .btn-guardar{background:#16a34a;color:#fff}
    .btn-volver{background:#334155;color:#fff;text-decoration:none;display:inline-block}
    .grid-dias{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
    .card-dia{border:1px dashed #555;border-radius:10px;padding:8px}
    small.muted{opacity:.7}
  </style>
</head>
<body>
<div class="contenedor">
  <h2>✏️ Editar Membresía</h2>

  <form action="guardar_edicion_membresia.php" method="POST" onsubmit="return prepararEnvio()">
    <input type="hidden" name="id" value="<?= (int)$membresia['id'] ?>">
    <input type="hidden" name="gimnasio_id" value="<?= (int)$gimnasio_id ?>">
    <input type="hidden" name="turnos_json" id="turnos_json">

    <div class="bloque">
      <label>Cliente</label>
      <input class="solo-lectura" type="text" value="<?= htmlspecialchars($membresia['apellido'].', '.$membresia['nombre'].' ('.$membresia['dni'].')') ?>" readonly>

      <label>Plan</label>
      <input class="solo-lectura" type="text" value="<?= htmlspecialchars($membresia['plan_nombre']) ?>" readonly>

      <label>Fecha de inicio</label>
      <input class="solo-lectura" type="date" value="<?= htmlspecialchars($membresia['fecha_inicio']) ?>" readonly>

      <label>Precio total</label>
      <input class="solo-lectura" type="text" value="$<?= number_format((float)$membresia['total'],2,',','.') ?>" readonly>

      <div class="ayuda">Estos campos son informativos.</div>
    </div>

    <div class="bloque">
      <label>Clases disponibles</label>
      <input type="number" name="clases_disponibles" min="0"
             value="<?= (int)($membresia['clases_disponibles'] ?? $membresia['clases_restantes'] ?? 0) ?>" required>

      <label>Fecha de vencimiento</label>
      <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="<?= htmlspecialchars($membresia['fecha_vencimiento']) ?>" required>
      <div class="ayuda">Podés modificar clases y vencimiento. Abajo podés editar los turnos personalizados (si los usás).</div>
    </div>

    <!-- ============================= -->
    <!--    TURNOS PERSONALIZADOS      -->
    <!-- ============================= -->
    <div class="bloque">
      <h3>🗓️ Turnos personalizados</h3>

      <label style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="pers_habilitar" name="pers_habilitar" <?= count($pers) ? 'checked' : '' ?> onclick="toggleBloquePers()">
        Activar/editar turnos personalizados para esta membresía
      </label>

      <div id="bloque_personalizados" style="display:<?= count($pers)?'block':'none' ?>;margin-top:10px;">
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
          <div style="min-width:240px;">
            <label>Profesor (aplica a todos los días):</label>
            <select id="profesor_id">
              <option value="">-- Sin asignar --</option>
              <?php
              // Si todos los existentes comparten el mismo profesor, lo preseleccionamos
              $pre_prof = null;
              if (count($pers)) {
                $todos = array_unique(array_map(fn($x)=> (string)($x['profesor_id'] ?? ''), $pers));
                if (count($todos) === 1) $pre_prof = (int)($todos[0] ?? 0);
              }
              foreach ($profes as $pr):
                $val = (int)$pr['id'];
                $sel = ($pre_prof && $pre_prof === $val) ? 'selected' : '';
              ?>
                <option value="<?= $val ?>" <?= $sel ?>>
                  <?= htmlspecialchars(($pr['apellido'] ?? '').', '.($pr['nombre'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="muted">Si tu vista de horarios filtra por profesor, asignalo aquí.</small>
          </div>
          <div>
            <label>Desde:</label>
            <input type="date" id="pers_desde" value="<?= htmlspecialchars($pers[0]['desde'] ?? ($membresia['fecha_inicio'] ?? date('Y-m-d'))) ?>" onchange="validarRangoPers()">
          </div>
          <div>
            <label>Hasta:</label>
            <input type="date" id="pers_hasta" value="<?= htmlspecialchars($pers[0]['hasta'] ?? ($membresia['fecha_vencimiento'] ?? date('Y-m-d'))) ?>" onchange="validarRangoPers()">
          </div>
        </div>

        <p style="margin:6px 0 10px;">Seleccioná días y horario (uno por día):</p>

        <div class="grid-dias">
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
          // mapear pers existentes por dow
          $byDow = [];
          foreach ($pers as $p) { if ($p['dow'] !== null) $byDow[(int)$p['dow']] = $p; }
          foreach ($dias as $d):
            $dow = (int)$d['dow'];
            $checked = isset($byDow[$dow]) ? 'checked' : '';
            $horaVal = isset($byDow[$dow]) ? htmlspecialchars($byDow[$dow]['hora']) : '';
          ?>
          <div class="card-dia">
            <label style="display:block;">
              <input type="checkbox" class="pers_dia_chk" data-dow="<?= $dow ?>" onchange="toggleHora(this)" <?= $checked ?>>
              <?= htmlspecialchars($d['label']) ?>
            </label>
            <label style="display:block; margin-top:6px; opacity:.8;">Hora:</label>
            <input type="time" class="pers_hora" data-dow="<?= $dow ?>" value="<?= $horaVal ?>" <?= $checked ? '' : 'disabled' ?>>
          </div>
          <?php endforeach; ?>
        </div>

        <div style="margin-top:10px;">
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="borrar_personalizados" id="borrar_personalizados">
            Borrar TODOS los turnos personalizados existentes para esta membresía
          </label>
          <small class="muted">Si marcás esta opción, se eliminarán los registros actuales. Si además dejás activado “turnos personalizados” y elegís nuevos días/horas, se re-crearán con lo que envíes.</small>
        </div>
      </div>
    </div>
    <!-- /TURNOS PERSONALIZADOS -->

    <div class="acciones">
      <button type="submit" class="btn btn-guardar">💾 Guardar cambios</button>
      <a class="btn btn-volver" href="ver_membresias.php">⬅ Volver</a>
    </div>
  </form>
</div>

<script>
const EXISTENTES = <?= json_encode($pers, JSON_UNESCAPED_UNICODE) ?>;

function toggleBloquePers(){
  const chk = document.getElementById('pers_habilitar');
  const b = document.getElementById('bloque_personalizados');
  b.style.display = (chk && chk.checked) ? 'block' : 'none';
}

function toggleHora(chk){
  const dow = chk.getAttribute('data-dow');
  const hora = document.querySelector('.pers_hora[data-dow="'+dow+'"]');
  if (hora) {
    hora.disabled = !chk.checked;
    if (!chk.checked) hora.value = '';
  }
}

function validarRangoPers(){
  const pf = document.getElementById('pers_desde');
  const ph = document.getElementById('pers_hasta');
  if (pf.value && ph.value && pf.value > ph.value){
    alert('El rango de personalizados es inválido: "Desde" no puede ser mayor que "Hasta".');
    ph.value = pf.value;
  }
}

// Empaquetar JSON con días/horas seleccionados + profesor/rango
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
      out.push({ dia: labels[dow] ?? ('DOW '+dow), dow, hora, desde, hasta, profesor_id });
    }
  });

  return JSON.stringify(out);
}

function prepararEnvio(){
  // empaquetar turnos personalizados para backend
  const tgt = document.getElementById('turnos_json');
  if (tgt) tgt.value = buildTurnosJSON();

  // validación mínima: si habilitó personalizados y NO marcó borrar, debe haber algo
  const enabled = document.getElementById('pers_habilitar')?.checked;
  const borrar  = document.getElementById('borrar_personalizados')?.checked;
  if (enabled && !borrar) {
    const arr = JSON.parse(tgt.value || '[]');
    if (!arr.length) {
      if (!confirm('No seleccionaste ningún día/horario en personalizados. ¿Continuar sin cambios en personalizados?')) {
        return false;
      }
    }
  }
  return true;
}
</script>
</body>
</html>
