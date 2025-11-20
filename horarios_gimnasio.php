<?php
/* =============================================================================
   horarios_gimnasio.php — Horarios GYM + planes fijos SIN reservas (OPTIMIZADO)
   - DÍAS FIJOS: Lunes a Sábado (se quita Domingo en UI y cálculos)
   - En la grilla de ocupación el día YA NO es editable (no más combobox),
     se muestra fijo y se envía en un input hidden al guardar.
   - Batch caches por día/hora para ocupación y listado de fijos
   - Regla: si la franja base NO tiene profesor, cuentan TODOS los fijos (incluye profesor_id NULL)
            si la franja base TIENE profesor, cuentan solo los fijos de ese profesor (excluye NULL)
   ============================================================================== */

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
include_once __DIR__.'/menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if (!$gimnasio_id) { header("Location: login.php"); exit; }
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit; }
$isAdmin = (isset($_SESSION['rol']) && strtolower($_SESSION['rol'])==='admin');

$msg = $_GET['ok'] ?? '';
$err = $_GET['err'] ?? '';

/* ===================== utilidades ===================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function nombreDiaEs($fechaYmd){
  $map = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Sábado'];
  return $map[date('l', strtotime($fechaYmd))] ?? 'Lunes';
}
function toHM($t){ return substr((string)$t,0,5); }
function validarFechaYmd($s){ $d=DateTime::createFromFormat('Y-m-d',$s); return $d && $d->format('Y-m-d')===$s; }
function safe_fetch($res){ return ($res && $res instanceof mysqli_result) ? $res->fetch_assoc() : null; }
function hoyYmd(){ return date('Y-m-d'); }

/* DÍAS DE GRACIA tras vencimiento para conservar el turno fijo */
if (!defined('GRACIA_VENCIMIENTO_DIAS')) {
  define('GRACIA_VENCIMIENTO_DIAS', 10);
}

/** preparar/ejecutar con manejo de errores */
function run_stmt(mysqli $db, string $sql, callable $binder=null, bool $returnResult=true){
  global $err, $isAdmin;
  $st = $db->prepare($sql);
  if(!$st){
    $m = "Error prepare: ".$db->error;
    $err = $err ? $err." | ".$m : $m;
    if ($isAdmin) echo "<div class='alert-err'>".$m."</div>";
    return false;
  }
  if($binder){ $binder($st); }
  if(!$st->execute()){
    $m = "Error execute: ".$st->error;
    $err = $err ? $err." | ".$m : $m;
    if ($isAdmin) echo "<div class='alert-err'>".$m."</div>";
    $st->close(); return false;
  }
  if(!$returnResult){ $st->close(); return true; }
  $res = $st->get_result();
  $st->close(); return $res;
}

/* ===================== PLANES (tu tabla) ===================== */
function traerPlanPorId($db, $plan_id) {
  if (!($db instanceof mysqli)) return null;
  $plan_id = (int)$plan_id;

  $res = run_stmt($db,
    "SELECT id, nombre, precio, cantidad_clases, clases, gimnasio_id,
            duracion_meses, dias_disponibles, duracion,
            clases_disponibles, duracion_dias
       FROM planes WHERE id=? LIMIT 1",
    function($st) use ($plan_id){ $st->bind_param("i",$plan_id); }
  );
  $row = safe_fetch($res);
  return $row ? $row : null;
}

/* ===================== pagos ===================== */
function existeTabla(mysqli $db, string $tabla): bool {
  $res = run_stmt($db, "SELECT 1 FROM information_schema.tables
                         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
                  function($st) use($tabla){ $st->bind_param("s",$tabla); });
  return (bool) safe_fetch($res);
}
function columnaDisponible(mysqli $db, string $tabla, array $candidatas): ?string {
  $escTabla = $db->real_escape_string($tabla);
  $in = implode("','", array_map(fn($c)=>$db->real_escape_string($c), $candidatas));
  $sql = "SELECT column_name FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = '{$escTabla}'
            AND column_name IN ('{$in}')
          ORDER BY FIELD(column_name,'fecha_pago','fecha','created_at') LIMIT 1";
  $r = $db->query($sql);
  if ($r && ($row=$r->fetch_assoc())) return $row['column_name'];
  return null;
}
function ultimaFechaPagoCliente(mysqli $db, int $cliente_id): ?string {
  $tabla = 'pagos';
  if (!existeTabla($db, $tabla)) return null;
  $col = columnaDisponible($db, $tabla, ['fecha_pago','fecha','created_at']);
  if (!$col) return null;
  $sql = "SELECT {$col} AS f FROM {$tabla}
           WHERE cliente_id = ? ORDER BY {$col} DESC LIMIT 1";
  $res = run_stmt($db, $sql, function($st) use($cliente_id){ $st->bind_param("i",$cliente_id); });
  $row = safe_fetch($res);
  if (!$row || empty($row['f'])) return null;
  $f = substr((string)$row['f'], 0, 10);
  if (!validarFechaYmd($f)) { $dt = date_create((string)$row['f']); if ($dt) $f=$dt->format('Y-m-d'); else return null; }
  return $f;
}
function rangoPlanDesdePago(array $planRow, ?string $ultimaFechaPago): array {
  $desde = $ultimaFechaPago ?: date('Y-m-d');
  $dm   = (int)($planRow['duracion_meses'] ?? 0);
  $dd   = (int)($planRow['duracion_dias'] ?? 0);
  if ($dm > 0) { $d=date_create($desde); $d->modify("+{$dm} months")->modify("-1 day"); $hasta=$d->format('Y-m-d'); }
  elseif ($dd > 0) { $d=date_create($desde); $d->modify("+{$dd} days")->modify("-1 day"); $hasta=$d->format('Y-m-d'); }
  else { $d=date_create($desde); $d->modify("+30 days")->modify("-1 day"); $hasta=$d->format('Y-m-d'); }
  return [$desde,$hasta];
}

/* ===================== membresías: detectar tabla y plan actual ===================== */
function detectarTablaMembresias(mysqli $db): ?array {
  $candidatos = [
    ['membresias',          ['cliente_id','plan_id'],        ['fecha_inicio','fecha','created_at']],
    ['membresias_clientes', ['cliente_id','plan_id'],        ['fecha_inicio','fecha','created_at']],
    ['clientes_membresias', ['cliente_id','plan_id'],        ['fecha_inicio','fecha','created_at']],
    ['planes_clientes',     ['cliente_id','plan_id'],        ['fecha_inicio','fecha','created_at']],
  ];
  foreach($candidatos as [$tabla,$reqCols,$fechaCols]){
    if (!existeTabla($db,$tabla)) continue;
    $escTabla = $db->real_escape_string($tabla);
    $in = implode("','", array_map(fn($c)=>$db->real_escape_string($c), $reqCols));
    $sql = "SELECT COUNT(*) c FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = '{$escTabla}'
              AND column_name IN ('{$in}')";
    $r = $db->query($sql); $ok = false;
    if ($r && ($row=$r->fetch_assoc())) $ok = ((int)$row['c'] === count($reqCols));
    if (!$ok) continue;

    $ordenCol = null;
    foreach($fechaCols as $fc){
      $chk = $db->query("SELECT 1 FROM information_schema.columns
                         WHERE table_schema = DATABASE() AND table_name = '{$escTabla}'
                           AND column_name = '".$db->real_escape_string($fc)."' LIMIT 1");
      if ($chk && $chk->fetch_assoc()){ $ordenCol=$fc; break; }
    }
    if (!$ordenCol) $ordenCol = 'id';
    return ['tabla'=>$tabla,'fecha_col'=>$ordenCol];
  }
  return null;
}
function planActualDeCliente(mysqli $db, int $cliente_id): ?array {
  $meta = detectarTablaMembresias($db);
  if (!$meta) return null;
  $tabla = $meta['tabla']; $fechaCol = $meta['fecha_col'];
  $sql = "SELECT plan_id FROM {$tabla} WHERE cliente_id=? ORDER BY {$fechaCol} DESC LIMIT 1";
  $res = run_stmt($db, $sql, function($st) use($cliente_id){ $st->bind_param("i",$cliente_id); });
  $row = safe_fetch($res);
  if (!$row || empty($row['plan_id'])) return null;
  return ['tabla'=>$tabla,'plan_id'=>(int)$row['plan_id']];
}

/* ===================== capacidad (cupo) ===================== */
function cupoParaFechaHora(mysqli $db,int $g,int $prof=null,string $fecha,string $hora):int{
  if ($prof===null){
    $res = run_stmt($db, "SELECT cupo_maximo FROM gym_capacidad_fecha
                          WHERE gimnasio_id=? AND fecha=? AND hora=? AND profesor_id IS NULL LIMIT 1",
                    function($st) use($g,$fecha,$hora){ $st->bind_param("iss",$g,$fecha,$hora); });
  } else {
    $res = run_stmt($db, "SELECT cupo_maximo FROM gym_capacidad_fecha
                          WHERE gimnasio_id=? AND fecha=? AND hora=? AND profesor_id=? LIMIT 1",
                    function($st) use($g,$fecha,$hora,$prof){ $st->bind_param("issi",$g,$fecha,$hora,$prof); });
  }
  $row = safe_fetch($res);
  if ($row) return max(1,(int)$row['cupo_maximo']);

  $dia = nombreDiaEs($fecha);
  if ($prof===null){
    $res = run_stmt($db, "SELECT cupo_maximo FROM gym_horarios_base
                          WHERE gimnasio_id=? AND LOWER(TRIM(dia))=LOWER(?) AND hora_inicio<=? AND hora_fin>?
                            AND profesor_id IS NULL
                          ORDER BY hora_inicio DESC LIMIT 1",
                    function($st) use($g,$dia,$hora){ $st->bind_param("isss",$g,$dia,$hora,$hora); });
  } else {
    $res = run_stmt($db, "SELECT cupo_maximo FROM gym_horarios_base
                          WHERE gimnasio_id=? AND LOWER(TRIM(dia))=LOWER(?) AND hora_inicio<=? AND hora_fin>?
                            AND profesor_id=?
                          ORDER BY hora_inicio DESC LIMIT 1",
                    function($st) use($g,$dia,$hora,$prof){ $st->bind_param("isssi",$g,$dia,$hora,$hora,$prof); });
  }
  $row = safe_fetch($res);
  return $row ? max(1,(int)$row['cupo_maximo']) : 1;
}

/* ===================== ocupación fija — OPTIMIZADO ===================== */
/* Política:
   - Franja base SIN profesor: ocupados = TODOS los fijos (incluye profesor_id NULL y los que tienen profesor)
   - Franja base CON profesor: ocupados = solo fijos de ese profesor (excluye NULL)
*/

function buildOcupacionCache(mysqli $db,int $g,string $fechaRef): array{
  global $DIAS_SEM; // DÍAS FIJOS Lunes–Sábado
  $dias = $DIAS_SEM;
  $cache = [];

  // Vigencia: desde<=hoy y hasta>= (hoy - GRACIA_VENCIMIENTO_DIAS)
  $dtRef = DateTime::createFromFormat('Y-m-d', $fechaRef) ?: new DateTime($fechaRef);
  $limiteVig = clone $dtRef;
  $limiteVig->modify('-'.GRACIA_VENCIMIENTO_DIAS.' days');
  $limiteVigStr = $limiteVig->format('Y-m-d');

  foreach($dias as $dia){
    $likeDia = '%"'.$db->real_escape_string($dia).'"%';

    // Conteo por profe específico (excluye NULL)
    $sql1 = "SELECT gp.hora, gp.profesor_id, COUNT(*) c
               FROM gym_clientes_plan gp
              WHERE gp.gimnasio_id=? AND gp.desde<=? AND gp.hasta>=?
                AND gp.profesor_id IS NOT NULL AND gp.dias_json LIKE ?
              GROUP BY gp.hora, gp.profesor_id";
    $res1 = run_stmt($db,$sql1,function($st) use($g,$fechaRef,$limiteVigStr,$likeDia){
      $st->bind_param("isss",$g,$fechaRef,$limiteVigStr,$likeDia);
    });

    // Conteo total ALL (incluye NULL)
    $sql2 = "SELECT gp.hora, COUNT(*) c
               FROM gym_clientes_plan gp
              WHERE gp.gimnasio_id=? AND gp.desde<=? AND gp.hasta>=?
                AND gp.dias_json LIKE ?
              GROUP BY gp.hora";
    $res2 = run_stmt($db,$sql2,function($st) use($g,$fechaRef,$limiteVigStr,$likeDia){
      $st->bind_param("isss",$g,$fechaRef,$limiteVigStr,$likeDia);
    });

    $cache[$dia] = $cache[$dia] ?? [];

    if($res1){ while($r=$res1->fetch_assoc()){
      $h = substr($r['hora'],0,5);
      $pid = (int)$r['profesor_id'];
      $c = (int)$r['c'];
      if(!isset($cache[$dia][$h])) $cache[$dia][$h] = ['SUM_ALL'=>0,'SUM_ASSIGNED'=>0];
      $cache[$dia][$h]['SUM_ASSIGNED'] += $c;
      $cache[$dia][$h][$pid] = $c;
    }}

    if($res2){ while($r=$res2->fetch_assoc()){
      $h = substr($r['hora'],0,5);
      $c = (int)$r['c'];
      if(!isset($cache[$dia][$h])) $cache[$dia][$h] = ['SUM_ALL'=>0,'SUM_ASSIGNED'=>0];
      $cache[$dia][$h]['SUM_ALL'] = $c;
    }}
  }
  return $cache;
}

function buildFijosCache(mysqli $db,int $g,string $fechaRef): array{
  global $DIAS_SEM; // DÍAS FIJOS Lunes–Sábado
  $dias = $DIAS_SEM;
  $cache = [];

  // Misma lógica de vigencia con días de gracia
  $dtRef = DateTime::createFromFormat('Y-m-d', $fechaRef) ?: new DateTime($fechaRef);
  $limiteVig = clone $dtRef;
  $limiteVig->modify('-'.GRACIA_VENCIMIENTO_DIAS.' days');
  $limiteVigStr = $limiteVig->format('Y-m-d');

  foreach($dias as $dia){
    $likeDia = '%"'.$db->real_escape_string($dia).'"%';
    $sql = "SELECT c.id AS cliente_id, c.apellido, c.nombre, gp.dias_json, gp.hora, gp.profesor_id
              FROM gym_clientes_plan gp
              JOIN clientes c ON c.id=gp.cliente_id
             WHERE gp.gimnasio_id=? AND gp.desde<=? AND gp.hasta>=?
               AND gp.dias_json LIKE ?
             ORDER BY c.apellido, c.nombre";
    $res = run_stmt($db,$sql,function($st) use($g,$fechaRef,$limiteVigStr,$likeDia){
      $st->bind_param("isss",$g,$fechaRef,$limiteVigStr,$likeDia);
    });
    if($res){ while($r=$res->fetch_assoc()){
      $h = substr($r['hora'],0,5);
      $pid = $r['profesor_id']===null ? 'NULL' : (string)(int)$r['profesor_id'];
      $cache[$dia][$h][$pid][] = $r;
    }}
  }
  return $cache;
}

/* ===================== estado del form (preview) ===================== */
$sel_cliente  = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
$sel_hora     = $_POST['hora'] ?? '';
$sel_prof     = isset($_POST['profesor_id']) && $_POST['profesor_id']!=='' ? (int)$_POST['profesor_id'] : null;
$sel_dias     = isset($_POST['dias']) && is_array($_POST['dias']) ? array_values($_POST['dias']) : [];
$accion       = $_POST['__accion'] ?? '';

/* ===================== 1A) Alta horario base (UN solo día) ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && $accion==='alta_base'){
  $dia = $_POST['dia'] ?? '';
  $hora_inicio = $_POST['hora_inicio'] ?? '';
  $hora_fin    = $_POST['hora_fin'] ?? '';
  $cupo        = max(1,(int)($_POST['cupo_maximo'] ?? 20));
  $profesor_id = ($_POST['profesor_id'] ?? '')!=='' ? (int)$_POST['profesor_id'] : null;

  $DIAS_VALIDOS = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  if (!in_array($dia,$DIAS_VALIDOS,true) || !$hora_inicio || !$hora_fin || $hora_inicio>=$hora_fin){
    header("Location: horarios_gimnasio.php?err=Datos%20inv%C3%A1lidos%20en%20horario%20base"); exit;
  }

  if ($profesor_id===null){
    $ex = run_stmt($conexion,"SELECT 1 FROM gym_horarios_base WHERE gimnasio_id=? AND dia=? AND hora_inicio=? AND hora_fin=? AND profesor_id IS NULL LIMIT 1",
      function($st) use($gimnasio_id,$dia,$hora_inicio,$hora_fin){ $st->bind_param("isss",$gimnasio_id,$dia,$hora_inicio,$hora_fin); });
    if ($ex && $ex->fetch_assoc()){ header("Location: horarios_gimnasio.php?ok=La%20franja%20ya%20exist%C3%ADa"); exit; }

    run_stmt($conexion, "INSERT INTO gym_horarios_base (gimnasio_id, dia, hora_inicio, hora_fin, cupo_maximo, profesor_id)
                         VALUES (?,?,?,?,?,NULL)",
      function($st) use($gimnasio_id,$dia,$hora_inicio,$hora_fin,$cupo){ $st->bind_param("isssi",$gimnasio_id,$dia,$hora_inicio,$hora_fin,$cupo); }, false);
  } else {
    $ex = run_stmt($conexion,"SELECT 1 FROM gym_horarios_base WHERE gimnasio_id=? AND dia=? AND hora_inicio=? AND hora_fin=? AND profesor_id=? LIMIT 1",
      function($st) use($gimnasio_id,$dia,$hora_inicio,$hora_fin,$profesor_id){ $st->bind_param("isssi",$gimnasio_id,$dia,$hora_inicio,$hora_fin,$profesor_id); });
    if ($ex && $ex->fetch_assoc()){ header("Location: horarios_gimnasio.php?ok=La%20franja%20ya%20exist%C3%ADa"); exit; }

    run_stmt($conexion, "INSERT INTO gym_horarios_base (gimnasio_id, dia, hora_inicio, hora_fin, cupo_maximo, profesor_id)
                         VALUES (?,?,?,?,?,?)",
      function($st) use($gimnasio_id,$dia,$hora_inicio,$hora_fin,$cupo,$profesor_id){ $st->bind_param("isssii",$gimnasio_id,$dia,$hora_inicio,$hora_fin,$cupo,$profesor_id); }, false);
  }
  header("Location: horarios_gimnasio.php?ok=Horario%20base%20guardado"); exit;
}

/* ===================== 1B) Alta horario base (MASIVA) ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && $accion==='alta_base_masiva'){
  $dias        = isset($_POST['dias_base']) && is_array($_POST['dias_base']) ? array_values($_POST['dias_base']) : [];
  $hora_inicio = $_POST['hora_inicio'] ?? '';
  $hora_fin    = $_POST['hora_fin'] ?? '';
  $paso        = max(1, (int)($_POST['paso_min'] ?? 60));
  $cupo        = max(1,(int)($_POST['cupo_maximo'] ?? 20));
  $profesor_id = ($_POST['profesor_id'] ?? '')!=='' ? (int)$_POST['profesor_id'] : null;

  $DIAS_VALIDOS = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  $dias = array_values(array_intersect($dias, $DIAS_VALIDOS));

  if (empty($dias) || !$hora_inicio || !$hora_fin || $hora_inicio>=$hora_fin){
    header("Location: horarios_gimnasio.php?err=Complet%C3%A1%20d%C3%ADas%2C%20rango%20de%20horario%20v%C3%A1lido%20y%20paso"); exit;
  }

  $selNull = $conexion->prepare("SELECT 1 FROM gym_horarios_base WHERE gimnasio_id=? AND dia=? AND hora_inicio=? AND hora_fin=? AND profesor_id IS NULL LIMIT 1");
  $insNull = $conexion->prepare("INSERT INTO gym_horarios_base (gimnasio_id, dia, hora_inicio, hora_fin, cupo_maximo, profesor_id) VALUES (?,?,?,?,?,NULL)");

  $selProf = $conexion->prepare("SELECT 1 FROM gym_horarios_base WHERE gimnasio_id=? AND dia=? AND hora_inicio=? AND hora_fin=? AND profesor_id=? LIMIT 1");
  $insProf = $conexion->prepare("INSERT INTO gym_horarios_base (gimnasio_id, dia, hora_inicio, hora_fin, cupo_maximo, profesor_id) VALUES (?,?,?,?,?,?)");

  $insertados = 0; $saltados = 0;

  foreach ($dias as $dia) {
    $t0 = DateTime::createFromFormat('H:i', substr($hora_inicio,0,5));
    $t1 = DateTime::createFromFormat('H:i', substr($hora_fin,0,5));
    if (!$t0 || !$t1) continue;

    for ($slot = clone $t0; $slot < $t1; $slot->modify("+{$paso} minutes")) {
      $slotFin = (clone $slot)->modify("+{$paso} minutes");
      if ($slotFin > $t1) break;
      $hi = $slot->format('H:i:00');
      $hf = $slotFin->format('H:i:00');

      if ($profesor_id===null){
        $selNull->bind_param("isss",$gimnasio_id,$dia,$hi,$hf);
        $selNull->execute();
        $exists = $selNull->get_result()->fetch_assoc();
        if ($exists){ $saltados++; continue; }

        $insNull->bind_param("isssi",$gimnasio_id,$dia,$hi,$hf,$cupo);
        $insNull->execute();
        if ($conexion->affected_rows>0) $insertados++; else $saltados++;
      } else {
        $selProf->bind_param("isssi",$gimnasio_id,$dia,$hi,$hf,$profesor_id);
        $selProf->execute();
        $exists = $selProf->get_result()->fetch_assoc();
        if ($exists){ $saltados++; continue; }

        $insProf->bind_param("isssii",$gimnasio_id,$dia,$hi,$hf,$cupo,$profesor_id);
        $insProf->execute();
        if ($conexion->affected_rows>0) $insertados++; else $saltados++;
      }
    }
  }
  $selNull->close(); $insNull->close();
  $selProf->close(); $insProf->close();

  header("Location: horarios_gimnasio.php?ok=Alta%20masiva:%20$insertados%20creados%2C%20$saltados%20saltados"); exit;
}

/* ===================== 1C) Eliminar horario base ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($accion==='del_base')) {
  $id = (int)($_POST['id'] ?? 0);
  if ($id<=0) { header("Location: horarios_gimnasio.php?err=ID%20inv%C3%A1lido"); exit; }

  run_stmt($conexion,
    "DELETE FROM gym_horarios_base WHERE id=? AND gimnasio_id=?",
    function($st) use($id,$gimnasio_id){ $st->bind_param("ii",$id,$gimnasio_id); },
    false
  );
  header("Location: horarios_gimnasio.php?ok=Turno%20eliminado"); exit;
}

/* ===================== 1D) Actualizar (editar) horario base ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($accion==='upd_base')) {
  $id          = (int)($_POST['id'] ?? 0);
  $dia         = trim($_POST['dia'] ?? ''); // viene hidden (fijo)
  $hora_inicio = $_POST['hora_inicio'] ?? '';
  $hora_fin    = $_POST['hora_fin'] ?? '';
  $cupo        = max(1,(int)($_POST['cupo_maximo'] ?? 1));
  $profesor_id = ($_POST['profesor_id'] ?? '')!=='' ? (int)$_POST['profesor_id'] : null;

  $DIAS_VALIDOS = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  if ($id<=0 || !in_array($dia,$DIAS_VALIDOS,true) || !$hora_inicio || !$hora_fin || $hora_inicio>=$hora_fin) {
    header("Location: horarios_gimnasio.php?err=Datos%20inv%C3%A1lidos%20al%20editar"); exit;
  }

  if ($profesor_id===null){
    $ex = run_stmt($conexion,
      "SELECT id FROM gym_horarios_base
        WHERE gimnasio_id=? AND dia=? AND hora_inicio=? AND hora_fin=? AND profesor_id IS NULL AND id<>? LIMIT 1",
      function($st) use($gimnasio_id,$dia,$hora_inicio,$hora_fin,$id){
        $st->bind_param("isssi",$gimnasio_id,$dia,$hora_inicio,$hora_fin,$id);
      }
    );
  } else {
    $ex = run_stmt($conexion,
      "SELECT id FROM gym_horarios_base
        WHERE gimnasio_id=? AND dia=? AND hora_inicio=? AND hora_fin=? AND profesor_id=? AND id<>? LIMIT 1",
      function($st) use($gimnasio_id,$dia,$hora_inicio,$hora_fin,$profesor_id,$id){
        $st->bind_param("isssii",$gimnasio_id,$dia,$hora_inicio,$hora_fin,$profesor_id,$id);
      }
    );
  }
  if ($ex && $ex->fetch_assoc()){
    header("Location: horarios_gimnasio.php?err=Ya%20existe%20otra%20franja%20id%C3%A9ntica"); exit;
  }

  if ($profesor_id===null){
    run_stmt($conexion,
      "UPDATE gym_horarios_base
          SET dia=?, hora_inicio=?, hora_fin=?, cupo_maximo=?, profesor_id=NULL
        WHERE id=? AND gimnasio_id=?",
      function($st) use($dia,$hora_inicio,$hora_fin,$cupo,$id,$gimnasio_id){
        $st->bind_param("sssiii",$dia,$hora_inicio,$hora_fin,$cupo,$id,$gimnasio_id);
      }, false
    );
  } else {
    run_stmt($conexion,
      "UPDATE gym_horarios_base
          SET dia=?, hora_inicio=?, hora_fin=?, cupo_maximo=?, profesor_id=?
        WHERE id=? AND gimnasio_id=?",
      function($st) use($dia,$hora_inicio,$hora_fin,$cupo,$profesor_id,$id,$gimnasio_id){
        $st->bind_param("sssiiii",$dia,$hora_inicio,$hora_fin,$cupo,$profesor_id,$id,$gimnasio_id);
      }, false
    );
  }

  header("Location: horarios_gimnasio.php?ok=Turno%20actualizado"); exit;
}

/* ===================== 2) Editar cupo base ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && $accion==='editar_cupo_base'){
  $dia = $_POST['dia'] ?? '';
  $hora_inicio = $_POST['hora_inicio'] ?? '';
  $hora_fin    = $_POST['hora_fin'] ?? '';
  $cupo        = max(1,(int)($_POST['cupo_maximo'] ?? 1));
  $profesor_id = ($_POST['profesor_id'] ?? '')!=='' ? (int)$_POST['profesor_id'] : null;

  $DIAS_VALIDOS = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  if (!in_array($dia,$DIAS_VALIDOS,true) || !$hora_inicio || !$hora_fin){
    header("Location: horarios_gimnasio.php?err=Datos%20inv%C3%A1lidos%20(cupo%20base)"); exit;
  }

  if ($profesor_id===null){
    run_stmt($conexion, "UPDATE gym_horarios_base
                         SET cupo_maximo=?
                         WHERE gimnasio_id=? AND LOWER(TRIM(dia))=LOWER(?) AND hora_inicio=? AND hora_fin=? AND profesor_id IS NULL",
             function($st) use($cupo,$gimnasio_id,$dia,$hora_inicio,$hora_fin){
               $st->bind_param("iisss",$cupo,$gimnasio_id,$dia,$hora_inicio,$hora_fin);
             }, false);
  } else {
    run_stmt($conexion, "UPDATE gym_horarios_base
                         SET cupo_maximo=?
                         WHERE gimnasio_id=? AND LOWER(TRIM(dia))=LOWER(?) AND hora_inicio=? AND hora_fin=? AND profesor_id=?",
             function($st) use($cupo,$gimnasio_id,$dia,$hora_inicio,$hora_fin,$profesor_id){
               $st->bind_param("iisssi",$cupo,$gimnasio_id,$dia,$hora_inicio,$hora_fin,$profesor_id);
             }, false);
  }
  header("Location: horarios_gimnasio.php?ok=Cupo%20base%20actualizado"); exit;
}

/* ===================== 3) Cupo por fecha (override) ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && $accion==='cupo_fecha'){
  $fecha = $_POST['fecha'] ?? '';
  $hora  = $_POST['hora'] ?? '';
  $cupo  = max(1,(int)($_POST['cupo_maximo'] ?? 1));
  $profesor_id = ($_POST['profesor_id'] ?? '')!=='' ? (int)$_POST['profesor_id'] : null;

  if (!validarFechaYmd($fecha) || !$hora){
    header("Location: horarios_gimnasio.php?err=Datos%20inv%C3%A1lidos%20(cupo%20por%20fecha)"); exit;
  }

  if ($profesor_id===null){
    run_stmt($conexion, "INSERT INTO gym_capacidad_fecha (gimnasio_id, fecha, hora, profesor_id, cupo_maximo)
                         VALUES (?,?,?,NULL,?)
                         ON DUPLICATE KEY UPDATE cupo_maximo=VALUES(cupo_maximo)",
             function($st) use($gimnasio_id,$fecha,$hora,$cupo){ $st->bind_param("issi",$gimnasio_id,$fecha,$hora,$cupo); }, false);
  } else {
    run_stmt($conexion, "INSERT INTO gym_capacidad_fecha (gimnasio_id, fecha, hora, profesor_id, cupo_maximo)
                         VALUES (?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE cupo_maximo=VALUES(cupo_maximo)",
             function($st) use($gimnasio_id,$fecha,$hora,$profesor_id,$cupo){ $st->bind_param("issii",$gimnasio_id,$fecha,$hora,$profesor_id,$cupo); }, false);
  }
  header("Location: horarios_gimnasio.php?ok=Cupo%20por%20fecha%20guardado"); exit;
}

/* ===================== 4) Plan fijo por cliente (alta/actualiza) ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && $accion==='plan_fijo'){
  $cliente_id  = (int)($_POST['cliente_id'] ?? 0);
  $profesor_id = ($_POST['profesor_id'] ?? '')!=='' ? (int)$_POST['profesor_id'] : null;
  $hora        = $_POST['hora'] ?? '';
  $dias        = $_POST['dias'] ?? [];

  if ($cliente_id<=0 || !$hora || empty($dias)){
    header("Location: horarios_gimnasio.php?err=Complet%C3%A1%20cliente%2C%20hora%20y%20al%20menos%20un%20d%C3%ADa"); exit;
  }

  $memb = planActualDeCliente($conexion, $cliente_id);
  if (!$memb || !$memb['plan_id']){
    header("Location: horarios_gimnasio.php?err=El%20cliente%20no%20tiene%20una%20membres%C3%ADa%20con%20plan%20asignado"); exit;
  }
  $plan_id = (int)$memb['plan_id'];

  $planRow = traerPlanPorId($conexion, $plan_id);
  if (!$planRow){ header("Location: horarios_gimnasio.php?err=Plan%20inexistente%20en%20'planes'"); exit; }

  $ultimaPago = ultimaFechaPagoCliente($conexion,$cliente_id);
  [$desde, $hasta] = rangoPlanDesdePago($planRow, $ultimaPago);

  $DIAS_VALIDOS = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  $dias = array_values(array_intersect($dias, $DIAS_VALIDOS));

  $dias_json = json_encode(array_values($dias), JSON_UNESCAPED_UNICODE);

  if ($profesor_id===null){
    run_stmt($conexion,
      "INSERT INTO gym_clientes_plan (gimnasio_id, cliente_id, plan_id, desde, hasta, hora, dias_json, profesor_id)
       VALUES (?,?,?,?,?,?,?,NULL)
       ON DUPLICATE KEY UPDATE dias_json=VALUES(dias_json), hora=VALUES(hora), desde=VALUES(desde), hasta=VALUES(hasta)",
       function($st) use($gimnasio_id,$cliente_id,$plan_id,$desde,$hasta,$hora,$dias_json){
         $st->bind_param("iiissss",$gimnasio_id,$cliente_id,$plan_id,$desde,$hasta,$hora,$dias_json);
       }, false
    );
  } else {
    run_stmt($conexion,
      "INSERT INTO gym_clientes_plan (gimnasio_id, cliente_id, plan_id, desde, hasta, hora, dias_json, profesor_id)
       VALUES (?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE dias_json=VALUES(dias_json), hora=VALUES(hora), profesor_id=VALUES(profesor_id), desde=VALUES(desde), hasta=VALUES(hasta)",
       function($st) use($gimnasio_id,$cliente_id,$plan_id,$desde,$hasta,$hora,$dias_json,$profesor_id){
         $st->bind_param("iiissssi",$gimnasio_id,$cliente_id,$plan_id,$desde,$hasta,$hora,$dias_json,$profesor_id);
       }, false
    );
  }

  $m="Plan fijo guardado. Período: {$desde} a {$hasta}.".($ultimaPago?" (desde pago {$ultimaPago})":"");
  header("Location: horarios_gimnasio.php?ok=".urlencode($m)); exit;
}

/* ===================== 4b) PREVIEW ===================== */
$preview = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && $accion==='preview' && $sel_cliente>0 && $sel_hora && !empty($sel_dias)){
  $memb = planActualDeCliente($conexion, $sel_cliente);
  if ($memb && $memb['plan_id']){
    $planRow = traerPlanPorId($conexion, (int)$memb['plan_id']);
    if ($planRow){
      $ultimaPago = ultimaFechaPagoCliente($conexion,$sel_cliente);
      [$desde, $hasta] = rangoPlanDesdePago($planRow, $ultimaPago);

      $fechas = [];
      $d1 = new DateTime($desde); $d2 = new DateTime($hasta);
      for ($dt = clone $d1; $dt <= $d2 && count($fechas)<8; $dt->modify('+1 day')){
        $f = $dt->format('Y-m-d');
        $diaNombre = nombreDiaEs($f);
        if (in_array($diaNombre, $sel_dias, true) && $diaNombre!=='Domingo') $fechas[] = $f;
      }

      $rows = [];
      foreach($fechas as $f){
        $cap = cupoParaFechaHora($conexion,$gimnasio_id,$sel_prof,$f,$sel_hora);
        $diaTxt = nombreDiaEs($f);

        if ($sel_prof === null){
          $sql = "SELECT COUNT(*) c
                    FROM gym_clientes_plan
                   WHERE gimnasio_id=? AND hora=? AND desde<=? AND hasta>=?
                     AND dias_json LIKE ?";
          $likeDia = '%"'.$conexion->real_escape_string($diaTxt).'"%';
          $res = run_stmt($conexion,$sql,function($st) use($gimnasio_id,$sel_hora,$f,$likeDia){
            $st->bind_param("issss",$gimnasio_id,$sel_hora,$f,$f,$likeDia);
          });
        } else {
          $sql = "SELECT COUNT(*) c
                    FROM gym_clientes_plan
                   WHERE gimnasio_id=? AND hora=? AND profesor_id=?
                     AND desde<=? AND hasta>=? AND dias_json LIKE ?";
          $likeDia = '%"'.$conexion->real_escape_string($diaTxt).'"%';
          $res = run_stmt($conexion,$sql,function($st) use($gimnasio_id,$sel_hora,$sel_prof,$f,$likeDia){
            $st->bind_param("isisss",$gimnasio_id,$sel_hora,$sel_prof,$f,$f,$likeDia);
          });
        }
        $c = (int)((safe_fetch($res)['c'] ?? 0));
        $rows[] = ['fecha'=>$f,'cupo'=>$cap,'ocupados'=>$c,'restante'=>max(0,$cap-$c)];
      }
      $preview = [
        'plan_nombre' => $planRow['nombre'] ?? '—',
        'desde' => $desde,
        'hasta' => $hasta,
        'ultima_pago' => $ultimaPago,
        'tabla' => $rows
      ];
    } else {
      $err = $err ? $err." | No se encontró el plan en 'planes'." : "No se encontró el plan en 'planes'.";
    }
  } else {
    $err = $err ? $err." | El cliente no tiene una membresía con plan." : "El cliente no tiene una membresía con plan.";
  }
}

/* ===================== NUEVO: eliminar fijo ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && $accion==='del_fijo'){
  $cliente_id  = (int)($_POST['cliente_id'] ?? 0);
  $hora_old    = $_POST['hora_old'] ?? '';
  $prof_old_raw= $_POST['profesor_old'] ?? '';
  $profesor_old= ($prof_old_raw==='') ? null : (int)$prof_old_raw;

  if ($cliente_id<=0 || !$hora_old){
    header("Location: horarios_gimnasio.php?err=Datos%20inv%C3%A1lidos%20(al%20eliminar%20fijo)"); exit;
  }

  if ($profesor_old===null){
    run_stmt($conexion,
      "DELETE FROM gym_clientes_plan
        WHERE gimnasio_id=? AND cliente_id=? AND hora=? AND profesor_id IS NULL
        LIMIT 1",
      function($st) use($gimnasio_id,$cliente_id,$hora_old){
        $st->bind_param("iis",$gimnasio_id,$cliente_id,$hora_old);
      }, false
    );
  } else {
    run_stmt($conexion,
      "DELETE FROM gym_clientes_plan
        WHERE gimnasio_id=? AND cliente_id=? AND hora=? AND profesor_id=?
        LIMIT 1",
      function($st) use($gimnasio_id,$cliente_id,$hora_old,$profesor_old){
        $st->bind_param("iisi",$gimnasio_id,$cliente_id,$hora_old,$profesor_old);
      }, false
    );
  }
  header("Location: horarios_gimnasio.php?ok=Fijo%20eliminado"); exit;
}

/* ===================== NUEVO: reemplazar/mover fijo ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && $accion==='rep_fijo'){
  $cliente_id   = (int)($_POST['cliente_id'] ?? 0);
  $hora_old     = $_POST['hora_old'] ?? '';
  $prof_old_raw = $_POST['profesor_old'] ?? '';
  $profesor_old = ($prof_old_raw==='') ? null : (int)$prof_old_raw;

  $hora_new     = $_POST['hora_new'] ?? '';
  $prof_new_raw = $_POST['profesor_new'] ?? '';
  $profesor_new = ($prof_new_raw==='') ? null : (int)$prof_new_raw;
  $dias_new     = isset($_POST['dias_new']) && is_array($_POST['dias_new']) ? array_values($_POST['dias_new']) : [];

  if ($cliente_id<=0 || !$hora_old || !$hora_new || empty($dias_new)){
    header("Location: horarios_gimnasio.php?err=Complet%C3%A1%20hora%20y%20d%C3%ADas%20en%20edici%C3%B3n%20de%20fijo"); exit;
  }

  // Restringir a Lunes–Sábado
  $DIAS_VALIDOS = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  $dias_new = array_values(array_intersect($dias_new, $DIAS_VALIDOS));

  $dias_json = json_encode($dias_new, JSON_UNESCAPED_UNICODE);

  if ($profesor_old===null){
    if ($profesor_new===null){
      run_stmt($conexion,
        "UPDATE gym_clientes_plan
            SET hora=?, dias_json=?, profesor_id=NULL
          WHERE gimnasio_id=? AND cliente_id=? AND hora=? AND profesor_id IS NULL
          LIMIT 1",
        function($st) use($hora_new,$dias_json,$gimnasio_id,$cliente_id,$hora_old){
          $st->bind_param("ssiis",$hora_new,$dias_json,$gimnasio_id,$cliente_id,$hora_old);
        }, false
      );
    } else {
      run_stmt($conexion,
        "UPDATE gym_clientes_plan
            SET hora=?, dias_json=?, profesor_id=?
          WHERE gimnasio_id=? AND cliente_id=? AND hora=? AND profesor_id IS NULL
          LIMIT 1",
        function($st) use($hora_new,$dias_json,$profesor_new,$gimnasio_id,$cliente_id,$hora_old){
          $st->bind_param("ssiiis",$hora_new,$dias_json,$profesor_new,$gimnasio_id,$cliente_id,$hora_old);
        }, false
      );
    }
  } else {
    if ($profesor_new===null){
      run_stmt($conexion,
        "UPDATE gym_clientes_plan
            SET hora=?, dias_json=?, profesor_id=NULL
          WHERE gimnasio_id=? AND cliente_id=? AND hora=? AND profesor_id=?
          LIMIT 1",
        function($st) use($hora_new,$dias_json,$gimnasio_id,$cliente_id,$hora_old,$profesor_old){
          $st->bind_param("ssiisi",$hora_new,$dias_json,$gimnasio_id,$cliente_id,$hora_old,$profesor_old);
        }, false
      );
    } else {
      run_stmt($conexion,
        "UPDATE gym_clientes_plan
            SET hora=?, dias_json=?, profesor_id=?
          WHERE gimnasio_id=? AND cliente_id=? AND hora=? AND profesor_id=?
          LIMIT 1",
        function($st) use($hora_new,$dias_json,$profesor_new,$gimnasio_id,$cliente_id,$hora_old,$profesor_old){
          $st->bind_param("ssiiisi",$hora_new,$dias_json,$profesor_new,$gimnasio_id,$cliente_id,$hora_old,$profesor_old);
        }, false
      );
    }
  }

  header("Location: horarios_gimnasio.php?ok=Fijo%20actualizado"); exit;
}

/* ===================== 5) Listas ===================== */
$DIAS_SEM = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado']; // DÍAS FIJOS
$filtra_dia = $_GET['dia'] ?? '';
if (!in_array($filtra_dia, $DIAS_SEM, true)) $filtra_dia = '';

$profes = run_stmt($conexion,
  "SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id=? ORDER BY apellido, nombre",
  function($st) use($gimnasio_id){ $st->bind_param("i",$gimnasio_id); }
);
$clientes = run_stmt($conexion,
  "SELECT id, apellido, nombre FROM clientes WHERE gimnasio_id=? ORDER BY apellido, nombre",
  function($st) use($gimnasio_id){ $st->bind_param("i",$gimnasio_id); }
);
if ($filtra_dia) {
  $base_list = run_stmt($conexion,
    "SELECT b.*, p.apellido, p.nombre
       FROM gym_horarios_base b
       LEFT JOIN profesores p ON p.id=b.profesor_id
      WHERE b.gimnasio_id=? AND b.dia=?
      ORDER BY b.hora_inicio, p.apellido, p.nombre",
    function($st) use($gimnasio_id,$filtra_dia){ $st->bind_param("is",$gimnasio_id,$filtra_dia); }
  );
} else {
  $base_list = run_stmt($conexion,
    "SELECT b.*, p.apellido, p.nombre
       FROM gym_horarios_base b
       LEFT JOIN profesores p ON p.id=b.profesor_id
      WHERE b.gimnasio_id=? AND b.dia IN ('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado')
      ORDER BY FIELD(b.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'),
               b.hora_inicio, p.apellido, p.nombre",
    function($st) use($gimnasio_id){ $st->bind_param("i",$gimnasio_id); }
  );
}

$hoy = hoyYmd();

/* ===================== CARGAR CACHES (rápido) ===================== */
$occCache   = buildOcupacionCache($conexion,$gimnasio_id,$hoy);
$fijosCache = buildFijosCache($conexion,$gimnasio_id,$hoy);

?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Horarios del Gimnasio</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{background:#0f1216 !important;color:#e5e7eb !important}
    .contenedor{max-width:1100px;margin:0 auto;padding:1rem}
    .card{border:1px solid #2a2f3a;border-radius:12px;padding:1rem;margin:.75rem 0;background:#0e1218 !important}
    h1,h2,h3{margin:.2rem 0;color:#e5e7eb !important}
    .fila{display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;margin:.5rem 0}
    select,input,button{padding:.45rem .6rem;border-radius:.5rem;border:1px solid #374151 !important;background:#0b0e13 !important;color:#e5e7eb !important}
    select option{background:#0b0e13 !important;color:#e5e7eb !important}
    input::placeholder{color:#9ca3af !important}
    button{background:#374151 !important;cursor:pointer}
    .btn-warn{background:#f59e0b !important;border:0 !important;color:#111827 !important;font-weight:700}
    .alert-ok{background:#e6ffed;color:#064e3b;border:1px solid #a7f3d0;padding:.5rem .8rem;border-radius:.5rem}
    .alert-err{background:#ffe6e6;color:#7f1d1d;border:1px solid #f5a0a0;padding:.5rem .8rem;border-radius:.5rem}

    table{width:100%;border-collapse:collapse;margin-top:.5rem;background:transparent !important}
    th,td{padding:.55rem;border-bottom:1px solid #2b2f3a !important;color:#e5e7eb !important;background:transparent !important}
    th{font-weight:700}
    .dias{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.35rem}
    .dias label{display:flex;align-items:center;gap:.35rem;background:#1c2230;padding:.35rem;border-radius:.4rem;color:#e5e7eb !important}
    .muted{color:#9ca3af !important}
    .inline{display:inline-flex; gap:.5rem; align-items:center; flex-wrap:wrap}
    .pill{display:inline-block;padding:.15rem .45rem;border-radius:.4rem;background:#1c2230;color:#e5e7eb;margin:.15rem .2rem 0 0;font-size:.9em}
    .inline-edit select,
    .inline-edit input[type="time"],
    .inline-edit input[type="number"]{ max-width: 140px; }

    .tiny{font-size:.85em;padding:.3rem .45rem}
    details.fijo-edit{display:inline-block;margin:.15rem 0 0 .25rem;}
    details.fijo-edit > summary{list-style:none;cursor:pointer;display:inline-block;padding:.05rem .4rem;border:1px solid #475569;border-radius:.4rem}
    details.fijo-edit[open] > summary{background:#1f2937}
    .fxform{background:#0b0e13;border:1px solid #334155;border-radius:.5rem;padding:.5rem;margin-top:.35rem}
    .fxform label{margin-right:.5rem}
    .fxform .days{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.25rem;margin-top:.35rem}
    .fxform .days label{background:#111827;padding:.2rem .35rem;border-radius:.35rem}
  </style>
  <script>
    function submitPreview(){
      const f = document.getElementById('form-plan');
      if(!f) return;
      f.__accion.value = 'preview';
      f.submit();
    }
    document.addEventListener('DOMContentLoaded', function(){
      const f = document.getElementById('form-plan');
      if(!f) return;
      f.cliente_id?.addEventListener('change', submitPreview);
      f.hora?.addEventListener('change', submitPreview);
      f.profesor_id?.addEventListener('change', submitPreview);
      f.querySelectorAll('input[name="dias[]"]').forEach(cb=>{
        cb.addEventListener('change', submitPreview);
      });
    });
  </script>
</head>
<body>
<div class="contenedor">
  <h1>🏋️ Horarios del Gimnasio</h1>
  <?php if($msg): ?><div class="alert-ok"><?=h($msg)?></div><?php endif; ?>
  <?php if($err): ?><div class="alert-err"><?=h($err)?></div><?php endif; ?>

  <!-- 1B) Alta horario base MASIVA -->
  <div class="card">
    <h2>➕ Alta de horario base (masiva)</h2>
    <form method="post" action="horarios_gimnasio.php">
      <input type="hidden" name="__accion" value="alta_base_masiva">

      <div class="fila">
        <div class="dias">
          <?php foreach($DIAS_SEM as $d): ?>
            <label><input type="checkbox" name="dias_base[]" value="<?=$d?>"> <?=$d?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="fila">
        <label>Inicio: <input type="time" name="hora_inicio" required value="19:00"></label>
        <label>Fin: <input type="time" name="hora_fin" required value="22:00"></label>
        <label>Paso (min): <input type="number" name="paso_min" min="5" step="5" value="60" style="width:90px"></label>
        <label>Cupo: <input type="number" name="cupo_maximo" min="1" value="20" style="width:90px"></label>
        <label>Profesor (opcional):
          <select name="profesor_id">
            <option value="">—</option>
            <?php if($profes){ mysqli_data_seek($profes,0); while($p=safe_fetch($profes)): ?>
              <option value="<?=$p['id']?>"><?=h($p['apellido'].' '.$p['nombre'])?></option>
            <?php endwhile; } ?>
          </select>
        </label>
      </div>

      <div class="fila inline">
        <button class="btn-warn">Cargar turnos por hora</button>
        <span class="muted">Ej.: Inicio 19:00, Fin 22:00, Paso 60 → crea 19–20, 20–21, 21–22 para cada día tildado.</span>
      </div>
    </form>
  </div>

  <!-- 1A) Alta horario base (un día) -->
  <div class="card">
    <h2>➕ Alta de horario base (un solo turno)</h2>
    <form method="post" action="horarios_gimnasio.php" class="fila">
      <input type="hidden" name="__accion" value="alta_base">
      <label>Día:
        <select name="dia" required>
          <?php foreach($DIAS_SEM as $d): ?>
            <option value="<?=$d?>"><?=$d?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Inicio: <input type="time" name="hora_inicio" required></label>
      <label>Fin: <input type="time" name="hora_fin" required></label>
      <label>Cupo: <input type="number" name="cupo_maximo" min="1" value="20" style="width:90px"></label>
      <label>Profesor (opcional):
        <select name="profesor_id">
          <option value="">—</option>
          <?php if($profes){ mysqli_data_seek($profes,0); while($p=safe_fetch($profes)): ?>
            <option value="<?=$p['id']?>"><?=h($p['apellido'].' '.$p['nombre'])?></option>
          <?php endwhile; } ?>
        </select>
      </label>
      <button class="btn-warn">Guardar</button>
    </form>
  </div>

  <!-- 2) Editar cupo base -->
  <div class="card">
    <h2>🎛️ Editar cupo base</h2>
    <form method="post" action="horarios_gimnasio.php" class="fila">
      <input type="hidden" name="__accion" value="editar_cupo_base">
      <label>Día:
        <select name="dia" required>
          <?php foreach($DIAS_SEM as $d): ?>
            <option value="<?=$d?>"><?=$d?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Inicio: <input type="time" name="hora_inicio" required></label>
      <label>Fin: <input type="time" name="hora_fin" required></label>
      <label>Cupo: <input type="number" name="cupo_maximo" min="1" value="20" style="width:90px"></label>
      <label>Profesor (opcional):
        <select name="profesor_id">
          <option value="">—</option>
          <?php if($profes){ mysqli_data_seek($profes,0); while($p=safe_fetch($profes)): ?>
            <option value="<?=$p['id']?>"><?=h($p['apellido'].' '.$p['nombre'])?></option>
          <?php endwhile; } ?>
        </select>
      </label>
      <button class="btn-warn">Actualizar cupo</button>
    </form>
    <p class="muted">Si existe el mismo día/hora en la base, se actualiza su cupo.</p>
  </div>

  <!-- 3) Plan fijo por cliente -->
  <div class="card">
    <h2>👤📆 Asignar plan fijo por cliente</h2>
    <form id="form-plan" method="post" action="horarios_gimnasio.php">
      <input type="hidden" name="__accion" value="preview">
      <div class="fila">
        <label>Cliente:
          <select name="cliente_id" required>
            <option value="">Seleccionar</option>
            <?php if($clientes){ mysqli_data_seek($clientes,0); while($c=safe_fetch($clientes)): ?>
              <option value="<?=$c['id']?>" <?= $sel_cliente===$c['id']?'selected':'' ?>>
                <?=h($c['apellido'].' '.$c['nombre'])?>
              </option>
            <?php endwhile; } ?>
          </select>
        </label>

        <label>Hora fija: <input type="time" name="hora" required value="<?=h($sel_hora ?: '19:00')?>"></label>

        <label>Profesor (opcional):
          <select name="profesor_id">
            <option value="">—</option>
            <?php if($profes){ mysqli_data_seek($profes,0); while($p=safe_fetch($profes)): ?>
              <option value="<?=$p['id']?>" <?= ($sel_prof!==null && $sel_prof==$p['id'])?'selected':'' ?>>
                <?=h($p['apellido'].' '.$p['nombre'])?>
              </option>
            <?php endwhile; } ?>
          </select>
        </label>
      </div>

      <div class="fila">
        <div class="dias">
          <?php foreach($DIAS_SEM as $d):
            $chk = in_array($d,$sel_dias,true) ? 'checked' : '';
          ?>
            <label><input type="checkbox" name="dias[]" value="<?=$d?>" <?=$chk?> onchange="submitPreview()"> <?=$d?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="fila inline">
        <button class="btn-warn" onclick="this.form.__accion.value='plan_fijo'">Guardar plan fijo</button>
        <span class="muted">El plan se detecta desde la <strong>membresía</strong>. No se generan reservas.</span>
      </div>
    </form>

    <?php if($preview): ?>
      <div class="card" style="margin-top:.75rem">
        <h3>🔎 Previsualización de capacidad (sin reservas)</h3>
        <p class="muted">
          Plan: <strong><?=h($preview['plan_nombre'])?></strong> —
          Período: <strong><?=h($preview['desde'])?></strong> a <strong><?=h($preview['hasta'])?></strong>
          <?php if($preview['ultima_pago']): ?> (desde pago <?=h($preview['ultima_pago'])?>)<?php endif; ?>
        </p>
        <table>
          <tr><th>Fecha</th><th>Hora</th><th>Cupo</th><th>Ocupados (fijos)</th><th>Restante</th></tr>
          <?php foreach($preview['tabla'] as $r): ?>
            <tr>
              <td><?=h($r['fecha'])?></td>
              <td><?=h(toHM($sel_hora))?></td>
              <td><?= (int)$r['cupo'] ?></td>
              <td><?= (int)$r['ocupados'] ?></td>
              <td><?= (int)$r['restante'] ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- 4) Ocupación actual por turno (base semanal) -->
  <div class="card">
    <div class="fila" style="margin-top:.25rem">
      <form method="get" action="horarios_gimnasio.php" class="inline">
        <label>Filtrar por día:
          <select name="dia" onchange="this.form.submit()">
            <option value="">Todos</option>
            <?php foreach($DIAS_SEM as $d): ?>
              <option value="<?=$d?>" <?= $filtra_dia===$d?'selected':'' ?>><?=$d?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>
    </div>

    <h2>⏱️ Ocupación actual por turno (base semanal)</h2>
    <table>
      <tr>
        <th>Día</th>
        <th>Inicio</th>
        <th>Fin</th>
        <th>Profesor</th>
        <th>Cupo</th>
        <th>Ocupados</th>
        <th>Restante</th>
        <th>Alumnos fijos</th>
        <th>Acciones</th>
      </tr>
      <?php if($base_list){ mysqli_data_seek($base_list,0); while($b=safe_fetch($base_list)):
        $dia = $b['dia'];
        $hora_ref = toHM($b['hora_inicio']); // usamos inicio como hora fija
        $prof_id = array_key_exists('profesor_id',$b) ? $b['profesor_id'] : null;

        $cupo_base = (int)$b['cupo_maximo'];

        $occDia = $occCache[$dia][$hora_ref] ?? null;
        if ($prof_id === null) {
          // SIN profesor: contar TODOS (incluye NULL)
          $ocupados = (int)($occDia['SUM_ALL'] ?? 0);
          // Listado: concatenar NULL + todos los profes
          $fijosData = [];
          if(isset($fijosCache[$dia][$hora_ref])){
            if(isset($fijosCache[$dia][$hora_ref]['NULL'])){
              foreach($fijosCache[$dia][$hora_ref]['NULL'] as $r){ $fijosData[]=$r; }
            }
            foreach($fijosCache[$dia][$hora_ref] as $pid => $arr){
              if($pid==='NULL') continue;
              foreach($arr as $r){ $fijosData[]=$r; }
            }
          }
        } else {
          // CON profesor: solo ese profesor
          $ocupados = (int)($occDia[(int)$prof_id] ?? 0);
          $fijosData = $fijosCache[$dia][$hora_ref][(string)(int)$prof_id] ?? [];
        }

        $restante  = max(0, $cupo_base - $ocupados);
        $profn = h(trim(($b['apellido']??'').' '.($b['nombre']??''))) ?: '—';
        $diasTodos = $DIAS_SEM;
      ?>
        <tr>
          <form method="post" action="horarios_gimnasio.php" class="inline-edit">
            <input type="hidden" name="__accion" value="upd_base">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">

            <!-- DÍA FIJO: sin combobox, se muestra y se envía hidden -->
            <td>
              <strong><?= h($dia) ?></strong>
              <input type="hidden" name="dia" value="<?= h($dia) ?>">
            </td>

            <td><input type="time" name="hora_inicio" value="<?= h(toHM($b['hora_inicio'])) ?>" required></td>
            <td><input type="time" name="hora_fin" value="<?= h(toHM($b['hora_fin'])) ?>" required></td>
            <td>
              <select name="profesor_id">
                <option value="" <?= $prof_id===null?'selected':'' ?>>—</option>
                <?php
                if($profes){ mysqli_data_seek($profes,0);
                  while($p=safe_fetch($profes)): ?>
                    <option value="<?=$p['id']?>" <?= ($prof_id!==null && (int)$prof_id===$p['id'])?'selected':'' ?>>
                      <?=h($p['apellido'].' '.$p['nombre'])?>
                    </option>
                <?php endwhile; } ?>
              </select>
            </td>
            <td><input type="number" name="cupo_maximo" min="1" value="<?= (int)$b['cupo_maximo'] ?>" style="width:90px"></td>
            <td><?= $ocupados ?></td>
            <td><?= $restante ?></td>
            <td>
              <?php if ($fijosData){
                foreach ($fijosData as $fd) {
                  $nm = trim(($fd['apellido']??'').' '.($fd['nombre']??''));
                  $dias_actuales = json_decode($fd['dias_json'] ?? '[]', true) ?: [];
                  $pidShow = is_null($fd['profesor_id']) ? '—' : (string)(int)$fd['profesor_id'];
                  ?>
                  <div class="pill">
                    <?= h($nm) ?> <span class="muted">(Prof: <?=$pidShow?>)</span>
                    <!-- eliminar fijo -->
                    <form method="post" action="horarios_gimnasio.php" style="display:inline" onsubmit="return confirm('¿Eliminar fijo de <?=h($nm)?>?');">
                      <input type="hidden" name="__accion" value="del_fijo">
                      <input type="hidden" name="cliente_id" value="<?= (int)$fd['cliente_id'] ?>">
                      <input type="hidden" name="hora_old" value="<?= h($hora_ref) ?>">
                      <input type="hidden" name="profesor_old" value="<?= is_null($fd['profesor_id']) ? '' : (int)$fd['profesor_id'] ?>">
                      <button class="tiny" title="Eliminar fijo">🗑️</button>
                    </form>

                    <!-- editar/reemplazar fijo -->
                    <details class="fijo-edit">
                      <summary title="Editar fijo">✏️</summary>
                      <div class="fxform">
                        <form method="post" action="horarios_gimnasio.php">
                          <input type="hidden" name="__accion" value="rep_fijo">
                          <input type="hidden" name="cliente_id" value="<?= (int)$fd['cliente_id'] ?>">
                          <input type="hidden" name="hora_old" value="<?= h($hora_ref) ?>">
                          <input type="hidden" name="profesor_old" value="<?= is_null($fd['profesor_id']) ? '' : (int)$fd['profesor_id'] ?>">

                          <label>Hora nueva:
                            <input type="time" name="hora_new" value="<?= h($hora_ref) ?>" required>
                          </label>

                          <label>Profesor:
                            <select name="profesor_new">
                              <option value="">—</option>
                              <?php if($profes){ mysqli_data_seek($profes,0); while($p2=safe_fetch($profes)): ?>
                                <option value="<?=$p2['id']?>" <?= ($prof_id!==null && (int)$prof_id===$p2['id'])?'selected':'' ?>>
                                  <?=h($p2['apellido'].' '.$p2['nombre'])?>
                                </option>
                              <?php endwhile; } ?>
                            </select>
                          </label>

                          <div class="days">
                            <?php foreach($diasTodos as $dopt): $ck=in_array($dopt,$dias_actuales,true)?'checked':''; ?>
                              <label><input type="checkbox" name="dias_new[]" value="<?=$dopt?>" <?=$ck?>> <?=$dopt?></label>
                            <?php endforeach; ?>
                          </div>
                          <div style="margin-top:.4rem">
                            <button class="btn-warn tiny">Guardar cambios</button>
                          </div>
                        </form>
                      </div>
                    </details>
                  </div>
              <?php }
              } else {
                echo '<span class="muted">—</span>';
              } ?>
            </td>
            <td>
              <button class="btn-warn" title="Guardar cambios">💾</button>
          </form>
          <form method="post" action="horarios_gimnasio.php" onsubmit="return confirm('¿Eliminar este turno?');" style="display:inline">
            <input type="hidden" name="__accion" value="del_base">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button style="margin-left:.35rem" title="Eliminar">🗑️</button>
          </form>
            </td>
        </tr>
      <?php endwhile; } else { ?>
        <tr><td colspan="9">Sin franjas cargadas.</td></tr>
      <?php } ?>
    </table>
    <p class="muted">
      Reglas: si la franja <strong>no</strong> tiene profesor, se cuentan todos los fijos (incluye sin profesor).
      Si la franja tiene profesor, solo los fijos de ese profesor.
    </p>
  </div>
</div>
</body>
</html>
<?php ob_end_flush(); ?>
