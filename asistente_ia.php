<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
include __DIR__ . '/menu_cliente.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

if (!$cliente_id || !$gimnasio_id) {
  echo "<div style='color:red; text-align:center; font-size:18px; padding:12px'>❌ Acceso denegado.</div>";
  exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function num($n, $dec=1){ return number_format((float)$n, $dec, ',', '.'); }

// ===== Cliente =====
$cliente = null;
if ($st = $conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1")) {
  $st->bind_param("ii", $cliente_id, $gimnasio_id);
  $st->execute();
  $cliente = $st->get_result()->fetch_assoc();
  $st->close();
}
if (!$cliente) {
  echo "<p style='color:red; padding:20px;'>⚠️ No se encontró el cliente.</p>";
  exit;
}
$nombre = trim(($cliente['apellido'] ?? '').' '.($cliente['nombre'] ?? ''));

// Altura: soporta `altura_cm` o `altura` (cm/m)
$altura_raw = $cliente['altura_cm'] ?? $cliente['altura'] ?? null;
if ($altura_raw === null || $altura_raw == 0) {
  $altura_m = 1.70; // fallback
} else {
  $altura_m = ((float)$altura_raw > 3) ? ((float)$altura_raw / 100.0) : (float)$altura_raw;
}

// ===== Último progreso =====
$ult = null;
if ($st = @$conexion->prepare("
  SELECT fecha, peso_antes, peso_despues, esfuerzo, duracion_entrenamiento, calorias_estimadas, enfermedades
  FROM progreso_cliente
  WHERE cliente_id=? AND gimnasio_id=?
  ORDER BY fecha DESC
  LIMIT 1
")) {
  $st->bind_param("ii", $cliente_id, $gimnasio_id);
  if ($st->execute()) $ult = $st->get_result()->fetch_assoc();
  $st->close();
}

// Peso de referencia: usa último registro; si no, `clientes.peso` o 70 kg
$peso_ref = null;
if ($ult && isset($ult['peso_despues']) && $ult['peso_despues'] > 0) {
  $peso_ref = (float)$ult['peso_despues'];
} else {
  $peso_ref = isset($cliente['peso']) ? (float)$cliente['peso'] : 70.0;
}

// Enfermedades: del último registro si hay, si no desde cliente si lo tuvieras
$enfermedades = trim((string)($ult['enfermedades'] ?? ''));
$es_diabetico = stripos($enfermedades, 'diab') !== false;

// ===== IMC y categoría =====
$imc = ($altura_m > 0) ? round($peso_ref / ($altura_m * $altura_m), 1) : 0.0;
$cat = 'Desconocido';
if ($imc > 0) {
  if ($imc < 18.5) $cat = 'Bajo peso';
  elseif ($imc < 25) $cat = 'Saludable';
  elseif ($imc < 30) $cat = 'Sobrepeso';
  else $cat = 'Obesidad';
}

// Rango de peso saludable aproximado (IMC 18.5–24.9)
$peso_min = round(18.5 * $altura_m * $altura_m, 1);
$peso_max = round(24.9 * $altura_m * $altura_m, 1);

// ===== Objetivo =====
$objetivo = strtolower(trim((string)($_GET['objetivo'] ?? '')));
if (!in_array($objetivo, ['bajar peso','mantener','subir peso'], true)) {
  // Por defecto segun IMC
  if     ($imc >= 25) $objetivo = 'bajar peso';
  elseif ($imc < 18.5) $objetivo = 'subir peso';
  else                 $objetivo = 'mantener';
}

// ===== Resumen últimos 7 días (si hay tabla) =====
$tz = new DateTimeZone('America/Argentina/San_Luis');
$hoy = new DateTime('today', $tz);
$desde = (clone $hoy)->modify('-6 days')->format('Y-m-d');
$hasta = $hoy->format('Y-m-d');

$stats = ['sesiones'=>0,'minutos'=>0,'kcal'=>0];
if ($st = @$conexion->prepare("
  SELECT COUNT(*) sesiones,
         COALESCE(SUM(duracion_entrenamiento),0) minutos,
         COALESCE(SUM(calorias_estimadas),0) kcal
  FROM progreso_cliente
  WHERE cliente_id=? AND gimnasio_id=? AND fecha BETWEEN ? AND ?
")) {
  $st->bind_param("iiss", $cliente_id, $gimnasio_id, $desde, $hasta);
  if ($st->execute()) $stats = $st->get_result()->fetch_assoc() ?: $stats;
  $st->close();
}

// ===== Sugerencias (agua, proteínas, kcal, macros) =====
$agua_l = max(1.5, round($peso_ref * 0.035, 1)); // 35 ml/kg
$prot_gkg = ($objetivo === 'bajar peso') ? 1.6 : (($objetivo === 'subir peso') ? 2.0 : 1.4);
$prot_total = round($peso_ref * $prot_gkg); // g/día aprox.

// Calorías objetivo (aprox, sin datos de edad/sexo: base 30 kcal/kg)
$kcal_base = (int)round($peso_ref * 30);
$kcal_obj = $kcal_base + (($objetivo === 'subir peso') ? +300 : (($objetivo === 'bajar peso') ? -400 : 0));

// Macros (aprox)
if ($objetivo === 'bajar peso') {
  $p_pct=0.30; $c_pct=0.40; $g_pct=0.30;
} elseif ($objetivo === 'subir peso') {
  $p_pct=0.25; $c_pct=0.50; $g_pct=0.25;
} else {
  $p_pct=0.25; $c_pct=0.45; $g_pct=0.30;
}
$g_prot = (int)round(($kcal_obj * $p_pct) / 4);
$g_carb = (int)round(($kcal_obj * $c_pct) / 4);
$g_gras = (int)round(($kcal_obj * $g_pct) / 9);

// ===== Plan de dieta semanal dinámico =====
function dieta_base($goal){
  // 3 opciones por comida; se rotan según día
  $bajar = [
    'desayuno'=>[
      'Infusión sin azúcar + 2 tostadas integrales con queso untable light',
      'Yogur descremado + granola sin azúcar + fruta',
      'Mate/te sin azúcar + omelette de 2 claras + 1 yema + tomate'
    ],
    'almuerzo'=>[
      'Pechuga a la plancha + ensalada verde + 1 cda aceite de oliva',
      'Atún + mix de hojas + quinoa (~70g cocida)',
      'Pollo salteado + verduras al wok + arroz integral pequeño'
    ],
    'merienda'=>[
      'Infusión con leche descremada + 2 galletas de arroz',
      'Yogur + fruta',
      'Batido de agua + proteína (opcional) + 1 banana'
    ],
    'cena'=>[
      'Sopa de verduras + tortilla de espinaca al horno',
      'Filet de merluza + puré de calabaza',
      'Carne magra + ensalada variada + 1 cda aceite'
    ]
  ];
  $subir = [
    'desayuno'=>[
      'Tostadas integrales con palta y huevo + batido con leche + banana',
      'Avena cocida con leche + fruta + mantequilla de maní',
      'Yogur entero + granola + frutos secos'
    ],
    'almuerzo'=>[
      'Arroz integral + pollo al horno + ensalada + fruta',
      'Pasta + salsa de tomate + atún + aceite de oliva',
      'Wrap integral de pollo + queso + verduras'
    ],
    'merienda'=>[
      'Sandwich integral de jamón/queso + fruta',
      'Yogur + frutos secos',
      'Batido con leche + banana + avena'
    ],
    'cena'=>[
      'Pasta con atún y aceite de oliva + pan integral',
      'Tarta integral de verduras + ensalada',
      'Guiso magro con papa y verduras'
    ]
  ];
  $mant = [
    'desayuno'=>[
      'Infusión + 2 tostadas integrales con queso',
      'Avena con leche + fruta',
      'Omelette + pan integral'
    ],
    'almuerzo'=>[
      'Carne magra + ensalada + arroz integral pequeño',
      'Pollo al horno + papas + ensalada',
      'Merluza + puré + ensalada'
    ],
    'merienda'=>[
      'Yogur + fruta',
      'Infusión + 2 galletas de arroz',
      'Sandwich integral pequeño'
    ],
    'cena'=>[
      'Sopa + tortilla de verduras',
      'Salteado de pollo + verduras + quinoa',
      'Carne magra + ensalada + 1 cda aceite'
    ]
  ];
  return $goal==='subir peso' ? $subir : ($goal==='bajar peso' ? $bajar : $mant);
}

// Ajustes para personas con diabetes (carbohidratos de baja carga y sin azúcar)
function ajustar_diabetes($plan){
  foreach ($plan as $tiempo=>$opciones) {
    foreach ($opciones as $i=>$txt) {
      $txt = str_ireplace(['mermelada','azúcar','dulce','galletas'],
                          ['queso descremado','sin azúcar','fruta','galletas de arroz'], $txt);
      $txt = preg_replace('/(jugo|gaseosa)/i', 'agua/infusión sin azúcar', $txt);
      $plan[$tiempo][$i] = $txt;
    }
  }
  return $plan;
}

$base = dieta_base($objetivo);
if ($es_diabetico) $base = ajustar_diabetes($base);

$dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$plan_dieta = [];
for ($i=0;$i<7;$i++){
  $plan_dieta[$dias[$i]] = [
    'Desayuno' => $base['desayuno'][$i % count($base['desayuno'])],
    'Almuerzo' => $base['almuerzo'][$i % count($base['almuerzo'])],
    'Merienda' => $base['merienda'][$i % count($base['merienda'])],
    'Cena'     => $base['cena'][$i % count($base['cena'])],
  ];
}

// ===== Mensaje =====
$mensaje = "Hola {$nombre}. Tu IMC actual es {$imc} ({$cat}).";
$mensaje .= " Rango saludable aprox: {$peso_min}–{$peso_max} kg.";

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asistente IA — Plan Nutricional</title>
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
    select,button{ padding:8px 10px; border-radius:12px; border:1px solid var(--border); background:#1a1d24; color:var(--fg) }
    .btn{ background:var(--acc); color:#111; border:none; font-weight:800 }
    .msg{ background:#14161c; border:1px solid var(--border); padding:12px; border-radius:12px; margin-top:8px }
    .grid3{ display:grid; gap:8px; grid-template-columns:1fr; }
    @media (min-width:680px){ .grid3{ grid-template-columns: repeat(3,1fr); } }
    .kpi{ text-align:center; background:#1a1d24; border:1px solid var(--border); border-radius:12px; padding:10px }
    .kpi b{ display:block; font-size:18px; margin-top:4px }
    table{ width:100%; border-collapse:collapse; margin-top:10px; font-size:14px }
    th,td{ padding:10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:left }
    th{ color:var(--muted); font-weight:700; background:#0f1118 }
    .muted{ color:var(--muted) }
    .pill{ display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid var(--border); font-size:12px; margin-left:6px }
  </style>
</head>
<body>
<div class="container">
  <h2>🤖 Asistente Nutricional</h2>

  <div class="row">
    <section class="card">
      <form method="GET" class="flex">
        <label>Objetivo:</label>
        <select name="objetivo">
          <option value="bajar peso" <?= $objetivo==='bajar peso'?'selected':'' ?>>Bajar peso</option>
          <option value="mantener"   <?= $objetivo==='mantener'  ?'selected':'' ?>>Mantener</option>
          <option value="subir peso" <?= $objetivo==='subir peso'?'selected':'' ?>>Subir peso</option>
        </select>
        <button class="btn" type="submit">Actualizar plan</button>
        <?php if ($es_diabetico): ?><span class="pill">⚠️ Ajustes para diabetes</span><?php endif; ?>
      </form>

      <div class="msg">
        <?= nl2br(h($mensaje)) ?><br>
        <span class="muted">* Esto es orientación general. No reemplaza consejo profesional.</span>
      </div>

      <div class="grid3" style="margin-top:8px">
        <div class="kpi">Peso actual<b><?= num($peso_ref,1) ?> kg</b></div>
        <div class="kpi">Altura<b><?= num($altura_m*100,0) ?> cm</b></div>
        <div class="kpi">IMC<b><?= num($imc,1) ?> (<?= h($cat) ?>)</b></div>
      </div>

      <div class="grid3" style="margin-top:8px">
        <div class="kpi">Agua diaria<b><?= num($agua_l,1) ?> L</b></div>
        <div class="kpi">Proteínas objetivo<b><?= (int)$prot_total ?> g/día</b></div>
        <div class="kpi">Calorías objetivo<b><?= (int)$kcal_obj ?> kcal</b></div>
      </div>

      <div class="grid3" style="margin-top:8px">
        <div class="kpi">Proteínas<b><?= (int)$g_prot ?> g</b></div>
        <div class="kpi">Carbohidratos<b><?= (int)$g_carb ?> g</b></div>
        <div class="kpi">Grasas<b><?= (int)$g_gras ?> g</b></div>
      </div>
    </section>

    <aside class="card">
      <h3 style="margin:0 0 8px">📝 Última sesión</h3>
      <?php if ($ult): ?>
        <?php
          $fec = h($ult['fecha'] ?? '');
          $pa  = (float)($ult['peso_antes'] ?? 0);
          $pd  = (float)($ult['peso_despues'] ?? 0);
          $du  = (int)($ult['duracion_entrenamiento'] ?? 0);
          $esf = h((string)($ult['esfuerzo'] ?? ''));
          $cal = (int)($ult['calorias_estimadas'] ?? 0);
          $delta = $pd - $pa;
        ?>
        <p style="margin:0 0 8px"><strong>Fecha:</strong> <?= $fec ?></p>
        <p style="margin:0 0 8px"><strong>Peso:</strong> <?= num($pa,1) ?> → <?= num($pd,1) ?> kg (Δ <?= ($delta>=0?'+':'−').num(abs($delta),2) ?> kg)</p>
        <p style="margin:0 0 8px"><strong>Duración:</strong> <?= (int)$du ?> min · <strong>Esfuerzo:</strong> <?= $esf ?: '-' ?></p>
        <p style="margin:0"><strong>Calorías:</strong> <?= (int)$cal ?> kcal</p>
      <?php else: ?>
        <p class="muted" style="margin:0">Sin registros aún.</p>
      <?php endif; ?>

      <h3 style="margin:12px 0 6px">📆 Últimos 7 días</h3>
      <p style="margin:0">Sesiones: <strong><?= (int)($stats['sesiones'] ?? 0) ?></strong></p>
      <p style="margin:0">Minutos: <strong><?= (int)($stats['minutos'] ?? 0) ?></strong></p>
      <p style="margin:0">Kcal: <strong><?= (int)($stats['kcal'] ?? 0) ?></strong></p>
    </aside>
  </div>

  <section class="card" style="margin-top:12px">
    <h3 style="margin:0 0 8px">🍽️ Plan semanal (<?= h($objetivo) ?><?= $es_diabetico ? ', con ajustes para diabetes' : '' ?>)</h3>
    <table>
      <thead>
        <tr>
          <th>Día</th>
          <th>Desayuno</th>
          <th>Almuerzo</th>
          <th>Merienda</th>
          <th>Cena</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plan_dieta as $dia => $comidas): ?>
          <tr>
            <td><?= h($dia) ?></td>
            <td><?= h($comidas['Desayuno']) ?></td>
            <td><?= h($comidas['Almuerzo']) ?></td>
            <td><?= h($comidas['Merienda']) ?></td>
            <td><?= h($comidas['Cena']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-top:8px">
      Sugerencia general: priorizar alimentos frescos, ajustar por saciedad, intolerancias y preferencias personales. 
      Para condiciones médicas específicas, seguí indicaciones de tu profesional.
    </p>
  </section>
</div>
</body>
</html>
