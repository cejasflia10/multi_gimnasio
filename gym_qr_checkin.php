<?php
/* ==========================================================
   gym_qr_checkin.php — Móvil + Backend (COMPLETO y CORREGIDO)
   • UI móvil dark, como el que enviaste.
   • Marca acceso en accesos_gimnasio (metodo='QR').
   • Setea SIEMPRE NOW() en todas las columnas de fecha existentes:
     - prioridad fecha_ingreso; además creado_en / created_at / fecha / ts / timestamp si existen.
   • Soporta:
      - QR que abre ?dni=...&g=...
      - Form móvil via POST -> ?ajax=1&g=...
   • Devuelve JSON en ?ajax=1 (para tu JS).
   • Muestra Plan / Clases restantes / Vencimiento si hay membresía.
   • Descuenta 1 clase de la membresía activa (si corresponde) y registra consumo.
   ========================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function table_exists(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $rs=$db->query("SHOW TABLES LIKE '$t'"); return $rs && $rs->num_rows>0; }
function col_exists(mysqli $db, string $t, string $c): bool { $t=$db->real_escape_string($t); $c=$db->real_escape_string($c); $rs=$db->query("SHOW COLUMNS FROM `$t` LIKE '$c'"); return $rs && $rs->num_rows>0; }
function pick_cols(mysqli $db, string $t, array $cands): array { $out=[]; foreach($cands as $c){ if(col_exists($db,$t,$c)) $out[]=$c; } return $out; }
function pick_col(mysqli $db, string $t, array $cands, string $fallback=null){ foreach($cands as $c) if (col_exists($db,$t,$c)) return $c; return $fallback; }
function jexit(array $p){ while(ob_get_level()) ob_end_clean(); header('Content-Type: application/json; charset=utf-8'); echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }

/* ===== Parámetros ===== */
$gimnasio_id = (int)($_GET['g'] ?? ($_SESSION['gimnasio_id'] ?? 0));
if ($gimnasio_id<=0){ http_response_code(400); exit('Falta g'); }

/* ===== Descubrir esquema ===== */
$T_CLIENT = table_exists($conexion,'clientes') ? 'clientes' : (table_exists($conexion,'clientes_gimnasio') ? 'clientes_gimnasio' : null);
if (!$T_CLIENT) { http_response_code(500); exit('❌ Falta tabla clientes'); }
$C_ID   = pick_col($conexion,$T_CLIENT,['id','cliente_id'],'id');
$C_DNI  = pick_col($conexion,$T_CLIENT,['dni','documento','doc'],'dni');
$C_GYM  = pick_col($conexion,$T_CLIENT,['gimnasio_id','id_gimnasio'], null);
$C_NOM  = pick_col($conexion,$T_CLIENT,['nombre','nombres'],'nombre');
$C_APE  = pick_col($conexion,$T_CLIENT,['apellido','apellidos'],'apellido');

$T_ACC  = 'accesos_gimnasio';
if (!table_exists($conexion,$T_ACC)) { http_response_code(500); exit('❌ Falta accesos_gimnasio'); }
$A_ID   = pick_col($conexion,$T_ACC,['id'],'id');
$A_GYM  = pick_col($conexion,$T_ACC,['gimnasio_id','id_gimnasio'],'gimnasio_id');
$A_CLI  = pick_col($conexion,$T_ACC,['cliente_id','id_cliente'],'cliente_id');
$A_MET  = pick_col($conexion,$T_ACC,['metodo','metodo_ingreso','medio','tipo'],'metodo');

/* Importante: columnas de fecha — seteo TODAS las que existan a NOW() */
$TS_CANDIDATAS = ['fecha_ingreso','creado_en','created_at','fecha','ts','timestamp'];
$A_TS_ALL = pick_cols($conexion,$T_ACC,$TS_CANDIDATAS);  // puede haber varias
$A_TS_MAIN = in_array('fecha_ingreso',$A_TS_ALL,true) ? 'fecha_ingreso' : ( $A_TS_ALL[0] ?? null ); // principal para lecturas

/* ===== Membresías (opcional) ===== */
$T_MEM  = null;
foreach (['membresias_clientes','membresias','membresias_vigentes'] as $t) if (table_exists($conexion,$t)) { $T_MEM=$t; break; }
$M_CLI  = $T_MEM ? pick_col($conexion,$T_MEM,['cliente_id','id_cliente'],'cliente_id') : null;
$M_GYM  = $T_MEM ? pick_col($conexion,$T_MEM,['gimnasio_id','id_gimnasio'], null) : null;
$M_PLAN = $T_MEM ? pick_col($conexion,$T_MEM,['plan','plan_nombre','nombre_plan'], null) : null;
$M_VEN  = $T_MEM ? pick_col($conexion,$T_MEM,['vence','fecha_vencimiento','vencimiento'], null) : null;
$M_RES  = $T_MEM ? pick_col($conexion,$T_MEM,['clases_disponibles','clases_restantes','restantes'], null) : null; // preferimos clases_disponibles
$M_TOT  = $T_MEM ? pick_col($conexion,$T_MEM,['clases_totales','clases'], null) : null;
$M_USE  = $T_MEM ? pick_col($conexion,$T_MEM,['clases_usadas','usadas'], null) : null;
$M_TS   = $T_MEM ? pick_col($conexion,$T_MEM,['creado_en','updated_at','fecha_alta','fecha_pago'], null) : null;

/* ==== Log de consumos (opcional) ==== */
$T_CONS = table_exists($conexion,'membresia_consumos') ? 'membresia_consumos' : null;
$K_MEMB = $T_CONS ? pick_col($conexion,$T_CONS,['membresia_id'],'membresia_id') : null;
$K_ACC  = $T_CONS ? pick_col($conexion,$T_CONS,['acceso_id'],'acceso_id') : null;
$K_CLI  = $T_CONS ? pick_col($conexion,$T_CONS,['cliente_id'],'cliente_id') : null;
$K_GYM  = $T_CONS ? pick_col($conexion,$T_CONS,['gimnasio_id'],'gimnasio_id') : null;
$K_FEC  = $T_CONS ? pick_col($conexion,$T_CONS,['fecha_consumo','creado_en','created_at'],'fecha_consumo') : null;
$K_MET  = $T_CONS ? pick_col($conexion,$T_CONS,['metodo','medio','tipo'],'metodo') : null;

/* ===== Rate limit simple por DNI (10s) ===== */
if (empty($_SESSION['qr_rate'])) $_SESSION['qr_rate'] = [];

/* ===== Handler AJAX (POST/GET) ===== */
$IS_AJAX = (isset($_GET['ajax']) && $_GET['ajax']=='1');

if ($IS_AJAX){
  $METHOD = $_SERVER['REQUEST_METHOD'];
  $dni  = trim((string)(($METHOD==='POST') ? ($_POST['dni'] ?? '') : ($_GET['dni'] ?? '')));
  if ($dni==='') jexit(['ok'=>false,'msg'=>'Ingresá un DNI']);

  if (!empty($_SESSION['qr_rate'][$dni]) && (time()-$_SESSION['qr_rate'][$dni])<10){
    jexit(['ok'=>true,'repeat'=>true,'msg'=>'⏱️ Ya estaba marcado hace instantes.']);
  }

  /* Buscar cliente */
  $dni_esc = $conexion->real_escape_string($dni);
  $whereGym = $C_GYM ? "AND C.$C_GYM=$gimnasio_id" : '';
  $sqlC = "SELECT C.$C_ID AS id, C.$C_NOM AS nombre, C.$C_APE AS apellido
           FROM $T_CLIENT C
           WHERE C.$C_DNI='$dni_esc' $whereGym
           LIMIT 1";
  $rc = $conexion->query($sqlC);
  if (!$rc || $rc->num_rows===0){
    $reg_url = "registro_online.php?g=".$gimnasio_id."&dni=".rawurlencode($dni);
    jexit(['ok'=>false,'no_reg'=>true,'msg'=>'No encontrado.','reg_url'=>$reg_url,'debug_sql'=>$sqlC]);
  }
  $cli = $rc->fetch_assoc();
  $cliente_id = (int)$cli['id'];

  /* ===== Transacción: acceso + descuento membresía + consumo (opcional) ===== */
  $conexion->begin_transaction();
  try {
    /* Insertar acceso — metodo='QR' y timestamps */
    $fields = [$A_GYM,$A_CLI,$A_MET];
    $values = [$gimnasio_id, $cliente_id, "'QR'"];
    foreach($A_TS_ALL as $tscol){ $fields[]=$tscol; $values[]="NOW()"; } // seteo TODAS las de fecha que existan

    $sqlI = "INSERT INTO $T_ACC (".implode(',',array_map('bt',$fields)).") VALUES (".implode(',', $values).")";
    if (!$conexion->query($sqlI)) {
      throw new Exception("Insert acceso: ".$conexion->error);
    }
    $acceso_id = (int)$conexion->insert_id;

    $plan = null; $vence=null; $restantes=null; $descuento_ok=false;

    if ($T_MEM){
      $hoy = date('Y-m-d');
      $condGym = $M_GYM ? "AND M.$M_GYM=$gimnasio_id" : "";
      // Lock de la membresía más reciente y vigente
      $sqlM = "SELECT M.id,
                      ".($M_PLAN?$M_PLAN:"NULL")." AS plan,
                      ".($M_VEN?$M_VEN:"NULL")." AS vence,
                      ".($M_RES?$M_RES:"NULL")." AS restantes,
                      ".($M_TOT?$M_TOT:"NULL")." AS totales,
                      ".($M_USE?$M_USE:"NULL")." AS usadas
               FROM $T_MEM M
               WHERE M.$M_CLI=$cliente_id $condGym
                 AND (M.activa=1 OR M.activa='1' OR M.activa IS NULL)
                 AND (".($M_VEN?$M_VEN:"NULL")." IS NULL OR ".($M_VEN?$M_VEN:"NULL")."='' OR ".($M_VEN?$M_VEN:"NULL")."='0000-00-00' OR ".($M_VEN?$M_VEN:"NULL")." >= '$hoy')
               ORDER BY COALESCE(".($M_VEN?$M_VEN:"NULL").",'9999-12-31') DESC, M.id DESC
               LIMIT 1
               FOR UPDATE";
      $rm = $conexion->query($sqlM);
      if ($rm && $rm->num_rows>0){
        $m = $rm->fetch_assoc();
        $membresia_id = (int)$m['id'];
        $plan  = $m['plan'] ?? null;
        $vence = $m['vence'] ?? null;

        if ($M_RES){ // caso columnas de disponibles directas
          $restantes = is_null($m['restantes']) ? null : (int)$m['restantes'];
          if (!is_null($restantes) && $restantes>0){
            $sqlU = "UPDATE $T_MEM SET $M_RES = $M_RES - 1 WHERE id=$membresia_id AND $M_RES > 0";
            if (!$conexion->query($sqlU)) throw new Exception("Update membresía: ".$conexion->error);
            if ($conexion->affected_rows>0){
              $descuento_ok = true;
              $restantes = $restantes - 1;
            }
          }
        } elseif ($M_TOT && $M_USE){ // caso total/usadas
          $tot = (int)($m['totales'] ?? 0);
          $usa = (int)($m['usadas'] ?? 0);
          $restantes = max(0, $tot - $usa);
          if ($restantes>0){
            $sqlU = "UPDATE $T_MEM SET $M_USE = $M_USE + 1 WHERE id=$membresia_id AND ($M_TOT - $M_USE) > 0";
            if (!$conexion->query($sqlU)) throw new Exception("Update usadas: ".$conexion->error);
            if ($conexion->affected_rows>0){
              $descuento_ok = true;
              $restantes = $restantes - 1;
            }
          }
        }

        // Log de consumo si existe la tabla
        if ($descuento_ok && $T_CONS){
          $cons_fields=[]; $cons_vals=[];
          if ($K_MEMB){ $cons_fields[]=$K_MEMB; $cons_vals[]=$membresia_id; }
          if ($K_ACC){  $cons_fields[]=$K_ACC;  $cons_vals[]=$acceso_id; }
          if ($K_CLI){  $cons_fields[]=$K_CLI;  $cons_vals[]=$cliente_id; }
          if ($K_GYM){  $cons_fields[]=$K_GYM;  $cons_vals[]=$gimnasio_id; }
          if ($K_FEC){  $cons_fields[]=$K_FEC;  $cons_vals[]="NOW()"; }
          if ($K_MET){  $cons_fields[]=$K_MET;  $cons_vals[]="'QR'"; }
          // formateo columnas
          foreach($cons_fields as $i=>$c){ $cons_fields[$i]=bt($c); }
          $vals_sql = [];
          foreach($cons_vals as $v){ $vals_sql[] = is_numeric($v) && !str_contains((string)$v,".") ? (string)$v : ( (strtoupper($v)==='NOW()') ? 'NOW()' : $v ); }
          $sqlL = "INSERT INTO $T_CONS (".implode(',',$cons_fields).") VALUES (".implode(',',$vals_sql).")";
          if (!$conexion->query($sqlL)) throw new Exception("Insert consumo: ".$conexion->error);
        }
      }
    }

    $conexion->commit();

    $_SESSION['qr_rate'][$dni] = time();

    jexit([
      'ok'=>true,
      'inserted'=>true,
      'msg'=>$descuento_ok ? "✅ Ingreso registrado y clase descontada" : "✅ Ingreso registrado",
      'cliente'=>[
        'id'=>$cliente_id,
        'nombre'=>trim(($cli['apellido']??'').' '.($cli['nombre']??'')),
      ],
      'membresia'=>[
        'plan'=>$plan,
        'vence'=>$vence,
        'clases_restantes'=>$restantes
      ]
    ]);

  } catch (Throwable $e){
    $conexion->rollback();
    jexit([
      'ok'=>false,
      'inserted'=>false,
      'msg'=>'❌ Error procesando ingreso',
      'error'=>$e->getMessage()
    ]);
  }
}

/* ===== UI MÓVIL =====
   • Si el QR abre con ?dni=..., auto-marca via fetch ?ajax=1.
   • Si el usuario escribe el DNI, también funciona.
========================================================== */
$dni_qr = trim((string)($_GET['dni'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Check-in</title>
<style>
  :root{ color-scheme:dark; }
  html,body{height:100%}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#0f0f10;color:#e6e6e6}
  .wrap{min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
  .card{width:100%;max-width:420px;background:#141414;border:1px solid #222;border-radius:16px;padding:16px;box-shadow:0 8px 24px rgba(0,0,0,.35)}
  h1{font-size:20px;margin:0 0 6px}
  .muted{opacity:.8;font-size:14px;margin:0 0 12px}
  .row{display:flex;gap:8px}
  input{flex:1;background:#1b1b1b;border:1px solid #333;border-radius:12px;padding:14px;color:#eee;font-size:18px}
  button{background:#2a2a2a;border:1px solid #3a3a3a;border-radius:12px;padding:14px 16px;color:#fff;font-weight:700;letter-spacing:.2px;min-width:110px}
  .adv{margin-top:10px;font-size:13px;opacity:.9}
  .ok{color:#8bd16a}.warn{color:#f0c36d}.bad{color:#e57373}
  .result{margin-top:12px;background:#101010;border:1px solid #262626;border-radius:14px;padding:12px}
  .big{font-size:20px;margin-bottom:6px}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#1d1d1d;border:1px solid #333;font-size:12px}
  .center{display:flex;justify-content:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
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

    <div id="reg" class="center" hidden>
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
    adv.textContent = (j.msg||'Error.') + (j.error ? ' · '+j.error : '') + (j.sql_error ? ' · '+j.sql_error : '');
    console.log(j); return;
  }
  if(j.no_reg){
    adv.textContent = 'No encontramos ese DNI.';
    reg.hidden = false;
    document.getElementById('regurl').href = j.reg_url;
    return;
  }
  adv.textContent = j.repeat ? '⏱️ Ya estaba marcado hace instantes.' : (j.msg || 'Hecho.');

  // Tarjeta de resultado
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
}

async function enviar(ev){
  ev.preventDefault();
  const dni = (document.getElementById('dni').value||'').trim();
  if(!dni){ document.getElementById('dni').focus(); return; }
  await marcar(dni);
  document.getElementById('dni').value='';
  document.getElementById('dni').focus();
}

// Si el QR ya trae ?dni=..., se auto-marca
window.addEventListener('load', ()=>{
  const url = new URL(location.href);
  const dni = url.searchParams.get('dni');
  if (dni) marcar(dni);
});
</script>
</body>
</html>
