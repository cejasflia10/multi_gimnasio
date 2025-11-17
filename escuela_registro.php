<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errores = [];
$ok_msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $escuela = trim((string)($_POST['escuela'] ?? ''));
  $email   = trim((string)($_POST['email'] ?? ''));
  $pass1   = (string)($_POST['pass1'] ?? '');
  $pass2   = (string)($_POST['pass2'] ?? '');

  if ($escuela === '') {
    $errores[] = 'Ingresá el nombre de la escuela / gimnasio.';
  }
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'Ingresá un email válido.';
  }
  if ($pass1 === '' || strlen($pass1) < 6) {
    $errores[] = 'La clave debe tener al menos 6 caracteres.';
  }
  if ($pass1 !== $pass2) {
    $errores[] = 'Las claves no coinciden.';
  }

  if (!$errores) {
    // ¿Ya existe ese email?
    if ($st = $conexion->prepare("SELECT id FROM escuelas_cuentas WHERE email = ? LIMIT 1")) {
      $st->bind_param('s', $email);
      $st->execute();
      $st->store_result();
      if ($st->num_rows > 0) {
        $errores[] = 'Ese email ya está registrado.';
      }
      $st->close();
    }

    if (!$errores) {
      $hash = password_hash($pass1, PASSWORD_DEFAULT);
      if ($st = $conexion->prepare("INSERT INTO escuelas_cuentas (escuela_nombre,email,pass_hash,verificado) VALUES (?,?,?,1)")) {
        $st->bind_param('sss', $escuela, $email, $hash);
        if ($st->execute()) {
          $ok_msg = 'Cuenta creada. Ya podés iniciar sesión.';
          // Podés limpiar POST si querés:
          $_POST = [];
        } else {
          $errores[] = 'No se pudo crear la cuenta: '.$conexion->error;
        }
        $st->close();
      } else {
        $errores[] = 'Error preparando el registro: '.$conexion->error;
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Registro de escuela</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css?v=3">
</head>
<body>

<?php require __DIR__ . '/menu_escuelas.php'; ?>

<div class="wrap">
  <div class="page-card" style="max-width:480px;margin:20px auto">
    <h2>🏫 Registro de escuela / gimnasio</h2>

    <?php if ($errores): ?>
      <div class="bad">
        <?php foreach($errores as $e): ?>
          <div><?= h($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($ok_msg): ?>
      <div class="ok"><?= h($ok_msg) ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Nombre de la escuela / gimnasio
        <input type="text" name="escuela" class="input" required
               value="<?= h($_POST['escuela'] ?? '') ?>">
      </label>

      <label>Email de acceso
        <input type="email" name="email" class="input" required
               value="<?= h($_POST['email'] ?? '') ?>">
      </label>

      <label>Clave
        <input type="password" name="pass1" class="input" required>
      </label>

      <label>Repetir clave
        <input type="password" name="pass2" class="input" required>
      </label>

      <button type="submit" class="btn" style="margin-top:10px">Registrar escuela</button>
    </form>

    <p style="margin-top:10px;font-size:13px">
      ¿Ya tenés cuenta? <a href="escuela_login.php">Iniciar sesión</a>
    </p>
  </div>
</div>
</body>
</html>
