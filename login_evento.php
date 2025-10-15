<?php
/* ============================================================
   login_evento.php — Login restringido por evento
   - Usuario + clave (usuarios_eventos)
   - Lista SOLO eventos del usuario (columna directa y/o tabla puente)
   - Opción de crear evento nuevo si no tiene ninguno
   - Verificación servidor-side de autorización al entrar
   - Redirige a ver_evento.php?evento_id=...
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
if (isset($conexion) && $conexion instanceof mysqli) { @$conexion->set_charset('utf8mb4'); }

$DEBUG = isset($_GET['debug']);
$DBG   = [];
$mensaje = '';

/* ----------------- Helpers ----------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '{$t}'");
  return $q && $q->num_rows > 0;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table);
  $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function pw_ok(string $plain, string $stored): bool {
  if ($stored === '') return false;
  if (hash_equals($stored, $plain)) return true;                      // texto plano
  if (preg_match('/^\$2[aby]\$|\$argon2(id|i)\$/', $stored)) {        // bcrypt/argon
    if (@password_verify($plain, $stored)) return true;
  }
  return false;
}

/* ---------- Eventos: tabla y utilidades ---------- */
function resolve_event_tables(mysqli $db): array {
  $candidatas = ['eventos_deportivos','eventos','evento'];
  $out = [];
  foreach ($candidatas as $t) if (has_table($db,$t)) $out[] = $t;
  return $out; // prioridad en orden
}
function evento_existe(mysqli $db, int $eid, ?string $tablaFija=null): bool {
  $tabs = $tablaFija ? [$tablaFija] : resolve_event_tables($db);
  foreach ($tabs as $t) {
    if (!has_table($db,$t)) continue;
    if (!($st=$db->prepare("SELECT 1 FROM `$t` WHERE id=? LIMIT 1"))) continue;
    $st->bind_param('i',$eid); $st->execute(); $r=$st->get_result();
    if ($r && $r->num_rows>0) { $st->close(); return true; }
    $st->close();
  }
  return false;
}
function titulo_evento(mysqli $db, string $t, array $row): string {
  $id = (int)$row['id'];
  $titulo = $row['titulo'] ?? ($row['nombre'] ?? null);
  if ($titulo && trim($titulo)!=='') return (string)$titulo;
  return "Evento #$id";
}

/* ---------- Usuario y vínculo ---------- */
function fetch_usuario(mysqli $db, string $usuario): ?array {
  if (!has_table($db,'usuarios_eventos')) return null;
  $sql = "SELECT id, usuario, clave, nombre, rol
          FROM usuarios_eventos
          WHERE LOWER(usuario)=LOWER(?)
          LIMIT 1";
  if (!($st=$db->prepare($sql))) return null;
  $st->bind_param('s',$usuario);
  $st->execute(); $r=$st->get_result();
  if (!$r || $r->num_rows===0) { $st->close(); return null; }
  $row=$r->fetch_assoc(); $st->close();
  return $row;
}

/**
 * Devuelve SOLO los eventos a los que el usuario tiene acceso explícito:
 * - usuarios_eventos.evento_id (si existe)
 * - usuarios_eventos_evento (usuario_id, evento_id) (si existe)
 * No “adivina” por organizador/created_by. Es estricto.
 */
function listar_eventos_del_usuario(mysqli $db, int $uid, ?string &$tablaEventos=null): array {
  $tabs = resolve_event_tables($db);
  if (!$tabs) return [];
  $t = $tabs[0];
  $tablaEventos = $t;

  $permitidos = [];

  // 1) usuarios_eventos.evento_id
  if (has_col($db,'usuarios_eventos','evento_id')) {
    if ($st=$db->prepare("SELECT evento_id FROM usuarios_eventos WHERE id=? AND evento_id IS NOT NULL")) {
      $st->bind_param('i',$uid); $st->execute();
      $r=$st->get_result();
      if ($r) while($row=$r->fetch_assoc()){ $permitidos[(int)$row['evento_id']]=true; }
      $st->close();
    }
  }

  // 2) tabla puente
  $tpuente='usuarios_eventos_evento';
  if (has_table($db,$tpuente) && has_col($db,$tpuente,'usuario_id') && has_col($db,$tpuente,'evento_id')) {
    if ($st=$db->prepare("SELECT evento_id FROM `$tpuente` WHERE usuario_id=?")) {
      $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result();
      if ($r) while($row=$r->fetch_assoc()){ $permitidos[(int)$row['evento_id']]=true; }
      $st->close();
    }
  }

  if (!$permitidos) return [];

  // armar SELECT reducido solo con esos IDs
  $ids = implode(',', array_map('intval', array_keys($permitidos)));
  $colsSel = ['id'];
  foreach (['titulo','nombre','fecha','created_at'] as $c) {
    if (has_col($db,$t,$c)) $colsSel[]=$c;
  }
  $sel = implode(',', array_map(fn($c)=>"`$c`",$colsSel));
  $ord = "ORDER BY ";
  if (in_array('fecha',$colsSel,true))           $ord .= "COALESCE(`fecha`, '1970-01-01') DESC, `id` DESC";
  elseif (in_array('created_at',$colsSel,true))  $ord .= "COALESCE(`created_at`, '1970-01-01') DESC, `id` DESC";
  else                                           $ord .= "`id` DESC";

  $sql = "SELECT $sel FROM `$t` WHERE id IN ($ids) $ord";
  $out=[];
  if ($r=$db->query($sql)) {
    while($row=$r->fetch_assoc()){
      $row['titulo_resuelto'] = titulo_evento($db,$t,$row);
      $out[]=$row;
    }
    $r->close();
  }
  return $out;
}

/* Vincula usuario↔evento (opcional: mantiene columna y puente) */
function asegurar_vinculo_usuario_evento(mysqli $db, int $uid, int $eid): void {
  if (has_col($db,'usuarios_eventos','evento_id')) {
    if ($st=$db->prepare("UPDATE usuarios_eventos SET evento_id=? WHERE id=?")) {
      $st->bind_param('ii',$eid,$uid); $st->execute(); $st->close();
    }
  }
  $t='usuarios_eventos_evento';
  if (has_table($db,$t) && has_col($db,$t,'usuario_id') && has_col($db,$t,'evento_id')) {
    $ex=false;
    if ($st=$db->prepare("SELECT 1 FROM `$t` WHERE usuario_id=? AND evento_id=? LIMIT 1")) {
      $st->bind_param('ii',$uid,$eid); $st->execute();
      $r=$st->get_result(); $ex=$r && $r->num_rows>0; $st->close();
    }
    if (!$ex && ($st=$db->prepare("INSERT INTO `$t` (usuario_id, evento_id) VALUES (?,?)"))) {
      $st->bind_param('ii',$uid,$eid); $st->execute(); $st->close();
    }
  }
}

/* Crear evento mínimo (solo si el usuario no tiene ninguno) */
function crear_evento_basico(mysqli $db, int $uid, string $usuario, ?string &$tablaEventos=null): ?int {
  $tabs = resolve_event_tables($db);
  if (!$tabs) return null;
  $t = $tabs[0]; $tablaEventos = $t;

  // columnas disponibles
  $cols = [];
  $r=$db->query("SELECT COLUMN_NAME, DATA_TYPE, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$db->real_escape_string($t)}'");
  if ($r){ while($c=$r->fetch_assoc()){ $cols[$c['COLUMN_NAME']]=$c; } $r->close(); }

  $base = [
    'titulo'      => "Evento de $usuario",
    'nombre'      => "Evento de $usuario",
    'fecha'       => date('Y-m-d'),
    'created_at'  => date('Y-m-d H:i:s'),
    'activo'      => 1,
    'organizador_id' => $uid,
    'usuario_id'  => $uid,
    'created_by'  => $uid,
  ];

  $insertCols=[]; $marks=[]; $vals=[]; $types='';
  foreach ($base as $c=>$v) {
    if (!isset($cols[$c])) continue;
    if (stripos((string)$cols[$c]['EXTRA'],'auto_increment')!==false) continue;
    $insertCols[]="`$c`";
    $marks[]='?';
    $vals[]=$v;
    $types .= is_int($v)?'i':'s';
  }
  if (!$insertCols) return null;

  $sql="INSERT INTO `$t` (".implode(',',$insertCols).") VALUES (".implode(',',$marks).")";
  if (!($st=$db->prepare($sql))) return null;
  $st->bind_param($types, ...$vals);
  if (!$st->execute()){ $st->close(); return null; }
  $id=$st->insert_id; $st->close();
  return $id ?: null;
}

/* ===================== CONTROL FLOW ===================== */
$paso   = 'login';       // login | elegir_evento
$uidTmp = null;
$usuarioTmp = null;
$rolTmp = null;
$tablaEventos = null;
$eventosPropios = [];

/* POST principal */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario   = trim((string)($_POST['usuario'] ?? ''));
  $clave     = (string)($_POST['clave'] ?? '');
  $evento_id = trim((string)($_POST['evento_id'] ?? '')); // puede venir vacío en el 1er submit

  if ($usuario === '' || $clave === '') {
    $mensaje = "⚠️ Ingresá usuario y contraseña.";
  } else {
    $u = fetch_usuario($conexion, $usuario);
    if (!$u) {
      $mensaje = "❌ Usuario no encontrado.";
    } elseif (!pw_ok($clave, (string)$u['clave'])) {
      $mensaje = "❌ Contraseña incorrecta.";
    } else {
      // Credenciales OK
      $uidTmp = (int)$u['id']; $usuarioTmp = (string)$u['usuario']; $rolTmp = (string)$u['rol'];

      // Si aún no eligió evento → listar SOLO los suyos
      if ($evento_id === '') {
        $eventosPropios = listar_eventos_del_usuario($conexion, $uidTmp, $tablaEventos);

        if ($rolTmp === 'admin') {
          // Admin: puede ver todos (opcional). Quitá este bloque si no hace falta.
          $tabs = resolve_event_tables($conexion);
          $t = $tabs ? $tabs[0] : null;
          if ($t) {
            $sql="SELECT id, ".(has_col($conexion,$t,'titulo')?'titulo':'nombre')." AS titulo, ".
                 (has_col($conexion,$t,'fecha')?'fecha':'NULL AS fecha')." FROM `$t` ORDER BY id DESC";
            if ($r=$conexion->query($sql)) {
              $eventosPropios = $r->fetch_all(MYSQLI_ASSOC);
              foreach($eventosPropios as &$ev){ $ev['titulo_resuelto'] = $ev['titulo'] ?: ("Evento #".$ev['id']); }
              unset($ev);
              $tablaEventos=$t;
            }
          }
        }

        if (!$eventosPropios) {
          // No tiene eventos → ofrecer crear nuevo
          $paso = 'elegir_evento';
          $mensaje = "ℹ️ No tenés eventos asignados. Podés crear uno nuevo.";
        } elseif (count($eventosPropios) === 1) {
          // Auto-entrar
          $eid = (int)$eventosPropios[0]['id'];
          session_regenerate_id(true);
          $_SESSION['evento_usuario_id']     = $uidTmp;
          $_SESSION['evento_usuario_nombre'] = (string)$u['nombre'];
          $_SESSION['evento_usuario_rol']    = $rolTmp;
          $_SESSION['usuario']               = strtolower($usuarioTmp);
          $_SESSION['user'] = ['id'=>$uidTmp,'usuario'=>$_SESSION['usuario'],'nombre'=>(string)$u['nombre'],'rol'=>$rolTmp];
          $_SESSION['evento_id_actual'] = $eid;
          header('Location: ver_evento.php?evento_id='.$eid);
          exit;
        } else {
          // Tiene varios → seleccionar
          $paso = 'elegir_evento';
        }
      } else {
        // Enviaron evento elegido o "nuevo"
        if ($evento_id === 'nuevo' || $evento_id === '-1') {
          $eid = crear_evento_basico($conexion, $uidTmp ?? (int)$u['id'], $usuarioTmp ?? (string)$u['usuario'], $tablaEventos);
          if ($eid === null) {
            $mensaje = "⚠️ No se pudo crear el evento nuevo. Pedile al admin que te asigne uno.";
            $paso = 'elegir_evento';
            $eventosPropios = listar_eventos_del_usuario($conexion, (int)$u['id'], $tablaEventos);
          } else {
            asegurar_vinculo_usuario_evento($conexion, (int)$u['id'], $eid);
            session_regenerate_id(true);
            $_SESSION['evento_usuario_id']     = (int)$u['id'];
            $_SESSION['evento_usuario_nombre'] = (string)$u['nombre'];
            $_SESSION['evento_usuario_rol']    = (string)$u['rol'];
            $_SESSION['usuario']               = strtolower((string)$u['usuario']);
            $_SESSION['user'] = ['id'=>(int)$u['id'],'usuario'=>$_SESSION['usuario'],'nombre'=>(string)$u['nombre'],'rol'=>(string)$u['rol']];
            $_SESSION['evento_id_actual'] = $eid;
            header('Location: ver_evento.php?evento_id='.$eid);
            exit;
          }
        } else {
          $eid = (int)$evento_id;
          // Verificar AUTORIZACIÓN: el evento elegido debe estar entre sus eventos
          $autorizados = listar_eventos_del_usuario($conexion, (int)$u['id'], $tablaEventos);
          $idset = array_column($autorizados,'id');
          $permitido = in_array($eid, array_map('intval',$idset), true);

          // Permitir admin a cualquier evento (opcional)
          if (!$permitido && (string)$u['rol'] === 'admin' && evento_existe($conexion, $eid, $tablaEventos)) {
            $permitido = true;
          }

          if (!$permitido) {
            $mensaje = "⛔ No estás autorizado para ese evento.";
            $paso = 'elegir_evento';
            $eventosPropios = $autorizados;
          } else {
            session_regenerate_id(true);
            $_SESSION['evento_usuario_id']     = (int)$u['id'];
            $_SESSION['evento_usuario_nombre'] = (string)$u['nombre'];
            $_SESSION['evento_usuario_rol']    = (string)$u['rol'];
            $_SESSION['usuario']               = strtolower((string)$u['usuario']);
            $_SESSION['user'] = ['id'=>(int)$u['id'],'usuario'=>$_SESSION['usuario'],'nombre'=>(string)$u['nombre'],'rol'=>(string)$u['rol']];
            $_SESSION['evento_id_actual'] = $eid;
            header('Location: ver_evento.php?evento_id='.$eid);
            exit;
          }
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Login — Evento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body{background:#0b0f19;color:#e5e7eb;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
    .wrap{max-width:420px;margin:8vh auto;padding:22px;border:1px solid #334155;border-radius:12px;background:#111827}
    h2{margin:0 0 10px}
    label{display:block;font-weight:700;margin:10px 0 6px}
    input,select{width:100%;height:44px;border:1px solid #64748b;border-radius:10px;background:#0b1220;color:#fff;padding:0 10px}
    button{margin-top:12px;width:100%;height:46px;border:0;border-radius:10px;background:#1e88e5;color:#fff;font-weight:800;cursor:pointer}
    .msg{margin-top:10px;color:#ff6b6b;font-weight:700;white-space:pre-line}
    a{color:#93c5fd}
    small{opacity:.8}
  </style>
</head>
<body>
  <div class="wrap">
    <h2>🎯 Acceso a tu evento</h2>

    <?php if ($mensaje !== ''): ?>
      <div class="msg"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($paso === 'login'): ?>
      <!-- Paso 1: Login -->
      <form method="POST" action="login_evento.php<?= $DEBUG? '?debug=1':'' ?>">
        <label>Usuario</label>
        <input type="text" name="usuario" required autofocus>

        <label>Contraseña</label>
        <input type="password" name="clave" required>

        <button type="submit" id="btnIngresar">Continuar</button>
      </form>
    <?php else: ?>
      <!-- Paso 2: Elegir evento (solo los propios) o crear -->
      <form method="POST" action="login_evento.php<?= $DEBUG? '?debug=1':'' ?>">
        <input type="hidden" name="usuario" value="<?= h($usuarioTmp ?? ($_POST['usuario'] ?? '')) ?>">
        <input type="hidden" name="clave"   value="<?= h($_POST['clave'] ?? '') ?>">

        <label>Evento</label>
        <select name="evento_id" required autofocus>
          <?php if ($eventosPropios): ?>
            <option value="" disabled selected>Elegí uno…</option>
            <?php foreach ($eventosPropios as $ev): ?>
              <option value="<?= (int)$ev['id'] ?>">#<?= (int)$ev['id'] ?> — <?= h($ev['titulo_resuelto'] ?? $ev['titulo'] ?? 'Evento') ?></option>
            <?php endforeach; ?>
            <option value="nuevo">➕ Crear evento nuevo</option>
          <?php else: ?>
            <option value="nuevo" selected>➕ Crear evento nuevo</option>
          <?php endif; ?>
        </select>

        <button type="submit">Ingresar</button>
      </form>
    <?php endif; ?>

    <p style="margin-top:10px"><a href="index.php">⬅ Volver</a></p>
  </div>

  <?php if ($DEBUG && !empty($DBG)): ?>
    <!-- ===== DEBUG =====
    <?= h(implode("\n", $DBG)) . "\n" ?>
    ===================== -->
  <?php endif; ?>

  <script>
    document.querySelector('form')?.addEventListener('submit', (e)=>{
      const btn = document.querySelector('button[type="submit"]');
      if(btn){ btn.disabled = true; btn.textContent = 'Procesando…'; }
    });
  </script>
</body>
</html>
