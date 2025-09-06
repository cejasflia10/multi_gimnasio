<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Sin BD']); exit; }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function jexit($ok,$msg,$extra=[]){ echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }

$pelea_id = (int)($_POST['pelea_id'] ?? 0);
$juez_id  = (int)($_POST['juez_id'] ?? 0);
$round    = (int)($_POST['round'] ?? 0);
$ganador  = strtolower(post('ganador')); // 'rojo' | 'azul' | 'empate'
$metodo   = strtoupper(post('metodo'));  // '', KO,KOT,RSC,EMPATE,DSQ,RC,SURRENDER
$coment   = post('comentario');

$validG   = ['rojo','azul','empate'];
$validM   = ['', 'KO','KOT','RSC','EMPATE','DSQ','RC','SURRENDER'];

if ($pelea_id<=0 || $juez_id<=0 || $round<=0) jexit(false,'Datos obligatorios faltantes');
if (!in_array($ganador, $validG, true)) jexit(false,'Ganador inválido');
if (!in_array($metodo, $validM, true)) jexit(false,'Método inválido');

/* Reglas: 
   - Si ganador = 'empate' => método debe ser '' o 'EMPATE'. Normalizamos a 'EMPATE'.
   - Si método = 'EMPATE'  => ganador debe ser 'empate'.
*/
if ($ganador === 'empate' && $metodo !== '' && $metodo !== 'EMPATE') $metodo = 'EMPATE';
if ($metodo === 'EMPATE') $ganador = 'empate';

/* Validar que el juez esté asignado a la pelea */
$st = $conexion->prepare("SELECT 1 FROM peleas_jueces WHERE pelea_id=? AND juez_id=? LIMIT 1");
if(!$st){ jexit(false,'SQL asignación: '.$conexion->error); }
$st->bind_param('ii',$pelea_id,$juez_id);
$st->execute();
$ok = $st->get_result()->num_rows>0; $st->close();
if(!$ok) jexit(false,'El juez no está asignado a esta pelea');

/* UPSERT de tarjeta */
$sql = "INSERT INTO puntajes_round (pelea_id, juez_id, round_num, ganador_round, metodo, comentario)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE ganador_round=VALUES(ganador_round), metodo=VALUES(metodo), comentario=VALUES(comentario)";
$st = $conexion->prepare($sql);
if(!$st){ jexit(false,'SQL guardar: '.$conexion->error); }
$st->bind_param('iiisss', $pelea_id, $juez_id, $round, $ganador, $metodo, $coment);
if(!$st->execute()){ $st->close(); jexit(false,'No se pudo guardar'); }
$st->close();

jexit(true,'Tarjeta guardada');
