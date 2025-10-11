<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$mensaje = '';
$tipo_msg = 'ok'; // ok | error | warn

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$es_admin    = (($_SESSION['rol'] ?? '') === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido'] ?? '');
    $nombre   = trim($_POST['nombre'] ?? '');
    $dni      = trim($_POST['dni'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $gim_id   = $es_admin ? (int)($_POST['gimnasio_id'] ?? 0) : $gimnasio_id;

    if (!$apellido || !$nombre || !$dni || ($es_admin && $gim_id<=0)) {
        $mensaje  = "⚠️ Completá todos los campos obligatorios.";
        $tipo_msg = 'warn';
    } else {
        $stmt = $conexion->prepare("INSERT INTO profesores (apellido, nombre, dni, telefono, email, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssi", $apellido, $nombre, $dni, $telefono, $email, $gim_id);
            if ($stmt->execute()) {
                $mensaje  = "✅ Profesor registrado correctamente.";
                $tipo_msg = 'ok';
            } else {
                // Manejo amistoso por DNI duplicado (si hay índice único)
                if ($conexion->errno == 1062) {
                    $mensaje  = "❌ El DNI ya está registrado en este gimnasio.";
                } else {
                    $mensaje  = "❌ Error al registrar: " . $stmt->error;
                }
                $tipo_msg = 'error';
            }
            $stmt->close();
        } else {
            $mensaje  = "❌ Error preparando consulta: " . $conexion->error;
            $tipo_msg = 'error';
        }
    }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registrar Profesor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tema unificado -->
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    /* ===== Overrides suaves alineados al index ===== */
    .wrap{ max-width: 900px; margin:24px auto; padding:0 16px 40px; }
    .card{ background: var(--card); border:1px solid var(--stroke); border-radius:18px; padding:16px; box-shadow: var(--shadow); }
    .page-title{
      margin:0 0 12px 0; font-weight:900; letter-spacing:.4px;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
      text-align:left;
    }

    form .row{ display:grid; grid-template-columns: 1fr; gap:12px; }
    @media(min-width:700px){ form .row.two{ grid-template-columns: 1fr 1fr; } }

    label{ display:block; font-size:13px; color:#64748b; margin-bottom:6px; font-weight:700; }
    input[type="text"], input[type="email"], select{
      width:100%; padding:12px; border-radius:12px;
      border:1px solid var(--stroke); background:linear-gradient(180deg,#fff,#f7fafc);
      color:var(--ink); font-size:15px; outline:none;
    }
    input:focus, select:focus{
      outline:3px solid rgba(245,158,11,.15);
      box-shadow:0 0 0 3px rgba(245,158,11,.08);
    }

    .actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
    .btn{
      padding:10px 14px; border-radius:12px; border:1px solid var(--stroke);
      background:linear-gradient(180deg,#fff,#f7fafc); color:var(--ink);
      font-weight:800; cursor:pointer; text-decoration:none; display:inline-block; min-width:140px; text-align:center;
    }

    /* Mensajes */
    .msg{ margin:12px 0; padding:12px; border-radius:14px; border:1px solid; }
    .msg.ok{ background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
    .msg.warn{ background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
    .msg.error{ background:#fef2f2; border-color:#fecaca; color:#991b1b; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1 class="page-title">➕ Registrar Profesor</h1>

    <?php if ($mensaje): ?>
      <div class="msg <?= h($tipo_msg) ?>"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" autocomplete="off" novalidate>
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
            <input type="text" id="telefono" name="telefono" placeholder="">
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
                  if ($gimnasios) while ($g = $gimnasios->fetch_assoc()):
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
  </div>
</body>
</html>
