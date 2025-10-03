<?php
// guardar_progreso.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id === 0 || $gimnasio_id === 0) {
  header('Location: form_progreso.php?err='.urlencode('Acceso denegado'), true, 303);
  exit;
}

/* ===== Rangos ===== */
const PESO_MIN    = 0.10;
const PESO_MAX    = 9999.99;
const ALTURA_MIN  = 30.00;
const ALTURA_MAX  = 300.00;
const DUR_MIN     = 0;
const DUR_MAX     = 1440;

/* ===== Helpers ===== */
function normalizar_decimal($rawVal, string $key, float $min, float $max): array {
  if ($rawVal === null) return [false, "Falta el campo {$key}", null];
  $raw = trim((string)$rawVal);
  if ($raw === '') return [false, "El campo {$key} es requerido", null];
  $clean = preg_replace('/[^0-9\.,]/u', '', $raw);
  if ($clean === '' || preg_match('/^[\.,]+$/', $clean)) return [false, "El campo {$key} es requerido", null];
  // quito separadores de miles tipo 1.234,56
  $clean = preg_replace('/\.(?=\d{3}(\D|$))/', '', $clean);
  $clean = str_replace(',', '.', $clean);
  if ($clean[0] === '.') $clean = '0'.$clean;
  if (!is_numeric($clean)) return [false, "Valor inválido en {$key}", null];
  $num = (float)$clean;
  if (!is_finite($num) || $num < $min || $num > $max) return [false, "Valor fuera de rango en {$key} (min {$min}, max {$max})", null];
  return [true, null, number_format($num, 2, '.', '')]; // string "##.##" apto DECIMAL
}
function normalizar_int($rawVal, string $key, int $min, int $max): array {
  if ($rawVal === null) return [false, "Falta el campo {$key}", null];
  $raw = trim((string)$rawVal);
  if ($raw === '') return [false, "El campo {$key} es requerido", null];
  $raw = str_replace([' ', "'"], '', $raw);
  $raw = str_replace(',', '.', $raw);
  if (!is_numeric($raw)) return [false, "Valor inválido en {$key}", null];
  $val = (int)round((float)$raw);
  if ($val < $min || $val > $max) return [false, "Valor fuera de rango en {$key} (min {$min}, max {$max})", null];
  return [true, null, $val];
}
function txt($k){ return trim((string)($_POST[$k] ?? '')); }
function objetivo_normalizado($raw) {
  $raw = strtolower(trim((string)$raw));
  if (in_array($raw, ['mantener','bajar','subir'], true)) return $raw;
  if (in_array($raw, ['down','baja','perder'], true)) return 'bajar';
  if (in_array($raw, ['up','ganar','aumentar'], true)) return 'subir';
  return 'mantener';
}

/* ===== Captura + validación ===== */
list($ok1, $e1, $peso_antes)   = normalizar_decimal($_POST['peso_antes']   ?? null, 'peso_antes',   PESO_MIN, PESO_MAX);
list($ok2, $e2, $peso_despues) = normalizar_decimal($_POST['peso_despues'] ?? null, 'peso_despues', PESO_MIN, PESO_MAX);
list($ok3, $e3, $altura_cm)    = normalizar_decimal($_POST['altura']       ?? null, 'altura',       ALTURA_MIN, ALTURA_MAX);
list($ok4, $e4, $duracion_min) = normalizar_int    ($_POST['duracion']     ?? null, 'duracion',     DUR_MIN, DUR_MAX);

$esfuerzo = strtolower(txt('esfuerzo'));          // no se guarda en DB
$objetivo = objetivo_normalizado(txt('objetivo')); // ENUM-like en texto
$notas    = txt('notas');

$errores = array_filter([$e1,$e2,$e3,$e4]);
if (!($ok1 && $ok2 && $ok3 && $ok4)) {
  $msg = 'Errores: ' . implode(' | ', $errores);
  header('Location: form_progreso.php?err='.urlencode($msg), true, 303);
  exit;
}

/* ===== Recalcular calorías en servidor ===== */
$factor = 7; if ($esfuerzo==='bajo') $factor=4; if ($esfuerzo==='alto') $factor=10;
$calorias_quemadas = max(0, (int)round($duracion_min * $factor));

/* ===== INSERT ===== */
$sql = "INSERT INTO progreso
  (cliente_id, gimnasio_id, fecha, peso_antes, peso_despues, altura_cm, duracion_min, calorias_quemadas, objetivo, notas)
  VALUES (?,?,CURDATE(),?,?,?,?,?,?,?)";
$st = $conexion->prepare($sql);
if (!$st) {
  header('Location: form_progreso.php?err='.urlencode('Error preparando SQL: '.$conexion->error), true, 303);
  exit;
}

$st->bind_param(
  "iisssiiss",
  $cliente_id,
  $gimnasio_id,
  $peso_antes,        // s (DECIMAL)
  $peso_despues,      // s
  $altura_cm,         // s
  $duracion_min,      // i
  $calorias_quemadas, // i
  $objetivo,          // s
  $notas              // s
);

$ok = $st->execute();
$err = $st->error;
$new_id = (int)$conexion->insert_id;
$st->close();

if (!$ok) {
  header('Location: form_progreso.php?err='.urlencode('❌ Error al guardar: '.$err), true, 303);
  exit;
}

/* Obtengo la fecha real del registro (por si tuvieras triggers/zona horaria distinta) */
$fecha_ins = date('Y-m-d');
if ($q=$conexion->prepare("SELECT fecha FROM progreso WHERE id=? AND cliente_id=? AND gimnasio_id=?")) {
  $q->bind_param("iii", $new_id, $cliente_id, $gimnasio_id);
  if ($q->execute()) {
    $r=$q->get_result()->fetch_assoc();
    if ($r && !empty($r['fecha'])) $fecha_ins = $r['fecha'];
  }
  $q->close();
}

/* PRG: Volver al formulario con OK y datos del registro */
header('Location: form_progreso.php?ok=1&progreso_id='.$new_id.'&fecha='.$fecha_ins, true, 303);
exit;
