<?php
// usuarios_evento.php — CRUD de usuarios del panel (sin gimnasios)
if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'usuarios_evento.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/rbac_eventos.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

ev_require_perm('usuarios.ver');

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_check($t){ if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) { http_response_code(419); exit('CSRF token inválido.'); } }

/* Roles */
$roles = [];
$r = $conexion->query("SELECT id,nombre FROM evento_roles ORDER BY id ASC");
while($row=$r->fetch_assoc()){ $roles[(int)$row['id']]=$row['nombre']; }
$r->close();

/* Flash */
$ok=''; $err='';

/* ================= Acciones ================= */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  csrf_check($_POST['csrf'] ?? '');
  $accion = $_POST['accion'] ?? '';

  if ($accion==='crear') {
    ev_require_perm('usuarios.crear');
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $pass   = (string)($_POST['password'] ?? '');
    $rol_id = (int)($_POST['rol_id'] ?? 4);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre==='' || $email==='' || $pass==='') { $err='Completá nombre, email y contraseña.'; }
    else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $sql="INSERT INTO evento_usuarios (nombre,email,pass_hash,rol_id,activo) VALUES (?,?,?,?,?)";
      $st=$conexion->prepare($sql);
      $st->bind_param('sssii',$nombre,$email,$hash,$rol_id,$activo);
      if($st->execute()){ $ok='Usuario creado.'; ev_refresh_perms($conexion); } else { $err='No se pudo crear: '.$conexion->error; }
      $st->close();
    }
  }

  if ($accion==='editar') {
    ev_require_perm('usuarios.editar');
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $rol_id = (int)($_POST['rol_id'] ?? 4);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($id<=0 || $nombre==='' || $email===''){ $err='Datos incompletos.'; }
    else {
      $sql="UPDATE evento_usuarios SET nombre=?, email=?, rol_id=?, activo=? WHERE id=?";
      $st=$conexion->prepare($sql);
      $st->bind_param('ssiii',$nombre,$email,$rol_id,$activo,$id);
      if($st->execute()){ $ok='Usuario actualizado.'; } else { $err='No se pudo actualizar: '.$conexion->error; }
      $st->close();
      if (!empty($_SESSION['evento_usuario_id']) && (int)$_SESSION['evento_usuario_id']===$id) {
        $_SESSION['evento_rol_nombre'] = $roles[$rol_id] ?? null;
        ev_refresh_perms($conexion);
      }
    }
  }

  if ($accion==='reset_pass') {
    ev_require_perm('usuarios.editar');
    $id   = (int)($_POST['id'] ?? 0);
    $pass = (string)($_POST['password'] ?? '');
    if ($id<=0 || $pass===''){ $err='Falta contraseña.'; }
    else {
      $hash=password_hash($pass,PASSWORD_DEFAULT);
      $st=$conexion->prepare("UPDATE evento_usuarios SET pass_hash=? WHERE id=?");
      $st->bind_param('si',$hash,$id);
      if($st->execute()){ $ok='Contraseña actualizada.'; } else { $err='No se pudo actualizar contraseña.'; }
      $st->close();
    }
  }

  if ($accion==='eliminar') {
    ev_require_perm('usuarios.eliminar');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ $err='ID inválido.'; }
    else {
      if (!empty($_SESSION['evento_usuario_id']) && (int)$_SESSION['evento_usuario_id']===$id) {
        $err='No podés eliminar tu propio usuario.';
      } else {
        $st=$conexion->prepare("DELETE FROM evento_usuarios WHERE id=?");
        $st->bind_param('i',$id);
        if($st->execute()){ $ok='Usuario eliminado.'; } else { $err='No se pudo eliminar.'; }
        $st->close();
      }
    }
  }
}

/* ================= Listado ================= */
$busca = trim((string)($_GET['q'] ?? ''));
$w="1=1"; $types=''; $vals=[];
if ($busca!==''){ $w.=" AND (nombre LIKE CONCAT('%',?,'%') OR email LIKE CONCAT('%',?,'%'))"; $types.='ss'; $vals[]=$busca; $vals[]=$busca; }
$sql="SELECT u.id,u.nombre,u.email,u.rol_id,u.activo,u.created_at, r.nombre AS rol_nombre
      FROM evento_usuarios u
      JOIN evento_roles r ON r.id=u.rol_id
      WHERE $w
      ORDER BY u.id DESC";
$st=$conexion->prepare($sql);
if ($types!==''){ $bind=[&$types]; foreach($vals as $k=>$_){ $bind[]=&$vals[$k]; } call_user_func_array([$st,'bind_param'],$bind); }
$st->execute(); $res=$st->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Usuarios del panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <style>
    :root{
      --bg:#0a0a0a; --fg:#e6eef4; --mut:#9ecbff; --brand:#d4af37;
      --card:#0f1720; --bd:#1f2a33; --line:#222;
      --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1; --badbg:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
    }
    *{box-sizing:border-box}
    html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:var(--brand);text-decoration:none}
    a:focus,button:focus,input:focus,select:focus{outline:2px dashed var(--brand); outline-offset:2px}

    .wrap{max-width:1100px;margin:18px auto;padding:16px}
    .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.58rem .9rem;border-radius:10px;border:1px solid var(--line);background:#151515;color:var(--brand);text-decoration:none;font-weight:600;cursor:pointer}
    .btn.gray{background:#1b2836;border-color:#2b3c4f;color:#ddd}
    .btn.primary{background:#0e7ad1;border-color:#27455c;color:#fff}
    .btn.red{background:#7a1f1f;border-color:#9a2b2b;color:#fff}

    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    input,select{padding:.56rem .7rem;border-radius:10px;border:1px solid var(--line);background:#111a24;color:var(--fg)}
    .pill{display:inline-block;padding:.25rem .6rem;border-radius:999px;border:1px solid #3b3b3b;font-size:.85rem;color:#ddd}

    .table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:780px}
    thead th{position:sticky;top:0;background:#121212;color:var(--brand);text-align:left;padding:.7rem .65rem;border-bottom:1px solid var(--bd);z-index:1}
    td{padding:.6rem .65rem;border-bottom:1px solid var(--bd);vertical-align:middle}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}

    @media (max-width: 820px){
      .table-wrap{border:0}
      table{border-collapse:separate;border-spacing:0 12px;min-width:0}
      thead{display:none}
      tbody tr{display:block;background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:10px 10px 6px}
      tbody td{display:flex;justify-content:space-between;gap:12px;padding:.55rem .3rem;border-bottom:0}
      tbody td::before{content:attr(data-label); color:var(--mut); min-width:42%}
      td[data-key="id"]{display:block;font-weight:700}
      td[data-key="acciones"]{display:flex;gap:8px;flex-wrap:wrap}
      .btn{flex:1 1 48%}
    }
  </style>
</head>
<body>
<div class="wrap">
  <?php @include __DIR__.'/menu_eventos.php'; ?>

  <div class="row" style="margin-bottom:10px">
    <a class="btn gray" href="panel_eventos.php">← Volver al panel</a>
    <span class="pill">Usuarios del panel</span>
  </div>

  <?php if($ok): ?><div class="ok"><?= h($ok) ?></div><?php endif; ?>
  <?php if($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <h3 style="margin:0 0 8px">Buscar</h3>
    <form method="get" class="row">
      <input name="q" placeholder="Nombre o email" value="<?= h($busca) ?>">
      <button class="btn primary" type="submit">Filtrar</button>
      <a class="btn gray" href="usuarios_evento.php">Limpiar</a>
    </form>
  </div>

  <?php if (ev_can('usuarios.crear')): ?>
  <div class="card" style="margin-top:12px">
    <h3 style="margin:0 0 8px">➕ Nuevo usuario</h3>
    <form method="post" class="row">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="accion" value="crear">
      <input name="nombre" placeholder="Nombre y apellido" required>
      <input name="email" type="email" placeholder="Email" required>
      <input name="password" type="password" placeholder="Contraseña" required>
      <select name="rol_id" required>
        <?php foreach($roles as $rid=>$rnm): ?>
          <option value="<?= (int)$rid ?>"><?= h($rnm) ?></option>
        <?php endforeach; ?>
      </select>
      <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="activo" checked> Activo</label>
      <button class="btn primary" type="submit">Crear</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:12px">
    <h3 style="margin:0 0 8px">Usuarios</h3>
    <div class="table-wrap" role="region" aria-label="Usuarios" tabindex="0">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Activo</th><th>Creado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="7" style="color:#9ecbff">Sin usuarios.</td></tr>
        <?php else: foreach($rows as $u): $uid=(int)$u['id']; ?>
          <tr>
            <td data-key="id" data-label="#"><?= $uid ?></td>
            <td data-label="Nombre"><?= h($u['nombre']) ?></td>
            <td data-label="Email"><?= h($u['email']) ?></td>
            <td data-label="Rol"><span class="pill"><?= h($u['rol_nombre']) ?></span></td>
            <td data-label="Activo"><?= ((int)$u['activo']===1?'Sí':'No') ?></td>
            <td data-label="Creado"><?= h((string)$u['created_at']) ?></td>
            <td data-key="acciones" data-label="Acciones" style="white-space:nowrap">
              <?php if (ev_can('usuarios.editar')): ?>
                <details>
                  <summary class="btn">✏️ Editar</summary>
                  <form method="post" style="margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:8px;min-width:260px">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <input name="nombre" value="<?= h($u['nombre']) ?>" placeholder="Nombre">
                    <input name="email" type="email" value="<?= h($u['email']) ?>" placeholder="Email">
                    <select name="rol_id">
                      <?php foreach($roles as $rid=>$rnm): ?>
                        <option value="<?= (int)$rid ?>" <?= (int)$u['rol_id']===(int)$rid?'selected':''; ?>><?= h($rnm) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="activo" <?= ((int)$u['activo']===1?'checked':'') ?>> Activo</label>
                    <button class="btn primary" type="submit">Guardar</button>
                  </form>
                </details>

                <details>
                  <summary class="btn gray">🔑 Reset pass</summary>
                  <form method="post" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="reset_pass">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <input name="password" type="password" placeholder="Nueva contraseña" required>
                    <button class="btn primary" type="submit">Actualizar</button>
                  </form>
                </details>
              <?php endif; ?>

              <?php if (ev_can('usuarios.eliminar')): ?>
                <form method="post" onsubmit="return confirm('¿Eliminar este usuario?');" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= $uid ?>">
                  <button class="btn red" type="submit">🗑️ Eliminar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="margin-top:12px" class="row">
    <a class="btn gray" href="panel_eventos.php">← Volver al panel</a>
  </div>
</div>
</body>
</html>
