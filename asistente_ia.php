<?php
// asistente_nutricional.php — Plan nutricional con MENÚ UNIFICADO (cliente)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if (!$cliente_id || !$gimnasio_id) { echo "<div style='color:red;text-align:center;padding:12px'>❌ Acceso denegado.</div>"; exit; }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function num($n,$d=1){ return number_format((float)$n,$d,',','.'); }
function db_has_table(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $r=$db->query("SHOW TABLES LIKE '{$t}'"); return ($r && $r->num_rows>0); }
function db_has_col(mysqli $db, string $t, string $c): bool { $t=$db->real_escape_string($t); $c=$db->real_escape_string($c); $r=$db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'"); return ($r && $r->num_rows>0); }
function pick_col(mysqli $db, string $t, array $cands): ?string { foreach ($cands as $c) if (db_has_col($db,$t,$c)) return $c; return null; }

/* ================= Cliente ================= */
$cliente=null;
if($st=$conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1")){
  $st->bind_param("ii",$cliente_id,$gimnasio_id);
  $st->execute(); $cliente=$st->get_result()->fetch_assoc(); $st->close();
}
if(!$cliente){ echo "<p style='color:red;padding:20px'>⚠️ No se encontró el cliente.</p>"; exit; }
$nombre = trim(($cliente['apellido']??'').' '.($cliente['nombre']??''));

/* Altura base */
$altura_raw = $cliente['altura_cm'] ?? $cliente['altura'] ?? null;
$altura_m = 1.70;
if ($altura_raw!==null && (float)$altura_raw>0) $altura_m = ((float)$altura_raw>3)? ((float)$altura_raw/100.0) : (float)$altura_raw;

/* ================= Progreso (id o último) ================= */
$progreso_id = (int)($_GET['progreso_id'] ?? 0);
$ult = null;
if (db_has_table($conexion,'progreso')) {
  if ($progreso_id>0) {
    $sql = "SELECT id, fecha, peso_antes, peso_despues, altura_cm, duracion_min, calorias_quemadas, objetivo, notas
            FROM progreso WHERE id=? AND cliente_id=? AND gimnasio_id=? LIMIT 1";
    if($st=$conexion->prepare($sql)){
      $st->bind_param("iii",$progreso_id,$cliente_id,$gimnasio_id);
      if($st->execute()) $ult=$st->get_result()->fetch_assoc();
      $st->close();
    }
  }
  if(!$ult){
    $sql = "SELECT id, fecha, peso_antes, peso_despues, altura_cm, duracion_min, calorias_quemadas, objetivo, notas
            FROM progreso WHERE cliente_id=? AND gimnasio_id=? ORDER BY fecha DESC, id DESC LIMIT 1";
    if($st=$conexion->prepare($sql)){
      $st->bind_param("ii",$cliente_id,$gimnasio_id);
      if($st->execute()) $ult=$st->get_result()->fetch_assoc();
      $st->close();
    }
  }
}

/* Si en el progreso hay altura, uso esa */
if ($ult && (float)($ult['altura_cm']??0)>0) $altura_m = ((float)$ult['altura_cm'])/100.0;

/* Peso de referencia */
$peso_ref = 70.0;
if ($ult && (float)($ult['peso_despues']??0)>0)      $peso_ref = (float)$ult['peso_despues'];
elseif (isset($cliente['peso']) && (float)$cliente['peso']>0) $peso_ref = (float)$cliente['peso'];

/* Enfermedades (si existe en clientes) */
$enfermedades = trim((string)($cliente['enfermedades'] ?? ''));
$es_diabetico = stripos($enfermedades, 'diab') !== false;

/* IMC */
$imc = ($altura_m>0) ? round($peso_ref/($altura_m*$altura_m),1) : 0.0;
$cat='Desconocido'; if($imc>0){ if($imc<18.5)$cat='Bajo peso'; elseif($imc<25)$cat='Saludable'; elseif($imc<30)$cat='Sobrepeso'; else $cat='Obesidad'; }
$peso_min = round(18.5*$altura_m*$altura_m,1);
$peso_max = round(24.9*$altura_m*$altura_m,1);

/* Objetivo (según progreso o IMC) */
$objetivo = strtolower(trim((string)($_GET['objetivo'] ?? '')));
if(!in_array($objetivo,['bajar peso','mantener','subir peso'],true)){
  $obj_prog = strtolower(trim((string)($ult['objetivo'] ?? '')));
  if(in_array($obj_prog,['bajar','mantener','subir'],true)){
    $objetivo = $obj_prog==='bajar' ? 'bajar peso' : ($obj_prog==='subir' ? 'subir peso' : 'mantener');
  }else{
    if($imc>=25) $objetivo='bajar peso'; elseif($imc<18.5) $objetivo='subir peso'; else $objetivo='mantener';
  }
}

/* ================= Ventana últimos 7 días ================= */
$tz = new DateTimeZone('America/Argentina/San_Luis');
$hoyDT = new DateTime('today',$tz);
$hoy   = $hoyDT->format('Y-m-d');
$desde = (clone $hoyDT)->modify('-6 days')->format('Y-m-d');
$hasta = $hoy;

$stats7 = ['sesiones'=>0,'minutos'=>0,'kcal'=>0];
if(db_has_table($conexion,'progreso')){
  $sqlS="SELECT COUNT(*) sesiones, COALESCE(SUM(duracion_min),0) minutos, COALESCE(SUM(calorias_quemadas),0) kcal
         FROM progreso WHERE cliente_id=? AND gimnasio_id=? AND fecha BETWEEN ? AND ?";
  if($st=$conexion->prepare($sqlS)){
    $st->bind_param("iiss",$cliente_id,$gimnasio_id,$desde,$hasta);
    if($st->execute()) $stats7=$st->get_result()->fetch_assoc() ?: $stats7;
    $st->close();
  }
}

/* Calorías ejercicio HOY */
$kcal_burn_hoy=0; $min_hoy=0;
if(db_has_table($conexion,'progreso')){
  $sqlK="SELECT COALESCE(SUM(calorias_quemadas),0) kcal, COALESCE(SUM(duracion_min),0) minutos
         FROM progreso WHERE cliente_id=? AND gimnasio_id=? AND fecha=?";
  if($st=$conexion->prepare($sqlK)){
    $st->bind_param("iis",$cliente_id,$gimnasio_id,$hoy);
    if($st->execute()){ $r=$st->get_result()->fetch_assoc(); $kcal_burn_hoy=(int)($r['kcal']??0); $min_hoy=(int)($r['minutos']??0); }
    $st->close();
  }
}

/* Objetivo calórico y macros (simple) */
$kcal_base = (int)round($peso_ref*30);
$kcal_obj  = $kcal_base + (($objetivo==='subir peso')?+300:(($objetivo==='bajar peso')?-400:0));
if($objetivo==='bajar peso'){ $p=0.30;$c=0.40;$g=0.30; }
elseif($objetivo==='subir peso'){ $p=0.25;$c=0.50;$g=0.25; }
else{ $p=0.25;$c=0.45;$g=0.30; }
$g_prot=(int)round(($kcal_obj*$p)/4);
$g_carb=(int)round(($kcal_obj*$c)/4);
$g_gras=(int)round(($kcal_obj*$g)/9);

/* ================= Ingesta del día (detección flexible) ================= */
function detectar_tabla_comidas(mysqli $db): ?array {
  $candidatas=['ingesta_diaria','ingestas','ingesta','registro_comidas','comidas','comidas_diarias','alimentos_consumidos','dieta_diaria','nutricion_diaria'];
  foreach($candidatas as $t){
    if(!db_has_table($db,$t)) continue;
    $cCli  = pick_col($db,$t,['cliente_id','id_cliente','user_id']);
    $cGym  = pick_col($db,$t,['gimnasio_id','id_gimnasio']);
    $cFec  = pick_col($db,$t,['fecha','dia','created_at','fecha_registro']);
    $cKcal = pick_col($db,$t,['kcal','calorias','calorias_totales','kilocalorias']);
    $cProt = pick_col($db,$t,['proteina','proteinas','proteinas_g','protein_g','g_proteina']);
    $cCarb = pick_col($db,$t,['carbohidratos','carbs','carbohidratos_g','carbs_g','g_carbohidrato']);
    $cGras = pick_col($db,$t,['grasas','fat','grasas_g','fat_g','g_grasa']);
    if($cFec && $cKcal && $cCli){
      return ['t'=>$t,'cli'=>$cCli,'gym'=>$cGym,'fec'=>$cFec,'kcal'=>$cKcal,'prot'=>$cProt,'carb'=>$cCarb,'gras'=>$cGras];
    }
  }
  return null;
}
$ingesta_hoy=['kcal'=>0,'prot'=>0,'carb'=>0,'gras'=>0,'origen'=>null];
if($det=detectar_tabla_comidas($conexion)){
  $cols=["COALESCE(SUM(`{$det['kcal']}`),0) AS kcal"];
  if($det['prot']) $cols[]="COALESCE(SUM(`{$det['prot']}`),0) AS prot";
  if($det['carb']) $cols[]="COALESCE(SUM(`{$det['carb']}`),0) AS carb";
  if($det['gras']) $cols[]="COALESCE(SUM(`{$det['gras']}`),0) AS gras";
  $sqlC="SELECT ".implode(',',$cols)." FROM `{$det['t']}` WHERE 1";
  $bind=''; $args=[];
  if($det['cli']){ $sqlC.=" AND `{$det['cli']}`=?"; $bind.='i'; $args[]=$cliente_id; }
  if($det['gym']){ $sqlC.=" AND `{$det['gym']}`=?"; $bind.='i'; $args[]=$gimnasio_id; }
  $sqlC.=" AND DATE(`{$det['fec']}`)=?"; $bind.='s'; $args[]=$hoy;

  if($st=$conexion->prepare($sqlC)){
    // Bind seguro según patrón
    if($bind==='i') $st->bind_param('i',$args[0]);
    elseif($bind==='ii') $st->bind_param('ii',$args[0],$args[1]);
    elseif($bind==='iis') $st->bind_param('iis',$args[0],$args[1],$args[2]);
    elseif($bind==='is') $st->bind_param('is',$args[0],$args[1]);
    else $st->bind_param($bind, ...$args);
    if($st->execute()){
      $r=$st->get_result()->fetch_assoc() ?: [];
      $ingesta_hoy['kcal']=(int)($r['kcal']??0);
      $ingesta_hoy['prot']=(float)($r['prot']??0);
      $ingesta_hoy['carb']=(float)($r['carb']??0);
      $ingesta_hoy['gras']=(float)($r['gras']??0);
      $ingesta_hoy['origen']=$det['t'];
    }
    $st->close();
  }
}

/* ================= Balance diario ================= */
$kcal_comidas_hoy=(int)$ingesta_hoy['kcal'];
$kcal_netas_hoy  = $kcal_comidas_hoy - $kcal_burn_hoy;
$kcal_restantes  = $kcal_obj - $kcal_netas_hoy;

/* Agua y proteínas guía */
$agua_l = max(1.5, round($peso_ref*0.035,1));
$prot_gkg = ($objetivo==='bajar peso')?1.6:(($objetivo==='subir peso')?2.0:1.4);
$prot_total = round($peso_ref*$prot_gkg);

/* ================= Plan semanal base ================= */
function dieta_base($goal){
  $bajar=['desayuno'=>['Infusión sin azúcar + 2 tostadas integrales con queso untable light','Yogur descremado + granola sin azúcar + fruta','Mate/te sin azúcar + omelette de 2 claras + 1 yema + tomate'],
          'almuerzo'=>['Pechuga a la plancha + ensalada verde + 1 cda aceite de oliva','Atún + mix de hojas + quinoa (~70g cocida)','Pollo salteado + verduras al wok + arroz integral pequeño'],
          'merienda'=>['Infusión con leche descremada + 2 galletas de arroz','Yogur + fruta','Batido de agua + proteína (opcional) + 1 banana'],
          'cena'=>['Sopa de verduras + tortilla de espinaca al horno','Filet de merluza + puré de calabaza','Carne magra + ensalada variada + 1 cda aceite']];
  $subir=['desayuno'=>['Tostadas integrales con palta y huevo + batido con leche + banana','Avena cocida con leche + fruta + mantequilla de maní','Yogur entero + granola + frutos secos'],
          'almuerzo'=>['Arroz integral + pollo al horno + ensalada + fruta','Pasta + salsa de tomate + atún + aceite de oliva','Wrap integral de pollo + queso + verduras'],
          'merienda'=>['Sandwich integral de jamón/queso + fruta','Yogur + frutos secos','Batido con leche + banana + avena'],
          'cena'=>['Pasta con atún y aceite de oliva + pan integral','Tarta integral de verduras + ensalada','Guiso magro con papa y verduras']];
  $mant=['desayuno'=>['Infusión + 2 tostadas integrales con queso','Avena con leche + fruta','Omelette + pan integral'],
         'almuerzo'=>['Carne magra + ensalada + arroz integral pequeño','Pollo al horno + papas + ensalada','Merluza + puré + ensalada'],
         'merienda'=>['Yogur + fruta','Infusión + 2 galletas de arroz','Sandwich integral pequeño'],
         'cena'=>['Sopa + tortilla de verduras','Salteado de pollo + verduras + quinoa','Carne magra + ensalada + 1 cda aceite']];
  return $goal==='subir peso' ? $subir : ($goal==='bajar peso' ? $bajar : $mant);
}
function ajustar_diabetes($plan){
  foreach($plan as $t=>$ops){
    foreach($ops as $i=>$txt){
      $txt=str_ireplace(['mermelada','azúcar','dulce','galletas'],['queso descremado','sin azúcar','fruta','galletas de arroz'],$txt);
      $txt=preg_replace('/(jugo|gaseosa)/i','agua/infusión sin azúcar',$txt);
      $plan[$t][$i]=$txt;
    }
  }
  return $plan;
}
$base=dieta_base($objetivo); if($es_diabetico) $base=ajustar_diabetes($base);
$dias=['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$plan=[]; for($i=0;$i<7;$i++){ $plan[$dias[$i]]=['Desayuno'=>$base['desayuno'][$i%3],'Almuerzo'=>$base['almuerzo'][$i%3],'Merienda'=>$base['merienda'][$i%3],'Cena'=>$base['cena'][$i%3]]; }

/* Mensaje */
$mensaje="Hola {$nombre}. Tu IMC actual es {$imc} ({$cat}). Rango saludable aprox: {$peso_min}–{$peso_max} kg.";

/* Última sesión formateada */
$ult_fmt=null;
if($ult){
  $pa=(float)($ult['peso_antes']??0); $pd=(float)($ult['peso_despues']??0); $delta=$pd-$pa;
  $ult_fmt=[
    'fecha'=>h($ult['fecha']??''),
    'peso'=>num($pa,1)." → ".num($pd,1)." kg (Δ ".(($delta>=0?'+':'−').num(abs($delta),2))." kg)",
    'dur'=>(int)($ult['duracion_min']??0),
    'kcal'=>(int)($ult['calorias_quemadas']??0),
    'obj'=>h((string)($ult['objetivo']??'')),
    'notas'=>h((string)($ult['notas']??'')),
  ];
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>🤖 Asistente Nutricional</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* ===== MENÚ UNIFICADO (idéntico) ===== */
    :root{
      --mnu-bg-bar: rgba(15,19,32,.78);
      --mnu-bg-drawer: rgba(10,12,20,.94);
      --mnu-fg: #fff;
      --mnu-fg-dim: #cbd5e1;
      --mnu-accent: #ffd600;
      --mnu-border: rgba(255,255,255,.16);
      --mnu-shadow: 0 10px 30px rgba(0,0,0,.45);
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    .mnu-bar{ position:sticky; top:0; z-index:1000; display:flex; align-items:center; gap:12px; padding:10px 14px; background:var(--mnu-bg-bar); -webkit-backdrop-filter: blur(10px) saturate(1.05); backdrop-filter: blur(10px) saturate(1.05); border-bottom:1px solid var(--mnu-border); }
    .mnu-title{ font-weight:800; color:var(--mnu-accent); }
    .mnu-spacer{ flex:1; }
    .mnu-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mnu-accent); color:#111; border:none; font-weight:700; text-decoration:none; }
    .mnu-btn--ghost{ background:transparent; color:#fff; border:1px solid var(--mnu-border); }
    .mnu-inline{ display:flex; gap:10px; flex-wrap:wrap; padding:10px 14px; background:transparent; border-bottom:1px solid var(--mnu-border); }
    .mnu-tab{ padding:10px 14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; }
    .mnu-tab:hover{ background:rgba(255,255,255,.06); }
    @media (max-width:920px){ .mnu-inline{ display:none !important; } }
    .mnu-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10005; display:none; }
    .mnu-drawer{ position:fixed; top:0; bottom:0; left:0; width:86vw; max-width:360px; background:var(--mnu-bg-drawer); border-right:1px solid var(--mnu-border); box-shadow:var(--mnu-shadow); transform:translateX(-100%); transition:transform .25s ease; z-index:10010; padding:14px; display:flex; flex-direction:column; gap:12px; }
    .mnu-drawer.open{ transform:translateX(0); }
    .mnu-backdrop.show{ display:block; }
    .mnu-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .mnu-close{ width:44px; height:44px; border-radius:50%; display:grid; place-items:center; cursor:pointer; background:var(--mnu-accent); color:#111; font-weight:900; border:none; }
    .mnu-list{ display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
    .mnu-item{ display:flex; align-items:center; gap:12px; padding:14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; background:transparent; }
    .mnu-item:hover{ background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.30); }
    .mnu-item__icon{ width:24px; display:inline-grid; place-items:center; color:#fff; }
    .mnu-item__text{ font-size:18px; }
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{ color:#fff !important; -webkit-text-fill-color:#fff !important; text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !IMPORTANT; }

    /* ===== BASE ===== */
    *{box-sizing:border-box}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg); color:var(--fg); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; }
    .container{ max-width:1100px; margin:0 auto; padding:16px 16px 48px; }
    .card{ background:#12141a; border:1px solid var(--border); border-radius:16px; padding:14px }
    .row{ display:grid; grid-template-columns:1fr; gap:12px; }
    @media (min-width:900px){ .row{ grid-template-columns: 1.2fr .8fr; } }
    h2{ margin:10px 0 12px; text-align:center }
    .flex{ display:flex; gap:8px; flex-wrap:wrap; align-items:center }
    label{ font-weight:700 }
    select,button{ padding:8px 10px; border-radius:12px; border:1px solid var(--border); background:#1a1d24; color:var(--fg) }
    .btn{ background:var(--acc); color:#111; border:none; font-weight:800 }
    .msg{ background:#14161c; border:1px solid var(--border); padding:12px; border-radius:12px; margin-top:8px }
    .grid3{ display:grid; gap:8px; grid-template-columns:1fr; }
    @media (min-width:680px){ .grid3{ grid-template-columns: repeat(3,1fr); } }
    .kpi{ text-align:center; background:#1a1d24; border:1px solid var(--border); border-radius:12px; padding:10px }
    .kpi b{ display:block; font-size:18px; margin-top:4px }
    table{ width:100%; border-collapse:collapse; margin-top:10px; font-size:14px }
    th,td{ padding:10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:left }
    th{ color:var(--muted); font-weight:700; background:#0f1118 }
    .muted{ color:var(--muted) }
    .pill{ display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid var(--border); font-size:12px; margin-left:6px }
    .ok{ color:#22c55e } .bad{ color:#ef4444 } .warn{ color:#f59e0b }
  </style>
</head>
<body>

<!-- ===== Menú Unificado ===== -->
<header>
  <div class="mnu-bar">
    <button class="mnu-btn mnu-open">☰ Menú</button>
    <div class="mnu-title">Panel Cliente</div>
    <div class="mnu-spacer"></div>
    <a class="mnu-btn mnu-btn--ghost" href="cliente_acceso.php?logout=1">Salir</a>
  </div>

  <!-- Tabs inline (PC) -->
  <nav class="mnu-inline">
    <a class="mnu-tab" href="panel_cliente.php">🏠 Inicio</a>
    <a class="mnu-tab" href="ver_turnos_cliente.php">📅 Ver Turnos</a>
    <a class="mnu-tab" href="ver_mis_pagos.php">💳 Mis Pagos</a>
    <a class="mnu-tab" href="pago_online.php">⚡ Pago Online</a>
    <a class="mnu-tab" href="form_progreso.php">📈 Ver Progreso</a>
    <a class="mnu-tab" href="evolucion_cliente.php">📊 Evolución</a>
    <a class="mnu-tab" href="tienda_indumentaria.php">🛍️ Indumentaria</a>
    <a class="mnu-tab" href="asistente_ia.php">🤖 Asistente IA</a>
    <a class="mnu-tab" href="cena_fin_anio.php">🍽️ Cena Fin de Año</a>
    <a class="mnu-tab" href="cliente_qr_maquinas.php">🧰 QR de Máquinas</a>
  </nav>

  <!-- Drawer (móvil) -->
  <div class="mnu-backdrop" id="mnu-backdrop"></div>
  <aside class="mnu-drawer" id="mnu-drawer">
    <div class="mnu-head">
      <button class="mnu-close" id="mnu-close">✕</button>
      <div class="mnu-title">Menú</div>
    </div>
    <ul class="mnu-list">
      <li><a class="mnu-item" href="panel_cliente.php"><span class="mnu-item__icon">🏠</span><span class="mnu-item__text">Inicio</span></a></li>
      <li><a class="mnu-item" href="ver_turnos_cliente.php"><span class="mnu-item__icon">📅</span><span class="mnu-item__text">Ver Turnos</span></a></li>
      <li><a class="mnu-item" href="ver_mis_pagos.php"><span class="mnu-item__icon">💳</span><span class="mnu-item__text">Mis Pagos</span></a></li>
      <li><a class="mnu-item" href="pago_online.php"><span class="mnu-item__icon">⚡</span><span class="mnu-item__text">Pago Online</span></a></li>
      <li><a class="mnu-item" href="form_progreso.php"><span class="mnu-item__icon">📈</span><span class="mnu-item__text">Ver Progreso</span></a></li>
      <li><a class="mnu-item" href="evolucion_cliente.php"><span class="mnu-item__icon">📊</span><span class="mnu-item__text">Evolución</span></a></li>
      <li><a class="mnu-item" href="tienda_indumentaria.php"><span class="mnu-item__icon">🛍️</span><span class="mnu-item__text">Indumentaria</span></a></li>
      <li><a class="mnu-item" href="asistente_ia.php"><span class="mnu-item__icon">🤖</span><span class="mnu-item__text">Asistente IA</span></a></li>
      <li><a class="mnu-item" href="cena_fin_anio.php"><span class="mnu-item__icon">🍽️</span><span class="mnu-item__text">Cena Fin de Año</span></a></li>
      <li><a class="mnu-item" href="cliente_qr_maquinas.php"><span class="mnu-item__icon">🧰</span><span class="mnu-item__text">QR de Máquinas</span></a></li>
      <li><a class="mnu-item" href="cliente_acceso.php?logout=1"><span class="mnu-item__icon">🚪</span><span class="mnu-item__text">Salir</span></a></li>
    </ul>
  </aside>
</header>

<div class="container">
  <h2>🤖 Asistente Nutricional</h2>

  <?php if (isset($_GET['ok'])): ?>
    <div class="msg">✅ ¡Progreso guardado! Mostrando la última carga de <b>form_progreso.php</b>.</div>
  <?php endif; ?>

  <div class="row">
    <section class="card">
      <form method="GET" class="flex">
        <input type="hidden" name="progreso_id" value="<?= (int)$progreso_id ?>">
        <label>Objetivo:</label>
        <select name="objetivo">
          <option value="bajar peso" <?= $objetivo==='bajar peso'?'selected':'' ?>>Bajar peso</option>
          <option value="mantener"   <?= $objetivo==='mantener'  ?'selected':'' ?>>Mantener</option>
          <option value="subir peso" <?= $objetivo==='subir peso'?'selected':'' ?>>Subir peso</option>
        </select>
        <button class="btn" type="submit">Actualizar plan</button>
        <?php if ($es_diabetico): ?><span class="pill">⚠️ Ajustes para diabetes</span><?php endif; ?>
        <?php if ($ingesta_hoy['origen']): ?><span class="pill">🍽️ Comidas: <?= h($ingesta_hoy['origen']) ?></span><?php endif; ?>
      </form>

      <div class="msg">
        <?= nl2br(h($mensaje)) ?><br>
        <span class="muted">* Orientación general; no reemplaza consejo profesional.</span>
      </div>

      <div class="grid3" style="margin-top:8px">
        <div class="kpi">Peso actual<b><?= num($peso_ref,1) ?> kg</b></div>
        <div class="kpi">Altura<b><?= num($altura_m*100,0) ?> cm</b></div>
        <div class="kpi">IMC<b><?= num($imc,1) ?> (<?= h($cat) ?>)</b></div>
      </div>

      <div class="grid3" style="margin-top:8px">
        <div class="kpi">Agua diaria<b><?= num($agua_l,1) ?> L</b></div>
        <div class="kpi">Proteínas objetivo<b><?= (int)$prot_total ?> g/día</b></div>
        <div class="kpi">Calorías objetivo<b><?= (int)$kcal_obj ?> kcal</b></div>
      </div>

      <div class="grid3" style="margin-top:8px">
        <div class="kpi">Proteínas<b><?= (int)$g_prot ?> g</b></div>
        <div class="kpi">Carbohidratos<b><?= (int)$g_carb ?> g</b></div>
        <div class="kpi">Grasas<b><?= (int)$g_gras ?> g</b></div>
      </div>
    </section>

    <aside class="card">
      <h3 style="margin:0 0 8px">📝 Última sesión</h3>
      <?php if ($ult_fmt): ?>
        <p style="margin:0 0 8px"><strong>Fecha:</strong> <?= $ult_fmt['fecha'] ?></p>
        <p style="margin:0 0 8px"><strong>Peso:</strong> <?= $ult_fmt['peso'] ?></p>
        <p style="margin:0 0 8px"><strong>Duración:</strong> <?= (int)$ult_fmt['dur'] ?> min</p>
        <p style="margin:0"><strong>Calorías perdidas:</strong> <?= (int)$ult_fmt['kcal'] ?> kcal</p>
        <?php if ($ult_fmt['obj']): ?><p class="muted" style="margin:6px 0 0">Objetivo: <?= $ult_fmt['obj'] ?></p><?php endif; ?>
        <?php if ($ult_fmt['notas']): ?><p class="muted" style="margin:0">Notas: <?= $ult_fmt['notas'] ?></p><?php endif; ?>
      <?php else: ?>
        <p class="muted" style="margin:0">Sin registros aún.</p>
      <?php endif; ?>

      <h3 style="margin:12px 0 6px">📆 Últimos 7 días</h3>
      <p style="margin:0">Sesiones: <strong><?= (int)($stats7['sesiones'] ?? 0) ?></strong></p>
      <p style="margin:0">Minutos: <strong><?= (int)($stats7['minutos'] ?? 0) ?></strong></p>
      <p style="margin:0">Kcal perdidas: <strong><?= (int)($stats7['kcal'] ?? 0) ?></strong></p>
    </aside>
  </div>

  <section class="card" style="margin-top:12px">
    <h3 style="margin:0 0 8px">📊 Balance diario (<?= h($hoy) ?>)</h3>
    <?php $clase = $kcal_restantes>=50?'ok':($kcal_restantes<=-50?'bad':'warn');
          $txt = $kcal_restantes>=0?'restantes vs objetivo':'excedente vs objetivo'; ?>
    <div class="grid3" style="margin-top:8px">
      <div class="kpi">Calorías comida hoy<b><?= (int)$kcal_comidas_hoy ?> kcal</b></div>
      <div class="kpi">Calorías perdidas hoy<b><?= (int)$kcal_burn_hoy ?> kcal</b><span class="muted"><br><?= (int)$min_hoy ?> min</span></div>
      <div class="kpi">Saldo neto<b><?= (int)$kcal_netas_hoy ?> kcal</b></div>
    </div>
    <div class="kpi" style="margin-top:8px; text-align:center">
      Objetivo diario: <b><?= (int)$kcal_obj ?> kcal</b> ·
      <span class="<?= $clase ?>"><b><?= (int)abs($kcal_restantes) ?> kcal</b> <?= $txt ?></span>
    </div>

    <?php if ($ingesta_hoy['prot'] || $ingesta_hoy['carb'] || $ingesta_hoy['gras']): ?>
      <div class="grid3" style="margin-top:8px">
        <div class="kpi">Proteínas ingeridas hoy<b><?= (int)$ingesta_hoy['prot'] ?> g</b></div>
        <div class="kpi">Carbohidratos ingeridos hoy<b><?= (int)$ingesta_hoy['carb'] ?> g</b></div>
        <div class="kpi">Grasas ingeridas hoy<b><?= (int)$ingesta_hoy['gras'] ?> g</b></div>
      </div>
    <?php endif; ?>
  </section>

  <section class="card" style="margin-top:12px">
    <h3 style="margin:0 0 8px">🍽️ Plan semanal (<?= h($objetivo) ?><?= $es_diabetico ? ', con ajustes para diabetes' : '' ?>)</h3>
    <table>
      <thead><tr><th>Día</th><th>Desayuno</th><th>Almuerzo</th><th>Merienda</th><th>Cena</th></tr></thead>
      <tbody>
        <?php foreach($plan as $dia=>$comidas): ?>
          <tr>
            <td><?= h($dia) ?></td>
            <td><?= h($comidas['Desayuno']) ?></td>
            <td><?= h($comidas['Almuerzo']) ?></td>
            <td><?= h($comidas['Merienda']) ?></td>
            <td><?= h($comidas['Cena']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-top:8px">Sugerencia general: priorizá alimentos frescos y adaptá por preferencias e indicaciones médicas.</p>
  </section>
</div>

<script>
/* ===== Menú (abrir/cerrar + bloquear scroll) ===== */
(function(){
  const drawer   = document.getElementById('mnu-drawer');
  const backdrop = document.getElementById('mnu-backdrop');
  const openBtn  = document.querySelector('.mnu-open');
  const closeBtn = document.getElementById('mnu-close');
  const lock = (on)=>{ document.documentElement.style.overflow = document.body.style.overflow = on?'hidden':''; }
  function open(){ drawer.classList.add('open'); backdrop.classList.add('show'); lock(true); }
  function close(){ drawer.classList.remove('open'); backdrop.classList.remove('show'); lock(false); }
  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  backdrop?.addEventListener('click', close);
  window.addEventListener('keydown', e=>{ if(e.key==='Escape') close(); });
})();
</script>
</body>
</html>
