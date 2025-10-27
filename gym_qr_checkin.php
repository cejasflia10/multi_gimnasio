<?php
/* ==========================================================
   gym_qr_checkin.php — Check-in por DNI (público/mostrador)
   • Si el cliente existe (dni + gimnasio_id) → crea registro
     en accesos_gimnasio (metodo='QR') y responde datos.
   • Si NO existe → ofrece ir a registro_online.php?g=...
   • Anti-doble envío: ignora repetición del mismo DNI por
     10 segundos y evita múltiples inserciones en bucle.
   • NO descuenta clases ni marca asistencia (eso es en
     registrar_asistencia.php). Aquí sólo es “ingreso por QR”.
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

date_default_timezone_set('America/Argentina/San_Luis');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function logo_url(?string $logo): ?string {
  $logo = trim((string)$logo);
  if ($logo==='') return null;
  if (preg_match('#^(https?:)?//#i', $logo) || str_starts_with($logo,'data:')) return $logo;
  $cands = [
    __DIR__ . '/uploads/gimnasios/' . $logo => '/uploads/gimnasios/' . rawurlencode($logo),
    __DIR__ . '/img/' . $logo              => '/img/' . rawurlencode($logo),
    __DIR__ . '/' . ltrim($logo,'/\\')     => '/' . ltrim($logo,'/\\'),
  ];
  foreach ($cands as $fs=>$url) if (is_file($fs)) return $url.'?v='.(int)@filemtime($fs);
  return $logo;
}

/* ===== Gimnasio actual ===== */
$gimnasio_id = (int)($_GET['g'] ?? ($_SESSION['gimnasio_id'] ?? 0));
if ($gimnasio_id<=0){ http_response_code(400); exit('Gimnasio no registrado'); }

/* ===== Datos para marca (logo + nombre) ===== */
$gym_name = 'Gimnasio'; $gym_logo = null;
if ($rs = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1")){
  if ($rs->num_rows){ $g=$rs->fetch_assoc();
    if (!empty($g['nombre'])) $gym_name = $g['nombre'];
    if (!empty($g['logo']))   $gym_logo = logo_url($g['logo']);
  }
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_qr'])) $_SESSION['csrf_qr'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_qr'];

/* ===== Anti-doble envío (10s misma persona) ===== */
if (empty($_SESSION['qr_rate'])) $_SESSION['qr_rate'] = []; // dni => ts

/* ===== AJAX ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['ajax'])){
  while(ob_get_level()) ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');

  $dni  = trim((string)($_POST['dni'] ?? ''));
  $csrf_in = (string)($_POST['csrf'] ?? '');
  if (!hash_equals($csrf, $csrf_in)) {
    echo json_encode(['ok'=>false,'msg'=>'❌ CSRF inválido. Refrescá la página.']); exit;
  }
  if ($dni===''){ echo json_encode(['ok'=>false,'msg'=>'Ingresá un DNI.']); exit; }

  // Simple rate limit
  $now=time();
  $last = (int)($_SESSION['qr_rate'][$dni] ?? 0);
  if ($now - $last < 10){
    echo json_encode(['ok'=>false,'msg'=>'⌛ Aguarda un momento antes de volver a enviar.']); exit;
  }

  // Buscar cliente por DNI + gimnasio
  $st = $conexion->prepare("SELECT id, apellido, nombre FROM clientes WHERE dni=? AND gimnasio_id=? LIMIT 1");
  $st->bind_param("si",$dni,$gimnasio_id);
  $st->execute();
  $cli = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$cli){
    echo json_encode([
      'ok'=>false,
      'registro'=>true,
      'msg'=>'No encontramos este DNI en el gimnasio.',
      'url_registro'=>'registro_online.php?g='.$gimnasio_id.'&dni='.rawurlencode($dni)
    ]);
    exit;
  }

  $cliente_id = (int)$cli['id'];

  // Evitar “tormenta” de inserts: ¿ya hay un acceso muy reciente?
  $chk = $conexion->prepare("
    SELECT id FROM accesos_gimnasio
     WHERE gimnasio_id=? AND cliente_id=? AND TIMESTAMPDIFF(SECOND, fecha_ingreso, NOW()) <= 10
     ORDER BY id DESC LIMIT 1
  ");
  $chk->bind_param("ii", $gimnasio_id, $cliente_id);
  $chk->execute();
  $reciente = (bool)$chk->get_result()->fetch_assoc();
  $chk->close();

  if (!$reciente){
    $ins = $conexion->prepare("
      INSERT INTO accesos_gimnasio (gimnasio_id, cliente_id, fecha_ingreso, metodo)
      VALUES (?, ?, NOW(), 'QR')
    ");
    $ins->bind_param("ii", $gimnasio_id, $cliente_id);
    $ins->execute();
    $ins->close();
  }

  $_SESSION['qr_rate'][$dni] = $now;

  $nombre = trim(($cli['apellido'] ?? '').' '.($cli['nombre'] ?? ''));
  echo json_encode([
    'ok'=>true,
    'msg'=>"✅ Ingreso registrado para {$nombre}.",
    'cliente'=>['id'=>$cliente_id,'nombre'=>$nombre]
  ]);
  exit;
}

/* ===== UI ===== */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Check-in QR · <?= h($gym_name) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{ --stroke:#e5e7eb; --ink:#0f172a; --mut:#64748b; --bg:#fafafa; --ok:#16a34a; --err:#b91c1c; }
  *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;background:var(--bg);color:var(--ink)}
  .wrap{max-width:520px;margin:20px auto;padding:0 14px}
  .card{background:#fff;border:1px solid var(--stroke);border-radius:16px;box-shadow:0 10px 24px rgba(2,6,23,.06);padding:16px}
  .brand{display:flex;align-items:center;gap:12px;margin-bottom:8px}
  .brand img{width:56px;height:56px;object-fit:cover;border-radius:12px;background:#fff;border:1px solid var(--stroke)}
  .brand h1{margin:0;font-size:18px;font-weight:900;letter-spacing:.3px}
  .mut{color:var(--mut);font-size:12px;margin-top:2px}
  .scan input{width:100%;padding:14px 14px;font-size:20px;border:1px solid var(--stroke);border-radius:12px}
  .btn{margin-top:10px;width:100%;padding:12px;border-radius:12px;border:1px solid var(--stroke);background:#f8fafc;font-weight:800;cursor:pointer}
  .adv{margin-top:10px;font-weight:800}
  .ok{color:var(--ok)} .err{color:var(--err)}
  .row{display:flex;gap:8px;margin-top:10px}
  .row .btn{flex:1}
</style>
<script>
  const AJAX = <?= json_encode(basename(__FILE__).'?ajax=1&g='.$gimnasio_id) ?>;
  const CSRF = <?= json_encode($csrf) ?>;

  function focusDni(){ const i=document.getElementById('dni'); if(i) i.focus({preventScroll:true}); }
  function enviar(e){
    e.preventDefault();
    const dni = (document.getElementById('dni').value || '').trim();
    if(!dni){ focusDni(); return; }
    const fd=new FormData(); fd.append('dni',dni); fd.append('csrf',CSRF);
    fetch(AJAX, {method:'POST', body:fd, cache:'no-store'})
      .then(r=>r.json())
      .then(j=>{
        const adv=document.getElementById('adv');
        if(!j){ adv.textContent='⚠️ Error inesperado'; adv.className='adv err'; return; }
        adv.textContent = j.msg || '';
        adv.className = 'adv ' + (j.ok ? 'ok' : 'err');
        if(!j.ok && j.registro && j.url_registro){
          const reg = document.getElementById('reg');
          reg.hidden = false;
          reg.querySelector('a').href = j.url_registro;
        } else {
          document.getElementById('reg').hidden = true;
        }
        // limpiar campo y esperar nuevo escaneo
        document.getElementById('dni').value='';
        focusDni();
      })
      .catch(()=>{
        const adv=document.getElementById('adv');
        adv.textContent='⚠️ Problema de conexión'; adv.className='adv err';
      });
  }

  window.addEventListener('load', () => { focusDni(); });
  document.addEventListener('visibilitychange', ()=>{ if(!document.hidden) focusDni(); });
</script>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">
        <?php if ($gym_logo): ?><img src="<?= h($gym_logo) ?>" alt="logo"><?php endif; ?>
        <div>
          <h1><?= h($gym_name) ?></h1>
          <div class="mut">Check-in por DNI (método: QR)</div>
        </div>
      </div>

      <form class="scan" onsubmit="enviar(event)" autocomplete="off">
        <input id="dni" inputmode="numeric" autocomplete="one-time-code" placeholder="Ingresar DNI y Enter…">
        <button class="btn" type="submit">Marcar ingreso</button>
      </form>

      <div id="adv" class="adv"></div>

      <div id="reg" class="row" hidden>
        <a class="btn" href="#" target="_blank" rel="noopener">📝 Registrarme</a>
      </div>
    </div>
  </div>
</body>
</html>
