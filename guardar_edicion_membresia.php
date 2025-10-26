<?php
// guardar_edicion_membresia.php
// Edita clases/vencimiento y actualiza turnos personalizados (profesor, rango, días/horas)
// Además: SINCRONIZA con gym_clientes_plan para que horarios_gimnasio.php descuente/ocupe cupos.

if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function hcol($db, $table, $col){
  if (!($db instanceof mysqli)) return false;
  $table = $db->real_escape_string($table);
  $col   = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
  return ($res && $res->num_rows > 0);
}
function table_exists($db, $t){
  if (!($db instanceof mysqli)) return false;
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  return ($q && $q->num_rows > 0);
}
function valid_date($s){
  if (!$s) return false;
  $d = DateTime::createFromFormat('Y-m-d', $s);
  return $d && $d->format('Y-m-d') === $s;
}
function clean_date($s){ return preg_replace('/[^0-9\-]/','', (string)$s); }
function clean_time($s){
  $t = preg_replace('/[^0-9:]/','', (string)$s);
  if (strlen($t) === 5) $t .= ':00';
  return $t;
}

/* ===== Gate de método ===== */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

/* ===== Entrada ===== */
$id             = (int)($_POST['id'] ?? 0);
$gimnasio_id    = (int)($_POST['gimnasio_id'] ?? ($_SESSION['gimnasio_id'] ?? 0));
$nuevas_clases  = isset($_POST['clases_disponibles']) ? (int)$_POST['clases_disponibles'] : 0;
$nuevo_vto      = (string)($_POST['fecha_vencimiento'] ?? '');
$turnos_json    = (string)($_POST['turnos_json'] ?? '[]'); // [{dow,hora,desde,hasta,profesor_id?}, ...]
$pers_habilitar = isset($_POST['pers_habilitar']);         // checkbox (recrear personalizados con lo enviado)
$borrar_pers    = !empty($_POST['borrar_personalizados']); // checkbox (borrar personalizados existentes)

if ($id <= 0 || $gimnasio_id <= 0) { http_response_code(400); exit('Datos inválidos'); }
if ($nuevas_clases < 0) $nuevas_clases = 0;
if (!valid_date($nuevo_vto)) { http_response_code(400); exit('Fecha de vencimiento inválida'); }

$items = json_decode($turnos_json, true);
if (!is_array($items)) $items = [];

// Mapa DOW→ES (0..6)
$MAP_DOW_ES = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

try {
  $conexion->begin_transaction();

  /* 1) Traer membresía y lockear */
  $stmt = $conexion->prepare("
    SELECT id, cliente_id, plan_id, fecha_inicio, fecha_vencimiento,
           clases_disponibles, IFNULL(clases_restantes, NULL) AS clases_restantes
    FROM membresias
    WHERE id = ? AND gimnasio_id = ?
    FOR UPDATE
  ");
  if (!$stmt) throw new Exception("Prepare select: ".$conexion->error);
  $stmt->bind_param("ii", $id, $gimnasio_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $mem = $res->fetch_assoc();
  $stmt->close();

  if (!$mem) { throw new Exception("Membresía no encontrada"); }

  $cliente_id = (int)$mem['cliente_id'];
  $plan_id    = (int)$mem['plan_id'];

  // Validar que no se retrocede por detrás del inicio
  if (valid_date($mem['fecha_inicio']) && $nuevo_vto < $mem['fecha_inicio']) {
    throw new Exception("La fecha de vencimiento no puede ser anterior a la fecha de inicio");
  }

  $old_vto = (string)$mem['fecha_vencimiento'];
  $old_cd  = is_null($mem['clases_disponibles']) ? null : (int)$mem['clases_disponibles'];
  $old_cr  = is_null($mem['clases_restantes'])   ? null : (int)$mem['clases_restantes'];

  /* 2) Actualizar clases + vencimiento (columnas dinámicas) */
  $has_cd = hcol($conexion, 'membresias', 'clases_disponibles');
  $has_cr = hcol($conexion, 'membresias', 'clases_restantes');

  $sets  = ["fecha_vencimiento = ?"];
  $types = 's';
  $vals  = [$nuevo_vto];

  if ($has_cd) { $sets[] = "clases_disponibles = ?"; $types .= 'i'; $vals[] = $nuevas_clases; }
  if ($has_cr) { $sets[] = "clases_restantes   = ?"; $types .= 'i'; $vals[] = $nuevas_clases; }

  $sqlU = "UPDATE membresias SET ".implode(', ', $sets)." WHERE id = ? AND gimnasio_id = ?";
  $stmtU = $conexion->prepare($sqlU);
  if (!$stmtU) throw new Exception("Prepare update: ".$conexion->error);
  $types .= 'ii';
  $vals[]  = $id;
  $vals[]  = $gimnasio_id;

  $refs=[]; $refs[]=&$types; foreach ($vals as $k=>$v){ $refs[]=&$vals[$k]; }
  if (!call_user_func_array([$stmtU,'bind_param'],$refs)) throw new Exception("bind_param update falló");
  if (!$stmtU->execute()) throw new Exception("Exec update: ".$stmtU->error);
  $stmtU->close();

  /* 3) Historial (opcional) */
  if (hcol($conexion, 'membresias_historial', 'membresia_id')) {
    $stmtH = $conexion->prepare("
      INSERT INTO membresias_historial
        (membresia_id, gimnasio_id, fecha, cambio, old_vencimiento, new_vencimiento, old_clases, new_clases, user_id)
      VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)
    ");
    if ($stmtH) {
      $cambio  = 'edicion';
      $old_cls = ($has_cr ? $old_cr : $old_cd);
      $user_id = (int)($_SESSION['usuario_id'] ?? 0);
      $stmtH->bind_param("iisssiii", $id, $gimnasio_id, $cambio, $old_vto, $nuevo_vto, $old_cls, $nuevas_clases, $user_id);
      $stmtH->execute();
      $stmtH->close();
    }
  }

  /* 4) Turnos personalizados (clientes_fijos o turnos_personalizados) */
  // Detectar tabla
  $fixed_table = null;
  foreach (['clientes_fijos','turnos_personalizados'] as $cand) {
    if (table_exists($conexion, $cand)) { $fixed_table = $cand; break; }
  }

  if ($fixed_table) {
    $has_gim = hcol($conexion,$fixed_table,'gimnasio_id');
    $has_mem = hcol($conexion,$fixed_table,'membresia_id');
    $has_cli = hcol($conexion,$fixed_table,'cliente_id');

    // detectar columna profesor si existe
    $col_prof = null;
    foreach (['profesor_id','profe_id','instructor_id','entrenador_id'] as $cp) {
      if (hcol($conexion, $fixed_table, $cp)) { $col_prof = $cp; break; }
    }

    // WHERE base para afectar SOLO registros de esta membresía/cliente en este gimnasio
    $w = [];
    if ($has_gim) $w[] = "gimnasio_id = ".$gimnasio_id;
    if ($has_mem) $w[] = "membresia_id = ".$id;
    elseif ($has_cli) $w[] = "cliente_id = ".$cliente_id;
    $where_sql = $w ? ("WHERE ".implode(" AND ", $w)) : "";

    // ¿Borrar existentes?
    if ($borrar_pers) {
      $conexion->query("DELETE FROM `$fixed_table` $where_sql");
    }

    // ¿Recrear con lo enviado?
    if ($pers_habilitar) {
      // si no se borró explícitamente, igualmente reemplazamos (borramos y creamos de nuevo)
      if (!$borrar_pers) {
        $conexion->query("DELETE FROM `$fixed_table` $where_sql");
      }

      // Insert por cada ítem válido
      if (!empty($items)) {
        // Construcción dinámica de columnas
        $cols = [];
        $typesIns = '';

        if ($has_gim) { $cols[] = 'gimnasio_id';  $typesIns .= 'i'; }
        if ($has_mem) { $cols[] = 'membresia_id'; $typesIns .= 'i'; }
        elseif ($has_cli){ $cols[] = 'cliente_id'; $typesIns .= 'i'; }
        if ($col_prof) { $cols[] = $col_prof;     $typesIns .= 'i'; }

        $cols = array_merge($cols, ['dow','hora','desde','hasta','creado_at']);
        // placeholders: todos menos creado_at, que va NOW()
        $ph = implode(',', array_fill(0, count($cols)-1, '?')).',NOW()';
        $sqlI = "INSERT INTO `$fixed_table` (".implode(',', $cols).") VALUES ($ph)";
        $stI = $conexion->prepare($sqlI);
        if (!$stI) throw new Exception("Prepare $fixed_table: ".$conexion->error);

        foreach ($items as $t) {
          $dow   = isset($t['dow']) ? (int)$t['dow'] : -1;
          $hora  = clean_time($t['hora']  ?? '');
          $desde = clean_date($t['desde'] ?? '');
          $hasta = clean_date($t['hasta'] ?? '');
          $prof  = isset($t['profesor_id']) && $t['profesor_id'] !== '' ? (int)$t['profesor_id'] : 0;

          if ($dow < 0 || $dow > 6 || !$hora || !$desde || !$hasta) continue;

          // armar values según columnas
          $vals = [];
          $types = $typesIns;

          if ($has_gim) $vals[] = $gimnasio_id;
          if ($has_mem) $vals[] = $id; else if ($has_cli) $vals[] = $cliente_id;
          if ($col_prof) { $vals[] = $prof; }

          $vals[] = $dow;   $types .= 'i';
          $vals[] = $hora;  $types .= 's';
          $vals[] = $desde; $types .= 's';
          $vals[] = $hasta; $types .= 's';

          $refs=[]; $refs[]=&$types; foreach ($vals as $k=>$v){ $refs[]=&$vals[$k]; }
          if (!call_user_func_array([$stI,'bind_param'],$refs)) throw new Exception("bind_param $fixed_table falló");
          if (!$stI->execute()) {
            if ((int)$stI->errno === 1062) { continue; } // duplicado -> ignorar
            throw new Exception("Exec $fixed_table: ".$stI->error);
          }
        }
        $stI->close();
      }
    } else {
      // Si NO se recrea y el vencimiento se extendió, extendemos 'hasta' de los existentes
      if (!$borrar_pers && $nuevo_vto > $old_vto && hcol($conexion,$fixed_table,'hasta')) {
        $conexion->query("UPDATE `$fixed_table` SET `hasta` = '".$conexion->real_escape_string($nuevo_vto)."' $where_sql");
      }
    }
  }

  /* 5) === SINCRONIZAR con gym_clientes_plan (para que horarios_gimnasio.php descuente/ocupe) ===
     Política:
     - Si $pers_habilitar: reemplazamos los fijos del cliente en gym_clientes_plan por lo enviado.
     - Si $borrar_pers y !pers_habilitar: eliminamos los fijos del cliente en gym_clientes_plan.
     - Si solo se extendió vencimiento: actualizamos `hasta` de las filas actuales.
  */

  // ¿Existe la tabla destino?
  if (table_exists($conexion, 'gym_clientes_plan')) {
    // a) Borrar todo del cliente si se va a recrear o borrar
    if ($pers_habilitar || $borrar_pers) {
      $delSql = "DELETE FROM gym_clientes_plan WHERE gimnasio_id=? AND cliente_id=?";
      $stDel = $conexion->prepare($delSql);
      if ($stDel) {
        $stDel->bind_param("ii", $gimnasio_id, $cliente_id);
        $stDel->execute();
        $stDel->close();
      }
    }

    // b) Recrear desde $items (si corresponde)
    if ($pers_habilitar && !empty($items)) {
      // Agrupar por (hora, profesor_id, desde, hasta) → dias_json (Lunes..Domingo)
      $grupos = []; // key => ['hora'=>..., 'prof'=>null|int, 'desde'=>..., 'hasta'=>..., 'dias'=>set()]
      foreach ($items as $t) {
        $dow   = isset($t['dow']) ? (int)$t['dow'] : -1;
        $hora  = clean_time($t['hora']  ?? '');
        $desde = clean_date($t['desde'] ?? '');
        $hasta = clean_date($t['hasta'] ?? '');
        $profRaw = isset($t['profesor_id']) ? $t['profesor_id'] : '';
        $prof    = ($profRaw === '' || $profRaw === null) ? null : (int)$profRaw;

        if ($dow < 0 || $dow > 6 || !$hora || !$desde || !$hasta) continue;

        $diaEs = $MAP_DOW_ES[$dow];
        $key = $hora.'|'.($prof===null?'NULL':$prof).'|'.$desde.'|'.$hasta;
        if (!isset($grupos[$key])) {
          $grupos[$key] = ['hora'=>$hora,'prof'=>$prof,'desde'=>$desde,'hasta'=>$hasta,'dias'=>[]];
        }
        $grupos[$key]['dias'][$diaEs] = true; // set
      }

      foreach ($grupos as $ginfo) {
        $dias_json = json_encode(array_keys($ginfo['dias']), JSON_UNESCAPED_UNICODE);
        if ($ginfo['prof'] === null) {
          $sqlG = "INSERT INTO gym_clientes_plan (gimnasio_id, cliente_id, plan_id, desde, hasta, hora, dias_json, profesor_id)
                   VALUES (?,?,?,?,?,?,?,NULL)
                   ON DUPLICATE KEY UPDATE dias_json=VALUES(dias_json), hora=VALUES(hora)";
          $stG = $conexion->prepare($sqlG);
          if (!$stG) throw new Exception("Prepare gym_clientes_plan (NULL): ".$conexion->error);
          $stG->bind_param("iiissss", $gimnasio_id, $cliente_id, $plan_id, $ginfo['desde'], $ginfo['hasta'], $ginfo['hora'], $dias_json);
        } else {
          $sqlG = "INSERT INTO gym_clientes_plan (gimnasio_id, cliente_id, plan_id, desde, hasta, hora, dias_json, profesor_id)
                   VALUES (?,?,?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE dias_json=VALUES(dias_json), hora=VALUES(hora), profesor_id=VALUES(profesor_id)";
          $stG = $conexion->prepare($sqlG);
          if (!$stG) throw new Exception("Prepare gym_clientes_plan (prof): ".$conexion->error);
          $stG->bind_param("iiissssi", $gimnasio_id, $cliente_id, $plan_id, $ginfo['desde'], $ginfo['hasta'], $ginfo['hora'], $dias_json, $ginfo['prof']);
        }
        if (!$stG->execute()) {
          if ((int)$stG->errno !== 1062) { // si no es duplicado
            throw new Exception("Exec gym_clientes_plan: ".$stG->error);
          }
        }
        $stG->close();
      }
    } elseif (!$pers_habilitar && !$borrar_pers) {
      // c) Solo actualizar vencimiento de lo existente (extensión)
      $sqlUpG = "UPDATE gym_clientes_plan SET hasta=? WHERE gimnasio_id=? AND cliente_id=? AND hasta<?";
      $stUpG = $conexion->prepare($sqlUpG);
      if ($stUpG) {
        $stUpG->bind_param("siis", $nuevo_vto, $gimnasio_id, $cliente_id, $nuevo_vto);
        $stUpG->execute();
        $stUpG->close();
      }
    }
    // d) Si se pidió borrar y no recrear → ya se eliminaron en (a)
  }

  $conexion->commit();
  header('Location: ver_membresias.php?edit_ok=1', true, 303);
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al editar membresía: ".h($e->getMessage());
}
