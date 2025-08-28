<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

// ===== Helpers =====
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== Gimnasio actual =====
$gimnasio_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { exit("❌ Acceso denegado."); }

$mensaje = '';

// ===== Cargar planes =====
$planes = $conexion->query("SELECT id, nombre FROM planes_gimnasio");
if (!$planes) { exit('Error cargando planes: '.$conexion->error); }

// ===== Datos actuales del gimnasio =====
$gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = {$gimnasio_id} LIMIT 1")->fetch_assoc();
if (!$gimnasio) { exit("❌ Gimnasio no encontrado."); }
$plan_id_actual = (int)($gimnasio['plan_id'] ?? 0);

// ===== Utilidades permisos =====
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

/**
 * Obtiene la lista de features relevantes para el gimnasio:
 * unión de los del plan y los ya existentes como override en el gimnasio.
 * Devuelve: [ feature => [ 'plan_enabled' => 0/1, 'gym_enabled' => 0/1, 'effective' => 0/1 ] ]
 */
function get_features_for_gym(mysqli $db, int $plan_id, int $gimnasio_id): array {
    $plan_id = (int)$plan_id;
    $gimnasio_id = (int)$gimnasio_id;
    $data = [];
    $sql = "
        SELECT f.feature,
               COALESCE(pp.enabled, 0) AS plan_enabled,
               gp.enabled AS gym_enabled,
               COALESCE(gp.enabled, pp.enabled, 0) AS effective
        FROM (
            SELECT feature FROM plan_permisos WHERE plan_id = {$plan_id}
            UNION
            SELECT feature FROM gimnasios_permisos WHERE gimnasio_id = {$gimnasio_id}
        ) f
        LEFT JOIN plan_permisos pp
               ON pp.plan_id = {$plan_id} AND pp.feature = f.feature
        LEFT JOIN gimnasios_permisos gp
               ON gp.gimnasio_id = {$gimnasio_id} AND gp.feature = f.feature
        ORDER BY f.feature
    ";
    if ($rs = $db->query($sql)) {
        while ($r = $rs->fetch_assoc()) {
            $data[$r['feature']] = [
                'plan_enabled' => (int)$r['plan_enabled'],
                'gym_enabled'  => is_null($r['gym_enabled']) ? null : (int)$r['gym_enabled'],
                'effective'    => (int)$r['effective'],
            ];
        }
        $rs->free();
    }
    return $data;
}

/**
 * Guarda overrides para TODOS los features recibidos en el form.
 * Si un feature no viene tildado => enabled = 0.
 */
function save_gym_perms(mysqli $db, int $gimnasio_id, array $all_features, array $posted_perms): void {
    // Usamos REPLACE para simplificar (o INSERT ... ON DUPLICATE KEY UPDATE)
    $sql = "REPLACE INTO gimnasios_permisos (gimnasio_id, feature, enabled) VALUES (?, ?, ?)";
    $st = $db->prepare($sql);
    foreach ($all_features as $feature => $_) {
        $enabled = isset($posted_perms[$feature]) ? 1 : 0;
        $st->bind_param('isi', $gimnasio_id, $feature, $enabled);
        $st->execute();
    }
    $st->close();
}

/**
 * Limpia overrides y vuelve a copiar desde el plan
 */
function reset_to_plan(mysqli $db, int $gimnasio_id, int $plan_id): void {
    $db->query("DELETE FROM gimnasios_permisos WHERE gimnasio_id = {$gimnasio_id}");
    seed_permisos_from_plan($db, $plan_id, $gimnasio_id);
}

// ===== Guardado perfil / plan / logo / permisos =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) Guardar datos del gimnasio
    if (isset($_POST['save_gym'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $cuit = trim($_POST['cuit'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $clave = trim($_POST['clave'] ?? '');
        $plan_id = (int)($_POST['plan_id'] ?? 0);

        if (!empty($clave)) {
            $clave_hashed = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("
                UPDATE gimnasios
                SET nombre=?, direccion=?, cuit=?, telefono=?, email=?, fecha_vencimiento=?, usuario=?, clave=?, plan_id=?
                WHERE id=?
            ");
            $stmt->bind_param("ssssssssii", $nombre, $direccion, $cuit, $telefono, $email, $fecha_vencimiento, $usuario, $clave_hashed, $plan_id, $gimnasio_id);
        } else {
            $stmt = $conexion->prepare("
                UPDATE gimnasios
                SET nombre=?, direccion=?, cuit=?, telefono=?, email=?, fecha_vencimiento=?, usuario=?, plan_id=?
                WHERE id=?
            ");
            $stmt->bind_param("sssssssii", $nombre, $direccion, $cuit, $telefono, $email, $fecha_vencimiento, $usuario, $plan_id, $gimnasio_id);
        }
        $stmt->execute();
        $stmt->close();

        // Si cambió el plan, opcional: copiar permisos base del plan (sin borrar overrides)
        // Si querés forzar a los del plan, usá el botón "Sincronizar con Plan" que limpia overrides primero.
        if ($plan_id !== $plan_id_actual) {
            seed_permisos_from_plan($conexion, $plan_id, $gimnasio_id);
            $plan_id_actual = $plan_id; // actualizar para recarga de lista
        }

        // Logo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['logo']['tmp_name'];
            if (!is_dir(__DIR__.'/logos')) mkdir(__DIR__.'/logos', 0777, true);
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['logo']['name']));
            $nombre_archivo = 'logos/logo_gimnasio_' . $gimnasio_id . '_' . $safeName;
            if (move_uploaded_file($tmp, __DIR__.'/'.$nombre_archivo)) {
                $stmt = $conexion->prepare("UPDATE gimnasios SET logo = ? WHERE id = ?");
                $stmt->bind_param('si', $nombre_archivo, $gimnasio_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        $mensaje = "✅ Datos actualizados.";
        // Refrescar datos
        $gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = {$gimnasio_id} LIMIT 1")->fetch_assoc();
        $plan_id_actual = (int)($gimnasio['plan_id'] ?? 0);
    }

    // 2) Guardar permisos manuales (overrides)
    if (isset($_POST['save_perms'])) {
        $plan_id = (int)($_POST['plan_id_ref'] ?? $plan_id_actual);
        $features = get_features_for_gym($conexion, $plan_id, $gimnasio_id);
        $all_features = array_keys($features);
        $posted = (array)($_POST['permisos'] ?? []);

        // Normalizamos array de features -> map boolean
        $posted_map = [];
        foreach ($posted as $feat => $on) { $posted_map[$feat] = 1; }

        // Guardar todos (tildado=1, destildado=0)
        save_gym_perms($conexion, $gimnasio_id, array_flip($all_features), $posted_map);
        $mensaje = "✅ Permisos actualizados para el gimnasio.";
    }

    // 3) Resetear a los permisos del plan (borra overrides y copia del plan)
    if (isset($_POST['sync_plan'])) {
        $plan_id = (int)($_POST['plan_id_ref'] ?? $plan_id_actual);
        reset_to_plan($conexion, $gimnasio_id, $plan_id);
        $mensaje = "✅ Permisos sincronizados con el plan (overrides limpiados).";
    }
}

// ===== Features actuales (después de cualquier POST) =====
$features = get_features_for_gym($conexion, $plan_id_actual, $gimnasio_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar Datos del Gimnasio</title>
<style>
  body{background:#000;color:gold;font-family:Arial;padding:30px}
  .card{max-width:900px;margin:0 auto;background:#111;padding:20px;border-radius:12px}
  label{display:block;margin-top:12px}
  input[type="text"],input[type="email"],input[type="file"],input[type="date"],input[type="password"],select,textarea{
    width:100%;padding:10px;margin-top:6px;border-radius:8px;border:1px solid #444;background:#1a1a1a;color:gold
  }
  .row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .btn{margin-top:16px;background:gold;color:#111;padding:10px 16px;border:none;border-radius:8px;font-weight:bold;cursor:pointer}
  .btn.secondary{background:#444;color:#fff}
  .btn.danger{background:#c0392b;color:#fff}
  .mensaje{color:lightgreen;font-weight:bold;margin:16px 0;text-align:center}
  table{border-collapse:collapse;width:100%;margin-top:8px}
  th,td{border:1px solid #444;padding:8px}
  th{background:#222;color:#fff}
  .hint{color:#aaa;font-size:12px}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;margin-left:8px;background:#222;color:#ccc}
</style>
</head>
<body>

<h2 style="text-align:center;">✏️ Editar Datos del Gimnasio</h2>
<?php if ($mensaje): ?><div class="mensaje"><?= $mensaje ?></div><?php endif; ?>

<div class="card">
  <!-- ===== Form datos básicos ===== -->
  <form method="POST" enctype="multipart/form-data">
    <div class="row">
      <div>
        <label>Nombre</label>
        <input type="text" name="nombre" value="<?= h($gimnasio['nombre'] ?? '') ?>" required>

        <label>Dirección</label>
        <input type="text" name="direccion" value="<?= h($gimnasio['direccion'] ?? '') ?>" required>

        <label>CUIT</label>
        <input type="text" name="cuit" value="<?= h($gimnasio['cuit'] ?? '') ?>">

        <label>Teléfono</label>
        <input type="text" name="telefono" value="<?= h($gimnasio['telefono'] ?? '') ?>">
      </div>

      <div>
        <label>Email</label>
        <input type="email" name="email" value="<?= h($gimnasio['email'] ?? '') ?>">

        <label>Fecha de vencimiento</label>
        <input type="date" name="fecha_vencimiento" value="<?= h($gimnasio['fecha_vencimiento'] ?? '') ?>">

        <label>Usuario</label>
        <input type="text" name="usuario" value="<?= h($gimnasio['usuario'] ?? '') ?>">

        <label>Contraseña (solo si desea cambiarla)</label>
        <input type="password" name="clave" placeholder="Nueva clave (opcional)">
      </div>
    </div>

    <label>Plan del gimnasio</label>
    <select name="plan_id" required>
      <option value="">Seleccione un plan</option>
      <?php while ($p = $planes->fetch_assoc()): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)$p['id'] === $plan_id_actual) ? 'selected' : '' ?>>
          <?= h($p['nombre']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label>Logo (opcional)</label>
    <input type="file" name="logo" accept="image/*">
    <?php if (!empty($gimnasio['logo']) && file_exists(__DIR__.'/'.$gimnasio['logo'])): ?>
      <div style="margin-top:8px">
        <img src="<?= h($gimnasio['logo']) ?>" style="max-height:80px;background:#fff;border-radius:6px;padding:4px">
      </div>
    <?php endif; ?>

    <button type="submit" name="save_gym" class="btn">💾 Guardar Datos</button>
    <a href="renovar_gimnasio.php?id=<?= (int)$gimnasio_id ?>" class="btn secondary" style="text-decoration:none;display:inline-block">🔁 Renovar Plan</a>
    <a href="editar_gimnasio.php?eliminar=<?= (int)$gimnasio_id ?>" onclick="return confirm('¿Seguro que deseas eliminar este gimnasio?')" class="btn danger" style="text-decoration:none;display:inline-block">🗑️ Eliminar Gimnasio</a>
    <a href="ver_gimnasios.php" class="btn secondary" style="text-decoration:none;display:inline-block">↩️ Volver</a>
  </form>
</div>

<!-- ===== Panel de permisos por feature ===== -->
<div class="card" style="margin-top:24px">
  <h3>🔒 Permisos por función (afecta acceso y visibilidad en menú)</h3>
  <div class="hint">Si un feature está <b>deshabilitado</b>, se bloqueará el acceso a su página y se <b>ocultará en el menú</b>.</div>

  <form method="POST">
    <input type="hidden" name="plan_id_ref" value="<?= (int)$plan_id_actual ?>">
    <table>
      <thead>
        <tr>
          <th style="width:40%">Feature</th>
          <th style="width:20%">Según Plan</th>
          <th style="width:20%">Override Gimnasio</th>
          <th style="width:20%">Efectivo</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($features)): ?>
          <tr><td colspan="4">No hay features definidos para este plan/gimnasio.</td></tr>
        <?php else: ?>
          <?php foreach ($features as $feat => $info): ?>
            <?php
              $plan_on = (int)$info['plan_enabled'] === 1;
              $gym_override = $info['gym_enabled']; // null => usa plan
              $eff = (int)$info['effective'] === 1;
            ?>
            <tr>
              <td>
                <label for="f_<?= h($feat) ?>" style="display:block;font-weight:bold">
                  <?= h($feat) ?>
                </label>
                <?php if (is_null($gym_override)): ?>
                  <span class="pill">Usando Plan</span>
                <?php else: ?>
                  <span class="pill">Override</span>
                <?php endif; ?>
              </td>
              <td><?= $plan_on ? '✅ On' : '🚫 Off' ?></td>
              <td>
                <!-- Checkbox de override del GYM:
                     - Si está tildado => override a 1
                     - Si NO está => override a 0
                   Nota: guardamos SIEMPRE overrides para todos los features al darle "Guardar permisos"
                -->
                <input type="checkbox"
                       id="f_<?= h($feat) ?>"
                       name="permisos[<?= h($feat) ?>]"
                       value="1"
                       <?= $eff ? 'checked' : '' ?>>
                <div class="hint">Dejar destildado = Off</div>
              </td>
              <td><?= $eff ? '🟢 Habilitado' : '🔴 Bloqueado' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap">
      <button type="submit" name="save_perms" class="btn">💾 Guardar permisos</button>
      <button type="submit" name="sync_plan" class="btn secondary" onclick="return confirm('Esto limpiará overrides y copiará desde el plan. ¿Continuar?')">
        ♻️ Sincronizar con Plan (limpiar overrides)
      </button>
    </div>
  </form>
</div>

</body>
</html>
