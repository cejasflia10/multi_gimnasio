<?php
/* ============================================================================
   crear_juez.php — TODO EN UNO (auto-migración + formulario + listado)
   - Sin menús
   - Usa $_SESSION['evento_id_actual'] y $_SESSION['evento_usuario_id']
   - Ajusta columna `clave` (NULL DEFAULT NULL) y maneja `escuela` si existe
   - Evita duplicados por (evento_id, dni)
   ============================================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

/* ---------- Config ---------- */
$DEBUG = true; // poner false en producción

/* ---------- Conexión ---------- */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function s($v){ return trim((string)($v ?? '')); }
function diag(mysqli $db, string $title){
  $dbName = '';
  if ($res = $db->query("SELECT DATABASE() db")) { $dbName = (string)($res->fetch_assoc()['db'] ?? ''); $res->close(); }
  return "<details style='margin-top:8px'><summary>ℹ️ $title</summary><div style='padding-top:6px;color:#cfe8ff'>
    <div><b>Base:</b> <code>".h($dbName)."</code></div>
    <div><b>errno:</b> ".$db->errno."</div>
    <div><b>error:</b> <code>".h($db->error)."</code></div>
  </div></details>";
}
function panic($html){ echo "<div style='max-width:920px;margin:16px auto;padding:12px;border:1px solid #5e2626;background:#2a1414;color:#ffb4b4;border-radius:10px;font-family:system-ui,sans-serif'>{$html}</div>"; exit; }
function gen_pass($len=12){
  $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  $out = '';
  for ($i=0;$i<$len;$i++) { $out .= $pool[random_int(0, strlen($pool)-1)]; }
  return $out;
}

/* ---------- Sesión requerida ---------- */
$evento_id      = (int)($_SESSION['evento_id_actual']  ?? 0);
$organizador_id = (int)($_SESSION['evento_usuario_id'] ?? 0);
if ($evento_id <= 0 || $organizador_id <= 0) {
  panic("❌ Falta sesión: <b>evento_id_actual</b> y/o <b>evento_usuario_id</b>.");
}

/* ---------- Columnas actuales ---------- */
$cols = [];
if ($res = $conexion->query("SHOW COLUMNS FROM `jueces_evento`")) {
  while ($c = $res->fetch_assoc()) { $cols[strtolower($c['Field'])] = $c; }
  $res->close();
} else {
  panic("❌ No puedo leer columnas de <b>jueces_evento</b>." . ($DEBUG ? diag($conexion, 'SHOW COLUMNS falló') : ''));
}

/* ---------- Auto-migración mínima ---------- */
$alters = [];
if (!isset($cols['evento_id']))     $alters[] = "ADD COLUMN `evento_id` INT NOT NULL DEFAULT 0 AFTER `id`";
if (!isset($cols['telefono']))      $alters[] = "ADD COLUMN `telefono` VARCHAR(30) NULL AFTER `dni`";
if (!isset($cols['rol']))           $alters[] = "ADD COLUMN `rol` VARCHAR(80) NULL";
if (!isset($cols['mesa']))          $alters[] = "ADD COLUMN `mesa` VARCHAR(80) NULL";
if (!isset($cols['created_at']))    $alters[] = "ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
if (!isset($cols['updated_at']))    $alters[] = "ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

if ($alters) {
  $sql = "ALTER TABLE `jueces_evento` \n  " . implode(",\n  ", $alters) . ";";
  if (!$conexion->query($sql)) {
    $extra = $DEBUG ? diag($conexion, 'ALTER TABLE para agregar columnas') : '';
    panic("❌ No pude ajustar columnas mínimas de <b>jueces_evento</b>.<br><pre style='white-space:pre-wrap'>".$sql."</pre>".$extra);
  }
  // refrescar definición de columnas
  $cols = [];
  if ($res = $conexion->query("SHOW COLUMNS FROM `jueces_evento`")) {
    while ($c = $res->fetch_assoc()) { $cols[strtolower($c['Field'])] = $c; }
    $res->close();
  }
}

/* ---------- Relajar `clave` si es NOT NULL sin default ---------- */
if (isset($cols['clave'])) {
  $isNotNull = (strtoupper((string)($cols['clave']['Null'] ?? 'NO')) === 'NO');
  $hasDefault = array_key_exists('Default', $cols['clave']) && $cols['clave']['Default'] !== null;
  if ($isNotNull && !$hasDefault) {
    // permitir NULL por defecto
    if (!$conexion->query("ALTER TABLE `jueces_evento` MODIFY `clave` VARCHAR(255) NULL DEFAULT NULL")) {
      if ($DEBUG) {
        echo "<div style='max-width:920px;margin:12px auto;padding:10px;border:1px solid #5e2626;background:#2a1414;color:#ffb4b4;border-radius:10px'>
                ⚠️ No pude relajar la columna <b>clave</b> a NULL. Si falla el INSERT, voy a setear una clave generada.
              ".diag($conexion, 'ALTER clave')."</div>";
      }
    } else {
      // refrescar columna
      if ($res = $conexion->query("SHOW COLUMNS FROM `jueces_evento` LIKE 'clave'")) {
        $cols['clave'] = $res->fetch_assoc(); $res->close();
      }
    }
  }
}

/* ---------- Asegurar UNIQUE(evento_id, dni) ---------- */
$have_uq = false; $single_unique_dni = false;
if ($res = $conexion->query("SHOW INDEX FROM `jueces_evento`")) {
  $by_name = [];
  while ($r = $res->fetch_assoc()) {
    $k = $r['Key_name']; $seq=(int)$r['Seq_in_index']; $col=strtolower($r['Column_name']);
    $uniq = ($r['Non_unique']==0);
    if (!isset($by_name[$k])) $by_name[$k] = ['unique'=>$uniq,'cols'=>[]];
    $by_name[$k]['cols'][$seq] = $col;
  }
  $res->close();
  foreach ($by_name as $k=>$info){ ksort($info['cols']); $list=array_values($info['cols']);
    if ($info['unique'] && $list===['evento_id','dni']) $have_uq = true;
    if ($info['unique'] && $list===['dni']) $single_unique_dni = true;
  }
}
if (!$have_uq) {
  @$conexion->query("CREATE UNIQUE INDEX `uq_evento_dni` ON `jueces_evento` (`evento_id`, `dni`)");
}

/* ---------- Elegir columna usuario ---------- */
$usuario_col = isset($cols['usuario_id']) ? 'usuario_id' : (isset($cols['user_id']) ? 'user_id' : null);

/* ---------- POST: guardar ---------- */
$msg = ''; $err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $dni      = s($_POST['dni'] ?? '');
  $nombre   = s($_POST['nombre'] ?? '');
  $apellido = s($_POST['apellido'] ?? '');
  $telefono = s($_POST['telefono'] ?? '');
  $email    = s($_POST['email'] ?? '');
  $rol      = s($_POST['rol'] ?? '');
  $mesa     = s($_POST['mesa'] ?? '');
  $escuela  = s($_POST['escuela'] ?? '');

  if ($dni === '' || $nombre === '' || $apellido === '') {
    $err = 'DNI, Nombre y Apellido son obligatorios.';
  } else {
    // Duplicado (evento_id, dni)
    $sqlDup = "SELECT id FROM `jueces_evento` WHERE `evento_id`=? AND `dni`=? LIMIT 1";
    if ($st = $conexion->prepare($sqlDup)) {
      $st->bind_param('is', $evento_id, $dni);
      if ($st->execute()) {
        $st->store_result();
        if ($st->num_rows > 0) $err = 'Ya existe un juez con ese DNI en este evento.';
      } else {
        $err = 'Error al ejecutar verificación de duplicado.' . ($DEBUG ? diag($conexion, 'Execute SELECT duplicado') : '');
      }
      $st->close();
    } else {
      $err = 'Error al preparar verificación de duplicado.' . ($DEBUG ? diag($conexion, 'Prepare SELECT duplicado') : '');
    }
  }

  if ($err === '') {
    // construir INSERT dinámico
    $cols_ins = ['evento_id','dni','nombre','apellido'];
    $vals_qm  = ['?','?','?','?'];
    $types    = 'isss';
    $binds    = [$evento_id, $dni, $nombre, $apellido];

    if (isset($cols['telefono'])) { $cols_ins[]='telefono'; $vals_qm[]='?'; $types.='s'; $binds[]=$telefono; }
    if (isset($cols['email']))    { $cols_ins[]='email';    $vals_qm[]='?'; $types.='s'; $binds[]=$email; }
    if (isset($cols['rol']))      { $cols_ins[]='rol';      $vals_qm[]='?'; $types.='s'; $binds[]=$rol; }
    if (isset($cols['mesa']))     { $cols_ins[]='mesa';     $vals_qm[]='?'; $types.='s'; $binds[]=$mesa; }
    if (isset($cols['escuela']))  { $cols_ins[]='escuela';  $vals_qm[]='?'; $types.='s'; $binds[]=$escuela; }
    if ($usuario_col)             { $cols_ins[]=$usuario_col; $vals_qm[]='?'; $types.='i'; $binds[]=$organizador_id; }

    // manejar `clave` si sigue siendo obligatoria
    $needClave = false;
    if (isset($cols['clave'])) {
      $notNull = (strtoupper((string)($cols['clave']['Null'] ?? 'NO')) === 'NO');
      $hasDef  = array_key_exists('Default', $cols['clave']) && $cols['clave']['Default'] !== null;
      if ($notNull && !$hasDef) $needClave = true; // sigue siendo obligatoria
    }
    if ($needClave) {
      $clave_val = gen_pass(12);
      $cols_ins[]='clave'; $vals_qm[]='?'; $types.='s'; $binds[]=$clave_val;
    }

    $sqlIns = "INSERT INTO `jueces_evento` (`".implode('`,`',$cols_ins)."`) VALUES (".implode(',',$vals_qm).")";
    if ($st = $conexion->prepare($sqlIns)) {
      $st->bind_param($types, ...$binds);
      if ($st->execute()) {
        $msg = 'Juez guardado correctamente.';
        $_POST = [];
      } else {
        if ($conexion->errno == 1062) {
          $err = 'Ya existe un juez con ese DNI en este evento.';
        } else {
          $err = 'Error al guardar: '.h($conexion->error) . ($DEBUG ? diag($conexion, 'Execute INSERT') : '');
        }
      }
      $st->close();
    } else {
      $err = 'Error al preparar INSERT.' . ($DEBUG ? "<br><code>".h($sqlIns)."</code>".diag($conexion, 'Prepare INSERT') : '');
    }
  }
}

/* ---------- Listado del evento ---------- */
$jueces = [];
$sqlList = "SELECT id, dni, nombre, apellido"
         . (isset($cols['telefono']) ? ", telefono" : "")
         . (isset($cols['email'])    ? ", email"    : "")
         . (isset($cols['rol'])      ? ", rol"      : "")
         . (isset($cols['mesa'])     ? ", mesa"     : "")
         . (isset($cols['escuela'])  ? ", escuela"  : "")
         . (isset($cols['created_at']) ? ", created_at" : "")
         . " FROM `jueces_evento` WHERE `evento_id` = ? ORDER BY id DESC LIMIT 200";

if ($st = $conexion->prepare($sqlList)) {
  $st->bind_param('i', $evento_id);
  $st->execute();
  $res = $st->get_result();
  if ($res) { $jueces = $res->fetch_all(MYSQLI_ASSOC); }
  $st->close();
} else if ($DEBUG) {
  $msg .= diag($conexion, 'Prepare listado de jueces_evento') . "<div style='color:#cfe8ff'><code>".h($sqlList)."</code></div>";
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear juez</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#0b1115; --panel:#0f1720; --ink:#e6eef4; --muted:#8bb3d9;
      --accent:#0e7ad1; --ok:#0ea768; --err:#d32f2f; --brd:#1f2a33;
    }
    *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--ink)}
    .wrap{max-width:1000px;margin:24px auto;padding:16px}
    .card{background:var(--panel);border:1px solid var(--brd);border-radius:12px;padding:18px}
    h1{margin:0 0 10px 0;font-size:22px}
    .note{color:var(--muted);margin-bottom:12px}
    label{display:block;margin:8px 0 4px;color:#9ecbff}
    input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:var(--ink)}
    .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
    .actions{margin-top:14px;display:flex;gap:12px;flex-wrap:wrap}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:var(--accent);color:#fff;cursor:pointer;text-decoration:none;display:inline-block}
    .btn.sec{background:#1b2836;border-color:#2b3c4f}
    .alert{margin:12px 0;padding:10px;border-radius:10px}
    .ok{background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
    table{width:100%;border-collapse:collapse;margin-top:14px}
    th,td{border-bottom:1px solid #1c2a36;padding:10px;text-align:left;font-size:14px}
    th{color:#9ecbff}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #2b3c4f;background:#13202c;font-size:12px}
    @media (max-width:720px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>➕ Crear juez</h1>
      <div class="note">Evento #<?= (int)$evento_id ?> · Organizador #<?= (int)$organizador_id ?><?= $single_unique_dni ? " · ⚠️ Hay UNIQUE(dni) global: duplicar el mismo DNI en 2 eventos podría fallar." : "" ?></div>

      <?php if ($msg): ?><div class="alert ok">✅ <?= $msg ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert bad">❌ <?= $err ?></div><?php endif; ?>

      <form method="post" action="">
        <div class="grid">
          <div>
            <label>DNI *</label>
            <input name="dni" maxlength="20" required value="<?= h($_POST['dni'] ?? '') ?>">
          </div>
          <div>
            <label>Teléfono</label>
            <input name="telefono" maxlength="30" value="<?= h($_POST['telefono'] ?? '') ?>">
          </div>
          <div>
            <label>Nombre *</label>
            <input name="nombre" maxlength="80" required value="<?= h($_POST['nombre'] ?? '') ?>">
          </div>
          <div>
            <label>Apellido *</label>
            <input name="apellido" maxlength="80" required value="<?= h($_POST['apellido'] ?? '') ?>">
          </div>
          <div>
            <label>Email</label>
            <input type="email" name="email" maxlength="120" value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div>
            <label>Rol</label>
            <input name="rol" maxlength="80" placeholder="Ej: Juez Principal / Lateral / Mesa" value="<?= h($_POST['rol'] ?? '') ?>">
          </div>
          <div>
            <label>Mesa / Tatami</label>
            <input name="mesa" maxlength="80" placeholder="Ej: Mesa 1 / Tatami 2" value="<?= h($_POST['mesa'] ?? '') ?>">
          </div>
          <?php if (isset($cols['escuela'])): ?>
          <div>
            <label>Escuela</label>
            <input name="escuela" maxlength="100" placeholder="Ej: Fight Academy / Panther Gym" value="<?= h($_POST['escuela'] ?? '') ?>">
          </div>
          <?php endif; ?>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Guardar juez</button>
          <a class="btn sec" href="ver_competidores_evento.php">Ver competidores</a>
        </div>
      </form>
    </div>

    <div class="card" style="margin-top:16px">
      <h1>👩‍⚖️ Jueces del evento</h1>
      <?php if (!$jueces): ?>
        <div class="note">No hay jueces cargados todavía.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>DNI</th>
              <th>Nombre</th>
              <th>Teléfono</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Mesa</th>
              <?php if (isset($cols['escuela'])): ?><th>Escuela</th><?php endif; ?>
              <th>Creado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jueces as $j): ?>
              <tr>
                <td class="mono"><?= (int)$j['id'] ?></td>
                <td class="mono"><?= h($j['dni'] ?? '') ?></td>
                <td><?= h(($j['apellido'] ?? '').', '.($j['nombre'] ?? '')) ?></td>
                <td><?= h(($j['telefono'] ?? '') !== '' ? $j['telefono'] : '-') ?></td>
                <td><?= h(($j['email'] ?? '') !== '' ? $j['email'] : '-') ?></td>
                <td><span class="pill"><?= h(($j['rol'] ?? '') !== '' ? $j['rol'] : '-') ?></span></td>
                <td><?= h(($j['mesa'] ?? '') !== '' ? $j['mesa'] : '-') ?></td>
                <?php if (isset($cols['escuela'])): ?><td><?= h(($j['escuela'] ?? '') !== '' ? $j['escuela'] : '-') ?></td><?php endif; ?>
                <td class="mono"><?= h($j['created_at'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
