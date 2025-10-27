<?php
/* ============================================================
   registro_online.php — Alta rápida de cliente (MultiGimnasio)
   • NO duplica DNI por gimnasio: si existe, muestra datos y usa ese registro
   • Acepta g ó gimnasio_id, prellena DNI, crea cliente solo si no existe
   • Redirige a return (o al QR). NO marca ingreso.
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function qv($db,$s){ return "'".$db->real_escape_string((string)$s)."'"; }
function json_out($arr){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

$gimnasio_id = (int)($_GET['gimnasio_id'] ?? $_GET['g'] ?? 0);
$dni_pref    = preg_replace('/\D+/','', (string)($_GET['dni'] ?? ''));
$return_url  = (string)($_GET['return'] ?? '');

/* === Helpers BD === */
function find_client_by_dni(mysqli $db, int $gimnasio_id, string $dni): ?array {
  if ($gimnasio_id<=0 || $dni==='') return null;
  $q = "SELECT id, nombre, apellido, dni, telefono, email
        FROM clientes
        WHERE gimnasio_id={$gimnasio_id} AND dni=".qv($db,$dni)."
        LIMIT 1";
  if ($rs = $db->query($q)) {
    if ($rs->num_rows) return $rs->fetch_assoc();
  }
  return null;
}

/* === AJAX: ver si DNI ya existe y devolver datos === */
if (($_GET['ajax'] ?? '') === 'dni_check') {
  $gid = (int)($_GET['gimnasio_id'] ?? 0);
  $dni = preg_replace('/\D+/','', (string)($_GET['dni'] ?? ''));
  $row = find_client_by_dni($conexion, $gid, $dni);
  if ($row) {
    json_out(['exists'=>true,'data'=>[
      'id'=>(int)$row['id'],
      'nombre'=>$row['nombre'] ?? '',
      'apellido'=>$row['apellido'] ?? '',
      'dni'=>$row['dni'] ?? '',
      'telefono'=>$row['telefono'] ?? '',
      'email'=>$row['email'] ?? '',
    ]]);
  }
  json_out(['exists'=>false]);
}

/* Validar gimnasio (opcionalmente ofrecer selector si no vino) */
$gym = null;
if ($gimnasio_id > 0) {
  $rsG = $conexion->query("SELECT id, nombre, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1");
  if ($rsG && $rsG->num_rows) { $gym = $rsG->fetch_assoc(); }
}
if (!$gym) {
  $gimnasios = [];
  if ($rs = $conexion->query("SELECT id, nombre FROM gimnasios ORDER BY nombre ASC")) {
    while($r=$rs->fetch_assoc()){ $gimnasios[]=$r; }
  }
}

/* === POST: guardar alta (sin duplicar DNI) === */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $gimnasio_id = (int)($_POST['gimnasio_id'] ?? 0);
  $nombre   = trim((string)($_POST['nombre'] ?? ''));
  $apellido = trim((string)($_POST['apellido'] ?? ''));
  $dni      = preg_replace('/\D+/','', (string)($_POST['dni'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));

  if ($gimnasio_id<=0){ $error="Falta seleccionar gimnasio."; }
  elseif ($dni===''){   $error="Falta DNI."; }
  elseif ($nombre===''){ $error="Falta nombre."; }
  else {
    /* Si ya existe ese DNI en el gimnasio, NO insertamos ni duplicamos:
       mostramos/aceptamos ese registro y seguimos. */
    $exist = find_client_by_dni($conexion, $gimnasio_id, $dni);
    if ($exist) {
      $cid = (int)$exist['id'];
      // (Opcional) Si el usuario cambió datos, podés habilitar actualización suave.
      // En este fix, por defecto NO sobreescribimos para evitar confusiones.
    } else {
      // Crear nuevo cliente sólo si no existe DNI en ese gimnasio
      $sql = "INSERT INTO clientes (nombre, apellido, dni, telefono, email, gimnasio_id)
              VALUES (".qv($conexion,$nombre).", ".qv($conexion,$apellido).",
                      ".qv($conexion,$dni).", ".qv($conexion,$telefono).",
                      ".qv($conexion,$email).", {$gimnasio_id})";
      if (!$conexion->query($sql)) {
        $error = "No se pudo registrar el cliente. (".$conexion->error.")";
      }
      $cid = (int)$conexion->insert_id;
    }

    if (empty($error)) {
      // Iniciar sesión del cliente y volver (NO marcamos ingreso)
      $_SESSION['cliente_id']  = (int)$cid;
      $_SESSION['gimnasio_id'] = (int)$gimnasio_id;

      $fallback = '/gym_qr_checkin.php?g='.$gimnasio_id;
      $dest = $return_url !== '' ? $return_url : $fallback;
      if (!headers_sent()) { header("Location: ".$dest, true, 302); exit; }
      echo '<meta http-equiv="refresh" content="0;url='.h($dest).'">'; exit;
    }
  }
}

/* ====== UI ====== */
$css = "
:root{--bg:#0c0c0d;--card:#141416;--ink:#eaeaea;--muted:#9aa0a6;--stroke:#222;--accent:#6ea8fe;--ok:#134e4a;--okbd:#065f46;}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:var(--bg);color:var(--ink)}
.wrap{max-width:520px;margin:0 auto;padding:18px}
.card{background:var(--card);border:1px solid var(--stroke);border-radius:16px;padding:18px}
.brand{display:flex;gap:12px;align-items:center;justify-content:center;margin-bottom:12px}
.brand img{width:56px;height:56px;object-fit:cover;border-radius:12px;background:#fff;border:1px solid #333}
.brand .name{font-size:22px;font-weight:800;letter-spacing:.2px;color:#fff}
h1{font-size:18px;margin:8px 0 14px;text-align:center;color:#cbd5e1}
label{display:block;margin:8px 0 6px;color:#cbd5e1;font-size:14px}
input,select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #303038;background:#101012;color:#fff;outline:none}
input:focus,select:focus{border-color:var(--accent)}
.btn{width:100%;padding:14px 16px;border-radius:12px;border:0;background:var(--accent);color:#000;font-weight:800;font-size:18px;margin-top:14px;cursor:pointer}
.flash{padding:12px 14px;border-radius:12px;margin-bottom:12px;font-size:14px}
.err{background:#5b1111;color:#ffd3d3;border:1px solid #7f1d1d}
.ok{background:var(--ok);border:1px solid var(--okbd);color:#d1fae5}
.help{font-size:12px;color:var(--muted);margin-top:10px;text-align:center}
.small{font-size:12px;color:#cbd5e1}
";

function logo_url(?string $logo): ?string {
  if (!$logo) return null;
  if (preg_match('#^(https?:)?//#i', $logo) || (function_exists('str_starts_with') && str_starts_with($logo,'data:'))) return $logo;
  $candidatos = [
    __DIR__ . '/uploads/gimnasios/' . $logo => '/uploads/gimnasios/' . rawurlencode($logo),
    __DIR__ . '/img/' . $logo              => '/img/' . rawurlencode($logo),
    __DIR__ . '/' . ltrim($logo, '/\\')    => '/' . ltrim($logo, '/\\'),
  ];
  foreach ($candidatos as $fs=>$url) if (is_file($fs)) return $url.'?v='.(int)@filemtime($fs);
  return $logo;
}
$logoGym   = null;
$nombreGym = 'Gimnasio';
if (!empty($gym)) {
  $logoGym   = logo_url($gym['logo'] ?? null);
  $nombreGym = $gym['nombre'] ?? 'Gimnasio';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Registro Online · <?= h($nombreGym) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">
<meta name="color-scheme" content="dark">
<style><?= $css ?></style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">
        <?php if ($logoGym): ?><img src="<?= h($logoGym) ?>" alt="Logo <?= h($nombreGym) ?>" loading="lazy" decoding="async"><?php endif; ?>
        <div class="name"><?= h($nombreGym) ?></div>
      </div>
      <h1>Registro de Cliente</h1>

      <?php if (!empty($error)): ?>
        <div class="flash err">⚠️ <?= h($error) ?></div>
      <?php endif; ?>

      <div id="existsBox" class="flash ok" style="display:none"></div>

      <form id="frm" method="post" novalidate>
        <input type="hidden" name="return" value="<?= h($return_url) ?>">
        <label>Gimnasio</label>
        <?php if ($gym): ?>
          <input type="hidden" name="gimnasio_id" id="gimnasio_id" value="<?= (int)$gimnasio_id ?>">
          <input value="#<?= (int)$gym['id'] ?> · <?= h($nombreGym) ?>" readonly>
        <?php else: ?>
          <select name="gimnasio_id" id="gimnasio_id" required>
            <option value="">Seleccioná tu gimnasio…</option>
            <?php foreach(($gimnasios ?? []) as $g): ?>
              <option value="<?= (int)$g['id'] ?>" <?= ($g['id']==$gimnasio_id?'selected':'') ?>>#<?= (int)$g['id'] ?> · <?= h($g['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <label>DNI</label>
        <input name="dni" id="dni" inputmode="numeric" pattern="[0-9]*" value="<?= h($dni_pref) ?>" placeholder="Ej: 35123456" required>

        <label>Nombre</label>
        <input name="nombre" id="nombre" placeholder="Tu nombre" required>

        <label>Apellido</label>
        <input name="apellido" id="apellido" placeholder="Tu apellido" required>

        <label>Teléfono (WhatsApp) <span class="small">(opcional)</span></label>
        <input name="telefono" id="telefono" placeholder="Ej: 2664 123456">

        <label>Email <span class="small">(opcional)</span></label>
        <input type="email" name="email" id="email" placeholder="tu@correo.com">

        <button class="btn" type="submit" id="btnSubmit">Registrarme</button>
        <div class="help">Al registrarte aceptás las políticas del gimnasio.</div>
      </form>

      <div class="help" style="margin-top:14px">
        <!-- Sugerencia de protección a nivel BD:
        Crear índice único para evitar duplicados reales:
        ALTER TABLE clientes ADD UNIQUE KEY ux_gym_dni (gimnasio_id, dni);
        -->
      </div>
    </div>
  </div>

<script>
(function(){
  const $ = sel => document.querySelector(sel);
  const dni = $('#dni'), gid = $('#gimnasio_id');
  const nombre = $('#nombre'), apellido = $('#apellido'), tel = $('#telefono'), email = $('#email');
  const box = $('#existsBox'), btn = $('#btnSubmit');

  function setReadonly(readonly){
    [nombre, apellido, tel, email].forEach(i => i.readOnly = readonly);
  }
  function fill(data){
    if (!data) return;
    nombre.value = data.nombre || '';
    apellido.value = data.apellido || '';
    tel.value = data.telefono || '';
    email.value = data.email || '';
  }
  async function check(){
    box.style.display = 'none';
    setReadonly(false);
    btn.textContent = 'Registrarme';

    const vgid = (gid && gid.value) ? gid.value : '0';
    const vdni = (dni.value||'').replace(/\D+/g,'');
    if (!vgid || !vdni) return;

    try{
      const url = `?ajax=dni_check&gimnasio_id=${encodeURIComponent(vgid)}&dni=${encodeURIComponent(vdni)}`;
      const r = await fetch(url, {credentials:'same-origin'});
      const j = await r.json();
      if (j && j.exists) {
        fill(j.data);
        setReadonly(true);
        box.innerHTML = '✅ <strong>DNI ya registrado.</strong> Usaremos el registro existente y te vamos a ingresar con esos datos.';
        box.style.display = '';
        btn.textContent = 'Continuar';
      }
    }catch(e){/* silencioso */}
  }

  if (dni) dni.addEventListener('blur', check);
  if (gid) gid.addEventListener('change', check);

  // Si ya vino el DNI por GET, disparar chequeo inicial:
  if (dni && dni.value) { check(); }
})();
</script>
</body>
</html>
