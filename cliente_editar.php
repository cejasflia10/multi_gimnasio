<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

/* ================== CONFIG ================== */
$MAX_MB          = 4;                       // Tamaño máx. de imagen
$MAX_BYTES       = $MAX_MB * 1024 * 1024;
$UPLOAD_DIR_BASE = __DIR__ . '/uploads/clientes';
$URL_BASE        = 'uploads/clientes';      // ruta pública relativa (para src de <img>)

/* ============== AUTENTICACIÓN ============== */
$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0) {
  header('Location: login.php'); exit;
}

/* ============== CSRF TOKEN ================= */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
function csrf_ok($token) {
  return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/* ============== HELPERS ==================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function ensure_dir($dir) {
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  return is_dir($dir) && is_writable($dir);
}

/** Devuelve true si la columna existe en la tabla dada */
function column_exists(mysqli $cx, string $table, string $column): bool {
  $t = $cx->real_escape_string($table);
  $c = $cx->real_escape_string($column);
  $res = $cx->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return ($res && $res->num_rows > 0);
}

/** Intenta agregar la columna foto_path si no existe */
function ensure_foto_column(mysqli $cx): bool {
  if (column_exists($cx, 'clientes', 'foto_path')) return true;
  // Crear columna foto_path si no existe
  $ok = $cx->query("ALTER TABLE clientes ADD COLUMN foto_path VARCHAR(255) NULL");
  return $ok ? true : column_exists($cx, 'clientes', 'foto_path');
}

/** Obtiene un cliente con columnas dinámicas, tolerante a esquema */
function get_cliente(mysqli $cx, int $cliente_id, int $gimnasio_id): array {
  $hasGym   = column_exists($cx, 'clientes', 'gimnasio_id');
  $hasEmail = column_exists($cx, 'clientes', 'email');
  $hasTel   = column_exists($cx, 'clientes', 'telefono');
  $hasFoto  = column_exists($cx, 'clientes', 'foto_path');

  // Columnas mínimas que esperamos tener
  $cols = ['id','nombre','apellido'];
  if ($hasEmail) $cols[] = 'email';
  if ($hasTel)   $cols[] = 'telefono';
  if ($hasFoto)  $cols[] = 'foto_path';

  $colstr = implode(',', $cols);
  $sql = "SELECT {$colstr} FROM clientes WHERE id = ?".($hasGym ? " AND gimnasio_id = ?" : "")." LIMIT 1";

  $st = $cx->prepare($sql);
  if (!$st) {
    // Fallback ultra tolerante
    $st = $cx->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
    if ($st) {
      $st->bind_param('i', $cliente_id);
      $st->execute();
      $row = $st->get_result()->fetch_assoc() ?: [];
      $st->close();
    } else {
      // Último recurso: devolver estructura vacía
      $row = [];
    }
  } else {
    if ($hasGym) { $st->bind_param('ii', $cliente_id, $gimnasio_id); }
    else         { $st->bind_param('i',  $cliente_id); }
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();
  }

  // Normalizar claves faltantes
  $row += ['id'=>$cliente_id, 'nombre'=>'', 'apellido'=>''];
  if ($hasEmail) $row += ['email'=>''];
  if ($hasTel)   $row += ['telefono'=>''];
  if ($hasFoto)  $row += ['foto_path'=>null];

  return $row;
}

/** UPDATE dinámico solo de columnas existentes */
function update_cliente_datos(mysqli $cx, int $cliente_id, int $gimnasio_id, array $data, string &$err): bool {
  $hasGym   = column_exists($cx, 'clientes', 'gimnasio_id');
  $hasEmail = column_exists($cx, 'clientes', 'email');
  $hasTel   = column_exists($cx, 'clientes', 'telefono');
  $hasNom   = column_exists($cx, 'clientes', 'nombre');
  $hasApe   = column_exists($cx, 'clientes', 'apellido');

  $set = [];
  $types = '';
  $vals = [];

  if ($hasNom) { $set[] = "nombre=?";   $types.='s'; $vals[] = $data['nombre']   ?? ''; }
  if ($hasApe) { $set[] = "apellido=?"; $types.='s'; $vals[] = $data['apellido'] ?? ''; }
  if ($hasEmail && array_key_exists('email',$data))   { $set[] = "email=?";    $types.='s'; $vals[] = $data['email']; }
  if ($hasTel   && array_key_exists('telefono',$data)){ $set[] = "telefono=?"; $types.='s'; $vals[] = $data['telefono']; }

  if (!$set) { $err = 'No hay columnas actualizables en la tabla clientes.'; return false; }

  $sql = "UPDATE clientes SET ".implode(',', $set)." WHERE id=?".($hasGym ? " AND gimnasio_id=?" : "")." LIMIT 1";
  $st = $cx->prepare($sql);
  if (!$st) { $err = 'No se pudo preparar la actualización.'; return false; }

  if ($hasGym) { $types .= 'ii'; $vals[]=$cliente_id; $vals[]=$gimnasio_id; }
  else         { $types .= 'i';  $vals[]=$cliente_id; }

  $st->bind_param($types, ...$vals);
  $ok = $st->execute();
  if (!$ok) { $err = 'No se pudo actualizar (intenta más tarde).'; }
  $st->close();
  return $ok;
}

/** UPDATE de foto_path (crea la columna si falta) */
function update_cliente_foto(mysqli $cx, int $cliente_id, int $gimnasio_id, string $newPath, string &$err): bool {
  if (!ensure_foto_column($cx)) { $err = 'No se pudo crear/usar la columna foto_path.'; return false; }
  $hasGym = column_exists($cx,'clientes','gimnasio_id');
  $sql = "UPDATE clientes SET foto_path=? WHERE id=?".($hasGym ? " AND gimnasio_id=?" : "")." LIMIT 1";
  $st = $cx->prepare($sql);
  if (!$st) { $err = 'No se pudo preparar el guardado de la foto.'; return false; }
  if ($hasGym) { $st->bind_param('sii', $newPath, $cliente_id, $gimnasio_id); }
  else         { $st->bind_param('si',  $newPath, $cliente_id); }
  $ok = $st->execute();
  if (!$ok) { $err = 'No se pudo guardar la foto en la base.'; }
  $st->close();
  return $ok;
}

/* ============== ESTADO / MENSAJES ========== */
$msg = '';
$err = '';

/* ============== ACCIONES POST ============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion = $_POST['__accion'] ?? '';
  if (!csrf_ok($_POST['csrf'] ?? '')) {
    $err = 'Acción no autorizada. Refrescá la página e intentá de nuevo.';
  } else {
    if ($accion === 'actualizar_datos') {
      $nombre   = trim($_POST['nombre']   ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $email    = trim($_POST['email']    ?? '');
      $telefono = trim($_POST['telefono'] ?? '');

      if ($nombre === '' || $apellido === '') {
        $err = 'Nombre y Apellido son obligatorios.';
      } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Email inválido.';
      } else {
        $ok = update_cliente_datos($conexion, $cliente_id, $gimnasio_id, [
          'nombre'=>$nombre, 'apellido'=>$apellido, 'email'=>$email, 'telefono'=>$telefono
        ], $err);
        if ($ok) $msg = 'Datos actualizados correctamente.';
      }

    } elseif ($accion === 'subir_foto') {
      if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        $err = 'Seleccioná una imagen.';
      } else {
        $f = $_FILES['foto'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
          $err = 'Error al subir la imagen (código '.$f['error'].').';
        } elseif ($f['size'] > $MAX_BYTES) {
          $err = "La imagen supera el máximo de {$MAX_MB}MB.";
        } else {
          $finfo = new finfo(FILEINFO_MIME_TYPE);
          $mime  = $finfo->file($f['tmp_name']) ?: '';
          $ok_mimes = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
          if (!isset($ok_mimes[$mime])) {
            $err = 'Formato no permitido. Usá JPG, PNG o WEBP.';
          } else {
            $imgInfo = @getimagesize($f['tmp_name']);
            if ($imgInfo === false) {
              $err = 'El archivo no es una imagen válida.';
            } else {
              $dir_cliente = $UPLOAD_DIR_BASE . '/' . $cliente_id;
              if (!ensure_dir($dir_cliente)) {
                $err = 'No se pudo preparar la carpeta de subida.';
              } else {
                $ext  = $ok_mimes[$mime];
                $file = 'perfil_'.$cliente_id.'_'.date('Ymd_His').'.'.$ext;
                $dest_fs  = $dir_cliente . '/' . $file;
                $dest_url = $URL_BASE . '/' . $cliente_id . '/' . $file;

                if (!move_uploaded_file($f['tmp_name'], $dest_fs)) {
                  $err = 'Error moviendo el archivo subido.';
                } else {
                  // Traer actual y actualizar
                  $row = get_cliente($conexion, $cliente_id, $gimnasio_id);
                  $old = $row['foto_path'] ?? null;

                  if (update_cliente_foto($conexion, $cliente_id, $gimnasio_id, $dest_url, $err)) {
                    $msg = 'Foto actualizada correctamente.';
                    // borrar anterior si era nuestra
                    if ($old && is_string($old)) {
                      $old_fs = __DIR__ . '/' . ltrim($old, '/');
                      if (strpos($old, $URL_BASE.'/') === 0 && is_file($old_fs)) { @unlink($old_fs); }
                    }
                  } else {
                    // revertir archivo si falla DB
                    @unlink($dest_fs);
                  }
                }
              }
            }
          }
        }
      }

    } elseif ($accion === 'eliminar_foto') {
      $row = get_cliente($conexion, $cliente_id, $gimnasio_id);
      $old = $row['foto_path'] ?? null;

      if (!ensure_foto_column($conexion)) {
        $err = 'No se pudo acceder a la columna de foto.';
      } else {
        $hasGym = column_exists($conexion, 'clientes', 'gimnasio_id');
        $sql = "UPDATE clientes SET foto_path=NULL WHERE id=?".($hasGym ? " AND gimnasio_id=?" : "")." LIMIT 1";
        $st = $conexion->prepare($sql);
        if ($st) {
          if ($hasGym) { $st->bind_param('ii', $cliente_id, $gimnasio_id); }
          else         { $st->bind_param('i',  $cliente_id); }
          if ($st->execute()) {
            if ($old && is_string($old)) {
              $old_fs = __DIR__ . '/' . ltrim($old, '/');
              if (strpos($old, $URL_BASE.'/') === 0 && is_file($old_fs)) { @unlink($old_fs); }
            }
            $msg = 'Foto eliminada.';
          } else { $err = 'No se pudo eliminar la foto.'; }
          $st->close();
        } else {
          $err = 'No se pudo preparar la eliminación.';
        }
      }
    }
  }
}

/* ============== CARGA INICIAL ============== */
$cliente = get_cliente($conexion, $cliente_id, $gimnasio_id);
$foto_url = (!empty($cliente['foto_path'])) ? $cliente['foto_path'] : 'https://via.placeholder.com/160x160?text=Sin+Foto';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar mi perfil</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor{max-width:900px;margin:0 auto}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
    .card{background:#111827;border:1px solid #374151;border-radius:.75rem;padding:1rem}
    .card h3{margin:.25rem 0 1rem}
    label{display:block;font-size:.9rem;margin:.4rem 0 .2rem}
    input[type=text], input[type=email], input[type=tel]{width:100%;padding:.55rem;border-radius:.5rem;border:1px solid #374151;background:#0b1220;color:#e5e7eb}
    .btn{display:inline-block;background:#2563eb;border:0;color:#fff;padding:.55rem .9rem;border-radius:.5rem;cursor:pointer}
    .btn.sec{background:#6b7280}
    .btn.danger{background:#dc2626}
    .msg{margin:.6rem 0;padding:.6rem .8rem;border-radius:.5rem}
    .ok{background:#ecfdf5;border:1px solid #34d399}
    .err{background:#fee2e2;border:1px solid #f87171}
    .avatar{width:160px;height:160px;border-radius:.75rem;object-fit:cover;background:#0b1220;border:1px solid #374151}
    .fila{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-top:.6rem}
    .hint{font-size:.8rem;color:#9ca3af}
  </style>
</head>
<body>
<div class="contenedor">
  <h1>👤 Editar mi perfil</h1>

  <?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>

  <div class="grid">
    <!-- Datos -->
    <div class="card">
      <h3>Datos personales</h3>
      <form method="POST">
        <input type="hidden" name="__accion" value="actualizar_datos">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <label>Nombre</label>
        <input type="text" name="nombre" value="<?= h($cliente['nombre'] ?? '') ?>" required>

        <label>Apellido</label>
        <input type="text" name="apellido" value="<?= h($cliente['apellido'] ?? '') ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= h($cliente['email'] ?? '') ?>" placeholder="tunombre@mail.com">

        <label>Teléfono</label>
        <input type="tel" name="telefono" value="<?= h($cliente['telefono'] ?? '') ?>" placeholder="+54 9 ...">

        <div class="fila">
          <button class="btn" type="submit">Guardar cambios</button>
          <a class="btn sec" href="ver_turnos_clientes.php">Volver</a>
        </div>
      </form>
    </div>

    <!-- Foto -->
    <div class="card">
      <h3>Foto de perfil</h3>
      <img src="<?= h($foto_url) ?>" alt="Foto de perfil" class="avatar" id="preview">
      <form method="POST" enctype="multipart/form-data" class="fila" style="margin-top:.6rem">
        <input type="hidden" name="__accion" value="subir_foto">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$MAX_BYTES ?>">
        <input type="file" name="foto" id="foto" accept="image/*" required>
        <button class="btn" type="submit">Subir/Actualizar</button>
      </form>
      <?php if (!empty($cliente['foto_path'])): ?>
        <form method="POST" class="fila">
          <input type="hidden" name="__accion" value="eliminar_foto">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <button class="btn danger" type="submit" onclick="return confirm('¿Eliminar foto actual?')">Eliminar foto</button>
        </form>
      <?php endif; ?>
      <p class="hint">Formatos permitidos: JPG, PNG, WEBP. Máx: <?= (int)$MAX_MB ?>MB.</p>
    </div>
  </div>
</div>

<script>
  // Vista previa instantánea
  const input = document.getElementById('foto');
  const preview = document.getElementById('preview');
  if (input && preview) {
    input.addEventListener('change', () => {
      const [file] = input.files || [];
      if (!file) return;
      const url = URL.createObjectURL(file);
      preview.src = url;
      preview.onload = () => URL.revokeObjectURL(url);
    });
  }
</script>
</body>
</html>
