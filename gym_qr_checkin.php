<?php
/* ==========================================================
   gym_qr_checkin.php — Check-in por DNI (público/mostrador)
   • Busca el cliente (dni + gimnasio_id).
   • Inserta acceso en accesos_gimnasio (metodo='QR').
   • Devuelve JSON con saludo + plan + clases + vencimiento.
   • Si no existe, sugiere registro_online.php?g=...&dni=...
   • Rate limit 10s por DNI para evitar dobles.
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ==== Helpers ==== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function table_exists(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $rs=$db->query("SHOW TABLES LIKE '$t'"); return $rs && $rs->num_rows>0; }
function col_exists(mysqli $db, string $t, string $c): bool { $t=$db->real_escape_string($t); $c=$db->real_escape_string($c); $rs=$db->query("SHOW COLUMNS FROM `$t` LIKE '$c'"); return $rs && $rs->num_rows>0; }
function pick_col(mysqli $db, string $t, array $cands, string $fallback=null){ foreach($cands as $c) if (col_exists($db,$t,$c)) return $c; return $fallback; }

/* ==== Inputs ==== */
$gimnasio_id = (int)($_GET['g'] ?? ($_SESSION['gimnasio_id'] ?? 0));
if ($gimnasio_id<=0){ http_response_code(400); exit('Falta g'); }

/* ==== Descubrir tablas/columnas ==== */
$T_CLIENT = table_exists($conexion,'clientes') ? 'clientes' : (table_exists($conexion,'clientes_gimnasio') ? 'clientes_gimnasio' : null);
if (!$T_CLIENT) { http_response_code(500); exit('❌ Falta tabla clientes'); }
$C_ID   = pick_col($conexion,$T_CLIENT,['id','cliente_id'],'id');
$C_DNI  = pick_col($conexion,$T_CLIENT,['dni','documento','doc'],'dni');
$C_GYM  = pick_col($conexion,$T_CLIENT,['gimnasio_id','id_gimnasio'], null);
$C_NOM  = pick_col($conexion,$T_CLIENT,['nombre','nombres'],'nombre');
$C_APE  = pick_col($conexion,$T_CLIENT,['apellido','apellidos'],'apellido');

$T_ACC  = 'accesos_gimnasio';
if (!table_exists($conexion,$T_ACC)) { http_response_code(500); exit('❌ Falta tabla accesos_gimnasio'); }
$A_ID   = pick_col($conexion,$T_ACC,['id'],'id');
$A_GYM  = pick_col($conexion,$T_ACC,['gimnasio_id','id_gimnasio'],'gimnasio_id');
$A_CLI  = pick_col($conexion,$T_ACC,['cliente_id','id_cliente'],'cliente_id');
$A_MET  = pick_col($conexion,$T_ACC,['metodo','metodo_ingreso','medio','tipo'],'metodo');
$A_TS   = pick_col($conexion,$T_ACC,['creado_en','created_at','fecha','ts','timestamp'],'creado_en');

$T_MEM  = null;
foreach (['membresias_clientes','membresias','membresias_vigentes'] as $t) if (table_exists($conexion,$t)) { $T_MEM=$t; break; }
$M_CLI  = $T_MEM ? pick_col($conexion,$T_MEM,['cliente_id','id_cliente'],'cliente_id') : null;
$M_GYM  = $T_MEM ? pick_col($conexion,$T_MEM,['gimnasio_id','id_gimnasio'], null) : null;
$M_PLAN = $T_MEM ? pick_col($conexion,$T_MEM,['plan','plan_nombre','nombre_plan'], null) : null;
$M_VEN  = $T_MEM ? pick_col($conexion,$T_MEM,['vence','fecha_vencimiento','vencimiento'], null) : null;
$M_RES  = $T_MEM ? pick_col($conexion,$T_MEM,['clases_restantes','restantes'], null) : null;
$M_TOT  = $T_MEM ? pick_col($conexion,$T_MEM,['clases_totales','clases'], null) : null;
$M_USE  = $T_MEM ? pick_col($conexion,$T_MEM,['clases_usadas','usadas'], null) : null;
$M_TS   = $T_MEM ? pick_col($conexion,$T_MEM,['creado_en','updated_at','fecha_alta','fecha_pago'], null) : null;

/* ===== Anti-doble envío (10s misma persona) ===== */
if (empty($_SESSION['qr_rate'])) $_SESSION['qr_rate'] = []; // dni => ts

/* ===== CSRF simple ===== */
if (empty($_SESSION['csrf_qr'])) $_SESSION['csrf_qr'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_qr'];

/* ===== AJAX ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['ajax'])){
  while(ob_get_level()) ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');

  $dni  = trim((string)($_POST['dni'] ?? ''));
  $csrf_in = (string)($_POST['csrf'] ?? '');
  if (!hash_equals($csrf, $csrf_in)) { echo json_encode(['ok'=>false,'msg'=>'❌ CSRF inválido. Refrescá.']); exit; }
  if ($dni===''){ echo json_encode(['ok'=>false,'msg'=>'Ingresá un DNI.']); exit; }

  if (!empty($_SESSION['qr_rate'][$dni]) && (time()-$_SESSION['qr_rate'][$dni])<10){
    echo json_encode(['ok'=>true,'repeat'=>true,'msg'=>'⏱️ Ya marcado hace instantes.']); exit;
  }

  /* === Buscar cliente === */
  $dni_esc = $conexion->real_escape_string($dni);
  $whereGym = $C_GYM ? "AND C.$C_GYM=$gimnasio_id" : '';
  $sqlC = "SELECT C.$C_ID AS id, C.$C_NOM AS nombre, C.$C_APE AS apellido
           FROM $T_CLIENT C
           WHERE C.$C_DNI='$dni_esc' $whereGym
           LIMIT 1";
  $rc = $conexion->query($sqlC);
  if (!$rc || $rc->num_rows===0){
    $reg_url = "registro_online.php?g=".$gimnasio_id."&dni=".rawurlencode($dni);
    echo json_encode(['ok'=>false,'no_reg'=>true,'msg'=>'No encontrado.','reg_url'=>$reg_url]); exit;
  }
  $cli = $rc->fetch_assoc();
  $cliente_id = (int)$cli['id'];

  /* === Insertar acceso === */
  $metodo = 'QR';
  $fields = [$A_GYM,$A_CLI,$A_MET];
  $values = [$gimnasio_id, $cliente_id, "'".$conexion->real_escape_string($metodo)."'"];
  // Si la columna timestamp NO es auto, seteamos NOW()
  if ($A_TS && col_exists($conexion,$T_ACC,$A_TS)){
    $rsDesc = $conexion->query("SHOW COLUMNS FROM `$T_ACC` LIKE '".$conexion->real_escape_string($A_TS)."'");
    if ($rsDesc && ($col = $rsDesc->fetch_assoc())){
      $hasDefault = stripos($col['Default'] ?? '', 'current_timestamp') !== false;
      if (!$hasDefault){ $fields[] = $A_TS; $values[] = "NOW()"; }
    }
  }
  $sqlI = "INSERT INTO $T_ACC (".implode(',',array_map('bt',$fields)).") VALUES (".implode(',',$values).")";
  $okI = $conexion->query($sqlI);

  $_SESSION['qr_rate'][$dni] = time();

  /* === Datos de membresía === */
  $plan = null; $vence=null; $restantes=null;
  if ($T_MEM){
    $condGym = $M_GYM ? "AND M.$M_GYM=$gimnasio_id" : "";
    $sqlM = "SELECT ".($M_PLAN?$M_PLAN:"NULL")." AS plan,
                    ".($M_VEN?$M_VEN:"NULL")." AS vence,
                    ".($M_RES?$M_RES:"NULL")." AS restantes,
                    ".($M_TOT?$M_TOT:"NULL")." AS totales,
                    ".($M_USE?$M_USE:"NULL")." AS usadas
             FROM $T_MEM M
             WHERE M.$M_CLI=$cliente_id $condGym
             ORDER BY ".($M_TS?:'1')." DESC
             LIMIT 1";
    if ($rm = $conexion->query($sqlM)){
      if ($m = $rm->fetch_assoc()){
        $plan = $m['plan'] ?? null;
        $vence = $m['vence'] ?? null;
        if ($M_RES){
          $restantes = is_null($m['restantes']) ? null : (int)$m['restantes'];
        } else if ($M_TOT){
          $tot = (int)($m['totales'] ?? 0);
          $usa = (int)($m['usadas'] ?? 0);
          $restantes = max(0, $tot - $usa);
        }
      }
    }
  }

  $nombre = trim(($cli['apellido']??'').' '.($cli['nombre']??''));
  echo json_encode([
    'ok'=>true,
    'inserted'=>$okI?true:false,
    'msg'=>$okI ? "✅ Ingreso registrado" : "⚠️ (mostrando datos, no se pudo registrar el ingreso)",
    'cliente'=>[
      'id'=>$cliente_id,
      'nombre'=>$nombre
    ],
    'membresia'=>[
      'plan'=>$plan,
      'vence'=>$vence,
      'clases_restantes'=>$restantes
    ]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== Render página (form) ===== */
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Check-in por DNI — Gimnasio #<?= (int)$gimnasio_id ?></title>
<style>
  :root{ color-scheme:dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial,sans-serif;background:#111;color:#eee;margin:0}
  .wrap{max-width:680px;margin:0 auto;padding:20px}
  h1{font-size:22px;margin:0 0 6px}
  p{opacity:.8}
  .scan{display:flex;gap:8px;margin:12px 0}
  input{flex:1;background:#1b1b1b;border:1px solid #333;border-radius:10px;padding:12px;color:#eee;font-size:18px}
  button{background:#2b2b2b;border:1px solid #444;border-radius:10px;padding:12px 16px;color:#fff;cursor:pointer}
  .card{background:#141414;border:1px solid #222;border-radius:14px;padding:14px;margin-top:12px}
  .muted{opacity:.75}
  .big{font-size:20px}
  .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#222;border:1px solid #333;font-size:12px}
  .ok{color:#8bd16a}
  .warn{color:#f0c36d}
  .bad{color:#e57373}
  .adv{margin-top:6px;font-size:13px;opacity:.8}
</style>
<script>
let csrf = <?= json_encode($csrf) ?>;
async function enviar(ev){
  ev.preventDefault();
  const i = document.getElementById('dni');
  const adv = document.getElementById('adv');
  const res = document.getElementById('res');
  const reg = document.getElementById('reg');
  const val = (i.value||'').trim();
  if(!val){ i.focus(); return; }
  adv.textContent = 'Marcando ingreso…';
  reg.hidden = true;
  const fd = new FormData();
  fd.set('dni', val);
  fd.set('csrf', csrf);
  const r = await fetch('?ajax=1&g=<?= (int)$gimnasio_id ?>', {method:'POST', body:fd});
  const j = await r.json().catch(()=>({ok:false,msg:'Error de red'}));
  if(!j.ok && !j.no_reg){ adv.textContent = j.msg||'Error.'; return; }
  if(j.no_reg){
    adv.textContent = 'No encontramos ese DNI.';
    reg.hidden = false;
    reg.querySelector('a').href = j.reg_url;
    return;
  }
  adv.textContent = j.repeat ? '⏱️ Ya estaba marcado hace instantes.' : (j.msg || 'Hecho.');
  // Mostrar tarjeta
  res.hidden = false;
  document.getElementById('cli').textContent = (j.cliente && j.cliente.nombre) ? j.cliente.nombre : 'Cliente';
  const m = j.membresia||{};
  const plan = m.plan || '—';
  const vence = m.vence ? new Date(m.vence.replace(' ','T')) : null;
  const venTxt = vence ? vence.toLocaleDateString() : '—';
  document.getElementById('plan').textContent = plan;
  document.getElementById('vence').textContent = venTxt;
  const rest = (typeof m.clases_restantes === 'number') ? m.clases_restantes : null;
  const restEl = document.getElementById('restantes');
  if (rest===null){ restEl.textContent = '—'; restEl.className='pill'; }
  else {
    restEl.textContent = rest;
    restEl.className = 'pill' + (rest<=0?' bad':(rest<=2?' warn':'')); 
  }
  i.value='';
  i.focus();
}
</script>
</head>
<body>
  <div class="wrap">
    <h1>Ingreso por DNI</h1>
    <p class="muted">Escribí el DNI y presioná Enter. Se marcará el acceso y verás tu estado de membresía.</p>

    <div class="card">
      <form class="scan" onsubmit="enviar(event)" autocomplete="off">
        <input id="dni" inputmode="numeric" autocomplete="one-time-code" placeholder="Ingresar DNI y Enter…">
        <button type="submit">Marcar</button>
      </form>
      <div id="adv" class="adv"></div>

      <div id="res" class="card" hidden>
        <div class="big" id="cli">Cliente</div>
        <div class="row" style="margin-top:6px">
          <div>Plan: <span id="plan" class="pill">—</span></div>
          <div>Clases: <span id="restantes" class="pill">—</span></div>
          <div>Vence: <span id="vence" class="">—</span></div>
        </div>
      </div>

      <div id="reg" class="row" hidden>
        <a class="pill" href="#" target="_blank" rel="noopener">📝 Registrarme</a>
      </div>
    </div>
  </div>
</body>
</html>
