<?php
/* ==========================================================
   api_combate_estado.php — Estado actual del combate (JSON)
   Params (GET):
     - pelea_id (int)  -> prioridad 1
     - evento_id (int) -> si no hay pelea_id, toma pelea_actual_id de combate_estado
   Respuesta:
     { ok, evento_id, pelea_id, numero, categoria{nombre,rango}, modalidad, division,
       total_rondas, estado{running,paused,ronda,duracion,descanso,remaining,activo},
       rojo{nombre,escuela}, azul{nombre,escuela}, ganador, ts }
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
function out($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function row($r){ return $r ? $r->fetch_assoc() : null; }

$pelea_id  = isset($_GET['pelea_id'])  ? (int)$_GET['pelea_id']  : 0;
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;

/* === Resolver pelea activa si no viene pelea_id === */
if ($pelea_id === 0) {
  if ($evento_id === 0) {
    $r = row($conexion->query("SELECT id FROM eventos_deportivos ORDER BY id DESC LIMIT 1"));
    $evento_id = (int)($r['id'] ?? 0);
  }
  if ($evento_id > 0) {
    $st = row($conexion->query("SELECT pelea_actual_id FROM combate_estado WHERE evento_id={$evento_id} AND activo=1 ORDER BY id DESC LIMIT 1"));
    $pelea_id = (int)($st['pelea_actual_id'] ?? 0);
  }
}

if ($pelea_id === 0) out(['ok'=>false,'error'=>'Sin pelea activa','evento_id'=>$evento_id,'pelea_id'=>0]);

/* === Datos de pelea + competidores + categoría === */
$sql = "
SELECT p.id AS pelea_id, p.evento_id, p.numero, p.total_rondas, p.modalidad, p.division, p.categoria_id, p.ganador,
       cr.id   AS rojo_id,  CONCAT(COALESCE(cr.apellido,''),' ',COALESCE(cr.nombre,'')) AS rojo_nom,
       cr.escuela AS rojo_escuela,
       ca.id   AS azul_id,  CONCAT(COALESCE(ca.apellido,''),' ',COALESCE(ca.nombre,'')) AS azul_nom,
       ca.escuela AS azul_escuela,
       cat.nombre AS categoria_nombre, cat.peso_min, cat.peso_max
FROM peleas_evento p
LEFT JOIN competidores_evento cr ON cr.id = p.rojo_competidor_id
LEFT JOIN competidores_evento ca ON ca.id = p.azul_competidor_id
LEFT JOIN categorias_evento   cat ON cat.id = p.categoria_id
WHERE p.id={$pelea_id}
LIMIT 1";
$pelea = row($conexion->query($sql));
if (!$pelea) out(['ok'=>false,'error'=>'Pelea inexistente','pelea_id'=>$pelea_id]);

$evento_id = (int)$pelea['evento_id'];

/* === Estado crono (desde TU tabla combate_estado) === */
$st = row($conexion->query("
  SELECT ronda, running, paused, duracion, descanso, remaining, activo, UNIX_TIMESTAMP(actualizado_en) AS uts
  FROM combate_estado
  WHERE evento_id={$evento_id}
  ORDER BY id DESC
  LIMIT 1
"));

$estado = [
  'ronda'    => (int)($st['ronda'] ?? 1),
  'running'  => (int)($st['running'] ?? 0),
  'paused'   => (int)($st['paused']  ?? 1),
  'duracion' => (int)($st['duracion']?? 180),
  'descanso' => (int)($st['descanso']?? 60),
  'remaining'=> (int)($st['remaining']?? 180),
  'activo'   => (int)($st['activo']   ?? 0),
];

$out = [
  'ok'        => true,
  'evento_id' => $evento_id,
  'pelea_id'  => (int)$pelea['pelea_id'],
  'numero'    => (int)($pelea['numero'] ?? 0),
  'categoria' => [
    'nombre' => (string)($pelea['categoria_nombre'] ?? ''),
    'rango'  => ($pelea['peso_min']!==null && $pelea['peso_max']!==null)
                ? ((float)$pelea['peso_min']).'–'.((float)$pelea['peso_max']).' kg' : '',
  ],
  'modalidad'    => (string)($pelea['modalidad'] ?? ''),
  'division'     => (string)($pelea['division']  ?? ''),
  'total_rondas' => (int)($pelea['total_rondas'] ?? 3),
  'estado'       => $estado,
  'rojo'         => ['nombre'=>(string)($pelea['rojo_nom'] ?? '—'), 'escuela'=>(string)($pelea['rojo_escuela'] ?? '')],
  'azul'         => ['nombre'=>(string)($pelea['azul_nom'] ?? '—'), 'escuela'=>(string)($pelea['azul_escuela'] ?? '')],
  'ganador'      => (string)($pelea['ganador'] ?? ''),
  'ts'           => (int)($st['uts'] ?? time())
];

out($out);
