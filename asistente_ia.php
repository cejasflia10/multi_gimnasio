<?php
// asistente_ia.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
include __DIR__ . '/menu_cliente.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if (!$cliente_id || !$gimnasio_id) { echo "<div style='color:red;text-align:center;padding:12px'>❌ Acceso denegado.</div>"; exit; }

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function num($n,$d=1){ return number_format((float)$n,$d,',','.'); }
function db_has_table(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $r=$db->query("SHOW TABLES LIKE '{$t}'"); return ($r && $r->num_rows>0); }
function db_has_col(mysqli $db, string $t, string $c): bool { $t=$db->real_escape_string($t); $c=$db->real_escape_string($c); $r=$db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'"); return ($r && $r->num_rows>0); }
function pick_col(mysqli $db, string $t, array $cands): ?string { foreach ($cands as $c) if (db_has_col($db,$t,$c)) return $c; return null; }
function mysql_today(mysqli $db): string { $res=$db->query("SELECT CURRENT_DATE() AS hoy"); if($res && ($row=$res->fetch_assoc())) return $row['hoy']; return date('Y-m-d'); }
function valid_ymd($s){ return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s) === 1; }

/* ================ Cliente ================ */
$cliente=null;
if($st=$conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1")){
  $st->bind_param("ii",$cliente_id,$gimnasio_id);
  $st->execute(); $cliente=$st->get_result()->fetch_assoc(); $st->close();
}
if(!$cliente){ echo "<p style='color:red;padding:20px'>⚠️ No se encontró el cliente.</p>"; exit; }

$nombre     = trim(($cliente['apellido']??'').' '.($cliente['nombre']??''));
$altura_raw = $cliente['altura_cm'] ?? $cliente['altura'] ?? null;
$altura_m   = 1.70;
if ($altura_raw!==null && (float)$altura_raw>0) $altura_m = ((float)$altura_raw>3)? ((float)$altura_raw/100.0) : (float)$altura_raw;

/* ================ Progreso: por id o último ================ */
if (!db_has_table($conexion,'progreso')) { echo "<p style='color:red;padding:20px'>⚠️ Falta la tabla <b>progreso</b>.</p>"; exit; }

$progreso_id = (int)($_GET['progreso_id'] ?? 0);
$ult = null;

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

/* Fecha de referencia principal: HOY por defecto (para cruzar comidas del día) */
$hoy_db     = mysql_today($conexion);
$fecha_ref  = isset($_GET['fecha']) && valid_ymd($_GET['fecha']) ? $_GET['fecha'] : $hoy_db;
$is_hoy     = ($fecha_ref === $hoy_db);

/* Si el progreso trae altura, usarla */
if ($ult && (float)($ult['altura_cm']??0)>0) $altura_m = ((float)$ult['altura_cm'])/100.0;

/* Peso de referencia */
$peso_ref = 70.0;
if ($ult && (float)($ult['peso_despues']??0)>0)      $peso_ref = (float)$ult['peso_despues'];
elseif (isset($cliente['peso']) && (float)$cliente['peso']>0) $peso_ref = (float)$cliente['peso'];

/* Enfermedades (si existe en clientes) */
$enfermedades = trim((string)($cliente['enfermedades'] ?? ''));
$es_diabetico = stripos($enfermedades, 'diab') !== false;

/* IMC y categorías */
$imc = ($altura_m>0) ? round($peso_ref/($altura_m*$altura_m),1) : 0.0;
$cat='Desconocido';
if($imc>0){ if($imc<18.5)$cat='Bajo peso'; elseif($imc<25)$cat='Saludable'; elseif($imc<30)$cat='Sobrepeso'; else $cat='Obesidad'; }
$peso_min = round(18.5*$altura_m*$altura_m,1);
$peso_max = round(24.9*$altura_m*$altura_m,1);

/* Objetivo (progreso → override; si no, por IMC) */
$objetivo = strtolower(trim((string)($_GET['objetivo'] ?? '')));
if(!in_array($objetivo,['bajar peso','mantener','subir peso'],true)){
  $obj_prog = strtolower(trim((string)($ult['objetivo'] ?? '')));
  if(in_array($obj_prog,['bajar','mantener','subir'],true)){
    $objetivo = $obj_prog==='bajar' ? 'bajar peso' : ($obj_prog==='subir' ? 'subir peso' : 'mantener');
  }else{
    if($imc>=25) $objetivo='bajar peso'; elseif($imc<18.5) $objetivo='subir peso'; else $objetivo='mantener';
  }
}

/* =================== QUEMADAS del día ===================
   IMPORTANTE: usar DATE(fecha)=? por si la columna es DATETIME */
$kcal_burn_dia=0; $min_dia=0; $sesiones_dia=0;
$sqlK="SELECT COUNT(*) sesiones,
              COALESCE(SUM(calorias_quemadas),0) kcal,
              COALESCE(SUM(duracion_min),0) minutos
       FROM progreso
       WHERE cliente_id=? AND gimnasio_id=? AND DATE(fecha)=?";
if($st=$conexion->prepare($sqlK)){
  $st->bind_param("iis",$cliente_id,$gimnasio_id,$fecha_ref);
  if($st->execute()){
    $r=$st->get_result()->fetch_assoc();
    $sesiones_dia=(int)($r['sesiones']??0);
    $kcal_burn_dia=(int)($r['kcal']??0);
    $min_dia=(int)($r['minutos']??0);
  }
  $st->close();
}

/* =================== Objetivo calórico + macros =================== */
$kcal_base = (int)round($peso_ref*30);
$kcal_obj  = $kcal_base + (($objetivo==='subir peso')?+300:(($objetivo==='bajar peso')?-400:0));
if($objetivo==='bajar peso'){ $p=0.30;$c=0.40;$g=0.30; }
elseif($objetivo==='subir peso'){ $p=0.25;$c=0.50;$g=0.25; }
else{ $p=0.25;$c=0.45;$g=0.30; }
$g_prot=(int)round(($kcal_obj*$p)/4);
$g_carb=(int)round(($kcal_obj*$c)/4);
$g_gras=(int)round(($kcal_obj*$g)/9);

/* =================== COMIDAS del día (detección flexible) =================== */
function detectar_tabla_comidas(mysqli $db): ?array {
  $candidatas=[
    'ingesta_diaria','ingestas','ingesta',
    'registro_comidas','comidas','comidas_diarias',
    'alimentos_consumidos','dieta_diaria','nutricion_diaria',
    'diario_comidas','diario_alimentos','comida_dia','nutricion','dietas'
  ];
  foreach($candidatas as $t){
    if(!db_has_table($db,$t)) continue;
    $cCli  = pick_col($db,$t,['cliente_id','id_cliente','user_id','id_usuario','usuario_id','persona_id']);
    $cGym  = pick_col($db,$t,['gimnasio_id','id_gimnasio']);
    $cFec  = pick_col($db,$t,['fecha','dia','created_at','fecha_registro']);
    $cKcal = pick_col($db,$t,['kcal','kcal_total','kcal_totales','calorias','calorias_totales','kilocalorias']);
    $cProt = pick_col($db,$t,['proteina','proteinas','proteinas_g','protein_g','g_proteina']);
    $cCarb = pick_col($db,$t,['carbohidratos','carbs','carbohidratos_g','carbs_g','g_carbohidrato']);
    $cGras = pick_col($db,$t,['grasas','fat','grasas_g','fat_g','g_grasa']);
    if($cFec && $cKcal && $cCli){
      return ['t'=>$t,'cli'=>$cCli,'gym'=>$cGym,'fec'=>$cFec,'kcal'=>$cKcal,'prot'=>$cProt,'carb'=>$cCarb,'gras'=>$cGras];
    }
  }
  return null;
}

$ingesta_dia=['kcal'=>0,'prot'=>0,'carb'=>0,'gras'=>0,'origen'=>null,'cols'=>[]];
if($det=detectar_tabla_comidas($conexion)){
  $cols=["COALESCE(SUM(`{$det['kcal']}`),0) AS kcal"];
  if($det['prot']) $cols[]="COALESCE(SUM(`{$det['prot']}`),0) AS prot";
  if($det['carb']) $cols[]="COALESCE(SUM(`{$det['carb']}`),0) AS carb";
  if($det['gras']) $cols[]="COALESCE(SUM(`{$det['gras']}`),0) AS gras";

  $sqlC="SELECT ".implode(', ',$cols)." FROM `{$det['t']}` WHERE 1";
  $bind=''; $args=[];
  if($det['cli']){ $sqlC.=" AND `{$det['cli']}`=?"; $bind.='i'; $args[]=$cliente_id; }
  if($det['gym']){ $sqlC.=" AND `{$det['gym']}`=?"; $bind.='i'; $args[]=$gimnasio_id; }
  // Igual que arriba: usar DATE(...) para ser robustos con DATETIME
  $sqlC.=" AND DATE(`{$det['fec']}`)=?"; $bind.='s'; $args[]=$fecha_ref;

  if($st=$conexion->prepare($sqlC)){
    if($bind==='i') $st->bind_param('i',$args[0]);
    elseif($bind==='ii') $st->bind_param('ii',$args[0],$args[1]);
    elseif($bind==='is') $st->bind_param('is',$args[0],$args[1]);
    elseif($bind==='iis') $st->bind_param('iis',$args[0],$args[1],$args[2]);
    else if ($bind!=='') $st->bind_param($bind, ...$args);

    if($st->execute()){
      $r=$st->get_result()->fetch_assoc() ?: [];
      $ingesta_dia['kcal']=(int)($r['kcal']??0);
      $ingesta_dia['prot']=(float)($r['prot']??0);
      $ingesta_dia['carb']=(float)($r['carb']??0);
      $ingesta_dia['gras']=(float)($r['gras']??0);
      $ingesta_dia['origen']=$det['t'];
      $ingesta_dia['cols']=['fec'=>$det['fec'],'kcal'=>$det['kcal'],'prot'=>$det['prot'],'carb'=>$det['carb'],'gras'=>$det['gras']];
    }
    $st->close();
  }
}

/* =================== Balance y “estudio” entrenamiento vs comidas =================== */
$kcal_comidas_dia = (int)$ingesta_dia['kcal'];
$kcal_netas_dia   = $kcal_comidas_dia - $kcal_burn_dia;        // comidas - ejercicio
$kcal_restantes   = $kcal_obj - $kcal_netas_dia;               // distancia al objetivo del día

// Estado del día
if ($kcal_netas_dia > ($kcal_obj + 100))      { $estado='Superávit alto'; $estado_cls='bad'; }
elseif ($kcal_netas_dia > ($kcal_obj - 100))  { $estado='Superávit leve'; $estado_cls='warn'; }
elseif ($kcal_netas_dia < ($kcal_obj - 300))  { $estado='Déficit alto';   $estado_cls='bad'; }
elseif ($kcal_netas_dia < ($kcal_obj - 100))  { $estado='Déficit leve';   $estado_cls='warn'; }
else                                          { $estado='Equilibrado';    $estado_cls='ok'; }

// Comentarios rápidos
$comentarios=[];
if($kcal_comidas_dia===0) $comentarios[]='No hay comidas registradas en la fecha.';
if($kcal_burn_dia===0)    $comentarios[]='No hay entrenamiento registrado en la fecha.';
if($ingesta_dia['prot']>0 && $g_prot>0){
  $pctP = round(($ingesta_dia['prot']/$g_prot)*100);
  $comentarios[] = "Proteínas: {$pctP}% del objetivo (" . (int)$ingesta_dia['prot'] . " / {$g_prot} g)";
}

/* Agua y proteínas guía */
$agua_l = max(1.5, round($peso_ref*0.035,1));
$prot_gkg = ($objetivo==='bajar peso')?1.6:(($objetivo==='subir peso')?2.0:1.4);
$prot_total = round($peso_ref*$prot_gkg);

/* Mensaje principal */
$mensaje="Hola {$nombre}. Tu IMC actual es {$imc} ({$cat}). Rango saludable aprox: {$peso_min}–{$peso_max} kg.";

/* Última sesión formateada (para mostrar detalle) */
$ult_fmt=null;
if($ult){
  $pa=(float)($ult['peso_antes']??0); $pd=(float)($ult['peso_despues']??0); $delta=$pd-$pa;
  $ult_fmt=[
    'id'   => (int)($ult['id'] ?? 0),
    'fecha'=> h($ult['fecha']??''),
    'peso' => num($pa,1)." → ".num($pd,1)." kg (Δ ".(($delta>=0?'+':'−').num(abs($delta),2))." kg)",
    'dur'  => (int)($ult['duracion_min']??0),
    'kcal' => (int)($ult['calorias_quemadas']??0),
    'obj'  => h((string)($ult['objetivo']??'')),
    'notas'=> h((string)($ult['notas']??'')),
  ];
}

/* Debug opcional */
$DEBUG = isset($_GET['debug']) && $_GET['debug']=='1';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asistente IA — Análisis Entrenamiento vs Comidas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0b0b0b; --card:#111; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12); }
    *{box-sizing:border-box}
    body{ margin:0; background:var(--bg); color:var(--fg); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; padding:16px }
    .container{ max-width:1000px; margin:0 auto }
    h2{ margin:8px 0 6px; text-align:center }
    .row{ display:grid; grid-template-columns:1fr; gap:12px; }
    @media (min-width:900px){ .row{ grid-template-columns: 1.2fr .8fr; } }
    .card{ background:var(--card); border:1px solid var(--border); border-radius:16px; padding:14px }
    .flex{ display:flex; gap:8px; flex-wrap:wrap; align-items:center }
    label{ font-weight:700 }
    input,select,button{ padding:8px 10px; border-radius:12px; border:1px solid var(--border); background:#1a1d24; color:#fff }
    .btn{ background:var(--acc); color:#111; border:none; font-weight:800 }
    .msg{ background:#14161c; border:1px solid var(--border); padding:12px; border-radius:12px; margin-top:8px }
    .grid4{ display:grid; gap:8px; grid-template-columns:1fr; } @media (min-width:700px){ .grid4{ grid-template-columns:repeat(4,1fr);} }
    .grid3{ display:grid; gap:8px; grid-template-columns:1fr; } @media (min-width:680px){ .grid3{ grid-template-columns: repeat(3,1fr); } }
    .kpi{ text-align:center; background:#1a1d24; border:1px solid var(--border); border-radius:12px; padding:10px }
    .kpi b{ display:block; font-size:18px; margin-top:4px }
    .muted{ color:var(--muted) }
    .pill{ display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid var(--border); font-size:12px; margin-left:6px }
    .ok{ color:#22c55e } .bad{ color:#ef4444 } .warn{ color:#f59e0b }
    a{ color:#f5c542; text-decoration:none }
    .dbg{ font-size:12px; background:#161922; border:1px dashed #334; padding:8px; border-radius:10px; margin:8px 0 }
  </style>
</head>
<body>
<div class="container">
  <h2>🤖 Asistente Nutricional — Entrenamiento vs Comidas</h2>

  <?php if (isset($_GET['ok'])): ?>
    <div class="msg">✅ ¡Progreso guardado! (desde <b>form_progreso.php</b>)</div>
  <?php endif; ?>

  <!-- Barra de filtros -->
  <section class="card">
    <form method="GET" class="flex">
      <input type="hidden" name="progreso_id" value="<?= (int)$progreso_id ?>">
      <label>Objetivo:</label>
      <select name="objetivo">
        <option value="bajar peso" <?= $objetivo==='bajar peso'?'selected':'' ?>>Bajar peso</option>
        <option value="mantener"   <?= $objetivo==='mantener'  ?'selected':'' ?>>Mantener</option>
        <option value="subir peso" <?= $objetivo==='subir peso'?'selected':'' ?>>Subir peso</option>
      </select>
      <label style="margin-left:10px">Fecha (comidas/entrenos):</label>
      <input type="date" name="fecha" value="<?= h($fecha_ref) ?>" />
      <button class="btn" type="submit">Aplicar</button>

      <?php if ($ult): ?>
        <a class="pill" href="?progreso_id=<?= (int)$ult['id'] ?>&fecha=<?= h($ult['fecha']) ?>&objetivo=<?= urlencode($objetivo) ?>">Usar fecha del progreso</a>
      <?php endif; ?>
      <?php if (!$is_hoy): ?>
        <a class="pill" href="?fecha=<?= h($hoy_db) ?>&objetivo=<?= urlencode($objetivo) ?>">Usar HOY</a>
      <?php endif; ?>
      <?php if ($ingesta_dia['origen']): ?><span class="pill">🍽️ Comidas: <?= h($ingesta_dia['origen']) ?></span><?php endif; ?>
      <?php if ($ult): ?><span class="pill">🏷️ progreso_id: <?= (int)$ult['id'] ?></span><?php endif; ?>
      <a class="pill" href="?<?= http_build_query(['fecha'=>$fecha_ref,'objetivo'=>$objetivo,'debug'=>1]) ?>">Debug</a>
    </form>
  </section>

  <!-- Resumen superior -->
  <section class="card">
    <div class="grid4">
      <div class="kpi">IMC<b><?= num($imc,1) ?></b></div>
      <div class="kpi">Objetivo ingesta<b><?= (int)$kcal_obj ?> kcal</b></div>
      <div class="kpi">Proteínas<b><?= (int)$g_prot ?> g/día</b></div>
      <div class="kpi">Agua<b><?= num($agua_l,1) ?> L/día</b></div>
    </div>

    <div class="grid3" style="margin-top:10px">
      <div class="kpi">📅 Fecha<b><?= h($is_hoy?'Hoy':$fecha_ref) ?></b></div>
      <div class="kpi">Ingeridas<b><?= (int)$kcal_comidas_dia ?> kcal</b></div>
      <div class="kpi">Quemadas<b><?= (int)$kcal_burn_dia ?> kcal</b><span class="muted"><br><?= (int)$min_dia ?> min · <?= (int)$sesiones_dia ?> sesión/es</span></div>
    </div>

    <?php $estado_cls_print = $estado_cls; ?>
    <div class="grid3" style="margin-top:8px">
      <div class="kpi">Balance neto (ingeridas − quemadas)<b><?= (int)$kcal_netas_dia ?> kcal</b></div>
      <div class="kpi">Estado<b class="<?= $estado_cls_print ?>"><?= h($estado) ?></b></div>
      <div class="kpi">Progreso ingesta<b><?= (int)$kcal_comidas_dia ?> / <?= (int)$kcal_obj ?> kc</b></div>
    </div>

    <?php if (!empty($comentarios)): ?>
      <div class="msg" style="margin-top:8px">
        <?php foreach($comentarios as $c) echo "• ".h($c)."<br>"; ?>
      </div>
    <?php endif; ?>

    <?php if ($DEBUG): ?>
      <div class="dbg">
        <b>DEBUG</b><br>
        fecha_ref: <?= h($fecha_ref) ?> (hoy_db: <?= h($hoy_db) ?>)<br>
        quemadas_dia: <?= (int)$kcal_burn_dia ?> | min_dia: <?= (int)$min_dia ?> | sesiones: <?= (int)$sesiones_dia ?><br>
        comidas: origen=<?= h($ingesta_dia['origen']??'N/D') ?> cols=<?= h(json_encode($ingesta_dia['cols'])) ?><br>
        ingeridas_dia: <?= (int)$kcal_comidas_dia ?> | netas: <?= (int)$kcal_netas_dia ?> | restantes vs objetivo: <?= (int)$kcal_restantes ?><br>
        Nota: consultas usan DATE(columna)=? para compatibilidad con DATE/DATETIME.
      </div>
    <?php endif; ?>
  </section>

  <div class="row" style="margin-top:12px">
    <!-- Panel objetivo y macros -->
    <section class="card">
      <h3 style="margin:0 0 8px">🎯 Objetivo y macros</h3>
      <div class="grid3">
        <div class="kpi">Proteínas objetivas<b><?= (int)$g_prot ?> g</b></div>
        <div class="kpi">Carbohidratos objetivos<b><?= (int)$g_carb ?> g</b></div>
        <div class="kpi">Grasas objetivas<b><?= (int)$g_gras ?> g</b></div>
      </div>
      <?php if ($ingesta_dia['prot'] || $ingesta_dia['carb'] || $ingesta_dia['gras']): ?>
        <div class="grid3" style="margin-top:8px">
          <div class="kpi">Proteínas ingeridas<b><?= (int)$ingesta_dia['prot'] ?> g</b></div>
          <div class="kpi">Carbohidratos ingeridos<b><?= (int)$ingesta_dia['carb'] ?> g</b></div>
          <div class="kpi">Grasas ingeridas<b><?= (int)$ingesta_dia['gras'] ?> g</b></div>
        </div>
      <?php endif; ?>
      <div class="msg" style="margin-top:8px">
        <?= nl2br(h("Hola {$nombre}. Tu IMC es {$imc} ({$cat}). Rango saludable aprox: {$peso_min}–{$peso_max} kg.")) ?><br>
        <span class="muted">* Orientación general; no reemplaza consejo profesional.</span>
      </div>
    </section>

    <!-- Última sesión -->
    <aside class="card">
      <h3 style="margin:0 0 8px">📝 Última sesión cargada</h3>
      <?php if ($ult_fmt): ?>
        <p style="margin:0 0 8px"><strong>Fecha:</strong> <?= $ult_fmt['fecha'] ?></p>
        <p style="margin:0 0 8px"><strong>Peso:</strong> <?= $ult_fmt['peso'] ?></p>
        <p style="margin:0 0 8px"><strong>Duración:</strong> <?= (int)$ult_fmt['dur'] ?> min</p>
        <p style="margin:0"><strong>Calorías perdidas (sesión):</strong> <?= (int)$ult_fmt['kcal'] ?> kcal</p>
        <?php if ($ult_fmt['obj']): ?><p class="muted" style="margin:6px 0 0">Objetivo: <?= $ult_fmt['obj'] ?></p><?php endif; ?>
        <?php if ($ult_fmt['notas']): ?><p class="muted" style="margin:0">Notas: <?= $ult_fmt['notas'] ?></p><?php endif; ?>
      <?php else: ?>
        <p class="muted" style="margin:0">Sin registros aún.</p>
      <?php endif; ?>
      <p style="margin-top:10px"><a href="form_progreso.php">⟵ Cargar nuevo progreso</a></p>
    </aside>
  </div>
</div>
</body>
</html>
