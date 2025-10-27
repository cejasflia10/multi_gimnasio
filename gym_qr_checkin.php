<?php
/* ==========================================================
   gym_qr_checkin.php — Check-in móvil por DNI (con reglas)
   • Tema claro (blanco), pensado para celulares.
   • Muestra plan, clases disponibles y vencimiento.
   • Reglas:
       - Sin membresía / vencida / sin clases -> DENEGADO.
       - Fuera de horario personalizado       -> DENEGADO.
       - Caso OK -> AUTORIZADO: inserta, descuenta y loguea consumo.
     En denegado se registra intento con metodo='QR-DENEGADO' (sin descuento).
   Tablas requeridas:
     - clientes(id, dni, gimnasio_id, nombre, apellido, [horario_desde TIME, horario_hasta TIME] opcional)
     - accesos_gimnasio(id, gimnasio_id, cliente_id, metodo, fecha_ingreso)
     - membresias(id, gimnasio_id, cliente_id, plan, clases_disponibles, activa, fecha_vencimiento)
     - membresia_consumos(id, membresia_id, acceso_id, cliente_id, gimnasio_id, fecha_consumo, metodo)
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ==== Helpers ==== */
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
function col_exists(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $rs=$db->query("SHOW COLUMNS FROM `$t` LIKE '$c'"); return $rs && $rs->num_rows>0;
}
function pick_col(mysqli $db, string $t, array $cands, ?string $fallback=null){
  foreach($cands as $c) if (col_exists($db,$t,$c)) return $c;
  return $fallback;
}

/* ==== Parámetros base ==== */
$gimnasio_id = (int)($_GET['g'] ?? ($_POST['g'] ?? ($_SESSION['gimnasio_id'] ?? 0)));
if ($gimnasio_id<=0){ http_response_code(400); exit('Falta g'); }

/* ==== Marca gym (logo + nombre) ==== */
$gym_name = 'Gimnasio'; $gym_logo = null;
if ($rs = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1")){
  if ($rs->num_rows){ $g=$rs->fetch_assoc();
    if (!empty($g['nombre'])) $gym_name = $g['nombre'];
    if (!empty($g['logo']))   $gym_logo = logo_url($g['logo']);
  }
}

/* ==== Rate limit 8s por DNI ==== */
if (empty($_SESSION['qr_rate'])) $_SESSION['qr_rate'] = [];

/* ==========================================================
   AJAX: validar reglas + (AUTORIZADO -> inserta/desc) | (DENEGADO -> log intento)
   ========================================================== */
if (isset($_GET['ajax']) && $_GET['ajax']=='1'){
  while(ob_get_level()) ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate');

  $dni = trim((string)($_POST['dni'] ?? $_GET['dni'] ?? ''));
  if ($dni===''){ echo json_encode(['ok'=>false,'msg'=>'Ingresá un DNI']); exit; }

  if (!empty($_SESSION['qr_rate'][$dni]) && (time()-$_SESSION['qr_rate'][$dni])<8){
    echo json_encode(['ok'=>true,'repeat'=>true,'msg'=>'⏱️ Ya estaba marcado hace instantes.']); exit;
  }

  /* === 1) Buscar cliente === */
  $dni_esc = $conexion->real_escape_string($dni);
  $sqlC = "SELECT id, nombre, apellido FROM clientes WHERE dni='$dni_esc' AND gimnasio_id=$gimnasio_id LIMIT 1";
  $rc = $conexion->query($sqlC);
  if (!$rc || !$rc->num_rows){
    $reg_url = "registro_online.php?g=".$gimnasio_id."&dni=".rawurlencode($dni);
    echo json_encode(['ok'=>false,'no_reg'=>true,'msg'=>'No encontrado.','reg_url'=>$reg_url]); exit;
  }
  $cli = $rc->fetch_assoc(); $cliente_id = (int)$cli['id'];

  /* === 2) (Opcional) Horario personalizado en clientes === */
  $C_TABLE = 'clientes';
  $COL_HDESDE = pick_col($conexion, $C_TABLE, ['horario_desde','hora_desde']);
  $COL_HHASTA = pick_col($conexion, $C_TABLE, ['horario_hasta','hora_hasta']);
  $hor_desde = null; $hor_hasta = null;
  if ($COL_HDESDE && $COL_HHASTA){
    $rsH = $conexion->query("SELECT $COL_HDESDE AS desde, $COL_HHASTA AS hasta FROM $C_TABLE WHERE id=$cliente_id LIMIT 1");
    if ($rsH && $rsH->num_rows){
      $HH = $rsH->fetch_assoc();
      $hor_desde = trim((string)$HH['desde']);
      $hor_hasta = trim((string)$HH['hasta']);
      if ($hor_desde==='') $hor_desde = null;
      if ($hor_hasta==='') $hor_hasta = null;
    }
  }

  $ahora = date('H:i:s');
  $en_horario = true; $txt_horario = null;
  if ($hor_desde && $hor_hasta){
    $txt_horario = $hor_desde.' – '.$hor_hasta;
    if ($hor_desde <= $hor_hasta){
      $en_horario = ($ahora >= $hor_desde && $ahora <= $hor_hasta);
    } else { // rango pasa medianoche (ej. 22:00–02:00)
      $en_horario = ($ahora >= $hor_desde || $ahora <= $hor_hasta);
    }
  }

  /* === 3) Buscar membresía activa y determinar reglas === */
  $hoy = date('Y-m-d');
  $plan=null; $vence=null; $rest=null; $membresia_id=null; $activa=false; $motivo=null;

  $sqlM = "SELECT id, plan, clases_disponibles, fecha_vencimiento, activa
           FROM membresias
           WHERE gimnasio_id=$gimnasio_id
             AND cliente_id=$cliente_id
           ORDER BY COALESCE(fecha_vencimiento,'9999-12-31') DESC, id DESC
           LIMIT 1";
  if ($rm = $conexion->query($sqlM)){
    if ($m = $rm->fetch_assoc()){
      $membresia_id = (int)$m['id'];
      $plan  = $m['plan'] ?? null;
      $vence = $m['fecha_vencimiento'] ?? null;
      $rest  = (int)($m['clases_disponibles'] ?? 0);
      $activa = ((string)($m['activa'] ?? '0') === '1') && (empty($vence) || $vence=='0000-00-00' || $vence >= $hoy);
      if (!$activa){
        $motivo = 'membresia_no_activa';
      } elseif ($rest <= 0){
        $motivo = 'sin_clases';
      }
    } else {
      $motivo = 'sin_membresia';
    }
  } else {
    echo json_encode(['ok'=>false,'msg'=>'Error consultando membresía']); exit;
  }

  if ($en_horario === false && !$motivo){
    $motivo = 'fuera_de_horario';
  }

  /* === 4) Autorizar/Denegar === */
  $autorizado = (!$motivo); // solo si todas las reglas pasaron

  $conexion->begin_transaction();
  try {
    if ($autorizado){
      // Insert autorizado + descuento + log
      $metodo = "QR-GYM/DNI";
      $sqlI = "INSERT INTO accesos_gimnasio (gimnasio_id, cliente_id, metodo, fecha_ingreso)
               VALUES ($gimnasio_id, $cliente_id, '$metodo', NOW())";
      if (!$conexion->query($sqlI)) throw new Exception("Insert acceso: ".$conexion->error);
      $acceso_id = (int)$conexion->insert_id;

      if ($membresia_id){
        // lock y descuento
        $sqlU = "UPDATE membresias SET clases_disponibles = clases_disponibles - 1
                 WHERE id=$membresia_id AND clases_disponibles > 0";
        if (!$conexion->query($sqlU)) throw new Exception("Update membresía: ".$conexion->error);
        if ($conexion->affected_rows>0){
          $rest = max(0, (int)$rest - 1);
          $sqlL = "INSERT INTO membresia_consumos (membresia_id, acceso_id, cliente_id, gimnasio_id, fecha_consumo, metodo)
                   VALUES ($membresia_id, $acceso_id, $cliente_id, $gimnasio_id, NOW(), '$metodo')";
          if (!$conexion->query($sqlL)) throw new Exception("Insert consumo: ".$conexion->error);
        }
      }

      $conexion->commit();
      $_SESSION['qr_rate'][$dni] = time();

      echo json_encode([
        'ok'=>true,
        'autorizado'=>true,
        'msg'=>'✅ Ingreso autorizado',
        'cliente'=>['id'=>$cliente_id,'nombre'=>trim(($cli['apellido']??'').' '.($cli['nombre']??''))],
        'membresia'=>['plan'=>$plan,'vence'=>$vence,'clases_restantes'=>$rest],
        'horario'=>['definido'=>(bool)$txt_horario,'rango'=>$txt_horario]
      ], JSON_UNESCAPED_UNICODE); exit;

    } else {
      // Denegado: registrar intento (rastro) sin descuento
      $metodo = "QR-DENEGADO";
      $sqlI = "INSERT INTO accesos_gimnasio (gimnasio_id, cliente_id, metodo, fecha_ingreso)
               VALUES ($gimnasio_id, $cliente_id, '$metodo', NOW())";
      // Si querés NO registrar intentos, comenta las 2 líneas anteriores.
      $conexion->query($sqlI); // si falla igual seguimos, no bloquea respuesta

      $conexion->commit();
      $_SESSION['qr_rate'][$dni] = time();

      // Mensaje según motivo
      $msg = '❌ Ingreso denegado';
      if ($motivo==='sin_membresia')      $msg = '❌ No tiene membresía activa. Debe cargar/renovar.';
      elseif ($motivo==='membresia_no_activa') $msg = '❌ Membresía inactiva o vencida. Debe renovar.';
      elseif ($motivo==='sin_clases')     $msg = '❌ No le quedan clases disponibles.';
      elseif ($motivo==='fuera_de_horario') $msg = '❌ Fuera de su horario permitido ('.$txt_horario.').';

      echo json_encode([
        'ok'=>true,
        'autorizado'=>false,
        'motivo'=>$motivo,
        'msg'=>$msg,
        'cliente'=>['id'=>$cliente_id,'nombre'=>trim(($cli['apellido']??'').' '.($cli['nombre']??''))],
        'membresia'=>['plan'=>$plan,'vence'=>$vence,'clases_restantes'=>$rest],
        'horario'=>['definido'=>(bool)$txt_horario,'rango'=>$txt_horario]
      ], JSON_UNESCAPED_UNICODE); exit;
    }
  } catch (Throwable $e){
    $conexion->rollback();
    echo json_encode(['ok'=>false,'msg'=>'❌ Error procesando ingreso','error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); exit;
  }
}

/* ====== UI móvil (tema claro) ====== */
$dni_qr = trim((string)($_GET['dni'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#ffffff">
<title>Ingreso · <?= h($gym_name) ?></title>
<style>
  :root{ color-scheme: light; }
  html,body{height:100%}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#f7f7f9;color:#222}
  .wrap{min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
  .card{width:100%;max-width:520px;background:#fff;border:1px solid #e6e6ea;border-radius:18px;padding:18px;box-shadow:0 8px 28px rgba(0,0,0,.06)}
  .brand{display:flex;align-items:center;gap:12px;margin-bottom:6px}
  .brand img{width:44px;height:44px;object-fit:cover;border-radius:12px;background:#fff;border:1px solid #e6e6ea}
  .brand .name{font-weight:900;font-size:18px;letter-spacing:.2px}
  h1{font-size:22px;margin:10px 0 6px}
  .muted{opacity:.75;font-size:14px;margin:0 0 14px}
  .row{display:flex;gap:8px}
  input{flex:1;background:#fff;border:1.5px solid #d7d7dc;outline:none;border-radius:14px;padding:14px;color:#222;font-size:18px;transition:border-color .15s, box-shadow .15s}
  input:focus{ border-color:#4c8bf5; box-shadow:0 0 0 3px rgba(76,139,245,.18) }
  button{background:#4c8bf5;border:0;border-radius:14px;padding:14px 18px;color:#fff;font-weight:800;letter-spacing:.2px;min-width:120px;cursor:pointer;box-shadow:0 2px 0 rgba(0,0,0,.05)}
  button:active{ transform:translateY(1px) }
  .adv{margin-top:10px;font-size:14px;display:flex;align-items:center;gap:8px}
  .adv.ok{color:#1e7d3e}.adv.warn{color:#8a6d1a}.adv.bad{color:#ba3a3a}
  .result{margin-top:14px;background:#fff;border:1px solid #ececf1;border-radius:14px;padding:12px}
  .big{font-size:20px;margin-bottom:6px;font-weight:700}
  .pill{display:inline-block;padding:5px 10px;border-radius:999px;background:#f1f3f8;border:1px solid #e0e3ea;font-size:12px;font-weight:600;color:#333}
  .pill.bad{background:#fdeeee;border-color:#f7d7d7;color:#ba3a3a}
  .pill.warn{background:#fff7e5;border-color:#fde2b2;color:#8a6d1a}
  .center{display:flex;justify-content:center}
  .reg{margin-top:12px}
  .reg a{ text-decoration:none;color:#1f5fbf;background:#f1f6ff;border:1px solid #dceaff;padding:7px 12px;border-radius:999px;font-weight:700 }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="brand">
      <?php if ($gym_logo): ?><img src="<?= h($gym_logo) ?>" alt="Logo"><?php endif; ?>
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
        <div id="horarioBox" hidden>Horario: <span id="horario" class="pill">—</span></div>
      </div>
    </div>

    <div id="reg" class="center reg" hidden>
      <a id="regurl" href="#" target="_blank" rel="noopener">📝 Registrarme / Renovar</a>
    </div>
  </div>
</div>

<script>
const G = <?= (int)$gimnasio_id ?>;

async function marcar(dni){
  const adv = document.getElementById('adv');
  const res = document.getElementById('res');
  const reg = document.getElementById('reg');

  adv.className='adv';
  adv.textContent = 'Marcando ingreso…';
  res.hidden = true; reg.hidden = true;

  try {
    const q = new URLSearchParams({ajax:'1', g:String(G), dni:String(dni)});
    const r = await fetch(location.pathname + '?' + q.toString(), {method:'GET', cache:'no-store', credentials:'same-origin'});
    let j; try { j = await r.json(); } catch { adv.className='adv bad'; adv.textContent='Error de respuesta del servidor.'; return; }

    if(!j.ok && !j.no_reg){
      adv.className='adv bad';
      adv.textContent = (j.msg||'Error.') + (j.error?(' · '+j.error):'');
      console.log(j); return;
    }
    if(j.no_reg){
      adv.className='adv bad';
      adv.textContent = 'No encontramos ese DNI.';
      reg.hidden = false;
      document.getElementById('regurl').href = j.reg_url;
      return;
    }

    // Completar tarjeta (siempre)
    res.hidden = false;
    document.getElementById('cli').textContent = (j.cliente && j.cliente.nombre) ? j.cliente.nombre : 'Cliente';
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

    // Horario si viene
    const hb = document.getElementById('horarioBox');
    const ht = document.getElementById('horario');
    if (j.horario && j.horario.definido && j.horario.rango){
      hb.hidden = false; ht.textContent = j.horario.rango;
    } else { hb.hidden = true; }

    if (j.autorizado){
      adv.className='adv ok';
      adv.textContent = j.msg || 'Ingreso autorizado.';
    } else {
      adv.className='adv bad';
      adv.textContent = j.msg || 'Ingreso denegado.';
      // Mostrar botón de registro/renovación si aplica
      if (j.motivo === 'sin_membresia' || j.motivo === 'membresia_no_activa' || j.motivo === 'sin_clases'){
        reg.hidden = false;
        // Si tuvieras una URL concreta de renovación, podés setearla aquí
        // document.getElementById('regurl').href = 'renovar.php?...';
      }
    }
  } catch (e){
    adv.className='adv bad';
    adv.textContent = 'Error de red.';
  }
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
  if (dni) { i.value=dni; marcar(dni); }
});
</script>
</body>
</html>
