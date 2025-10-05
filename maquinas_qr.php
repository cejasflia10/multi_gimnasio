<?php
/* =============================================================================
   maquinas_qr.php — Máquinas con QR y rutinas por nivel (Admin + Público + Historial)
   - Público por token (?t=TOKEN) sin menú ni login.
   - Admin con $_SESSION['gimnasio_id'] > 0 (con menú).
   - Si el CLIENTE está logueado, podrá guardar su HISTORIAL del día.
   ========================================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ==== Dominio público para armar enlaces de QR ==== */
const APP_PUBLIC_ORIGIN = 'https://multi-gimnasio-51bq.onrender.com';
function public_base(): string { return rtrim(APP_PUBLIC_ORIGIN, '/') . '/maquinas_qr.php?t='; }

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rnd_token($len=16){ return bin2hex(random_bytes((int)max(8, $len/2))); }
function is_admin(): bool { return (int)($_SESSION['gimnasio_id'] ?? 0) > 0; }
function textarea_to_steps(string $txt): array {
  $arr = preg_split('/\R/u', $txt); $out=[];
  foreach ($arr as $line) { $line = trim($line); if ($line!=='') $out[] = $line; }
  return $out;
}
function steps_to_text(array $steps): string { return implode("\n", array_map(fn($v)=>(string)$v, $steps)); }
function normalize_levels(?array $byLevel, ?array $fallback): array {
  $fallback = is_array($fallback)&&$fallback ? $fallback : ['Series x repeticiones','Descanso 60–90s','Registrar carga'];
  $L = ['principiante','medio','avanzado']; $out=[];
  foreach($L as $k){
    $v = $byLevel[$k] ?? null; if (!is_array($v) || !$v) $v=$fallback;
    $out[$k] = array_values(array_map('strval',$v));
  }
  return $out;
}
function db_prepare(mysqli $cx, string $sql): mysqli_stmt {
  $stmt = $cx->prepare($sql);
  if (!$stmt) {
    http_response_code(500);
    echo "<pre>SQL PREPARE ERROR\nErrno: ".$cx->errno."\nError: ".$cx->error."\nSQL:\n".$sql."</pre>";
    exit;
  }
  return $stmt;
}

/* ================= Tablas base ================= */
$conexion->query("
CREATE TABLE IF NOT EXISTS maquinas_gym(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gimnasio_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  ubicacion VARCHAR(120) NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (gimnasio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conexion->query("
CREATE TABLE IF NOT EXISTS rutinas_maquina(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  maquina_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  pasos_json LONGTEXT NOT NULL,
  notas TEXT NULL,
  pasos_por_nivel_json LONGTEXT NULL,
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_maquina (maquina_id),
  CONSTRAINT fk_rm_maquina FOREIGN KEY (maquina_id) REFERENCES maquinas_gym(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conexion->query("
CREATE TABLE IF NOT EXISTS qr_scans(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  maquina_id INT UNSIGNED NOT NULL,
  ip VARBINARY(16) NULL,
  user_agent VARCHAR(255) NULL,
  escaneado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_scan_maquina FOREIGN KEY (maquina_id) REFERENCES maquinas_gym(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ====== NUEVO: historial del cliente ====== */
$conexion->query("
CREATE TABLE IF NOT EXISTS rutina_logs(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gimnasio_id INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  maquina_id INT UNSIGNED NOT NULL,
  nivel ENUM('principiante','medio','avanzado') NOT NULL,
  pasos_hechos_json LONGTEXT NOT NULL,      -- array de índices de pasos chequeados
  rpe TINYINT UNSIGNED NULL,                -- esfuerzo percibido 1–10
  tiempo_min SMALLINT UNSIGNED NULL,        -- minutos totales
  notas_cliente TEXT NULL,                  -- observaciones del cliente
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (gimnasio_id, cliente_id, creada_en),
  INDEX (maquina_id, creada_en),
  CONSTRAINT fk_rl_maquina FOREIGN KEY (maquina_id) REFERENCES maquinas_gym(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conexion->query("
CREATE TABLE IF NOT EXISTS rutina_log_comentarios(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_id BIGINT UNSIGNED NOT NULL,
  profesor_id INT UNSIGNED NULL,
  comentario TEXT NOT NULL,
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (log_id, creada_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ================= Modo actual ================= */
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$__is_public = isset($_GET['t']) && $_GET['t']!=='';
$__is_admin  = is_admin() && !$__is_public;

/* ================= Vista pública (t=token) — SIN MENÚ ================= */
if ($__is_public) {
  $t = (string)$_GET['t'];

  // Si el cliente envía historial (POST)
  if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_log']) && $cliente_id>0) {
    $nivel = $_POST['nivel'] ?? 'principiante';
    if (!in_array($nivel, ['principiante','medio','avanzado'], true)) $nivel='principiante';
    $pasos = json_encode((array)json_decode($_POST['pasos_idx'] ?? '[]', true), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $rpe   = (int)($_POST['rpe'] ?? 0); if ($rpe<1 || $rpe>10) $rpe = null;
    $tmin  = (int)($_POST['tiempo_min'] ?? 0); if ($tmin<=0) $tmin=null;
    $notas = trim($_POST['notas_cliente'] ?? '');

    // recuperar maquina + gimnasio por token
    $stmt = db_prepare($conexion, "SELECT id, gimnasio_id FROM maquinas_gym WHERE token=? LIMIT 1");
    $stmt->bind_param('s', $t); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();

    if ($row) {
      $mid = (int)$row['id']; $gim = (int)$row['gimnasio_id'];
      $stmt = db_prepare($conexion, "
        INSERT INTO rutina_logs (gimnasio_id, cliente_id, maquina_id, nivel, pasos_hechos_json, rpe, tiempo_min, notas_cliente)
        VALUES (?,?,?,?,?,?,?,?)
      ");
      $stmt->bind_param('iiissiis', $gim, $cliente_id, $mid, $nivel, $pasos, $rpe, $tmin, $notas);
      $stmt->execute(); $stmt->close();
      $_SESSION['flash_pub'] = '✅ Historial guardado.';
    } else {
      $_SESSION['flash_pub'] = '❌ No se pudo asociar la máquina.';
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // Cargar datos de la máquina + rutina
  $sql = "
    SELECT m.id, m.gimnasio_id, m.nombre, m.ubicacion, r.titulo, r.pasos_json, r.pasos_por_nivel_json, r.notas
    FROM maquinas_gym m
    LEFT JOIN rutinas_maquina r ON r.maquina_id=m.id
    WHERE m.token=? LIMIT 1
  ";
  $stmt = db_prepare($conexion, $sql);
  $stmt->bind_param('s', $t);
  $stmt->execute();
  $res = $stmt->get_result();
  $data = $res->fetch_assoc();
  $stmt->close();

  if (!$data) {
    http_response_code(404);
    echo "<div style='max-width:720px;margin:20px auto;font-family:system-ui,sans-serif'>
            <h2>❌ QR inválido o máquina no encontrada</h2>
            <p>Consultá al gimnasio.</p>
          </div>";
    exit;
  }

  // Log de escaneo
  $packed = @inet_pton($_SERVER['REMOTE_ADDR'] ?? '');
  $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
  $stmt = db_prepare($conexion, "INSERT INTO qr_scans (maquina_id, ip, user_agent) VALUES (?,?,?)");
  $stmt->bind_param('iss', $data['id'], $packed, $ua);
  $stmt->execute(); $stmt->close();

  $pasos_general = json_decode($data['pasos_json'] ?? '[]', true); if (!is_array($pasos_general)) $pasos_general=[];
  $por_nivel     = json_decode($data['pasos_por_nivel_json'] ?? 'null', true);
  $niveles       = normalize_levels(is_array($por_nivel)?$por_nivel:null, $pasos_general);

  $title = $data['titulo'] ?: 'Rutina';
  $mname = $data['nombre'];
  $ubic  = $data['ubicacion'];
  $flash_pub = $_SESSION['flash_pub'] ?? null; unset($_SESSION['flash_pub']);

  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo h($mname).' — '.h($title); ?></title>
    <style>
      :root { --a:#0ea5e9; --b:#06b6d4; }
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color:#111; margin:0; background:#f8fafc; }
      .wrap { max-width: 720px; margin: 0 auto; padding: 20px; }
      .card { background:white; border-radius:16px; box-shadow:0 6px 22px rgba(0,0,0,.08); padding:18px; margin-bottom:16px; }
      h1 { font-size: 1.6rem; margin: 0 0 6px; }
      h2 { font-size: 1.1rem; margin: 0 0 12px; color:#0f172a; }
      .pill { display:inline-block; background:linear-gradient(90deg,var(--a),var(--b)); color:#fff; padding:4px 10px; border-radius:999px; font-size:.8rem; }
      .row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
      select, input, textarea { padding:8px 10px; border-radius:10px; border:1px solid #cbd5e1; background:#fff; }
      .step { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px dashed #e5e7eb; }
      .step:last-child{ border-bottom:0; }
      .chk { width:22px; height:22px; border:2px solid #0ea5e9; border-radius:6px; display:inline-block; position:relative; cursor:pointer; flex:0 0 22px; margin-top:2px; }
      .chk.done::after{ content:""; position:absolute; inset:3px; background:#0ea5e9; border-radius:3px; }
      .subl { color:#475569; font-size:.95rem; }
      .toolbar { display:flex; gap:8px; flex-wrap:wrap; }
      button { background:#0ea5e9; color:#fff; border:0; padding:10px 14px; border-radius:10px; cursor:pointer; font-weight:600; }
      button.secondary { background:#111; }
      .muted { color:#64748b; font-size:.9rem; }
      .ok { color:#16a34a }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="card">
        <div class="pill">Máquina</div>
        <h1><?php echo h($mname); ?></h1>
        <?php if ($ubic): ?><div class="muted">Ubicación: <?php echo h($ubic); ?></div><?php endif; ?>
      </div>

      <div class="card">
        <div class="row" style="justify-content:space-between">
          <h2 style="margin:0"><?php echo h($title); ?></h2>
          <div class="row">
            <label for="nivel" class="subl">Nivel:</label>
            <select id="nivel">
              <option value="principiante">Principiante</option>
              <option value="medio">Medio</option>
              <option value="avanzado">Avanzado</option>
            </select>
          </div>
        </div>
        <div id="steps"></div>

        <?php if (trim((string)($data['notas'] ?? ''))!==''): ?>
          <div class="card" style="background:#f1f5f9;margin-top:12px">
            <h2>Notas</h2>
            <div class="subl"><?php echo nl2br(h($data['notas'])); ?></div>
          </div>
        <?php endif; ?>

        <div class="toolbar" style="margin-top:10px">
          <button id="btnReset">Reiniciar</button>
          <button class="secondary" id="btnShare">Compartir</button>
        </div>
        <div class="muted" style="margin-top:8px">Elegí tu nivel y marcá cada paso a medida que avanzás.</div>
      </div>

      <?php if ($cliente_id > 0): ?>
      <div class="card">
        <h2 style="margin:0 0 8px">Guardar historial del día</h2>
        <?php if ($flash_pub): ?><div class="ok" style="margin:6px 0 8px"><?php echo h($flash_pub); ?></div><?php endif; ?>
        <form method="post" id="logForm">
          <input type="hidden" name="save_log" value="1">
          <input type="hidden" name="pasos_idx" id="pasos_idx">
          <div class="row">
            <div>
              <label class="subl">Nivel utilizado:</label><br>
              <select name="nivel" id="nivelForm">
                <option value="principiante">Principiante</option>
                <option value="medio">Medio</option>
                <option value="avanzado">Avanzado</option>
              </select>
            </div>
            <div>
              <label class="subl">RPE (1–10):</label><br>
              <input type="number" name="rpe" min="1" max="10" placeholder="7">
            </div>
            <div>
              <label class="subl">Tiempo (min):</label><br>
              <input type="number" name="tiempo_min" min="1" max="300" placeholder="25">
            </div>
          </div>
          <div style="margin-top:10px">
            <label class="subl">Notas (opcional):</label><br>
            <textarea name="notas_cliente" rows="3" placeholder="Cómo te sentiste, cargas usadas, molestias, etc."></textarea>
          </div>
          <div style="margin-top:12px">
            <button type="submit">Guardar historial</button>
          </div>
        </form>
        <div class="muted" style="margin-top:6px">Este registro queda visible para tu profesor.</div>
      </div>
      <?php endif; ?>

      <div class="muted" style="text-align:center">© <?php echo date('Y'); ?> Tu Gimnasio</div>
    </div>

    <script>
      const niveles = <?php echo json_encode(normalize_levels(json_decode($data['pasos_por_nivel_json'] ?? 'null', true), json_decode($data['pasos_json'] ?? '[]', true)), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
      const keyBase = 'qrsteps:'+location.pathname+location.search;

      const stepsBox = document.getElementById('steps');
      const selNivel = document.getElementById('nivel');
      const savedNivel = localStorage.getItem(keyBase+':nivel') || 'principiante';
      if (['principiante','medio','avanzado'].includes(savedNivel)) selNivel.value = savedNivel;

      function render(){
        const nivel = selNivel.value, pasos = niveles[nivel] || [];
        stepsBox.innerHTML = ''; let n=1;
        pasos.forEach((p, i)=>{
          const row=document.createElement('div'); row.className='step';
          const box=document.createElement('span'); box.className='chk'; box.setAttribute('role','checkbox'); box.tabIndex=0; box.dataset.idx=i;

          const keyNivel=keyBase+':'+nivel; const saved=JSON.parse(localStorage.getItem(keyNivel)||'[]');
          if (saved.includes(i)) box.classList.add('done');

          const t=document.createElement('div'); t.innerHTML='<div><strong>Paso '+(n++)+':</strong> '+String(p)+'</div>';
          box.addEventListener('click', ()=>{ box.classList.toggle('done'); saveNivel(nivel); });
          box.addEventListener('keydown', ev=>{ if (ev.key===' '||ev.key==='Enter'){ ev.preventDefault(); box.click(); } });

          row.appendChild(box); row.appendChild(t); stepsBox.appendChild(row);
        });
        syncFormNivel();
      }
      function saveNivel(nivel){
        const keyNivel=keyBase+':'+nivel, idx=[];
        document.querySelectorAll('.step .chk').forEach((el,i)=>{ if (el.classList.contains('done')) idx.push(i); });
        localStorage.setItem(keyNivel, JSON.stringify(idx));
        syncFormNivel();
      }
      function syncFormNivel(){
        const form = document.getElementById('logForm'); if (!form) return;
        const nivel = selNivel.value;
        const idx=[];
        document.querySelectorAll('.step .chk').forEach((el,i)=>{ if (el.classList.contains('done')) idx.push(i); });
        document.getElementById('pasos_idx').value = JSON.stringify(idx);
        document.getElementById('nivelForm').value = nivel;
      }
      selNivel.addEventListener('change', ()=>{ localStorage.setItem(keyBase+':nivel', selNivel.value); render(); });
      document.getElementById('btnReset').onclick = ()=>{ document.querySelectorAll('.step .chk').forEach(el=>el.classList.remove('done')); saveNivel(selNivel.value); };
      document.getElementById('btnShare').onclick = async ()=>{ try{ await navigator.share({title:document.title, url:location.href}); } catch(e){ navigator.clipboard.writeText(location.href); alert('Enlace copiado'); } };
      render();
    </script>
  </body>
  </html>
  <?php
  exit;
}

/* ================= Solo ADMIN desde acá ================= */
if (!$__is_admin) {
  http_response_code(403);
  echo "<div style='max-width:720px;margin:20px auto;font-family:system-ui,sans-serif'>
          <h2>Acceso restringido</h2>
          <p>Ingresá al panel del gimnasio para administrar máquinas y rutinas.</p>
        </div>";
  exit;
}

/* A PARTIR DE AQUÍ SÍ SE INCLUYE EL MENÚ DEL SISTEMA */
@include __DIR__ . '/menu_horizontal.php';

/* ====== POST: Crear/editar/eliminar ====== */
if ($_SERVER['REQUEST_METHOD']==='POST') {

  if (isset($_POST['save_machine'])) {
    $maquina_id = (int)($_POST['maquina_id'] ?? 0);
    $nombre     = trim($_POST['nombre'] ?? '');
    $ubicacion  = trim($_POST['ubicacion'] ?? '');
    $titulo     = trim($_POST['titulo'] ?? '');
    $pasos_raw  = trim($_POST['pasos'] ?? '');
    $notas      = trim($_POST['notas'] ?? '');

    $p_prin_raw = trim($_POST['pasos_principiante'] ?? '');
    $p_medio_raw= trim($_POST['pasos_medio'] ?? '');
    $p_avz_raw  = trim($_POST['pasos_avanzado'] ?? '');

    if ($gimnasio_id <= 0) { $_SESSION['flash'] = '❌ Sesión inválida.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }
    if ($nombre === '' || $titulo === '') { $_SESSION['flash'] = '⚠️ Completá Nombre de máquina y Título.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }

    $steps_general = textarea_to_steps($pasos_raw);
    if (!$steps_general) $steps_general = ['Series x repeticiones','Descanso 60–90s','Registrar carga'];
    $byLevel = normalize_levels([
      'principiante'=>textarea_to_steps($p_prin_raw),
      'medio'       =>textarea_to_steps($p_medio_raw),
      'avanzado'    =>textarea_to_steps($p_avz_raw),
    ], $steps_general);

    $json_general = json_encode($steps_general, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $json_levels  = json_encode($byLevel,       JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

    if ($maquina_id > 0) {
      $chk = db_prepare($conexion, "SELECT id FROM maquinas_gym WHERE id=? AND gimnasio_id=?");
      $chk->bind_param('ii', $maquina_id, $gimnasio_id); $chk->execute();
      $has = $chk->get_result()->fetch_assoc(); $chk->close();
      if (!$has) { $_SESSION['flash']='❌ Máquina no pertenece a tu gimnasio.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }

      $stmt = db_prepare($conexion, "UPDATE maquinas_gym SET nombre=?, ubicacion=? WHERE id=?");
      $stmt->bind_param('ssi', $nombre, $ubicacion, $maquina_id); $stmt->execute(); $stmt->close();

      $stmt = db_prepare($conexion, "UPDATE rutinas_maquina SET titulo=?, pasos_json=?, notas=?, pasos_por_nivel_json=? WHERE maquina_id=?");
      $stmt->bind_param('ssssi', $titulo, $json_general, $notas, $json_levels, $maquina_id);
      $stmt->execute();
      if ($stmt->affected_rows===0) {
        $stmt->close();
        $stmt = db_prepare($conexion, "INSERT INTO rutinas_maquina (maquina_id,titulo,pasos_json,notas,pasos_por_nivel_json) VALUES (?,?,?,?,?)");
        $stmt->bind_param('issss', $maquina_id, $titulo, $json_general, $notas, $json_levels);
        $stmt->execute();
      }
      $stmt->close();
      $_SESSION['flash'] = '✅ Máquina y rutina (con niveles) actualizadas.';
    } else {
      $token = rnd_token(16);
      $stmt = db_prepare($conexion, "INSERT INTO maquinas_gym (gimnasio_id,nombre,ubicacion,token) VALUES (?,?,?,?)");
      $stmt->bind_param('isss', $gimnasio_id, $nombre, $ubicacion, $token);
      $stmt->execute(); $new_id = $stmt->insert_id; $stmt->close();

      $stmt = db_prepare($conexion, "INSERT INTO rutinas_maquina (maquina_id,titulo,pasos_json,notas,pasos_por_nivel_json) VALUES (?,?,?,?,?)");
      $stmt->bind_param('issss', $new_id, $titulo, $json_general, $notas, $json_levels);
      $stmt->execute(); $stmt->close();

      $_SESSION['flash'] = '🆕 Máquina y rutina (con niveles) creadas.';
    }

    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  if (isset($_POST['regen_token'], $_POST['maquina_id'])) {
    $mid = (int)$_POST['maquina_id'];
    $chk = db_prepare($conexion, "SELECT id FROM maquinas_gym WHERE id=? AND gimnasio_id=?");
    $chk->bind_param('ii', $mid, $gimnasio_id); $chk->execute();
    $has = $chk->get_result()->fetch_assoc(); $chk->close();

    if ($has) {
      $token = rnd_token(16);
      $stmt = db_prepare($conexion, "UPDATE maquinas_gym SET token=? WHERE id=?");
      $stmt->bind_param('si', $token, $mid); $stmt->execute(); $stmt->close();
      $_SESSION['flash'] = '♻️ Token regenerado. Imprimí el nuevo QR.';
    } else {
      $_SESSION['flash'] = '❌ Máquina no pertenece a tu gimnasio.';
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  if (isset($_POST['delete_machine'], $_POST['maquina_id'])) {
    $mid = (int)$_POST['maquina_id'];
    $stmt = db_prepare($conexion, "DELETE FROM maquinas_gym WHERE id=? AND gimnasio_id=?");
    $stmt->bind_param('ii', $mid, $gimnasio_id); $stmt->execute();
    $ok = $stmt->affected_rows > 0; $stmt->close();
    $_SESSION['flash'] = $ok ? '🗑️ Máquina eliminada.' : '❌ No se pudo eliminar.';
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
}

/* ================= Vista ADMIN ================= */

// Obtener máquina a editar (opcional, validada por gimnasio)
$edit_id = (int)($_GET['edit'] ?? 0);
$edit = null; $edit_r = null;

if ($edit_id>0) {
  $stmt = db_prepare($conexion, "SELECT * FROM maquinas_gym WHERE id=? AND gimnasio_id=?");
  $stmt->bind_param('ii', $edit_id, $gimnasio_id);
  $stmt->execute();
  $edit = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($edit) {
    $stmt = db_prepare($conexion, "SELECT * FROM rutinas_maquina WHERE maquina_id=?");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $edit_r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

// Prepara valores para el form de niveles
$pasos_general_form = '';
$niveles_form = ['principiante'=>[],'medio'=>[],'avanzado'=>[]];
if ($edit_r) {
  $pasos_general_form = steps_to_text(json_decode($edit_r['pasos_json'] ?? '[]', true) ?: []);
  $por_nivel = json_decode($edit_r['pasos_por_nivel_json'] ?? 'null', true);
  $niveles_form = normalize_levels(is_array($por_nivel)?$por_nivel:null, json_decode($edit_r['pasos_json'] ?? '[]', true) ?: []);
}
$public_base = public_base();

// Listado (solo del gimnasio en sesión)
$where = "WHERE gimnasio_id=".$gimnasio_id;
$res = $conexion->query("SELECT m.*, (SELECT COUNT(*) FROM qr_scans s WHERE s.maquina_id=m.id) scans FROM maquinas_gym m $where ORDER BY m.id DESC");
$rows = [];
while($r = $res->fetch_assoc()) $rows[] = $r;

// Flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>QR de máquinas — Admin</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:#0b1220; color:#e5e7eb; margin:0; }
    .wrap { max-width: 1080px; margin: 0 auto; padding: 24px; }
    .grid { display:grid; grid-template-columns: 1.2fr 1fr; gap:20px; }
    .card { background:#0f172a; border:1px solid #1f2937; border-radius:16px; padding:16px; }
    input, textarea { width:100%; padding:10px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#e5e7eb; }
    label { font-size:.9rem; color:#cbd5e1; }
    .row { display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    button { background:#22d3ee; border:0; color:#111; font-weight:800; padding:10px 14px; border-radius:10px; cursor:pointer; }
    table{ width:100%; border-collapse: collapse; }
    th, td{ text-align:left; padding:10px; border-bottom:1px solid #1f2937; }
    th{ color:#94a3b8; font-weight:600; }
    .muted{ color:#94a3b8; font-size:.9rem; }
    .qr { background:#fff; padding:6px; border-radius:8px; }
    .danger{ background:#ef4444; color:#fff; }
    .warn{ background:#f59e0b; color:#111; }
    .badge{ display:inline-block; background:#1f2937; padding:4px 8px; border-radius:999px; font-size:.8rem; }
    .actions{ display:flex; gap:6px; flex-wrap:wrap; }
    .center{ text-align:center; }
    .small{ font-size:.85rem; }
    .link{ color:#60a5fa; text-decoration:none; }
    .link:hover{text-decoration:underline;}
    .lvlgrid{ display:grid; grid-template-columns: 1fr; gap:10px; }
    @media (min-width: 900px){ .lvlgrid{ grid-template-columns: 1fr 1fr 1fr; } }
    .subtle{ color:#94a3b8; font-size:.85rem; margin-bottom:6px }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>QR de máquinas — Admin</h1>
    <p class="muted">Creá máquinas, definí sus rutinas por nivel y descargá los QR para imprimir.</p>
    <?php if ($flash): ?>
      <div class="card" style="border:1px solid #22d3ee; margin-bottom:12px"><?php echo h($flash); ?></div>
    <?php endif; ?>

    <div class="grid">
      <div class="card">
        <h2><?php echo $edit ? 'Editar máquina' : 'Nueva máquina'; ?></h2>
        <form method="post">
          <?php if ($edit): ?>
            <input type="hidden" name="maquina_id" value="<?php echo (int)$edit['id']; ?>">
          <?php endif; ?>
          <div class="row">
            <div>
              <label>Nombre de máquina</label>
              <input name="nombre" required value="<?php echo h($edit['nombre'] ?? ''); ?>">
            </div>
            <div>
              <label>Ubicación (opcional)</label>
              <input name="ubicacion" value="<?php echo h($edit['ubicacion'] ?? ''); ?>">
            </div>
          </div>
          <div style="margin-top:10px">
            <label>Título de la rutina</label>
            <input name="titulo" required value="<?php echo h($edit_r['titulo'] ?? 'Rutina sugerida'); ?>">
          </div>

          <div style="margin-top:16px">
            <div class="subtle">Podés cargar un listado general y/o por nivel. Si un nivel queda vacío, hereda del general.</div>
            <label>Pasos GENERALES (uno por línea)</label>
            <textarea name="pasos" rows="6" placeholder="Ej: 4x12 prensa 45°&#10;3x10 sentadilla en hack&#10;Descanso 60–90s"><?php
              echo h($pasos_general_form);
            ?></textarea>
          </div>

          <div style="margin-top:16px">
            <label>Pasos por nivel</label>
            <div class="lvlgrid" style="margin-top:8px">
              <div>
                <div class="subtle">Principiante</div>
                <textarea name="pasos_principiante" rows="8" placeholder="Ej: 3x12 con carga baja&#10;Descanso 90s"><?php
                  echo h(steps_to_text($niveles_form['principiante'] ?? []));
                ?></textarea>
              </div>
              <div>
                <div class="subtle">Medio</div>
                <textarea name="pasos_medio" rows="8" placeholder="Ej: 4x10 carga moderada&#10;Descanso 60–90s"><?php
                  echo h(steps_to_text($niveles_form['medio'] ?? []));
                ?></textarea>
              </div>
              <div>
                <div class="subtle">Avanzado</div>
                <textarea name="pasos_avanzado" rows="8" placeholder="Ej: 5x8 carga alta + técnica&#10;Descanso 60s"><?php
                  echo h(steps_to_text($niveles_form['avanzado'] ?? []));
                ?></textarea>
              </div>
            </div>
          </div>

          <div style="margin-top:10px">
            <label>Notas (opcional)</label>
            <textarea name="notas" rows="3" placeholder="Aclaraciones, técnica, respiración, etc."><?php echo h($edit_r['notas'] ?? ''); ?></textarea>
          </div>

          <div style="margin-top:12px">
            <button name="save_machine" value="1"><?php echo $edit ? 'Guardar cambios' : 'Crear máquina + rutina'; ?></button>
          </div>
        </form>

        <?php if ($edit): ?>
        <form method="post" onsubmit="return confirm('Esto invalida el QR anterior. ¿Continuar?')">
          <input type="hidden" name="maquina_id" value="<?php echo (int)$edit['id']; ?>">
          <button class="warn" name="regen_token" value="1" style="margin-top:8px">Regenerar token (nuevo QR)</button>
        </form>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Máquinas</h2>
        <?php if (empty($rows)): ?>
          <p class="muted">Todavía no cargaste máquinas.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Máquina</th>
                <th>QR / Enlace</th>
                <th>Scans</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r):
                $url = $public_base . urlencode($r['token']);
                $qr  = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data='.rawurlencode($url);
              ?>
                <tr>
                  <td><?php echo (int)$r['id']; ?></td>
                  <td>
                    <strong><?php echo h($r['nombre']); ?></strong>
                    <?php if ($r['ubicacion']): ?><div class="small muted"><?php echo h($r['ubicacion']); ?></div><?php endif; ?>
                    <div class="small muted">Creada: <?php echo h($r['creada_en']); ?></div>
                  </td>
                  <td>
                    <div class="actions">
                      <a class="link" href="<?php echo h($url); ?>" target="_blank">Ver pública</a>
                      <a class="link" href="<?php echo h($qr); ?>" target="_blank" download="QR_<?php echo (int)$r['id']; ?>.png">Descargar QR</a>
                    </div>
                    <div class="center" style="margin-top:6px">
                      <img class="qr" src="<?php echo h($qr); ?>" alt="QR" width="120" height="120" loading="lazy">
                    </div>
                    <div class="small muted"><?php echo h($url); ?></div>
                  </td>
                  <td><span class="badge"><?php echo (int)$r['scans']; ?></span></td>
                  <td>
                    <div class="actions">
                      <a class="link" href="?edit=<?php echo (int)$r['id']; ?>">Editar</a>
                      <form method="post" onsubmit="return confirm('¿Eliminar máquina y su rutina?')">
                        <input type="hidden" name="maquina_id" value="<?php echo (int)$r['id']; ?>">
                        <button class="danger" name="delete_machine" value="1">Eliminar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <h3>Consejos de impresión</h3>
      <ul>
        <li>Imprimí el PNG del QR en alta (500×500) y plastificalo en la máquina.</li>
        <li>Si cambiás la rutina seguido, mantené el mismo QR y solo actualizá los pasos.</li>
        <li>Si un QR se filtra, regenerá el token para invalidarlo.</li>
      </ul>
    </div>
  </div>
</body>
</html>
