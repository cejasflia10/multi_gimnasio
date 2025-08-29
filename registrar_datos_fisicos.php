<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
@date_default_timezone_set('America/Argentina/San_Luis');

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ok($s){ return "<div style='margin:10px 0;padding:10px;border-radius:8px;border:1px solid #1e7f56;background:#052b18;color:#b7f7cf'>✅ ".h($s)."</div>"; }
function err($s){ return "<div style='margin:10px 0;padding:10px;border-radius:8px;border:1px solid #7f1e1e;background:#2b0505;color:#ffb4b4'>❌ ".h($s)."</div>"; }
function toFloat($s){ $s=str_replace(['.',','],['','.'],$s); return is_numeric($s)?(float)$s:null; }
function bmi($pesoKg, $alturaCm){ if ($pesoKg<=0 || $alturaCm<=0) return null; $m=$alturaCm/100.0; if($m<=0) return null; return round($pesoKg/($m*$m),2); }

/* ============ Resolver identidad desde la sesión (robusto) ============ */
function resolver_identidad(): array {
  // rol
  $rol_raw = strtolower((string)(
      $_SESSION['rol']
      ?? $_SESSION['perfil']
      ?? $_SESSION['tipo']
      ?? ''
  ));
  // normalizaciones comunes
  if (in_array($rol_raw, ['admin','administrator','superadmin'], true)) $rol = 'admin';
  elseif (in_array($rol_raw, ['profesor','profe','prof','teacher'], true) || !empty($_SESSION['profesor_id'])) $rol = 'profesor';
  else $rol = 'cliente';

  // cliente_id (múltiples nombres frecuentes)
  $cliente_id = (int)(
      $_SESSION['cliente_id']
      ?? $_SESSION['id_cliente']
      ?? $_SESSION['id']
      ?? 0
  );

  // gimnasio (opcional)
  $gym_id = (int)(
      $_SESSION['gimnasio_id']
      ?? $_SESSION['gym_id']
      ?? 0
  );

  return [$rol, $cliente_id, $gym_id];
}

/* ================= Debug opcional ================= */
if (!empty($_GET['debug_sesion'])) {
  header('Content-Type: text/html; charset=utf-8');
  [$rol, $cliente_id, $gym_id] = resolver_identidad();
  echo "<pre style='background:#111;color:#0f0;padding:12px;border-radius:8px'>";
  echo "DEBUG SESIÓN\n\n";
  echo "rol detectado: {$rol}\n";
  echo "cliente_id:    {$cliente_id}\n";
  echo "gimnasio_id:   {$gym_id}\n\n";
  echo "Dump \$_SESSION:\n";
  print_r($_SESSION);
  echo "</pre>";
  exit;
}

/* ================= Variables base ================= */
[$rol, $cliente_id_sesion, $gym_id] = resolver_identidad();
$is_prof = in_array($rol, ['profesor','admin'], true);

$mensaje  = '';

/* ================= Resolver cliente objetivo =================
   - Profesor/Admin: usa ?cliente=ID o buscador por DNI.
   - Cliente: usa su propio cliente_id de sesión.
---------------------------------------------------------------- */
$target_cliente_id = 0;
if ($is_prof) {
  if (isset($_GET['cliente'])) {
    $target_cliente_id = max(0, (int)$_GET['cliente']);
  } elseif (isset($_POST['buscar_dni'])) {
    $dni = trim($_POST['buscar_dni']);
    if ($dni !== '') {
      if ($gym_id > 0) {
        $st = $conexion->prepare("SELECT id FROM clientes WHERE dni=? AND gimnasio_id=? LIMIT 1");
        $st->bind_param('si', $dni, $gym_id);
      } else {
        $st = $conexion->prepare("SELECT id FROM clientes WHERE dni=? LIMIT 1");
        $st->bind_param('s', $dni);
      }
      $st->execute();
      $rs = $st->get_result()->fetch_assoc();
      $st->close();
      if ($rs) {
        header("Location: ".$_SERVER['PHP_SELF']."?cliente=".$rs['id']);
        exit;
      } else {
        $mensaje .= err("No se encontró cliente con DNI {$dni}".($gym_id? " en este gimnasio.":"."));
      }
    }
  }
} else {
  // Cliente final
  $target_cliente_id = (int)$cliente_id_sesion;
}

/* ================= Vistas seguras según lo resuelto ================= */
if ($target_cliente_id <= 0) {
  if ($is_prof) {
    // Vista de búsqueda para profesor/admin (nunca acceso denegado)
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Datos físicos - Buscar cliente</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body{background:#000;color:gold;font-family:Arial;margin:0;padding:24px}
        .card{max-width:600px;margin:24px auto;background:#111;padding:18px;border-radius:12px;border:1px solid #222}
        input,button{padding:10px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:gold}
        button{cursor:pointer}
        .row{display:grid;grid-template-columns:1fr auto;gap:8px}
        @media (max-width:700px){ .row{grid-template-columns:1fr} }
      </style>
    </head>
    <body>
      <div class="card">
        <h2>🔎 Buscar cliente por DNI</h2>
        <?= $mensaje ?>
        <form method="POST">
          <div class="row">
            <input type="text" name="buscar_dni" inputmode="numeric" placeholder="DNI del cliente..." autofocus>
            <button type="submit">Buscar</button>
          </div>
        </form>
        <div style="margin-top:10px;color:#aaa;font-size:12px">
          ¿Te aparece “Acceso denegado” siendo profe? Revisá que en tu login se setee <code>$_SESSION['rol']='profesor'</code>
          o <code>$_SESSION['profesor_id']</code>. También podés usar <code>?debug_sesion=1</code>.
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
  } else {
    // Cliente sin cliente_id en sesión
    echo err('Acceso denegado. Falta identificar al cliente en la sesión. Probá iniciar sesión nuevamente o pedí a recepción que te reingrese.');
    echo "<div style='color:#aaa;font-size:12px;margin-top:8px'>Tip: podés abrir <code>".h($_SERVER['PHP_SELF'])."?debug_sesion=1</code> para ver qué falta en la sesión.</div>";
    exit;
  }
}

/* ================= Cargar datos del cliente ================= */
$st = $conexion->prepare("SELECT id, apellido, nombre, dni FROM clientes WHERE id=? LIMIT 1");
$st->bind_param('i', $target_cliente_id);
$st->execute();
$cliente = $st->get_result()->fetch_assoc();
$st->close();
if (!$cliente) { echo err('Cliente no encontrado.'); exit; }

/* ================= Historial ================= */
$st = $conexion->prepare("SELECT * FROM datos_fisicos WHERE cliente_id=? ORDER BY fecha DESC, id DESC");
$st->bind_param('i', $target_cliente_id);
$st->execute();
$rs = $st->get_result();
$historial = [];
while ($row = $rs->fetch_assoc()) { $historial[] = $row; }
$st->close();

$tiene_registros = count($historial) > 0;
$ultimo = $tiene_registros ? $historial[0] : null;

/* ================= Permisos por rol ================= */
$puede_crear_cliente = (!$is_prof && !$tiene_registros); // cliente: solo si aún no cargó
$puede_crear_prof    = ($is_prof);                       // prof/admin: siempre
$puede_editar_prof   = ($is_prof);                       // prof/admin: sí

/* ================= Modo edición ================= */
$edit_row = null;
if ($puede_editar_prof && isset($_GET['edit'])) {
  $edit_id = (int)$_GET['edit'];
  if ($edit_id > 0) {
    $st = $conexion->prepare("SELECT * FROM datos_fisicos WHERE id=? AND cliente_id=? LIMIT 1");
    $st->bind_param('ii', $edit_id, $target_cliente_id);
    $st->execute();
    $edit_row = $st->get_result()->fetch_assoc();
    $st->close();
  }
}

/* ================= Guardar (crear/actualizar) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act'])) {
  $act = $_POST['act'];

  $peso        = toFloat($_POST['peso']           ?? '');
  $altura      = toFloat($_POST['altura']         ?? '');
  $remera      = trim($_POST['talle_remera']      ?? '');
  $pantalon    = trim($_POST['talle_pantalon']    ?? '');
  $calzado     = trim($_POST['talle_calzado']     ?? '');
  $patologias  = isset($_POST['patologias']) ? implode(", ", array_map('trim', (array)$_POST['patologias'])) : '';
  $tipo_diab   = trim($_POST['tipo_diabetes']     ?? '');
  $medic       = trim($_POST['medicaciones']      ?? '');
  $obs         = trim($_POST['observaciones']     ?? '');
  $fecha_in    = date('Y-m-d');

  if ($act === 'create') {
    if (($is_prof && $puede_crear_prof) || (!$is_prof && $puede_crear_cliente)) {
      $st = $conexion->prepare("
        INSERT INTO datos_fisicos
          (cliente_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, patologias, tipo_diabetes, medicaciones, observaciones)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
      ");
      $st->bind_param('issssssssss',
        $target_cliente_id, $fecha_in, $peso, $altura, $remera, $pantalon, $calzado, $patologias, $tipo_diab, $medic, $obs
      );
      if ($st->execute()) {
        $conexion->query("UPDATE clientes SET datos_completos=1 WHERE id={$target_cliente_id}");
        header("Location: ".$_SERVER['PHP_SELF']."?cliente=".$target_cliente_id);
        exit;
      } else { $mensaje .= err('Error al guardar los datos.'); }
      $st->close();
    } else {
      $mensaje .= err('No tenés permiso para crear un registro.');
    }
  }

  if ($act === 'update' && $puede_editar_prof) {
    $edit_id = (int)($_POST['id'] ?? 0);
    if ($edit_id > 0) {
      $st = $conexion->prepare("SELECT 1 FROM datos_fisicos WHERE id=? AND cliente_id=?");
      $st->bind_param('ii', $edit_id, $target_cliente_id);
      $st->execute();
      $okRow = $st->get_result()->fetch_row();
      $st->close();
      if ($okRow) {
        $st = $conexion->prepare("
          UPDATE datos_fisicos
          SET peso=?, altura=?, talle_remera=?, talle_pantalon=?, talle_calzado=?, patologias=?, tipo_diabetes=?, medicaciones=?, observaciones=?
          WHERE id=? AND cliente_id=?
        ");
        $st->bind_param('ssssssssii',
          $peso, $altura, $remera, $pantalon, $calzado, $patologias, $tipo_diab, $medic, $obs, $edit_id, $target_cliente_id
        );
        if ($st->execute()) {
          header("Location: ".$_SERVER['PHP_SELF']."?cliente=".$target_cliente_id);
          exit;
        } else { $mensaje .= err('Error al actualizar el registro.'); }
        $st->close();
      } else {
        $mensaje .= err('Registro inválido para este cliente.');
      }
    } else { $mensaje .= err('ID de edición inválido.'); }
  }

  // recargar historial si hubo error y no redirigimos
  $st = $conexion->prepare("SELECT * FROM datos_fisicos WHERE cliente_id=? ORDER BY fecha DESC, id DESC");
  $st->bind_param('i', $target_cliente_id);
  $st->execute();
  $rs = $st->get_result();
  $historial = [];
  while ($row = $rs->fetch_assoc()) { $historial[] = $row; }
  $st->close();
  $tiene_registros = count($historial) > 0;
  $ultimo = $tiene_registros ? $historial[0] : null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Datos Físicos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    :root{ --bg:#000; --fg:gold; --card:#101114; --line:#262a33; --muted:#a0a7b4; }
    body{background:var(--bg);color:var(--fg);font-family:Arial;margin:0}
    .wrap{max-width:1100px;margin:0 auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin:12px 0}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .row{display:grid;grid-template-columns:1fr;gap:8px}
    input,select,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid var(--line);background:#0d0f14;color:var(--fg)}
    textarea{min-height:80px}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid var(--line);padding:8px;text-align:left}
    th{background:#141824}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid var(--line);background:#1a1f2b;color:#fff;text-decoration:none;cursor:pointer}
    .btn:hover{background:#21293a}
    .muted{color:var(--muted);font-size:12px}
    @media (max-width:900px){
      .grid{grid-template-columns:1fr}
      .grid-3{grid-template-columns:1fr}
    }
  </style>
  <script>
    function toggleDiabetes(){
      const box = document.getElementById('chk-diabetes');
      const panel = document.getElementById('tipo-diabetes-panel');
      if (box && panel) panel.style.display = box.checked ? 'block' : 'none';
    }
  </script>
</head>
<body>
<div class="wrap">

  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <h2 style="margin:0">👤 <?= h(($cliente['apellido']??'').' '.($cliente['nombre']??'')) ?></h2>
        <div class="muted">DNI: <?= h($cliente['dni']??'') ?> · ID: <?= (int)$cliente['id'] ?></div>
      </div>
      <?php if ($is_prof): ?>
        <form method="POST" style="display:flex;gap:8px;align-items:center">
          <input type="text" name="buscar_dni" placeholder="Buscar otro DNI..." inputmode="numeric">
          <button class="btn" type="submit">Buscar</button>
        </form>
      <?php endif; ?>
    </div>
    <?= $mensaje ?>
  </div>

  <?php if (!empty($historial)): ?>
    <div class="card">
      <h3 style="margin-top:0">📌 Último registro</h3>
      <div class="grid">
        <div>
          <div>Peso: <b><?= h($ultimo['peso']) ?> kg</b></div>
          <div>Altura: <b><?= h($ultimo['altura']) ?> cm</b></div>
          <div>IMC: <b><?php $imc=bmi((float)$ultimo['peso'], (float)$ultimo['altura']); echo $imc? h($imc):'—'; ?></b></div>
        </div>
        <div>
          <div>Remera: <b><?= h($ultimo['talle_remera']) ?></b></div>
          <div>Pantalón: <b><?= h($ultimo['talle_pantalon']) ?></b></div>
          <div>Calzado: <b><?= h($ultimo['talle_calzado']) ?></b></div>
        </div>
      </div>
      <div class="grid">
        <div>
          <div>Patologías: <b><?= h($ultimo['patologias'] ?: '—') ?></b></div>
          <div>Tipo diabetes: <b><?= h($ultimo['tipo_diabetes'] ?: '—') ?></b></div>
        </div>
        <div>
          <div>Medicaciones: <div class="muted"><?= nl2br(h($ultimo['medicaciones'] ?: '—')) ?></div></div>
          <div>Observaciones: <div class="muted"><?= nl2br(h($ultimo['observaciones'] ?: '—')) ?></div></div>
        </div>
      </div>
      <div class="muted" style="margin-top:6px">Fecha: <?= h($ultimo['fecha']) ?></div>
    </div>
  <?php endif; ?>

  <!-- Form CREAR / EDITAR -->
  <div class="card">
    <?php if ($is_prof && !empty($edit_row)): ?>
      <h3 style="margin-top:0">✏️ Editar registro (ID #<?= (int)$edit_row['id'] ?>)</h3>
      <form method="POST" class="row">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">

        <div class="grid-3">
          <div><label>Peso (kg)</label><input type="text" name="peso" value="<?= h($edit_row['peso']) ?>" required></div>
          <div><label>Altura (cm)</label><input type="text" name="altura" value="<?= h($edit_row['altura']) ?>" required></div>
          <div><label>Talle Remera</label><input type="text" name="talle_remera" value="<?= h($edit_row['talle_remera']) ?>"></div>
        </div>

        <div class="grid-3">
          <div><label>Talle Pantalón</label><input type="text" name="talle_pantalon" value="<?= h($edit_row['talle_pantalon']) ?>"></div>
          <div><label>Talle Calzado</label><input type="text" name="talle_calzado" value="<?= h($edit_row['talle_calzado']) ?>"></div>
          <div><label class="muted">Patologías (marcá abajo)</label></div>
        </div>

        <?php $tieneDiab = stripos((string)$edit_row['patologias'],'diabetes')!==false; ?>
        <div>
          <label>Patologías</label>
          <label><input id="chk-diabetes" type="checkbox" name="patologias[]" value="Diabetes" <?= $tieneDiab?'checked':''; ?> onclick="toggleDiabetes()"> Diabetes</label>
          <label><input type="checkbox" name="patologias[]" value="Hipertensión" <?= (stripos((string)$edit_row['patologias'],'hipertensión')!==false)?'checked':''; ?>> Hipertensión</label>
          <label><input type="checkbox" name="patologias[]" value="Asma" <?= (stripos((string)$edit_row['patologias'],'asma')!==false)?'checked':''; ?>> Asma</label>
          <label><input type="checkbox" name="patologias[]" value="Otra" <?= (stripos((string)$edit_row['patologias'],'otra')!==false)?'checked':''; ?>> Otra</label>

          <div id="tipo-diabetes-panel" style="margin-top:8px; display: <?= $tieneDiab ? 'block':'none' ?>;">
            <label>Tipo de Diabetes</label>
            <select name="tipo_diabetes">
              <option value="">-- Seleccionar --</option>
              <?php foreach(['Tipo 1','Tipo 2','Gestacional'] as $op): ?>
                <option <?= ($edit_row['tipo_diabetes']===$op)?'selected':''; ?>><?= h($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid">
          <div><label>Medicaciones</label><textarea name="medicaciones"><?= h($edit_row['medicaciones']) ?></textarea></div>
          <div><label>Observaciones</label><textarea name="observaciones"><?= h($edit_row['observaciones']) ?></textarea></div>
        </div>

        <div>
          <button class="btn" type="submit">💾 Guardar cambios</button>
          <a class="btn" href="<?= h($_SERVER['PHP_SELF'].'?cliente='.$target_cliente_id) ?>">Cancelar</a>
        </div>
      </form>
    <?php else: ?>
      <h3 style="margin-top:0">📝 Nuevo registro</h3>
      <?php if (($is_prof && $puede_crear_prof) || (!$is_prof && $puede_crear_cliente)): ?>
        <form method="POST" class="row">
          <input type="hidden" name="act" value="create">
          <div class="grid-3">
            <div><label>Peso (kg)</label><input type="text" name="peso" required></div>
            <div><label>Altura (cm)</label><input type="text" name="altura" required></div>
            <div><label>Talle Remera</label><input type="text" name="talle_remera"></div>
          </div>
          <div class="grid-3">
            <div><label>Talle Pantalón</label><input type="text" name="talle_pantalon"></div>
            <div><label>Talle Calzado</label><input type="text" name="talle_calzado"></div>
            <div><label class="muted">Patologías (marcá abajo)</label></div>
          </div>
          <div>
            <label>Patologías</label>
            <label><input id="chk-diabetes" type="checkbox" name="patologias[]" value="Diabetes" onclick="toggleDiabetes()"> Diabetes</label>
            <label><input type="checkbox" name="patologias[]" value="Hipertensión"> Hipertensión</label>
            <label><input type="checkbox" name="patologias[]" value="Asma"> Asma</label>
            <label><input type="checkbox" name="patologias[]" value="Otra"> Otra</label>
            <div id="tipo-diabetes-panel" style="display:none; margin-top:8px">
              <label>Tipo de Diabetes</label>
              <select name="tipo_diabetes">
                <option value="">-- Seleccionar --</option>
                <option>Tipo 1</option><option>Tipo 2</option><option>Gestacional</option>
              </select>
            </div>
          </div>
          <div class="grid">
            <div><label>Medicaciones</label><textarea name="medicaciones"></textarea></div>
            <div><label>Observaciones</label><textarea name="observaciones"></textarea></div>
          </div>
          <button class="btn" type="submit">💾 Guardar</button>
        </form>
      <?php else: ?>
        <div class="muted">Este cliente ya cargó sus datos. Para actualizar, un profesor puede editar desde el historial.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Evolución / Historial -->
  <div class="card">
    <h3 style="margin-top:0">📈 Evolución / Historial</h3>
    <?php if (empty($historial)): ?>
      <div class="muted">Sin registros aún.</div>
    <?php else: ?>
      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>Fecha</th><th>Peso (kg)</th><th>Altura (cm)</th><th>IMC</th>
              <th>Remera</th><th>Pantalón</th><th>Calzado</th>
              <th>Patologías</th><th>Tipo Diabetes</th>
              <th>Medicaciones</th><th>Observaciones</th>
              <?php if ($is_prof): ?><th>Acciones</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historial as $row): ?>
              <tr>
                <td><?= h($row['fecha']) ?></td>
                <td><?= h($row['peso']) ?></td>
                <td><?= h($row['altura']) ?></td>
                <td><?php $imc=bmi((float)$row['peso'], (float)$row['altura']); echo $imc? h($imc):'—'; ?></td>
                <td><?= h($row['talle_remera']) ?></td>
                <td><?= h($row['talle_pantalon']) ?></td>
                <td><?= h($row['talle_calzado']) ?></td>
                <td><?= h($row['patologias']) ?></td>
                <td><?= h($row['tipo_diabetes']) ?></td>
                <td><?= nl2br(h($row['medicaciones'])) ?></td>
                <td><?= nl2br(h($row['observaciones'])) ?></td>
                <?php if ($is_prof): ?>
                  <td><a class="btn" href="<?= h($_SERVER['PHP_SELF'].'?cliente='.$target_cliente_id.'&edit='.$row['id']) ?>">Editar</a></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
