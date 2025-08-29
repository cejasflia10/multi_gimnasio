<?php
/* ================= DEBUG DURO (desactivar cuando quede OK) ================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    http_response_code(500);
    echo "<pre style='background:#200;color:#fdd;padding:12px;white-space:pre-wrap'>".
         "🔥 FATAL: {$e['message']}\nArchivo: {$e['file']}:{$e['line']}\n</pre>";
  }
});
/* ========================================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/permiso.php';     // 🔒 permisos
guardia_permiso();                         // exige feature 'panel_gimnasio'

// Si querés trazar, probá con ?debug=1
if (isset($_GET['debug'])) { echo "<div style='background:#333;color:#fff;padding:6px'>Pasé guardia_permiso()</div>"; }

// (Opcional) Menú — dejalo al final si te tira errores, o úsalo así:
@include __DIR__ . '/menu_horizontal.php';

/* ---------- Conexión y charset ---------- */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit("<div style='color:#ff6b6b; padding:12px; text-align:center'>❌ No hay conexión a la base de datos.</div>");
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/** Devuelve fecha YYYY-MM-DD válida o NULL (para evitar 0000-00-00) */
function ymd_or_null(?string $s): ?string {
  $s = trim((string)$s);
  if ($s === '' || $s === '0000-00-00') return null;
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
}
/** Copia permisos base del plan hacia el gimnasio */
function seed_permisos_from_plan(mysqli $db, int $plan_id, int $gimnasio_id): void {
  $sql = "
    INSERT INTO gimnasios_permisos (gimnasio_id, feature, enabled)
    SELECT ?, pp.feature, pp.enabled
    FROM plan_permisos pp
    WHERE pp.plan_id = ?
    ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)
  ";
  if ($st = $db->prepare($sql)) {
    $st->bind_param('ii', $gimnasio_id, $plan_id);
    $st->execute();
    $st->close();
  }
}

/* ---------- Cargar planes ---------- */
$planes = $conexion->query("SELECT id, nombre, precio FROM planes_gimnasio");
if (!$planes) { die("Error en consulta de planes: " . $conexion->error); }

$planes_data = [];
while ($p = $planes->fetch_assoc()) {
  $planes_data[(int)$p['id']] = [
    'nombre' => $p['nombre'],
    'precio' => is_null($p['precio']) ? 0.0 : (float)$p['precio'],
  ];
}
$planes->free();

$mensaje = "";

/* ========================================================================== */
/* =========================== HANDLERS (POST/GET) =========================== */
/* ========================================================================== */

/* ===== Alta de gimnasio ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['sync_plan_gym_id'])) {

  // Capturar datos
  $nombre            = trim($_POST["nombre"]            ?? '');
  $direccion         = trim($_POST["direccion"]         ?? '');
  $telefono          = trim($_POST["telefono"]          ?? '');
  $email             = trim($_POST["email"]             ?? '');

  $fecha_inicio      = ymd_or_null($_POST["fecha_inicio"]      ?? '');
  $fecha_vencimiento = ymd_or_null($_POST["fecha_vencimiento"] ?? '');

  $plan_id           = (int)($_POST["plan_id"] ?? 0);

  // Monto (acepta 1.234,56 / 1234,56 / 1234.56). Si es 0, toma precio del plan.
  $monto_raw         = trim($_POST["monto_plan"] ?? '');
  $monto_norm        = str_replace(['.', ','], ['', '.'], $monto_raw);
  $monto_plan        = is_numeric($monto_norm) ? (float)$monto_norm : 0.0;
  if ($monto_plan <= 0 && $plan_id > 0 && isset($planes_data[$plan_id])) {
    $monto_plan = (float)$planes_data[$plan_id]['precio'];
  }

  $forma_pago        = trim($_POST["forma_pago"]        ?? '');

  $usuario           = trim($_POST["usuario"]           ?? '');
  $email_usuario     = trim($_POST["email_usuario"]     ?? $email); // fallback al email del gym
  $clave_texto       = trim($_POST["clave"]             ?? '');

  $alias             = trim($_POST["alias"]             ?? '');
  $cuit              = trim($_POST["cuit"]              ?? '');
  $estado            = trim($_POST["estado"]            ?? '');
  $nota_admin        = trim($_POST["nota_admin"]        ?? '');
  $mensaje_alumno    = trim($_POST["mensaje_alumno"]    ?? '');
  $redes_sociales    = trim($_POST["redes_sociales"]    ?? '');

  if ($usuario === '' || $clave_texto === '' || $email_usuario === '') {
    $mensaje = "<p style='color:#ff6b6b;'>El usuario, email y la contraseña son obligatorios.</p>";
  } elseif ($plan_id <= 0) {
    $mensaje = "<p style='color:#ff6b6b;'>Debés seleccionar un plan válido.</p>";
  } else {
    $clave = password_hash($clave_texto, PASSWORD_DEFAULT);

    // Verificar usuario duplicado ANTES de crear el gimnasio
    $existe_usuario = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
    if (!$existe_usuario) die("Error en prepare existe_usuario: " . $conexion->error);
    $existe_usuario->bind_param("s", $usuario);
    $existe_usuario->execute();
    $res_usuario = $existe_usuario->get_result();
    if ($res_usuario && $res_usuario->num_rows > 0) {
      $existe_usuario->close();
      $mensaje = "<p style='color:#ff6b6b;'>El usuario ya existe. Elegí otro.</p>";
    } else {
      $existe_usuario->close();

      // Insertar gimnasio (fechas pueden ser NULL)
      $stmt = $conexion->prepare("
        INSERT INTO gimnasios 
          (nombre, direccion, telefono, email, fecha_inicio, fecha_vencimiento, monto_plan, forma_pago, plan_id, alias, cuit, estado, nota_admin, mensaje_alumno, redes_sociales) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ");
      if (!$stmt) die("Error en prepare gimnasios: " . $conexion->error);

      $stmt->bind_param(
        "ssssssdsissssss",
        $nombre, $direccion, $telefono, $email,
        $fecha_inicio, $fecha_vencimiento, $monto_plan, $forma_pago, $plan_id,
        $alias, $cuit, $estado, $nota_admin, $mensaje_alumno, $redes_sociales
      );
      if (!$stmt->execute()) die("Error al insertar gimnasio: " . $stmt->error);
      $nuevo_gimnasio_id = $conexion->insert_id;
      $stmt->close();

      // Insertar usuario del gimnasio
      $stmt_user = $conexion->prepare("
        INSERT INTO usuarios (usuario, email, contrasena, rol, gimnasio_id, debe_cambiar_contrasena)
        VALUES (?, ?, ?, 'cliente_gym', ?, 1)
      ");
      if (!$stmt_user) die("Error en prepare usuarios: " . $conexion->error);
      $stmt_user->bind_param("sssi", $usuario, $email_usuario, $clave, $nuevo_gimnasio_id);
      if (!$stmt_user->execute()) die("Error al insertar usuario: " . $stmt_user->error);
      $stmt_user->close();

      // Copiar permisos base del plan al gimnasio
      seed_permisos_from_plan($conexion, $plan_id, $nuevo_gimnasio_id);

      // Refrescar cache de permisos si existe util
      if (function_exists('refresh_permissions')) {
        refresh_permissions($nuevo_gimnasio_id);
      }

      $mensaje = "<p style='color:#22c55e;'>Gimnasio y usuario creados correctamente. Permisos aplicados según el plan.</p>";
    }
  }
}

/* ===== Eliminar gimnasio ===== */
if (isset($_GET['eliminar'])) {
  $id = (int)$_GET['eliminar'];

  // Limpiar permisos asociados (si no hay FK ON DELETE CASCADE)
  $conexion->query("DELETE FROM gimnasios_permisos WHERE gimnasio_id = {$id}");
  // Opcional: limpiar historial de pagos y usuarios del gym (si corresponde a tu lógica)
  // $conexion->query("DELETE FROM gimnasios_pagos WHERE gimnasio_id = {$id}");
  // $conexion->query("DELETE FROM usuarios WHERE gimnasio_id = {$id}");

  if ($conexion->query("DELETE FROM gimnasios WHERE id = {$id}")) {
    $mensaje = "<p style='color:#22c55e;'>Gimnasio eliminado correctamente.</p>";
  } else {
    $mensaje = "<p style='color:#ff6b6b;'>Error al eliminar gimnasio: " . h($conexion->error) . "</p>";
  }
}

/* ===== Sincronizar permisos de un gimnasio con su plan ===== */
if (isset($_POST['sync_plan_gym_id'])) {
  $gymId = (int)$_POST['sync_plan_gym_id'];

  // Obtener plan del gimnasio
  $planId = 0;
  if ($st = $conexion->prepare("SELECT plan_id FROM gimnasios WHERE id = ? LIMIT 1")) {
    $st->bind_param('i', $gymId);
    $st->execute();
    $st->bind_result($planId);
    $st->fetch();
    $st->close();
  }

  if ($planId > 0) {
    // Limpiar overrides
    $conexion->query("DELETE FROM gimnasios_permisos WHERE gimnasio_id = {$gymId}");

    // Copiar desde plan
    $sql = "
      INSERT INTO gimnasios_permisos (gimnasio_id, feature, enabled)
      SELECT ?, pp.feature, pp.enabled
      FROM plan_permisos pp
      WHERE pp.plan_id = ?
      ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)
    ";
    if ($st = $conexion->prepare($sql)) {
      $st->bind_param('ii', $gymId, $planId);
      $st->execute();
      $st->close();
    }

    if (function_exists('refresh_permissions')) {
      refresh_permissions($gymId);
    }

    $mensaje = "<p style='color:#22c55e;'>Permisos del gimnasio #{$gymId} sincronizados con el plan.</p>";
  } else {
    $mensaje = "<p style='color:#ff6b6b;'>No se pudo sincronizar: el gimnasio no tiene plan asignado.</p>";
  }
}

/* ========================================================================== */
/* =============================== LISTADO ================================== */
/* ========================================================================== */

$sql_listado = "
  SELECT g.*, p.nombre AS nombre_plan 
  FROM gimnasios g 
  LEFT JOIN planes_gimnasio p ON g.plan_id = p.id
";
$resultado = $conexion->query($sql_listado);
if (!$resultado) die("Error al listar gimnasios: " . $conexion->error);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gimnasios y Pagos</title>
  <style>
    body{background:#000;color:gold;font-family:Arial,Helvetica,sans-serif;margin:0;padding:16px}
    h2{margin:12px 0}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #999;padding:8px;vertical-align:top}
    th{background:#444;color:#fff}
    .btn{padding:4px 8px;background:#666;color:#fff;text-decoration:none;margin-right:5px;border-radius:3px}
    .btn:hover{background:#999}
    .volver{margin-top:15px;display:inline-block}
    form input, form select, form textarea{display:block;margin:6px 0;padding:8px;width:100%;max-width:520px;color:#111}
    form label{margin-top:8px;color:#bbb}
    .btn-inline{display:inline-block}
    .notice{margin:10px 0}
  </style>
  <script>
    const planes = <?= json_encode($planes_data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    function actualizarPrecio(){
      const sel = document.getElementById('plan_id');
      const precio = (planes?.[sel.value]?.precio) || 0;
      document.getElementById('monto_plan').value = precio;
    }
  </script>
</head>
<body>

<?= $mensaje ? '<div class="notice">'.$mensaje.'</div>' : '' ?>

<h2>🏢 Agregar Gimnasio</h2>

<form method="POST" autocomplete="off">
  <input type="text" name="nombre" placeholder="Nombre del gimnasio" required>
  <input type="text" name="direccion" placeholder="Dirección" required>
  <input type="text" name="telefono" placeholder="Teléfono" required>
  <input type="email" name="email" placeholder="Email del gimnasio" required>

  <input type="email" name="email_usuario" placeholder="Email para usuario" required>

  <input type="text"  name="usuario" placeholder="Usuario de acceso" required>
  <input type="password" name="clave" placeholder="Contraseña" required>

  <label>Fecha de Inicio:</label>
  <input type="date" name="fecha_inicio" required>

  <label>Fecha de Vencimiento del Plan:</label>
  <input type="date" name="fecha_vencimiento" required>

  <input type="number" step="0.01" name="monto_plan" id="monto_plan" placeholder="Monto del Plan" required>

  <select name="forma_pago" required>
    <option value="">Forma de Pago</option>
    <option value="Efectivo">Efectivo</option>
    <option value="Transferencia">Transferencia</option>
    <option value="Débito">Débito</option>
    <option value="Crédito">Crédito</option>
  </select>

  <select name="plan_id" id="plan_id" required onchange="actualizarPrecio()">
    <option value="">Seleccionar Plan</option>
    <?php foreach ($planes_data as $id => $p): ?>
      <option value="<?= (int)$id ?>"><?= h($p['nombre']) ?></option>
    <?php endforeach; ?>
  </select>

  <input type="text" name="alias" placeholder="Alias para transferencia">
  <input type="text" name="cuit" placeholder="CUIT">

  <select name="estado" required>
    <option value="">Estado del gimnasio</option>
    <option value="activo">Activo</option>
    <option value="vencido">Vencido</option>
    <option value="suspendido">Suspendido</option>
  </select>

  <textarea name="nota_admin" placeholder="Nota administrativa interna (solo visible por el admin)"></textarea>
  <textarea name="mensaje_alumno" placeholder="Mensaje visible por los alumnos en su panel"></textarea>
  <textarea name="redes_sociales" placeholder="Redes sociales (Facebook, Instagram, etc)"></textarea>

  <button type="submit" class="btn">💾 Agregar Gimnasio</button>
</form>

<h2>📋 Listado de Gimnasios</h2>
<table>
  <thead>
    <tr>
      <th>Nombre</th>
      <th>Email</th>
      <th>Teléfono</th>
      <th>Inicio</th>
      <th>Vencimiento</th>
      <th>Monto</th>
      <th>Forma de Pago</th>
      <th>Plan</th>
      <th>Estado</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($fila = $resultado->fetch_assoc()) { ?>
      <tr>
        <td><?= h($fila["nombre"]) ?></td>
        <td><?= h($fila["email"]) ?></td>
        <td><?= h($fila["telefono"]) ?></td>
        <td><?= !empty($fila["fecha_inicio"]) ? h($fila["fecha_inicio"]) : '-' ?></td>
        <td><?= (!empty($fila["fecha_vencimiento"]) && $fila["fecha_vencimiento"]!='0000-00-00') ? date('d/m/Y', strtotime($fila["fecha_vencimiento"])) : 'Sin fecha' ?></td>
        <td>$<?= number_format((float)$fila["monto_plan"], 2, ',', '.') ?></td>
        <td><?= h($fila["forma_pago"] ?? 'No especificado') ?></td>
        <td><?= h($fila["nombre_plan"] ?? 'Sin plan') ?></td>
        <td><?= h(ucfirst($fila["estado"])) ?></td>
        <td>
          <a class="btn" href="editar_gimnasio.php?id=<?= (int)$fila['id'] ?>">Editar</a>
          <a class="btn" href="agregar_gimnasio.php?eliminar=<?= (int)$fila['id'] ?>" onclick="return confirm('¿Eliminar este gimnasio?')">Eliminar</a>
          <a class="btn" href="renovar_gimnasio.php?id=<?= (int)$fila['id'] ?>">Renovar</a>

          <!-- ♻️ Sincronizar permisos con el plan -->
          <form method="POST" class="btn-inline" style="display:inline" onsubmit="return confirm('¿Sincronizar permisos con el plan para este gimnasio? Se perderán overrides.')">
            <input type="hidden" name="sync_plan_gym_id" value="<?= (int)$fila['id'] ?>">
            <button class="btn" type="submit">♻️ Sincronizar</button>
          </form>
        </td>
      </tr>
    <?php } ?>
  </tbody>
</table>

<a href="index.php" class="btn volver">⬅️ Volver al Menú</a>

</body>
</html>
