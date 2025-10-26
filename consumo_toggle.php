<?php
/* consumo_toggle.php — Ajusta membresias.clases_disponibles y loguea en membresia_consumos */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function qv($db,$s){ return "'".$db->real_escape_string((string)$s)."'"; }
function mem_is_activa_row(array $m): bool {
  $ok_estado = true;
  if (array_key_exists('activa',$m) && $m['activa']!==null) $ok_estado = ((string)$m['activa']==='1');
  $ok_vto = true;
  if (!empty($m['fecha_vencimiento']) && $m['fecha_vencimiento']!=='0000-00-00') $ok_vto = ($m['fecha_vencimiento'] >= date('Y-m-d'));
  return $ok_estado && $ok_vto;
}

$gimnasio_id = (int)($_POST['g'] ?? 0);
$accion = $_POST['accion'] ?? '';
$acceso_id = (int)($_POST['acceso_id'] ?? 0);
if ($gimnasio_id<=0 || $acceso_id<=0 || !in_array($accion,['aplicar','deshacer'],true)){
  http_response_code(400); exit('Parámetros inválidos');
}

/* Acceso + cliente */
$q = "SELECT a.id, a.fecha_ingreso, a.cliente_id FROM accesos_gimnasio a
      WHERE a.id={$acceso_id} AND a.gimnasio_id={$gimnasio_id} LIMIT 1";
$rs = $conexion->query($q);
if (!$rs || !$rs->num_rows){ http_response_code(404); exit('Acceso no encontrado'); }
$acc = $rs->fetch_assoc();
$cliente_id = (int)$acc['cliente_id'];

/* Membresía activa más reciente (gimnasio_id) */
$hoy = date('Y-m-d');
$qm = "SELECT id, plan, clases_disponibles, activa, fecha_vencimiento
       FROM membresias
       WHERE gimnasio_id={$gimnasio_id} AND cliente_id={$cliente_id}
         AND (fecha_vencimiento IS NULL OR fecha_vencimiento='0000-00-00' OR fecha_vencimiento>='{$hoy}')
       ORDER BY COALESCE(fecha_vencimiento, '9999-12-31') DESC, id DESC
       LIMIT 1";
$rsm = $conexion->query($qm);
if (!$rsm || !$rsm->num_rows){ exit('⚠️ Sin membresía activa'); }
$mem = $rsm->fetch_assoc();
if (!mem_is_activa_row($mem)){ exit('⚠️ Membresía no activa/vencida'); }
$mem_id = (int)$mem['id'];

if ($accion==='aplicar'){
  $ck = $conexion->query("SELECT id FROM membresia_consumos WHERE acceso_id={$acceso_id} LIMIT 1");
  if ($ck && $ck->num_rows){ header("Location: accesos_gimnasio.php?g={$gimnasio_id}"); exit; }

  $conexion->query("UPDATE membresias
                    SET clases_disponibles = CASE WHEN clases_disponibles>0 THEN clases_disponibles-1 ELSE 0 END
                    WHERE id={$mem_id} AND gimnasio_id={$gimnasio_id} AND clases_disponibles>0");
  if ($conexion->affected_rows===0){ exit('⚠️ Sin clases disponibles'); }

  $conexion->query("INSERT INTO membresia_consumos (gimnasio_id, cliente_id, membresia_id, acceso_id, origen)
                    VALUES ({$gimnasio_id}, {$cliente_id}, {$mem_id}, {$acceso_id}, 'panel')");
  header("Location: accesos_gimnasio.php?g={$gimnasio_id}"); exit;
}

if ($accion==='deshacer'){
  $ck = $conexion->query("SELECT id FROM membresia_consumos WHERE acceso_id={$acceso_id} LIMIT 1");
  if (!$ck || !$ck->num_rows){ header("Location: accesos_gimnasio.php?g={$gimnasio_id}"); exit; }

  $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles + 1 WHERE id={$mem_id} AND gimnasio_id={$gimnasio_id}");
  $conexion->query("DELETE FROM membresia_consumos WHERE acceso_id={$acceso_id} LIMIT 1");
  header("Location: accesos_gimnasio.php?g={$gimnasio_id}"); exit;
}
