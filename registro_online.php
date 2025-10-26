<?php
/* ============================================================
   registro_online.php — Alta rápida de cliente (MultiGimnasio)
   Acepta g ó gimnasio_id, prellena DNI, crea/actualiza cliente
   y redirige a return (o al QR). NO marca ingreso.
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function qv($db,$s){ return "'".$db->real_escape_string((string)$s)."'"; }

$gimnasio_id = (int)($_GET['gimnasio_id'] ?? $_GET['g'] ?? 0);
$dni_pref    = preg_replace('/\D+/','', (string)($_GET['dni'] ?? ''));
$return_url  = (string)($_GET['return'] ?? '');

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

/* === POST: guardar alta/actualización === */
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
    // ¿ya existe?
    $q = "SELECT id FROM clientes WHERE dni=".qv($conexion,$dni)." AND gimnasio_id={$gimnasio_id} LIMIT 1";
    $rs = $conexion->query($q);
    if ($rs && $rs->num_rows){
      $row = $rs->fetch_assoc();
      $cid = (int)$row['id'];
      $conexion->query("UPDATE clientes SET
                          nombre=".qv($conexion,$nombre).",
                          apellido=".qv($conexion,$apellido).",
                          telefono=".qv($conexion,$telefono).",
                          email=".qv($conexion,$email)."
                        WHERE id={$cid} LIMIT 1");
    } else {
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
:root{--bg:#0c0c0d;--card:#141416;--ink:#eaeaea;--muted:#9aa0a6;--stroke:#222;--accent:#6ea8fe;}
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
.help{font-size:12px;color:var(--muted);margin-top:10px;text-align:center}
";

function logo_url(?string $logo): ?string {
  if (!$logo) return null;
  if (preg_match('#^(https?:)?//#i', $logo) || str_starts_with($logo,'data:')) return $logo;
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

      <form method="post" novalidate>
        <input type="hidden" name="return" value="<?= h($return_url) ?>">
        <label>Gimnasio</label>
        <?php if ($gym): ?>
          <input type="hidden" name="gimnasio_id" value="<?= (int)$gimnasio_id ?>">
          <input value="#<?= (int)$gym['id'] ?> · <?= h($nombreGym) ?>" readonly>
        <?php else: ?>
          <select name="gimnasio_id" required>
            <option value="">Seleccioná tu gimnasio…</option>
            <?php foreach(($gimnasios ?? []) as $g): ?>
              <option value="<?= (int)$g['id'] ?>" <?= ($g['id']==$gimnasio_id?'selected':'') ?>>#<?= (int)$g['id'] ?> · <?= h($g['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <label>DNI</label>
        <input name="dni" inputmode="numeric" pattern="[0-9]*" value="<?= h($dni_pref) ?>" placeholder="Ej: 35123456" required>

        <label>Nombre</label>
        <input name="nombre" placeholder="Tu nombre" required>

        <label>Apellido</label>
        <input name="apellido" placeholder="Tu apellido" required>

        <label>Teléfono (WhatsApp)</label>
        <input name="telefono" placeholder="Ej: 2664 123456">

        <label>Email</label>
        <input type="email" name="email" placeholder="tu@correo.com">

        <button class="btn" type="submit">Registrarme</button>
        <div class="help">Al registrarte aceptás las políticas del gimnasio.</div>
      </form>
    </div>
  </div>
</body>
</html>
