<?php
// guardar_progreso.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id === 0 || $gimnasio_id === 0) {
  http_response_code(403);
  exit('Acceso denegado');
}

/** Rangos y helpers (mismo parser robusto de antes) */
const PESO_MIN    = 0.10;
const PESO_MAX    = 9999.99;
const ALTURA_MIN  = 30.00;
const ALTURA_MAX  = 300.00;
const DUR_MIN     = 0;
const DUR_MAX     = 1440;

function normalizar_decimal($rawVal, string $key, float $min, float $max): array {
  if ($rawVal === null) return [false, "Falta el campo {$key}", null];
  $raw = trim((string)$rawVal);
  if ($raw === '') return [false, "El campo {$key} es requerido", null];
  $clean = preg_replace('/[^0-9\.,]/u', '', $raw);
  if ($clean === '' || preg_match('/^[\.,]+$/', $clean)) return [false, "El campo {$key} es requerido", null];
  $clean = preg_replace('/\.(?=\d{3}(\D|$))/', '', $clean);
  $clean = str_replace(',', '.', $clean);
  if ($clean[0] === '.') $clean = '0'.$clean;
  if (!is_numeric($clean)) return [false, "Valor inválido en {$key}", null];
  $num = (float)$clean;
  if (!is_finite($num) || $num < $min || $num > $max) return [false, "Valor fuera de rango en {$key} (min {$min}, max {$max})", null];
  return [true, null, number_format($num, 2, '.', '')];
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

/** Captura */
list($ok1, $e1, $peso_antes)     = normalizar_decimal($_POST['peso_antes']   ?? null, 'peso_antes',   PESO_MIN, PESO_MAX);
list($ok2, $e2, $peso_despues)   = normalizar_decimal($_POST['peso_despues'] ?? null, 'peso_despues', PESO_MIN, PESO_MAX);
list($ok3, $e3, $altura_cm)      = normalizar_decimal($_POST['altura']       ?? null, 'altura',       ALTURA_MIN, ALTURA_MAX);
list($ok4, $e4, $duracion_min)   = normalizar_int    ($_POST['duracion']     ?? null, 'duracion',     DUR_MIN, DUR_MAX);

$esfuerzo = strtolower(txt('esfuerzo'));          // no se guarda
$objetivo = objetivo_normalizado(txt('objetivo')); // ENUM
$notas    = txt('notas');

$errores = array_filter([$e1,$e2,$e3,$e4]);
if (!($ok1 && $ok2 && $ok3 && $ok4)) {
  http_response_code(400);
  echo "Errores: " . implode(' | ', $errores);
  exit;
}

/** Recalcular calorías servidor */
$factor = 7; if ($esfuerzo==='bajo') $factor=4; if ($esfuerzo==='alto') $factor=10;
$calorias_quemadas = max(0, (int)round($duracion_min * $factor));

/** INSERT */
$sql = "INSERT INTO progreso
  (cliente_id, gimnasio_id, peso_antes, peso_despues, altura_cm, duracion_min, calorias_quemadas, objetivo, notas)
  VALUES (?,?,?,?,?,?,?,?,?)";
$st = $conexion->prepare($sql);
if (!$st) { http_response_code(500); exit('Error preparando SQL: '.$conexion->error); }

$st->bind_param(
  "iisssiiss",
  $cliente_id,
  $gimnasio_id,
  $peso_antes,        // s "##.##"
  $peso_despues,      // s
  $altura_cm,         // s
  $duracion_min,      // i
  $calorias_quemadas, // i
  $objetivo,          // s
  $notas              // s
);

$ok = $st->execute();
$err = $st->error;
$new_id = $conexion->insert_id ?? 0;
$st->close();

if ($ok) {
  // Tras guardar, vamos al asistente pasando el id recién creado:
  header('Location: asistente_nutricional.php?ok=1&progreso_id='.(int)$new_id);
  exit;
} else {
  http_response_code(500);
  echo '❌ Error al guardar: ' . $err;
}
