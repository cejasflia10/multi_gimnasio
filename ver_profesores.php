<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) { http_response_code(403); exit('Acceso denegado.'); }

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$es_admin    = (($_SESSION['rol'] ?? '') === 'admin');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* CSRF */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

$mensaje = '';
$tipo_msg = 'ok'; // ok | error | warn

/* =========================
   🗑️ ELIMINAR (ahora por POST + CSRF)
   ========================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__a'] ?? '')==='eliminar') {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    $mensaje  = '❌ CSRF inválido.';
    $tipo_msg = 'error';
  } else {
    $profesor_id = (int)($_POST['profesor_id'] ?? 0);
    if ($profesor_id > 0) {
      $conexion->begin_transaction();
      try {
        // Tablas que podrían tener profesor_id
        $tablas = [
          "rfid_registros","rfid_profesores","rfid_profesores_registros","registro_profesores",
          "registros_profesores","registro_asistencias_profesores","profesores_turnos","turnos_profesor",
          "pagos_profesor","controles_fisicos","planes_entrenamiento","progreso_tecnico",
          "evaluaciones_fisicas","fotos_evolucion","graduaciones","competencias","archivos_profesor",
          "mensajes_chat","asistencias_profesor","alumnos_profesor","alumnos_asignados_profesor",
          "escaneos_profesor","progreso_alumno","membresias","progreso_fisico","tarifas_profesor",
          "datos_fisicos","asistencias_profesores"
        ];

        // Eliminar dependencias si existen esas tablas
        foreach ($tablas as $tabla) {
          $chk = $conexion->query("SHOW TABLES LIKE '".$conexion->real_escape_string($tabla)."'");
          if ($chk && $chk->num_rows > 0) {
            // Intentamos borrar filtrando por profesor_id; si la tabla no lo tiene, fallará silencioso
            @$conexion->query("DELETE FROM `$tabla` WHERE profesor_id = {$profesor_id}");
          }
        }

        // Reservas por turnos del profesor
        $conexion->query("
          DELETE FROM reservas
          WHERE turno_id IN (SELECT id FROM turnos WHERE profesor_id = {$profesor_id})
        ");

        // Turnos del profesor
        $conexion->query("DELETE FROM turnos WHERE profesor_id = {$profesor_id}");

        // Finalmente el profesor (limitado a su gym si corresponde)
        $conexion->query("DELETE FROM profesores WHERE id = {$profesor_id} ".($es_admin?'':"AND gimnasio_id = {$gimnasio_id}"));

        $conexion->commit();
        $mensaje  = "✅ Profesor y todos sus registros fueron eliminados.";
        $tipo_msg = 'ok';
      } catch (Throwable $e) {
        $conexion->rollback();
        $mensaje  = "❌ Error al eliminar: ".h($e->getMessage());
        $tipo_msg = 'error';
      }
    }
  }
}

/* =========================
   🟢 AGREGAR PROFESOR
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__a'] ?? '')==='crear') {
  $apellido = trim($_POST['apellido'] ?? '');
  $nombre   = trim($_POST['nombre'] ?? '');
  $dni      = trim($_POST['dni'] ?? '');
  $telefono = trim($_POST['telefono'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $g_sel    = $es_admin ? (int)($_POST['gimnasio_id'] ?? 0) : $gimnasio_id;

  if (!$apellido || !$nombre || !$dni || ($es_admin && $g_sel<=0)) {
    $mensaje  = "⚠️ Completá todos los campos obligatorios.";
    $tipo_msg = 'warn';
  } else {
    $st = $conexion->prepare("INSERT INTO profesores (apellido, nombre, dni, telefono, email, gimnasio_id) VALUES (?,?,?,?,?,?)");
    if ($st) {
      $st->bind_param("sssssi", $apellido, $nombre, $dni, $telefono, $email, $g_sel);
      if ($st->execute()) {
        $mensaje  = "✅ Profesor registrado correctamente.";
        $tipo_msg = 'ok';
      } else {
        // Si hay índice único en DNI por gym
        if ($conexion->errno == 1062) {
          $mensaje = "❌ El DNI ya está registrado en este gimnasio.";
        } else {
          $mensaje = "❌ Error al registrar: ".$st->error;
        }
        $tipo_msg = 'error';
      }
      $st->close();
    } else {
      $mensaje  = "❌ Error preparando consulta: ".$conexion->error;
      $tipo_msg = 'error';
    }
  }
}

/* =========================
   🔹 LISTADO
   ========================= */
if ($es_admin) {
  $sql = "
    SELECT p.id, p.apellido, p.nombre, p.dni, g.nombre AS gimnasio
    FROM profesores p
    JOIN gimnasios g ON g.id = p.gimnasio_id
    ORDER BY p.apellido, p.nombre
  ";
} else {
  $sql = "
    SELECT id, apellido, nombre, dni
    FROM profesores
    WHERE gimnasio_id = {$gimnasio_id}
    ORDER BY apellido, nombre
  ";
}
$profesores = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestionar Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tema unificado -->
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    /* ===== Integración con el panel unificado ===== */
    .wrap{ max-width: 1100px; margin:24px auto; padding:0 16px 40px; }
    .page-title{
      margin:0 0 14px; font-weight:900; letter-spacing:.5px;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }
    .card{ background:var(--card); border:1px solid var(--stroke); border-radius:18px; padding:16px; box-shadow:var(--shadow); margin-bottom:16px; }

    /* Form layout */
    form .row{ display:grid; grid-template-columns:1fr; gap:12px; }
    @media(min-width:720px){ form .row.two{ grid-template-columns: 1fr 1fr; } }

    label{ display:block; margin-bottom:6px; font-weight:700; color:#64748b; font-size:13px; }
    input[type="text"], input[type="email"], select{
      width:100%; padding:12px; border-radius:12px;
      border:1px solid var(--stroke); background:linear-gradient(180deg,#fff,#f7fafc);
      color:var(--ink); font-size:15px; outline:none;
    }
    input:focus, select:focus{ outline:3px solid rgba(245,158,11,.15); box-shadow:0 0 0 3px rgba(245,158,11,.08); }

    .actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
    .btn{
      padding:10px 14px; border-radius:12px; border:1px solid var(--stroke);
      background:linear-gradient(180deg,#fff,#f7fafc); color:var(--ink);
      font-weight:800; cursor:pointer; text-decoration:none; min-width:160px; text-align:center;
    }
    .btn.danger{ background:#fee2e2; border-color:#fecaca; color:#991b1b; }
    .btn.small{ padding:8px 12px; min-width:auto; }

    /* Mensajes */
    .msg{ margin:12px 0; padding:12px; border-radius:14px; border:1px solid; }
    .msg.ok{ background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
    .msg.warn{ background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
    .msg.error{ background:#fef2f2; border-color:#fecaca; color:#991b1b; }

    /* Tablas unificadas */
    .table-wrap{ width:100%; overflow:auto; -webkit-overflow-scrolling:touch; }
    table.tabla{ width:100%; border-collapse:collapse; background:#fff; }
    .tabla thead th{ position:sticky; top:0; z-index:1; background:#f8fafc; }
    .tabla th, .tabla td{ padding:12px; border-bottom:1px solid var(--stroke); text-align:left; white-space:nowrap; }
    .tabla tr:hover{ background:#f9fafb; }
    .td-actions{ white-space:nowrap; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1 class="page-title">👨‍🏫 Gestionar Profesores</h1>

    <?php if ($mensaje): ?>
      <div class="msg <?= h($tipo_msg) ?>"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <!-- Formulario alta -->
    <div class="card">
      <h3 style="margin-top:0">➕ Registrar Profesor</h3>
      <form method="POST" autocomplete="off" novalidate>
        <input type="hidden" name="__a" value="crear">
        <div class="row two">
          <div>
            <label for="apellido">Apellido *</label>
            <input type="text" id="apellido" name="apellido" required>
          </div>
          <div>
            <label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" required>
          </div>
        </div>

        <div class="row two">
          <div>
            <label for="dni">DNI *</label>
            <input type="text" id="dni" name="dni" inputmode="numeric" pattern="\d+" placeholder="Solo números" required>
          </div>
          <div>
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono">
          </div>
        </div>

        <div class="row">
          <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="correo@ejemplo.com">
          </div>
        </div>

        <?php if ($es_admin): ?>
          <div class="row">
            <div>
              <label for="gimnasio_id">Gimnasio *</label>
              <select id="gimnasio_id" name="gimnasio_id" required>
                <option value="">Seleccione gimnasio</option>
                <?php
                  $gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios ORDER BY nombre");
                  if ($gimnasios) while($g = $gimnasios->fetch_assoc()):
                ?>
                  <option value="<?= (int)$g['id'] ?>"><?= h($g['nombre']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
        <?php endif; ?>

        <div class="actions">
          <button class="btn" type="submit">💾 Guardar Profesor</button>
        </div>
      </form>
    </div>

    <!-- Listado -->
    <div class="card">
      <h3 style="margin-top:0">📋 Lista de Profesores</h3>
      <div class="table-wrap">
        <table class="tabla">
          <thead>
            <tr>
              <th>Apellido</th>
              <th>Nombre</th>
              <th>DNI</th>
              <?php if ($es_admin): ?><th>Gimnasio</th><?php endif; ?>
              <th class="td-actions">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($profesores && $profesores->num_rows): ?>
              <?php while($p = $profesores->fetch_assoc()): ?>
                <tr>
                  <td><?= h($p['apellido']) ?></td>
                  <td><?= h($p['nombre']) ?></td>
                  <td><?= h($p['dni']) ?></td>
                  <?php if ($es_admin): ?><td><?= h($p['gimnasio'] ?? '') ?></td><?php endif; ?>
                  <td class="td-actions">
                    <form method="POST" style="display:inline" onsubmit="return confirm('❗ Se eliminará el profesor y todos sus datos. ¿Continuar?')">
                      <input type="hidden" name="__a" value="eliminar">
                      <input type="hidden" name="profesor_id" value="<?= (int)$p['id'] ?>">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <button type="submit" class="btn danger small">🗑️ Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="<?= $es_admin?5:4 ?>">Sin profesores cargados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</body>
</html>
