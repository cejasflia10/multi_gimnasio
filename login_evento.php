<?php
/* ============================================================
   login_evento.php — Acceso al Panel de Eventos (simple)
   - Valida usuario/clave en usuarios_eventos
   - Si ok → redirige a panel_eventos.php
   - PARCHE: fija $_SESSION['evento_id_actual'] al loguear
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

/* ---------- Helpers mínimos ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table);
  $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/**
 * Intenta deducir un evento_id para el usuario dado (por ID).
 * Prioriza: 1) usuarios_eventos.evento_id  2) tablas puente  3) eventos_deportivos por organizador  4) único evento activo  5) último por fecha
 */
function pick_evento_id_for_user(mysqli $db, int $usuario_id): ?int {
  // 1) Columna directa en usuarios_eventos
  if (has_col($db,'usuarios_eventos','evento_id')) {
    if ($st=$db->prepare("SELECT evento_id FROM usuarios_eventos WHERE id=? AND evento_id IS NOT NULL LIMIT 1")) {
      $st->bind_param('i',$usuario_id); $st->execute();
      $r=$st->get_result(); if($r && $row=$r->fetch_assoc()){ $st->close(); return (int)$row['evento_id']; }
      $st->close();
    }
  }

  // 2) Tablas puente comunes
  $puentes = [
    ['tabla'=>'usuarios_eventos_evento','col_user'=>'usuario_id','col_ev'=>'evento_id'],
    ['tabla'=>'eventos_usuarios','col_user'=>'usuario_id','col_ev'=>'evento_id'],
  ];
  foreach ($puentes as $p) {
    if ($db->query("SHOW TABLES LIKE '".$db->real_escape_string($p['tabla'])."'")->num_rows) {
      $sql="SELECT e.id
            FROM ".$p['tabla']." ue
            JOIN eventos_deportivos e ON e.id=ue.".$p['col_ev']."
            WHERE ue.".$p['col_user']."=?
            ORDER BY (CASE WHEN COALESCE(e.activo,1)=1 THEN 0 ELSE 1 END), COALESCE(e.fecha, e.created_at, '1970-01-01') DESC
            LIMIT 1";
      if ($st=$db->prepare($sql)) {
        $st->bind_param('i',$usuario_id); $st->execute();
        $r=$st->get_result(); if($r && $row=$r->fetch_assoc()){ $st->close(); return (int)$row['id']; }
        $st->close();
      }
    }
  }

  // 3) eventos_deportivos con vínculo directo al usuario
  if ($db->query("SHOW TABLES LIKE 'eventos_deportivos'")->num_rows) {
    foreach (['organizador_id','usuario_id','created_by'] as $col) {
      if (has_col($db,'eventos_deportivos',$col)) {
        $sql="SELECT id FROM eventos_deportivos
              WHERE $col=? 
              ORDER BY (CASE WHEN COALESCE(activo,1)=1 THEN 0 ELSE 1 END), COALESCE(fecha, created_at, '1970-01-01') DESC
              LIMIT 1";
        if ($st=$db->prepare($sql)) {
          $st->bind_param('i',$usuario_id); $st->execute();
          $r=$st->get_result(); if($r && $row=$r->fetch_assoc()){ $st->close(); return (int)$row['id']; }
          $st->close();
        }
      }
    }

    // 3.b) Si existe exactamente 1 activo, tomarlo
    $q1=$db->query("SELECT id FROM eventos_deportivos WHERE COALESCE(activo,1)=1 LIMIT 2");
    if ($q1 && $q1->num_rows===1) { $row=$q1->fetch_assoc(); return (int)$row['id']; }

    // 3.c) Tomar el último por fecha
    $q2=$db->query("SELECT id FROM eventos_deportivos ORDER BY COALESCE(fecha, created_at, '1970-01-01') DESC LIMIT 1");
    if ($q2 && $row=$q2->fetch_assoc()) return (int)$row['id'];
  }

  return null;
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
          // ==== SESIÓN DE USUARIO ====
          $_SESSION['evento_usuario_id']     = (int)$datos['id'];
          $_SESSION['evento_usuario_nombre'] = (string)$datos['nombre'];
          $_SESSION['evento_usuario_rol']    = (string)$datos['rol'];
          $_SESSION['usuario']               = strtolower(trim((string)$datos['usuario']));

          // (Opcional) Estructura unificada:
          $_SESSION['user'] = [
            'id'        => (int)$datos['id'],
            'nombre'    => (string)$datos['nombre'],
            'rol'       => (string)$datos['rol'],
            'usuario'   => strtolower(trim((string)$datos['usuario'])),
          ];

          // ==== RESOLVER EVENTO Y FIJAR EN SESIÓN ====
          $uid = (int)$datos['id'];
          $evento_id = pick_evento_id_for_user($conexion, $uid);
          if ($evento_id !== null) {
            $_SESSION['evento_id_actual'] = (int)$evento_id;
            // Si querés, dejá un alias por compatibilidad:
            $_SESSION['user']['evento_id'] = (int)$evento_id;
          }

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
