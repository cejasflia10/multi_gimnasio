<?php
/* ============================================================
   login_evento.php — Acceso al Panel de Eventos (simple)
   - Valida usuario/clave en usuarios_eventos
   - Si ok → redirige a panel_eventos.php
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$mensaje = '';

/* ---------- Conexión y charset ---------- */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  $mensaje = '❌ Error de conexión a la base de datos.';
} else {
  if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
  @$conexion->set_charset('utf8mb4');
}

/* ---------- Procesar login ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($mensaje)) {
  $usuario = strtolower(trim($_POST['usuario'] ?? ''));
  $clave   = trim($_POST['clave'] ?? '');

  if ($usuario === '' || $clave === '') {
    $mensaje = "⚠️ Ingresá usuario y contraseña.";
  } else {
    if ($st = $conexion->prepare("SELECT id, nombre, clave, rol, usuario
                                  FROM usuarios_eventos
                                  WHERE LOWER(usuario) = ?
                                  LIMIT 1")) {
      $st->bind_param('s', $usuario);
      $st->execute();
      $res = $st->get_result();

      if ($res && $res->num_rows > 0) {
        $datos = $res->fetch_assoc();
        $hash  = (string)($datos['clave'] ?? '');
        $ok    = ($clave === $hash) || password_verify($clave, $hash);

        if ($ok) {
          $_SESSION['evento_usuario_id']     = (int)$datos['id'];
          $_SESSION['evento_usuario_nombre'] = (string)$datos['nombre'];
          $_SESSION['evento_usuario_rol']    = (string)$datos['rol'];
          $_SESSION['usuario']               = strtolower(trim((string)$datos['usuario']));

          header('Location: panel_eventos.php');
          exit;
        } else {
          $mensaje = "❌ Contraseña incorrecta.";
        }
      } else {
        $mensaje = "❌ Usuario no encontrado.";
      }
      $st->close();
    } else {
      $mensaje = "❌ Error preparando la consulta.";
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
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body style="background:black;color:gold;">
  <div class="contenedor" style="max-width:400px;margin-top:60px;">
    <h2>🎯 Acceso Panel de Eventos</h2>

    <?php if ($mensaje !== ''): ?>
      <p style="color:#ff6b6b;"><?= h($mensaje) ?></p>
    <?php endif; ?>

    <form method="POST" action="login_evento.php">
      <label>Usuario:</label>
      <input type="text" name="usuario" required autofocus>

      <label>Contraseña:</label>
      <input type="password" name="clave" required>

      <button type="submit">🔐 Ingresar</button>
    </form>

    <div style="margin-top:10px;">
      <a href="index.php" class="boton-volver">⬅ Volver</a>
    </div>
  </div>
</body>
</html>
