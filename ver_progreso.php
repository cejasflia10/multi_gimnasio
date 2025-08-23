<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
include __DIR__ . '/menu_cliente.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

if (!$cliente_id || !$gimnasio_id) {
  echo "<div style='color:red; text-align:center; font-size:20px;'>❌ Acceso denegado.</div>";
  exit;
}

// ---------- helpers ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function db_has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}
function db_has_col(mysqli $db, string $t, string $c): bool {
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $res = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return ($res && $res->num_rows > 0);
}
function pick_col(mysqli $db, string $t, array $candidates): ?string {
  foreach ($candidates as $c) if (db_has_col($db,$t,$c)) return $c;
  return null;
}

// ---------- filtro por fechas (AR San Luis) ----------
$tz = new DateTimeZone('America/Argentina/San_Luis');
$hoy = new DateTime('today', $tz);

$filtro = $_GET['filtro'] ?? 'mensual';
$filtro = in_array($filtro, ['semanal','mensual','anual'], true) ? $filtro : 'mensual';

switch ($filtro) {
  case 'semanal':
    $desde = (clone $hoy)->modify('-6 days'); // últimos 7 días incluyendo hoy
    $hasta = (clone $hoy);
    break;
  case 'anual':
    $desde = new DateTime($hoy->format('Y-01-01'), $tz);
    $hasta = (clone $hoy);
    break;
  case 'mensual':
  default:
    $desde = new DateTime($hoy->format('Y-m-01'), $tz);
    $hasta = (clone $hoy);
    break;
}
$desde_str = $desde->format('Y-m-d');
$hasta_str = $hasta->format('Y-m-d');

// ---------- elegir tabla y columnas ----------
$tables_try = ['progreso_cliente','progreso','progreso_fisico','progresos'];
$table = null;
foreach ($tables_try as $tb) { if (db_has_table($conexion, $tb)) { $table = $tb; break; } }

$rows = [];
if ($table) {
  // columnas posibles
  $cFecha = pick_col($conexion, $table, ['fecha','created_at','fecha_registro']);
  $cPA    = pick_col($conexion, $table, ['peso_antes','peso_inicio']);
  $cPD    = pick_col($conexion, $table, ['peso_despues','peso_fin','peso_post']);

  $cEsf   = pick_col($conexion, $table, ['esfuerzo','nivel_esfuerzo','intensidad']);
  $cDur   = pick_col($conexion, $table, ['duracion_entrenamiento','duracion_min','duracion']);
  $cCal   = pick_col($conexion, $table, ['calorias_estimadas','calorias_quemadas','calorias']);
  $cEnf   = pick_col($conexion, $table, ['enfermedades','condiciones','condiciones_medicas']);

  $cCli   = pick_col($conexion, $table, ['cliente_id','id_cliente']);
  $cGym   = pick_col($conexion, $table, ['gimnasio_id','id_gimnasio']);

  // SELECT robusto con alias estandarizados
  $parts = [];
  $parts[] = $cFecha ? "`$cFecha` AS fecha" : "'0000-00-00' AS fecha";
  $parts[] = $cPA    ? "`$cPA` AS peso_antes" : "NULL AS peso_antes";
  $parts[] = $cPD    ? "`$cPD` AS peso_despues" : "NULL AS peso_despues";
  $parts[] = $cDur   ? "`$cDur` AS duracion" : "NULL AS duracion";
  $parts[] = $cEsf   ? "`$cEsf` AS esfuerzo" : "NULL AS esfuerzo";
  $parts[] = $cCal   ? "`$cCal` AS calorias" : "NULL AS calorias";
  $parts[] = $cEnf   ? "`$cEnf` AS enfermedades" : "NULL AS enfermedades";

  $sql = "SELECT ".implode(", ", $parts)." FROM `{$table}` WHERE 1";

  $bind = ''; $vals = [];

  if ($cCli) { $sql .= " AND `$cCli` = ?"; $bind .= 'i'; $vals[] = $cliente_id; }
  if ($cGym) { $sql .= " AND `$cGym` = ?"; $bind .= 'i'; $vals[] = $gimnasio_id; }
  if ($cFecha) { // filtrar por fecha solo si existe columna de fecha
    $sql .= " AND `$cFecha` BETWEEN ? AND ?";
    $bind .= 'ss'; $vals[] = $desde_str; $vals[] = $hasta_str;
  }

  $orderCol = $cFecha ?: (db_has_col($conexion,$table,'id') ? 'id' : null);
  if ($orderCol) $sql .= " ORDER BY `$orderCol` DESC";

  if ($st = @$conexion->prepare($sql)) {
    if ($bind !== '') {
      // Bind manual para 0,1,2,3-4 params más comunes
      if ($bind === 'i')          { $st->bind_param('i', $vals[0]); }
      elseif ($bind === 'ii')     { $st->bind_param('ii', $vals[0], $vals[1]); }
      elseif ($bind === 'iss')    { $st->bind_param('iss', $vals[0], $vals[1], $vals[2]); }
      elseif ($bind === 'iiss')   { $st->bind_param('iiss', $vals[0], $vals[1], $vals[2], $vals[3]); }
      elseif ($bind === 'ss')     { $st->bind_param('ss', $vals[0], $vals[1]); }
      else {
        // fallback genérico
        $ref = array_merge([$st,$bind], $vals);
        $tmp = [];
        foreach ($ref as $k=>$v) { $tmp[$k] = &$ref[$k]; }
        call_user_func_array('mysqli_stmt_bind_param', $tmp);
      }
    }
    if ($st->execute()) {
      $res = $st->get_result();
      while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    $st->close();
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>📊 Mi Progreso</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0b0b0b; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12); }
    *{box-sizing:border-box}
    body{ margin:0; background:var(--bg); color:var(--fg); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; padding:16px }
    .contenedor{ max-width:950px; margin:0 auto; }
    h2{ text-align:center; margin:4px 0 10px }
    .filtros{ text-align:center; margin:8px 0 16px }
    .filtros a{
      display:inline-block; margin:0 6px; padding:8px 12px; border-radius:12px;
      border:1px solid var(--border); color:var(--fg); text-decoration:none; font-weight:700
    }
    .filtros a.active{ background:var(--acc); color:#111; border-color:transparent }
    .card{ background:#111; border:1px solid var(--border); border-radius:16px; padding:12px }
    table{ width:100%; border-collapse:collapse; margin-top:10px; font-size:14px }
    th,td{ padding:10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:center }
    th{ color:var(--muted); font-weight:700 }
    .muted{ color:var(--muted) }
    .stats{ display:grid; grid-template-columns:repeat(2,1fr); gap:8px; margin-top:8px }
    @media (min-width:720px){ .stats{ grid-template-columns:repeat(4,1fr); } }
    .stat{ background:#1a1d24; border:1px solid var(--border); border-radius:12px; padding:10px; text-align:center }
    .stat b{ display:block; font-size:18px; margin-top:4px }
  </style>
</head>
<body>
<div class="contenedor">
  <h2>📈 Historial de Progreso Físico</h2>

  <div class="filtros">
    <?php
      $mk = function($k,$label) use($filtro){
        $cls = $filtro===$k ? 'active' : '';
        echo '<a class="'.$cls.'" href="?filtro='.h($k).'">'.$label.'</a>';
      };
      $mk('semanal','📅 Semanal');
      $mk('mensual','🗓️ Mensual');
      $mk('anual','📆 Anual');
    ?>
  </div>

  <!-- Resumen -->
  <div class="stats">
    <?php
      $total = count($rows);
      $sumCal = 0; $sumDur=0; $sumPA=0; $sumPD=0;
      foreach ($rows as $r) {
        $sumCal += (int)($r['calorias'] ?? 0);
        $sumDur += (int)($r['duracion'] ?? 0);
        $sumPA  += (float)($r['peso_antes'] ?? 0);
        $sumPD  += (float)($r['peso_despues'] ?? 0);
      }
      $avgPA = $total ? $sumPA/$total : 0;
      $avgPD = $total ? $sumPD/$total : 0;
    ?>
    <div class="stat">Sesiones<b><?= (int)$total ?></b></div>
    <div class="stat">Calorías totales<b><?= (int)$sumCal ?> kcal</b></div>
    <div class="stat">Prom. peso antes<b><?= number_format($avgPA,1,',','.') ?> kg</b></div>
    <div class="stat">Prom. peso después<b><?= number_format($avgPD,1,',','.') ?> kg</b></div>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>📅 Fecha</th>
          <th>Peso antes</th>
          <th>Peso después</th>
          <th>Duración</th>
          <th>Esfuerzo</th>
          <th>Calorías</th>
          <th>Enfermedades</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $row): 
            $fec  = $row['fecha'] ?? '';
            $pa   = (float)($row['peso_antes'] ?? 0);
            $pd   = (float)($row['peso_despues'] ?? 0);
            $dur  = (int)($row['duracion'] ?? 0);
            $esf  = trim((string)($row['esfuerzo'] ?? ''));
            $cal  = (int)($row['calorias'] ?? 0);
            $enf  = trim((string)($row['enfermedades'] ?? ''));
          ?>
            <tr>
              <td><?= h($fec) ?></td>
              <td><?= number_format($pa,1,',','.') ?> kg</td>
              <td><?= number_format($pd,1,',','.') ?> kg</td>
              <td><?= (int)$dur ?> min</td>
              <td><?= h($esf ?: '-') ?></td>
              <td><?= (int)$cal ?> kcal</td>
              <td><?= h($enf ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="muted">No se encontraron registros para este período.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
