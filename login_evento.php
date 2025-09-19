<?php
/* ============================================================
   login_evento.php — Acceso al Panel de Eventos
   - Valida usuario/clave en usuarios_eventos
   - Setea $_SESSION['evento_usuario_id'] y $_SESSION['rol']
   - Redirige a panel_eventos.php (o return_to si viene)
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$mensaje = '';

/* ---------- Conexión ---------- */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  $mensaje = '❌ Error de conexión a la base de datos.';
} else {
  if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
  @$conexion->set_charset('utf8mb4');
}

/* ---------- Procesar login ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($mensaje)) {
  $usuario = strtolower(trim($_POST['usuario'] ?? ''));
  $clave   = (string)($_POST['clave'] ?? '');

  if ($usuario === '' || $clave === '') {
    $mensaje = "⚠️ Ingresá usuario y contraseña.";
  } else {
    $sql = "SELECT id, nombre, clave, rol, usuario
            FROM usuarios_eventos
            WHERE LOWER(usuario) = ?
            LIMIT 1";
    $st = $conexion->prepare($sql);
    if (!$st) {
      $mensaje = "❌ Error preparando la consulta.";
    } else {
      $st->bind_param('s', $usuario);
      $st->execute();
      $res = $st->get_result();

      if ($res && $res->num_rows > 0) {
        $datos = $res->fetch_assoc();
        $hash  = (string)($datos['clave'] ?? '');

        // Acepta hash con password_hash() o contraseña en texto plano (legacy)
        $ok = ($clave === $hash) || password_verify($clave, $hash);

        if ($ok) {
          // Normalizar rol a ORGANIZADOR / STAFF / JUEZ
          $rol_db = strtoupper(trim((string)($datos['rol'] ?? '')));
          $roles_validos = ['ORGANIZADOR','STAFF','JUEZ'];
          if (!in_array($rol_db, $roles_validos, true)) {
            // Por defecto ORGANIZADOR si el campo en BD está vacío o inválido
            $rol_db = 'ORGANIZADOR';
          }

          // Sesión limpia y consistente con lo que usa el panel
          session_regenerate_id(true);
          $_SESSION['evento_usuario_id']     = (int)$datos['id'];
          $_SESSION['evento_usuario_nombre'] = (string)$datos['nombre'];
          $_SESSION['usuario']               = strtolower(trim((string)$datos['usuario']));
          $_SESSION['rol']                   = $rol_db; // <<--- CLAVE para el panel

          // Redirección
          $return_to = isset($_GET['return_to']) ? (string)$_GET['return_to'] : '';
          // Evitar open redirect: solo rutas locales
          if ($return_to === '' || str_starts_with($return_to, 'http')) {
            $return_to = 'panel_eventos.php';
          }
          header('Location: ' . $return_to);
          exit;
        } else {
          $mensaje = "❌ Contraseña incorrecta.";
        }
      } else {
        $mensaje = "❌ Usuario no encontrado.";
      }
      $st->close();
    }
  }
}

/* ---------- Helper HTML ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Panel de Eventos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{background:#0a0a0a;color:#f6f6f6;font-family:system-ui,Segoe UI,Roboto;margin:0}
    .contenedor{max-width:420px;margin:60px auto;padding:20px;border:1px solid #222;border-radius:12px;background:#111}
    h2{margin-top:0}
    label{display:block;margin:.6rem 0 .25rem;color:#c9c9c9}
    input{width:100%;padding:.6rem .7rem;border-radius:10px;border:1px solid #333;background:#0b0b0b;color:#f6f6f6}
    button{margin-top:12px;width:100%;padding:.7rem;border-radius:10px;border:1px solid #333;background:#151515;color:#d4af37;font-weight:700}
    a{color:#d4af37;text-decoration:none}
    .msg{color:#ff6b6b;margin:.6rem 0}
  </style>
</head>
<body>
  <div class="contenedor">
    <h2>🎯 Acceso Panel de Eventos</h2>

    <?php if ($mensaje !== ''): ?>
      <p class="msg"><?= h($mensaje) ?></p>
    <?php endif; ?>

    <form method="POST" action="login_evento.php<?= isset($_GET['return_to']) ? '?return_to='.urlencode($_GET['return_to']) : '' ?>">
      <label>Usuario:</label>
      <input type="text" name="usuario" required autofocus>

      <label>Contraseña:</label>
      <input type="password" name="clave" required>

      <button type="submit">🔐 Ingresar</button>
    </form>

    <div style="margin-top:10px;">
      <a href="index.php">⬅ Volver</a>
    </div>
  </div>
</body>
</html>
