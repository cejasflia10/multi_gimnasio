<?php
/* ============================================================================
   detalle_membresia.php — Ficha de membresía (turnos + eliminar)
   - Carga estilo_unificado.css
   - Pagos: tabla pagos_membresia/pagos o embebidos
   - Turnos: gym_clientes_plan (cliente_id OR plan_id) usando gimnasio de la membresía
   - Botón Eliminar turno (gym_clientes_plan) con CSRF simple
   ============================================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
@require_once __DIR__ . '/menu_horizontal.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Producción: ocultar warnings en pantalla */
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function has_table(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $r=$db->query("SHOW TABLES LIKE '$t'"); return $r&&$r->num_rows>0; }
function col_exists(mysqli $db, string $table, string $col): bool {
  $table=$db->real_escape_string($table); $col=$db->real_escape_string($col);
  $r=$db->query("SHOW COLUMNS FROM `$table` LIKE '$col'"); return $r&&$r->num_rows>0;
}
function pick_col(mysqli $db, string $table, array $cands){ foreach($cands as $c){ if(col_exists($db,$table,$c)) return $c; } return null; }
function fetch_all_assoc(mysqli_result $res): array { $o=[]; while($x=$res->fetch_assoc()) $o[]=$x; return $o; }
function fmt_date($d){ if(!$d || $d==='0000-00-00') return '—'; $ts=strtotime($d); return $ts?date('d/m/Y',$ts):h($d); }
function fmt_hhmm_safe($t){ if ($t===null || $t==='') return '—'; return substr((string)$t,0,5); }
function days_diff($a,$b){ $ta=strtotime($a); $tb=strtotime($b); if(!$ta||!$tb) return null; return (int)floor(($tb-$ta)/86400); }

/* Normaliza dias_json con comillas dobladas y varios formatos */
function parse_dias_any($raw){
  if ($raw===null || $raw==='') return '—';
  $s = (string)$raw;
  if (strpos($s,'""') !== false) $s = str_replace('""','"',$s); // "[""Lunes"",""Martes""]" -> "["Lunes","Martes"]"
  $s = trim($s);
  if (strlen($s)>=2 && ($s[0]==='"' && substr($s,-1)==='"')) $s = trim($s,'"');

  $j = json_decode($s, true);
  $list = [];
  if (json_last_error() === JSON_ERROR_NONE) {
    if (is_array($j)) {
      $is_assoc = array_keys($j) !== range(0, count($j)-1);
      if ($is_assoc) { foreach ($j as $k=>$v) if ($v) $list[]=(string)$k; }
      else { foreach ($j as $v) $list[]=(string)$v; }
    } elseif (is_string($j)) {
      $list = preg_split('/[\s,;|]+/u', $j, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
  } else {
    $list = preg_split('/[\s,;|]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  }

  $map = [
    '0'=>'Dom','1'=>'Lun','2'=>'Mar','3'=>'Mié','4'=>'Jue','5'=>'Vie','6'=>'Sáb','7'=>'Dom',
    'dom'=>'Dom','domingo'=>'Dom','lun'=>'Lun','lunes'=>'Lun','mar'=>'Mar','martes'=>'Mar',
    'mie'=>'Mié','mié'=>'Mié','miercoles'=>'Mié','miércoles'=>'Mié',
    'jue'=>'Jue','jueves'=>'Jue','vie'=>'Vie','viernes'=>'Vie',
    'sab'=>'Sáb','sáb'=>'Sáb','sabado'=>'Sáb','sábado'=>'Sáb'
  ];
  $out=[];
  foreach ($list as $d){
    $k=strtolower(trim($d));
    if (isset($map[$k])) $out[]=$map[$k];
    elseif (ctype_digit($k) && isset($map[$k])) $out[]=$map[$k];
    elseif ($k!=='') $out[]=ucfirst($k);
  }
  $out=array_values(array_unique($out));
  return $out? implode(', ',$out) : '—';
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/* ===== Param ===== */
$id = (int)($_GET['id'] ?? $_GET['membresia_id'] ?? 0);
if ($id<=0){ header('Location: membresias.php'); exit; }

/* ===== Acciones (Eliminar turno) ===== */
$msg = null; $msg_err = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion']) && $_POST['accion']==='del_turno') {
  $csrf_ok = hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
  $del_id = (int)($_POST['del_turno_id'] ?? 0);
  if (!$csrf_ok) {
    $msg_err = 'Operación no autorizada (CSRF).';
  } elseif ($del_id<=0) {
    $msg_err = 'ID de turno inválido.';
  } else {
    // Validar que el turno existe y pertenece al mismo gimnasio de la membresía
    $qG = $conexion->query("SELECT gcp.id, gcp.gimnasio_id FROM gym_clientes_plan gcp WHERE gcp.id = $del_id LIMIT 1");
    if (!$qG || $qG->num_rows===0) {
      $msg_err = 'Turno no encontrado.';
    } else {
      $gcp = $qG->fetch_assoc();
      // Aún no sabemos el gym de la membresía; lo verificamos tras cargar la membresía (debajo).
      $_SESSION['__pending_delete_gcp'] = (int)$gcp['id']; // guardo para verificar después de cargar M
    }
  }
}

/* ===== Tablas base ===== */
$TAB_M = has_table($conexion,'membresias') ? 'membresias' : (has_table($conexion,'membresia') ? 'membresia' : null);
$TAB_C = has_table($conexion,'clientes')   ? 'clientes'   : null;
$TAB_P = has_table($conexion,'planes')     ? 'planes'     : null;
if (!$TAB_M){ http_response_code(500); exit('❌ No existe la tabla de membresías.'); }

/* Cols membresía */
$COL_ID          = pick_col($conexion,$TAB_M,['id','membresia_id']);
$COL_CLIENTE_ID  = pick_col($conexion,$TAB_M,['cliente_id','id_cliente']);
$COL_GYM_ID      = pick_col($conexion,$TAB_M,['gimnasio_id','id_gimnasio','gimnasio']);
$COL_PLAN_ID     = pick_col($conexion,$TAB_M,['plan_id','id_plan']);
$COL_FINI        = pick_col($conexion,$TAB_M,['fecha_inicio','inicio','desde']);
$COL_FVTO        = pick_col($conexion,$TAB_M,['fecha_vencimiento','vencimiento','hasta']);
$COL_CL_DISP     = pick_col($conexion,$TAB_M,['clases_disponibles','clases_restantes','clases']);
$COL_CL_TOT      = pick_col($conexion,$TAB_M,['clases_totales','clases_cantidad','total_clases']);
$COL_TOTAL       = pick_col($conexion,$TAB_M,['total','monto_total','precio_total','importe','total_pagar']);
$COL_OBS         = pick_col($conexion,$TAB_M,['observaciones','observacion','obs','nota']);
$COL_ESTADO      = pick_col($conexion,$TAB_M,['estado','status']);

/* SELECT principal */
$sel = [];
$sel[] = "m.`$COL_ID` AS mid";
if ($COL_CLIENTE_ID) $sel[] = "m.`$COL_CLIENTE_ID` AS cliente_id";
if ($COL_GYM_ID)     $sel[] = "m.`$COL_GYM_ID` AS gimnasio_id";
if ($COL_PLAN_ID)    $sel[] = "m.`$COL_PLAN_ID` AS plan_id";
if ($COL_FINI)       $sel[] = "m.`$COL_FINI` AS fecha_inicio";
if ($COL_FVTO)       $sel[] = "m.`$COL_FVTO` AS fecha_vencimiento";
if ($COL_CL_DISP)    $sel[] = "m.`$COL_CL_DISP` AS clases_disponibles";
if ($COL_CL_TOT)     $sel[] = "m.`$COL_CL_TOT` AS clases_totales";
if ($COL_TOTAL)      $sel[] = "m.`$COL_TOTAL` AS total";
if ($COL_OBS)        $sel[] = "m.`$COL_OBS` AS observaciones";
if ($COL_ESTADO)     $sel[] = "m.`$COL_ESTADO` AS estado";

foreach ([
  'pago_efectivo','pago_transferencia','pago_debito','pago_credito','pago_cuenta_corriente','otros_pagos','descuento'
] as $alias){ if (col_exists($conexion,$TAB_M,$alias)) $sel[] = "m.`$alias` AS `$alias`"; }

$sql = "SELECT ".implode(", ", $sel)." FROM `$TAB_M` m WHERE m.`$COL_ID` = ".(int)$id;
$res = $conexion->query($sql);
if (!$res || $res->num_rows===0){ http_response_code(404); exit('❌ No se encontró la membresía.'); }
$M = $res->fetch_assoc();

/* Gym a usar para filtrar turnos: primero el de la membresía, si no hay, el de sesión */
$gimnasio_id_memb = (int)($M['gimnasio_id'] ?? 0);
$gymFilterId = $gimnasio_id_memb > 0 ? $gimnasio_id_memb : (int)($_SESSION['gimnasio_id'] ?? 0);

/* Si quedó una eliminación pendiente, validar gimnasio y ejecutar */
if (isset($_SESSION['__pending_delete_gcp'])) {
  $del_id = (int)$_SESSION['__pending_delete_gcp'];
  unset($_SESSION['__pending_delete_gcp']);
  $qVal = $conexion->query("SELECT id, gimnasio_id FROM gym_clientes_plan WHERE id = $del_id");
  if ($qVal && $qVal->num_rows>0) {
    $rowV = $qVal->fetch_assoc();
    if ((int)$rowV['gimnasio_id'] === $gymFilterId) {
      if ($conexion->query("DELETE FROM gym_clientes_plan WHERE id = $del_id")) {
        $msg = 'Turno eliminado correctamente.';
      } else {
        $msg_err = 'No se pudo eliminar el turno. '.$conexion->error;
      }
    } else {
      $msg_err = 'El turno no pertenece a este gimnasio. Operación cancelada.';
    }
  } else {
    $msg_err = 'Turno no encontrado al eliminar.';
  }
}

/* Derivados */
$hoy = date('Y-m-d');
$fecha_inicio = $M['fecha_inicio'] ?? null;
$fecha_venc   = $M['fecha_vencimiento'] ?? null;
$dias_restantes = ($fecha_venc ? days_diff($hoy,$fecha_venc) : null);
$estado_txt='—'; $estado_cl='';
if ($fecha_venc){
  if ($hoy > $fecha_venc){ $estado_txt='Vencida'; $estado_cl='estado-vencida'; }
  else { $estado_txt='Activa'; $estado_cl='estado-activa'; if ($dias_restantes!==null && $dias_restantes<=3){ $estado_txt='Próxima a vencer'; $estado_cl='estado-alerta'; } }
} elseif (!empty($M['estado'])) { $estado_txt = (string)$M['estado']; }

/* Clases */
$clases_totales     = isset($M['clases_totales']) ? (int)$M['clases_totales'] : null;
$clases_disponibles = isset($M['clases_disponibles']) ? (int)$M['clases_disponibles'] : null;

/* Pagos (tabla secundaria) */
$pagos=[]; $TAB_PAG=null;
foreach (['pagos_membresia','pagos'] as $tp){
  if (!has_table($conexion,$tp)) continue;
  $mid = pick_col($conexion,$tp,['membresia_id','id_membresia']); if(!$mid) continue;
  $TAB_PAG = $tp;
  $COL_PAG_FECHA = pick_col($conexion,$tp,['fecha','fecha_pago','created_at']);
  $COL_PAG_MONTO = pick_col($conexion,$tp,['monto','importe','total']);
  $COL_PAG_MET   = pick_col($conexion,$tp,['metodo','medio','forma']);
  $COL_PAG_OBS   = pick_col($conexion,$tp,['observaciones','obs','nota']);
  $sqlp = "SELECT ".
          ($COL_PAG_FECHA ? "`$COL_PAG_FECHA` AS fecha," : "NULL AS fecha,").
          ($COL_PAG_MONTO ? "`$COL_PAG_MONTO` AS monto," : "NULL AS monto,").
          ($COL_PAG_MET   ? "`$COL_PAG_MET`   AS metodo," : "NULL AS metodo,").
          ($COL_PAG_OBS   ? "`$COL_PAG_OBS`   AS obs"     : "NULL AS obs").
          " FROM `$tp` WHERE `$mid` = ".(int)$id." ORDER BY ".($COL_PAG_FECHA? "`$COL_PAG_FECHA` DESC":"1");
  if ($rp = $conexion->query($sqlp)){ $pagos = fetch_all_assoc($rp); }
  break;
}

/* Pagos embebidos (fallback) */
$emb = [
  'efectivo'       => (float)($M['pago_efectivo'] ?? 0),
  'transferencia'  => (float)($M['pago_transferencia'] ?? 0),
  'debito'         => (float)($M['pago_debito'] ?? 0),
  'credito'        => (float)($M['pago_credito'] ?? 0),
  'cuenta_corriente'=> (float)($M['pago_cuenta_corriente'] ?? 0),
  'otros'          => (float)($M['otros_pagos'] ?? 0),
];
$descuento_val = (float)($M['descuento'] ?? 0);
$total_plan = (float)($M['total'] ?? 0);
$total_pagado_emb = array_sum($emb);
$dif = $total_plan - $total_pagado_emb;

/* ================= Turnos (gym_clientes_plan) ================= */
$turnos = [];
$turnoFuente = null;
$cliente_id   = (int)($M['cliente_id'] ?? 0);
$plan_id_memb = (int)($M['plan_id'] ?? 0);

if (has_table($conexion,'gym_clientes_plan') && ($cliente_id>0 || $plan_id_memb>0)) {
  $t = 'gym_clientes_plan';

  $condsOR = [];
  if ($cliente_id>0)   $condsOR[] = "`cliente_id` = $cliente_id";
  if ($plan_id_memb>0) $condsOR[] = "`plan_id` = $plan_id_memb";

  $where = [];
  if ($condsOR) $where[] = '('.implode(' OR ', $condsOR).')';
  if ($gymFilterId>0)  $where[] = "`gimnasio_id` = $gymFilterId";
  if (!$where) $where[] = '1';

  $sqlt = "SELECT id, gimnasio_id, cliente_id, plan_id, desde, hasta, hora, dias_json, profesor_id
           FROM `$t`
           WHERE ".implode(' AND ', $where)."
           ORDER BY desde, hora";

  if ($rt = $conexion->query($sqlt)) {
    while ($r = $rt->fetch_assoc()) {
      $dj = (string)($r['dias_json'] ?? '');
      $turnos[] = [
        'fuente' => 'gym_clientes_plan',
        'id'     => (int)$r['id'],
        'desde'  => $r['desde'] ?? null,
        'hasta'  => $r['hasta'] ?? null,
        'hora'   => $r['hora'] ?? null,
        'dias'   => parse_dias_any($dj),
        'profesor_id' => $r['profesor_id'] ?? null,
      ];
    }
  }
  if ($turnos) $turnoFuente = 'gym_clientes_plan';
}

/* ===== URLs ===== */
$URL_LISTA  = 'membresias.php';
$URL_NUEVA  = 'nueva_membresia.php';
$URL_PRINT  = 'waiver_print.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Detalle de Membresía #<?php echo (int)$M['mid']; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor { --lh: 1.45; }
    .contenedor, .contenedor * { line-height: var(--lh); }
    .card { padding: 14px 16px; }
    .fila { display:flex; gap:10px; align-items:flex-start; padding:6px 0; }
    .fila .lbl { min-width: 135px; color: var(--texto-muted, #bbb); font-weight:600; }
    .fila .val { flex:1 1 auto; word-break: break-word; overflow-wrap: anywhere; hyphens: auto; }
    .badge, .pill { display:inline-block; padding:4px 10px; border-radius:999px; border:1px solid rgba(255,255,255,.12); }
    .estado-activa{color:#16a34a}.estado-alerta{color:#f59e0b}.estado-vencida{color:#ef4444}
    .resaltado { color: var(--acento, #d4af37); }
    .table-wrap { width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table.tabla { width:100%; border-collapse:separate; border-spacing:0; table-layout:fixed; font-size:0.98rem; }
    table.tabla th, table.tabla td { padding:10px 12px; border-bottom:1px solid rgba(255,255,255,.12); vertical-align:top; word-break:break-word; overflow-wrap:anywhere; hyphens:auto; }
    table.tabla th { color: var(--texto-muted, #bbb); font-weight:700; }
    .num { text-align:right; font-variant-numeric: tabular-nums; }
    .nowrap { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .acciones { display:flex; gap:6px; flex-wrap:wrap; }
    .msg { margin:10px 0; padding:10px; border-radius:6px; }
    .msg.ok { background:#052; color:#bff7cf; border:1px solid #0a4; }
    .msg.err{ background:#320; color:#ffd7cc; border:1px solid #a40; }
    @media (max-width:720px){ .fila .lbl{min-width:110px} table.tabla th, table.tabla td{padding:10px} }
  </style>
  <script>
    function confirmarEliminacion(form){
      if(confirm('¿Eliminar este turno fijo? Esta acción no se puede deshacer.')){
        form.submit();
      }
      return false;
    }
  </script>
</head>
<body>
<?php if (function_exists('render_menu_horizontal')) { @render_menu_horizontal('membresias'); } ?>
<div class="contenedor">

  <?php if ($msg): ?><div class="msg ok"><?php echo h($msg); ?></div><?php endif; ?>
  <?php if ($msg_err): ?><div class="msg err"><?php echo h($msg_err); ?></div><?php endif; ?>

  <h2>Detalle de Membresía <span class="resaltado">#<?php echo (int)$M['mid']; ?></span></h2>

  <div class="grid-2">
    <div class="card">
      <h3>Datos</h3>
      <div class="fila"><span class="lbl">Estado:</span> <span class="badge <?php echo h($estado_cl); ?>"><?php echo h($estado_txt); ?><?php if($fecha_venc): ?> · vence <?php echo fmt_date($fecha_venc); ?><?php endif; ?></span></div>
      <div class="fila"><span class="lbl">Cliente:</span>
        <span class="val">
          <?php echo h(trim(($M['cli_nombre'] ?? '').' '.($M['cli_apellido'] ?? '')) ?: '—'); ?>
        </span>
      </div>
      <div class="fila"><span class="lbl">Plan:</span>
        <span class="val"><?php echo h($M['plan_nombre'] ?? '—'); ?></span>
      </div>
      <div class="fila"><span class="lbl">Período:</span>
        <span class="val"><?php echo fmt_date($M['fecha_inicio'] ?? ''); ?> → <?php echo fmt_date($fecha_venc); ?>
          <?php if($dias_restantes!==null && $fecha_venc && $hoy <= $fecha_venc): ?> · <span class="pill"><?php echo (int)$dias_restantes; ?> días restantes</span><?php endif; ?>
        </span>
      </div>
      <div class="fila"><span class="lbl">Clases:</span>
        <span class="val">
        <?php
          if ($clases_totales!==null && $clases_disponibles!==null){
            $usadas = max(0, $clases_totales-$clases_disponibles);
            echo "$usadas usadas / $clases_totales totales · <b>$clases_disponibles</b> disponibles";
          } elseif ($clases_disponibles!==null){
            echo "<b>$clases_disponibles</b> disponibles";
          } else { echo "—"; }
        ?>
        </span>
      </div>
      <div class="fila"><span class="lbl">Importe plan:</span>
        <span class="val"><?php echo isset($M['total']) ? '$'.number_format((float)$M['total'],2,',','.') : '—'; ?></span>
      </div>

      <?php if(!empty($M['observaciones'])): ?>
      <div class="fila"><span class="lbl">Observaciones:</span>
        <div class="val" style="white-space:pre-wrap;"><?php echo h($M['observaciones']); ?></div>
      </div>
      <?php endif; ?>

      <div class="fila acciones" style="gap:8px; flex-wrap:wrap;">
        <a class="btn" href="<?php echo h($URL_LISTA); ?>">← Volver</a>
        <a class="btn" href="<?php echo h($URL_NUEVA); ?>?cliente_id=<?php echo (int)($M['cliente_id'] ?? 0); ?>">＋ Nueva</a>
        <a class="btn" href="<?php echo h($URL_PRINT); ?>?id=<?php echo (int)$M['mid']; ?>">🖨️ Imprimir</a>
      </div>
    </div>

    <div class="card">
      <h3>Pagos</h3>
      <div class="table-wrap">
        <table class="tabla">
          <?php if (!empty($pagos)): ?>
            <colgroup><col style="width:22%"><col style="width:28%"><col style="width:22%"><col style="width:28%"></colgroup>
            <thead><tr><th>Fecha</th><th>Método</th><th class="num">Monto</th><th class="muted">Obs</th></tr></thead>
            <tbody>
              <?php foreach($pagos as $pg): ?>
              <tr>
                <td class="nowrap"><?php echo fmt_date($pg['fecha'] ?? ''); ?></td>
                <td><?php echo h($pg['metodo'] ?? '—'); ?></td>
                <td class="num">$<?php echo number_format((float)($pg['monto'] ?? 0),2,',','.'); ?></td>
                <td><?php echo h($pg['obs'] ?? ''); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          <?php else: ?>
            <colgroup><col style="width:60%"><col style="width:40%"></colgroup>
            <thead><tr><th>Concepto</th><th class="num">Importe</th></tr></thead>
            <tbody>
              <tr><td>Efectivo</td><td class="num">$<?php echo number_format((float)($emb['efectivo'] ?? 0),2,',','.'); ?></td></tr>
              <tr><td>Transferencia</td><td class="num">$<?php echo number_format((float)($emb['transferencia'] ?? 0),2,',','.'); ?></td></tr>
              <tr><td>Débito</td><td class="num">$<?php echo number_format((float)($emb['debito'] ?? 0),2,',','.'); ?></td></tr>
              <tr><td>Crédito</td><td class="num">$<?php echo number_format((float)($emb['credito'] ?? 0),2,',','.'); ?></td></tr>
              <tr><td>Cuenta corriente</td><td class="num">$<?php echo number_format((float)($emb['cuenta_corriente'] ?? 0),2,',','.'); ?></td></tr>
              <tr><td>Otros</td><td class="num">$<?php echo number_format((float)($emb['otros'] ?? 0),2,',','.'); ?></td></tr>
              <tr><td>Descuento</td><td class="num"><?php echo $descuento_val>0? '$'.number_format($descuento_val,2,',','.') : '—'; ?></td></tr>
              <tr><th>Total pagado</th><th class="num">$<?php echo number_format($total_pagado_emb,2,',','.'); ?></th></tr>
              <tr><th>Total del plan</th><th class="num">$<?php echo number_format($total_plan,2,',','.'); ?></th></tr>
              <tr><th>Diferencia</th><th class="num" style="color:<?php echo $dif<=0?'#16a34a':'#ef4444'; ?>">$<?php echo number_format($dif,2,',','.'); ?></th></tr>
            </tbody>
          <?php endif; ?>
        </table>
      </div>

      <div class="fila acciones" style="gap:8px; flex-wrap:wrap;">
        <a class="btn" href="registrar_pago.php?membresia_id=<?php echo (int)$M['mid']; ?>">＋ Registrar pago</a>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Turnos personalizados</h3>
    <div class="table-wrap">
      <table class="tabla">
        <colgroup>
          <col style="width:22%"><col style="width:32%"><col style="width:16%"><col style="width:14%"><col style="width:16%">
        </colgroup>
        <thead><tr><th>Vigencia</th><th>Días</th><th class="nowrap">Hora</th><th>Profesor</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if (!empty($turnos)): ?>
          <?php foreach ($turnos as $t): ?>
            <?php
              $vig     = fmt_date($t['desde'] ?? null) . ' → ' . fmt_date($t['hasta'] ?? null);
              $horaTxt = fmt_hhmm_safe($t['hora'] ?? null);
              $profTxt = (isset($t['profesor_id']) && $t['profesor_id'] !== null && $t['profesor_id'] !== '')
                          ? '#'.(int)$t['profesor_id'] : '—';
              $diasTxt = isset($t['dias']) && $t['dias'] !== '' ? (string)$t['dias'] : '—';
            ?>
            <tr>
              <td class="nowrap"><?php echo $vig; ?></td>
              <td><?php echo h($diasTxt); ?></td>
              <td class="nowrap"><?php echo $horaTxt; ?></td>
              <td><?php echo $profTxt; ?></td>
              <td>
                <?php if (($t['fuente'] ?? '') === 'gym_clientes_plan' && !empty($t['id'])): ?>
                  <form method="post" onsubmit="return confirmarEliminacion(this)" style="margin:0">
                    <input type="hidden" name="accion" value="del_turno">
                    <input type="hidden" name="csrf" value="<?php echo h($CSRF); ?>">
                    <input type="hidden" name="del_turno_id" value="<?php echo (int)$t['id']; ?>">
                    <button class="btn btn-danger" type="submit">Eliminar</button>
                  </form>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" class="muted">No se detectaron turnos/reservas para este cliente.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($turnos)): ?>
      <div class="muted" style="margin-top:6px">Fuente: <b>gym_clientes_plan</b> (eliminación solo disponible para esta fuente)</div>
    <?php endif; ?>

    <div class="fila acciones" style="gap:8px; flex-wrap:wrap;">
      <a class="btn" href="ver_turnos_cliente.php?cliente_id=<?php echo (int)($M['cliente_id']??0); ?>">Ver turnos del cliente</a>
    </div>
  </div>

</div>
</body>
</html>
