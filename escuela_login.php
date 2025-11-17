<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
@$conexion->set_charset('utf8mb4');

/* Helper para escapar HTML */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['pass'] ?? '');

  if ($email === '' || $pass === '') {
    $errores[] = 'Completá email y clave.';
  } else {
    if ($st = $conexion->prepare("SELECT id, escuela_nombre, email, pass_hash, verificado FROM escuelas_cuentas WHERE email=? LIMIT 1")) {
      $st->bind_param('s', $email);
      $st->execute();
      $rs  = $st->get_result();
      $row = $rs->fetch_assoc();
      $st->close();

      if ($row && password_verify($pass, $row['pass_hash'])) {
        if ((int)$row['verificado'] !== 1) {
          $errores[] = 'La cuenta aún no está verificada.';
        } else {
          // ✅ Login OK — solo variables de ESCUELA, no tocamos nada de eventos
          $_SESSION['escuela_id']      = (int)$row['id'];
          $_SESSION['escuela_nombre']  = (string)$row['escuela_nombre'];
          $_SESSION['escuela_email']   = (string)$row['email'];

          // Marcar último login
          $conexion->query("UPDATE escuelas_cuentas SET ultimo_login = NOW() WHERE id = ".(int)$row['id']." LIMIT 1");

          // Redirigir al panel / registro de peleas de la escuela
          header('Location: escuela_registro.php');
          exit;
        }
      } else {
        $errores[] = 'Email o clave incorrectos.';
      }
    } else {
      $errores[] = 'Error de conexión: '.$conexion->error;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Login escuela</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css?v=3">
</head>
<body>
<?php @include __DIR__.'/menu_escuelas.php'; ?>

<div class="wrap">
  <div class="page-card" style="max-width:420px;margin:20px auto">
    <h2>🔐 Acceso escuelas</h2>

    <?php if ($errores): ?>
      <div class="bad">
        <?php foreach($errores as $e): ?>
          <div><?= h($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <label>Email
        <input type="email" name="email" class="input" required
               value="<?= h($_POST['email'] ?? '') ?>">
      </label>
      <label>Clave
        <input type="password" name="pass" class="input" required>
      </label>
      <button type="submit" class="btn" style="margin-top:10px">Ingresar</button>
    </form>

    <p style="margin-top:10px;font-size:13px">
      ¿No tenés cuenta? <a href="escuela_registro.php">Registrar escuela</a>
    </p>
  </div>
</div>
</body>
</html>
