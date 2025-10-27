<?php
/* ==========================================================
   accesos_gimnasio.php — Panel de accesos (lectura)
   • Lista accesos_gimnasio del rango elegido.
   • Muestra nombre del cliente, método, plan y clases.
   • Encabezado con logo/nombre del gym + keepalive.
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function qv($db,$s){ return "'".$db->real_escape_string((string)$s)."'"; }
function mem_is_activa_row(array $m): bool {
  $ok_estado = true;
  if (array_key_exists('activa',$m) && $m['activa']!==null) $ok_estado = ((string)$m['activa']==='1');
  $ok_vto = true;
  if (!empty($m['fecha_vencimiento']) && $m['fecha_vencimiento']!=='0000-00-00') $ok_vto = ($m['fecha_vencimiento'] >= date('Y-m-d'));
  return $ok_estado && $ok_vto;
}
function logo_url(?string $logo): ?string {
  $logo = trim((string)$logo);
  if ($logo==='') return null;
  if (preg_match('#^(https?:)?//#i', $logo) || str_starts_with($logo,'data:')) return $logo;
  $cands = [
    __DIR__ . '/uploads/gimnasios/' . $logo => '/uploads/gimnasios/' . rawurlencode($logo),
    __DIR__ . '/img/' . $logo              => '/img/' . rawurlencode($logo),
    __DIR__ . '/' . ltrim($logo,'/\\')     => '/' . ltrim($logo,'/\\'),
  ];
  foreach ($cands as $fs=>$url) if (is_file($fs)) return $url.'?v='.(int)@filemtime($fs);
  return $logo;
}

/* ==== Inputs ==== */
$gimnasio_id = (int)($_GET['g'] ?? ($_SESSION['gimnasio_id'] ?? 0));
if ($gimnasio_id<=0){ http_response_code(400); exit('Falta g'); }
$hoy   = date('Y-m-d');
$desde = $_GET['desde'] ?? $hoy;
$hasta = $_GET['hasta'] ?? $hoy;

/* ==== Marca gimnasio ==== */
$gym_name = 'Gimnasio'; $gym_logo = null;
if ($rs = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1")){
  if ($rs->num_rows){ $g=$rs->fetch_assoc();
    if (!empty($g['nombre'])) $gym_name = $g['nombre'];
    if (!empty($g['logo']))   $gym_logo = logo_url($g['logo']);
  }
}

/* ==== Accesos del rango ==== */
$sql = "SELECT a.id, a.fecha_ingreso, a.metodo, a.cliente_id,
               c.nombre, c.apellido
        FROM accesos_gimnasio a
        JOIN clientes c ON c.id=a.cliente_id
        WHERE a.gimnasio_id={$gimnasio_id}
          AND DATE(a.fecha_ingreso) BETWEEN ".qv($conexion,$desde)." AND ".qv($conexion,$hasta)."
        ORDER BY a.fecha_ingreso DESC";
$rs = $conexion->query($sql);
$accesos=[]; if ($rs) while($r=$rs->fetch_assoc()){ $accesos[]=$r; }

/* ==== Membresía activa más reciente por cliente para este gym ==== */
$cli_ids = array_unique(array_map(fn($x)=>(int)$x['cliente_id'],$accesos));
$mapMem = [];
if ($cli_ids){
  $ids = implode(',', $cli_ids);
  $q = "SELECT id, cliente_id, gimnasio_id, plan, clases_disponibles, activa, fecha_vencimiento
        FROM membresias
        WHERE gimnasio_id={$gimnasio_id} AND cliente_id IN ({$ids})
        ORDER BY COALESCE(fecha_vencimiento,'9999-12-31') DESC, id DESC";
  $rm = $conexion->query($q);
  if ($rm) while($m=$rm->fetch_assoc()){
    if (!isset($mapMem[(int)$m['cliente_id']]) && mem_is_activa_row($m)) {
      $mapMem[(int)$m['cliente_id']] = $m;
    }
  }
}

/* ==== ¿Consumo aplicado? (membresia_consumos) ==== */
foreach ($accesos as &$A){
  $A['mem'] = $mapMem[(int)$A['cliente_id']] ?? null;
  $rlog = $conexion->query("SELECT id FROM membresia_consumos WHERE acceso_id=".(int)$A['id']." LIMIT 1");
  $A['consumo_aplicado'] = ($rlog && $rlog->num_rows)?1:0;
}
unset($A);

/* ==== UI ==== */
$css = "
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#0f0f10;color:#e6e6e6}
.wrap{max-width:1200px;margin:22px auto;padding:0 16px}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.brand img{width:44px;height:44px;object-fit:cover;border-radius:10px;background:#fff;border:1px solid #2a2a2a}
.brand .name{font-weight:900;font-size:22px;letter-spacing:.2px}
.subtitle{color:#9aa0a6;font-size:13px;margin-top:-2px}
h1{font-size:18px;margin:8px 0 12px}
.filters{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:12px}
input,button{padding:8px 10px;border-radius:10px;border:1px solid #2a2a2a;background:#151515;color:#e6e6e6}
button{background:#7fb3ff;color:#000;border:0;font-weight:700;cursor:pointer}
table{width:100%;border-collapse:separate;border-spacing:0 8px}
th,td{text-align:left;padding:10px 12px}
thead th{color:#b9c0c8;font-size:13px}
tr{background:#171717;border:1px solid #222}
.badge{display:inline-block;padding:3px 8px;border-radius:9999px;font-size:12px}
.ok{background:#14b86a33;color:#88ffbf;border:1px solid #1f9b62}
.warn{background:#b88f1433;color:#ffe88a;border:1px solid #9b7d1f}
.danger{background:#b8141433;color:#ff9e9e;border:1px solid #9b1f1f}
.actions{display:flex;gap:6px}
small{color:#aaa}
footer{margin:12px 0;color:#7a7f87;font-size:12px;display:flex;gap:8px;align-items:center}
footer .dot{width:6px;height:6px;border-radius:9999px;background:#3b82f6;display:inline-block}
";

/* ===== Salida ===== */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Accesos · <?= h($gym_name) ?> (#<?= (int)$gimnasio_id ?>)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style><?= $css ?></style>
<meta http-equiv="refresh" content="10">
<script>
// KeepAlive cada 4 min para que no se corte la sesión
function keepAlive(){
  fetch('keepalive.php', {credentials:'same-origin', cache:'no-store'}).catch(()=>{});
}
setInterval(keepAlive, 4*60*1000);
document.addEventListener('visibilitychange', ()=>{ if(!document.hidden) keepAlive(); });
window.addEventListener('load', keepAlive);
</script>
</head>
<body>
  <div class="wrap">
    <div class="brand">
      <?php if ($gym_logo): ?><img src="<?= h($gym_logo) ?>" alt="Logo <?= h($gym_name) ?>" loading="lazy" decoding="async"><?php endif; ?>
      <div>
        <div class="name"><?= h($gym_name) ?></div>
        <div class="subtitle">Panel de Accesos · Gimnasio #<?= (int)$gimnasio_id ?></div>
      </div>
    </div>

    <form class="filters" method="get">
      <input type="hidden" name="g" value="<?= (int)$gimnasio_id ?>">
      <div><label>Desde</label><br><input type="date" name="desde" value="<?= h($desde) ?>"></div>
      <div><label>Hasta</label><br><input type="date" name="hasta" value="<?= h($hasta) ?>"></div>
      <div><button type="submit">Filtrar</button></div>
      <div><a href="?g=<?= (int)$gimnasio_id ?>&desde=<?= h($hoy) ?>&hasta=<?= h($hoy) ?>"><button type="button">Hoy</button></a></div>
    </form>

    <table>
      <thead>
        <tr>
          <th>Hora</th><th>Cliente</th><th>Método</th><th>Plan</th><th>Clases disp.</th><th>Consumo</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$accesos): ?>
        <tr><td colspan="7"><small>Sin accesos en el rango.</small></td></tr>
      <?php else: foreach($accesos as $row):
        $m = $row['mem'];
        $disp = is_null($m)? null : (int)$m['clases_disponibles'];
        $badge = is_null($m)? 'warn' : ($disp>0?'ok':'danger'); ?>
        <tr>
          <td><?= h(date('H:i', strtotime($row['fecha_ingreso']))) ?></td>
          <td><?= h($row['apellido'].' '.$row['nombre']) ?></td>
          <td><span class="badge"><?= h($row['metodo']) ?></span></td>
          <td>
            <?php if ($m): ?>
              <span class="badge ok"><?= h($m['plan'] ?? 'Plan') ?></span>
              <?php if (!empty($m['fecha_vencimiento']) && $m['fecha_vencimiento']!=='0000-00-00'): ?>
                <small>vto <?= h($m['fecha_vencimiento']) ?></small>
              <?php endif; ?>
            <?php else: ?>
              <span class="badge danger">Sin activa</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($m): ?>
              <span class="badge <?= $badge ?>">Disponibles: <?= (int)$disp ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <?php if ($row['consumo_aplicado']): ?>
              <span class="badge ok">Aplicado</span>
            <?php else: ?>
              <span class="badge warn">Pendiente</span>
            <?php endif; ?>
          </td>
          <td class="actions">
            <?php if ($m): ?>
              <?php if (!$row['consumo_aplicado']): ?>
                <form action="consumo_toggle.php" method="post" style="display:inline">
                  <input type="hidden" name="g" value="<?= (int)$gimnasio_id ?>">
                  <input type="hidden" name="accion" value="aplicar">
                  <input type="hidden" name="acceso_id" value="<?= (int)$row['id'] ?>">
                  <button>Aplicar consumo</button>
                </form>
              <?php else: ?>
                <form action="consumo_toggle.php" method="post" style="display:inline" onsubmit="return confirm('¿Deshacer consumo de esta asistencia?');">
                  <input type="hidden" name="g" value="<?= (int)$gimnasio_id ?>">
                  <input type="hidden" name="accion" value="deshacer">
                  <input type="hidden" name="acceso_id" value="<?= (int)$row['id'] ?>">
                  <button>Deshacer</button>
                </form>
              <?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>

    <footer>
      <span class="dot" title="KeepAlive activo"></span>
      <span>La sesión se mantiene activa mientras esta ventana permanezca abierta.</span>
    </footer>
  </div>
</body>
</html>
