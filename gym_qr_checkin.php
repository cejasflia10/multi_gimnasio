<?php
/* ==========================================================
   gym_qr_checkin.php — Check-in móvil por DNI (COMPLETO)
   • Encabezado con logo + nombre del gimnasio (look original).
   • Inserta en accesos_gimnasio: metodo='QR-GYM/DNI', fecha_ingreso=NOW().
   • Descuenta 1 clase de membresías activas (si hay cupo) y loguea en membresia_consumos.
   • Devuelve JSON en ?ajax=1 (para el front).
   • Si la URL trae ?dni=..., auto-marca al cargar.
   Requisitos de tablas (nombres fijos):
     - clientes(id, dni, gimnasio_id, nombre, apellido)
     - accesos_gimnasio(id, gimnasio_id, cliente_id, metodo, fecha_ingreso)
     - membresias(id, gimnasio_id, cliente_id, plan, clases_disponibles, activa, fecha_vencimiento)
     - membresia_consumos(id, membresia_id, acceso_id, cliente_id, gimnasio_id, fecha_consumo, metodo)
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

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

/* ==== Parámetros base ==== */
$gimnasio_id = (int)($_GET['g'] ?? ($_POST['g'] ?? ($_SESSION['gimnasio_id'] ?? 0)));
if ($gimnasio_id<=0){ http_response_code(400); exit('Falta g'); }

/* ==== Marca gimnasio (para el header móvil) ==== */
$gym_name = 'Gimnasio'; $gym_logo = null;
if ($rs = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1")){
  if ($rs->num_rows){ $g=$rs->fetch_assoc();
    if (!empty($g['nombre'])) $gym_name = $g['nombre'];
    if (!empty($g['logo']))   $gym_logo = logo_url($g['logo']);
  }
}

/* ==== Rate limit 8s por DNI (evitar doble escaneo) ==== */
if (empty($_SESSION['qr_rate'])) $_SESSION['qr_rate'] = [];

/* ==== Handler AJAX: insertar + descontar ==== */
if (isset($_GET['ajax']) && $_GET['ajax']=='1'){
  while(ob_get_level()) ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');

  $dni = trim((string)($_POST['dni'] ?? $_GET['dni'] ?? ''));
  if ($dni===''){ echo json_encode(['ok'=>false,'msg'=>'Ingresá un DNI']); exit; }

  if (!empty($_SESSION['qr_rate'][$dni]) && (time()-$_SESSION['qr_rate'][$dni])<8){
    echo json_encode(['ok'=>true,'repeat'=>true,'msg'=>'⏱️ Ya estaba marcado hace instantes.']); exit;
  }

  // Buscar cliente por DNI + GYM
  $dni_esc = $conexion->real_escape_string($dni);
  $sqlC = "SELECT id, nombre, apellido FROM clientes WHERE dni='$dni_esc' AND gimnasio_id=$gimnasio_id LIMIT 1";
  $rc = $conexion->query($sqlC);
  if (!$rc || !$rc->num_rows){
    $reg_url = "registro_online.php?g=".$gimnasio_id."&dni=".rawurlencode($dni);
    echo json_encode(['ok'=>false,'no_reg'=>true,'msg'=>'No encontrado.','reg_url'=>$reg_url,'debug_sql'=>$sqlC]); exit;
  }
  $cli = $rc->fetch_assoc(); $cliente_id = (int)$cli['id'];

  $conexion->begin_transaction();
  try {
    // 1) Insert acceso (lo que lee el panel)
    $metodo = "QR-GYM/DNI";
    $sqlI = "INSERT INTO accesos_gimnasio (gimnasio_id, cliente_id, metodo, fecha_ingreso)
             VALUES ($gimnasio_id, $cliente_id, '$metodo', NOW())";
    if (!$conexion->query($sqlI)) throw new Exception("Insert acceso: ".$conexion->error);
    $acceso_id = (int)$conexion->insert_id;

    // 2) Descontar 1 clase si hay membresía activa no vencida con cupo
    $plan=null; $vence=null; $rest=null; $descuento_ok=false;
    $hoy = date('Y-m-d');
    $sqlM = "SELECT id, plan, clases_disponibles, fecha_vencimiento
             FROM membresias
             WHERE gimnasio_id=$gimnasio_id
               AND cliente_id=$cliente_id
               AND (activa=1 OR activa='1')
               AND (fecha_vencimiento IS NULL OR fecha_vencimiento='' OR fecha_vencimiento='0000-00-00' OR fecha_vencimiento >= '$hoy')
             ORDER BY COALESCE(fecha_vencimiento,'9999-12-31') DESC, id DESC
             LIMIT 1 FOR UPDATE";
    if ($rm=$conexion->query($sqlM)){
      if ($m=$rm->fetch_assoc()){
        $membresia_id = (int)$m['id'];
        $plan  = $m['plan'] ?? null;
        $vence = $m['fecha_vencimiento'] ?? null;
        $rest  = (int)$m['clases_disponibles'];

        if ($rest > 0){
          $sqlU = "UPDATE membresias SET clases_disponibles = clases_disponibles - 1
                   WHERE id=$membresia_id AND clases_disponibles > 0";
          if (!$conexion->query($sqlU)) throw new Exception("Update membresía: ".$conexion->error);
          if ($conexion->affected_rows>0){
            $descuento_ok = true;
            $rest -= 1;

            // 3) Log de consumo
            $sqlL = "INSERT INTO membresia_consumos (membresia_id, acceso_id, cliente_id, gimnasio_id, fecha_consumo, metodo)
                     VALUES ($membresia_id, $acceso_id, $cliente_id, $gimnasio_id, NOW(), '$metodo')";
            if (!$conexion->query($sqlL)) throw new Exception("Insert consumo: ".$conexion->error);
          }
        }
      }
    }

    $conexion->commit();
    $_SESSION['qr_rate'][$dni] = time();

    echo json_encode([
      'ok'       => true,
      'inserted' => true,
      'msg'      => $descuento_ok ? '✅ Ingreso registrado y clase descontada' : '✅ Ingreso registrado',
      'cliente'  => ['id'=>$cliente_id,'nombre'=>trim(($cli['apellido']??'').' '.($cli['nombre']??''))],
      'membresia'=> ['plan'=>$plan,'vence'=>$vence,'clases_restantes'=>($rest===null?null:$rest)]
    ], JSON_UNESCAPED_UNICODE);
    exit;

  } catch (Throwable $e){
    $conexion->rollback();
    echo json_encode(['ok'=>false,'inserted'=>false,'msg'=>'❌ Error procesando ingreso','error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

/* ===== UI móvil (con marca del gym) ===== */
$dni_qr = trim((string)($_GET['dni'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Ingreso · <?= h($gym_name) ?></title>
<style>
  :root{ color-scheme:dark; }
  html,body{height:100%}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#0f0f10;color:#e6e6e6}
  .wrap{min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:18px}
  .card{width:100%;max-width:460px;background:#141414;border:1px solid #222;border-radius:18px;padding:18px;box-shadow:0 8px 24px rgba(0,0,0,.35)}
  .brand{display:flex;align-items:center;gap:10px;margin-bottom:4px}
  .brand img{width:40px;height:40px;object-fit:cover;border-radius:10px;background:#fff;border:1px solid #2a2a2a}
  .brand .name{font-weight:900;font-size:18px;letter-spacing:.2px}
  h1{font-size:20px;margin:8px 0 6px}
  .muted{opacity:.85;font-size:14px;margin:0 0 12px}
  .row{display:flex;gap:8px}
  input{flex:1;background:#1b1b1b;border:1px solid #333;border-radius:14px;padding:14px;color:#eee;font-size:18px}
  button{background:#2a2a2a;border:1px solid #3a3a3a;border-radius:14px;padding:14px 16px;color:#fff;font-weight:700;letter-spacing:.2px;min-width:110px}
  .adv{margin-top:10px;font-size:13px;opacity:.95;display:flex;align-items:center;gap:8px}
  .result{margin-top:12px;background:#101010;border:1px solid #262626;border-radius:14px;padding:12px}
  .big{font-size:20px;margin-bottom:6px}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#1d1d1d;border:1px solid #333;font-size:12px}
  .ok{color:#8bd16a}.warn{color:#f0c36d}.bad{color:#e57373}
  .center{display:flex;justify-content:center}
  .reg{margin-top:10px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="brand">
      <?php if ($gym_logo): ?><img src="<?= h($gym_logo) ?>" alt="Logo"><?= endif; ?>
      <div class="name"><?= h($gym_name) ?></div>
    </div>
    <h1>Ingreso por DNI</h1>
    <p class="muted">Escaneá el QR (abre esta página con tu DNI) o escribilo y tocá “Marcar”.</p>

    <form class="row" onsubmit="enviar(event)" autocomplete="off">
      <input id="dni" inputmode="numeric" autocomplete="one-time-code" placeholder="DNI" value="<?= h($dni_qr) ?>">
      <button type="submit">Marcar</button>
    </form>

    <div id="adv" class="adv"></div>

    <div id="res" class="result" hidden>
      <div class="big" id="cli">Cliente</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <div>Plan: <span id="plan" class="pill">—</span></div>
        <div>Clases: <span id="restantes" class="pill">—</span></div>
        <div>Vence: <span id="vence" class="">—</span></div>
      </div>
    </div>

    <div id="reg" class="center reg" hidden>
      <a id="regurl" class="pill" href="#" target="_blank" rel="noopener">📝 Registrarme</a>
    </div>
  </div>
</div>

<script>
const G = <?= (int)$gimnasio_id ?>;

async function marcar(dni){
  const adv = document.getElementById('adv');
  const res = document.getElementById('res');
  const reg = document.getElementById('reg');
  adv.textContent = 'Marcando ingreso…';
  res.hidden = true; reg.hidden = true;

  const q = new URLSearchParams({ajax:'1', g:String(G), dni:String(dni)});
  const r = await fetch(location.pathname + '?' + q.toString(), {method:'GET', cache:'no-store'});
  let j; try{ j = await r.json(); }catch(e){ adv.textContent = 'Error de red.'; return; }

  if(!j.ok && !j.no_reg){
    adv.textContent = (j.msg||'Error.') + (j.error?(' · '+j.error):'');
    console.log(j); return;
  }
  if(j.no_reg){
    adv.textContent = 'No encontramos ese DNI.';
    reg.hidden = false;
    document.getElementById('regurl').href = j.reg_url;
    return;
  }

  adv.innerHTML = '<span class="ok">☑</span> ' + (j.msg || 'Hecho.');
  res.hidden = false;

  document.getElementById('cli').textContent =
    (j.cliente && j.cliente.nombre) ? j.cliente.nombre : 'Cliente';

  const m = j.membresia || {};
  const plan = m.plan || '—';
  const vence = m.vence ? new Date(String(m.vence).replace(' ','T')) : null;
  const venTxt = vence ? vence.toLocaleDateString() : '—';
  document.getElementById('plan').textContent = plan;
  document.getElementById('vence').textContent = venTxt;

  const rest = (typeof m.clases_restantes === 'number') ? m.clases_restantes : null;
  const restEl = document.getElementById('restantes');
  if (rest===null){ restEl.textContent='—'; restEl.className='pill'; }
  else { restEl.textContent=rest; restEl.className='pill' + (rest<=0?' bad':(rest<=2?' warn':'')); }
}

async function enviar(ev){
  ev.preventDefault();
  const i = document.getElementById('dni');
  const dni = (i.value||'').trim();
  if(!dni){ i.focus(); return; }
  await marcar(dni);
  i.value=''; i.focus();
}

// Auto-marca si viene ?dni= en la URL del QR
window.addEventListener('load', ()=>{
  const i = document.getElementById('dni');
  i.focus();
  const url = new URL(location.href);
  const dni = url.searchParams.get('dni');
  if (dni) marcar(dni);
});
</script>
</body>
</html>
