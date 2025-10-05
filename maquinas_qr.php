<?php
/* =============================================================================
   GYM QR — Máquinas con rutinas escaneables con NIVELES (Principiante/Medio/Avanzado)
   Responsive para móviles, tablets y PC
   Modo:
     - ADMIN automático si hay $_SESSION['gimnasio_id'] > 0
     - PÚBLICO con ?t={token}
   Requisitos:
     - PHP 7.4+ y MySQL/MariaDB
     - conexion.php que expone $conexion (mysqli)
   ========================================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

// ⚠️ Este include debe imprimir SOLO el <nav> del menú, NO un documento HTML completo.
// Si tu archivo actual es una página completa, muévelo a un snippet de menú.
@include __DIR__ . '/menu_horizontal.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  echo "❌ Sin conexión a BD.";
  exit;
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ================= Helpers ================ */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rnd_token($len=16){ return bin2hex(random_bytes((int)max(8, $len/2))); }
function base_url(): string {
  $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $path  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
  return $proto.'://'.$host.($path ? $path.'/' : '/');
}
function is_admin(): bool { return (int)($_SESSION['gimnasio_id'] ?? 0) > 0; }
function textarea_to_steps(string $txt): array {
  $arr = preg_split('/\R/u', $txt);
  $out = [];
  foreach ($arr as $line) { $line = trim($line); if ($line !== '') $out[] = $line; }
  return $out;
}
function steps_to_text(array $steps): string { return implode("\n", array_map(fn($v)=> (string)$v, $steps)); }
function normalize_levels(?array $byLevel, ?array $fallback): array {
  $fallback = is_array($fallback) && $fallback ? $fallback : ['Series x repeticiones', 'Descanso 60–90s', 'Registrar carga'];
  $L = ['principiante','medio','avanzado'];
  $out = [];
  foreach ($L as $k) {
    $v = $byLevel[$k] ?? null;
    if (!is_array($v) || empty($v)) $v = $fallback;
    $out[$k] = array_values(array_map('strval', $v));
  }
  return $out;
}

/* ================= Tablas (auto) ================ */
$conexion->query("
CREATE TABLE IF NOT EXISTS maquinas_gym(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gimnasio_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  ubicacion VARCHAR(120) DEFAULT NULL,
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
  pasos_json JSON NOT NULL,
  notas TEXT NULL,
  pasos_por_nivel_json JSON NULL,
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_maquina (maquina_id),
  CONSTRAINT fk_rm_maquina FOREIGN KEY (maquina_id) REFERENCES maquinas_gym(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
@$conexion->query("ALTER TABLE rutinas_maquina ADD COLUMN IF NOT EXISTS pasos_por_nivel_json JSON NULL");

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$admin = is_admin() && empty($_GET['t']); // admin por defecto si no hay token

/* ================= Vista pública (t=token) ================= */
if (isset($_GET['t']) && $_GET['t']!=='') {
  $t = $_GET['t'];
  $stmt = $conexion->prepare("
    SELECT m.id, m.nombre, m.ubicacion, r.titulo, r.pasos_json, r.pasos_por_nivel_json, r.notas
    FROM maquinas_gym m
    LEFT JOIN rutinas_maquina r ON r.maquina_id=m.id
    WHERE m.token=? LIMIT 1
  ");
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
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $packed = @inet_pton($ip);
  $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
  $stmt = $conexion->prepare("INSERT INTO qr_scans (maquina_id, ip, user_agent) VALUES (?,?,?)");
  $stmt->bind_param('iss', $data['id'], $packed, $ua);
  $stmt->execute(); $stmt->close();

  $pasos_general = json_decode($data['pasos_json'] ?? '[]', true);
  $por_nivel     = json_decode($data['pasos_por_nivel_json'] ?? 'null', true);
  $niveles       = normalize_levels(is_array($por_nivel) ? $por_nivel : null, is_array($pasos_general) ? $pasos_general : null);

  $title = $data['titulo'] ?: 'Rutina';
  $mname = $data['nombre'];
  $ubic  = $data['ubicacion'];

  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo h($mname).' — '.h($title); ?></title>
    <style>
      :root{
        --bg:#f8fafc; --card:#ffffff; --muted:#64748b; --ink:#0f172a; --brand:#0ea5e9; --brand2:#06b6d4;
        --radius:16px; --shadow:0 6px 22px rgba(0,0,0,.08);
        --space:clamp(14px, 2.5vw, 22px);
        --fs-h1:clamp(1.25rem, 2.5vw, 1.8rem);
        --fs-h2:clamp(1rem, 2vw, 1.2rem);
        --fs:clamp(0.96rem, 1.6vw, 1rem);
      }
      *{box-sizing:border-box}
      body{ margin:0; background:var(--bg); color:var(--ink); font:400 var(--fs)/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
      .wrap{ width:min(920px, 100% - 2*var(--space)); margin:0 auto; padding:var(--space) 0 var(--space) }
      .card{ background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); padding:clamp(14px,2vw,18px); margin-bottom:clamp(12px,2vw,16px); }
      h1{ font-size:var(--fs-h1); margin:0 0 6px }
      h2{ font-size:var(--fs-h2); margin:0 0 12px; color:#0f172a }
      .pill{ display:inline-block; background:linear-gradient(90deg,var(--brand),var(--brand2)); color:#fff; padding:4px 10px; border-radius:999px; font-size:.8rem; }
      .row{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
      select,button{ font:inherit }
      select{ padding:10px 12px; border-radius:12px; border:1px solid #cbd5e1; background:#fff; min-width:170px }
      .step{ display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px dashed #e5e7eb; }
      .step:last-child{ border-bottom:0 }
      .chk{ width:26px; height:26px; border:2px solid var(--brand); border-radius:8px; display:inline-block; position:relative; cursor:pointer; flex:0 0 26px; margin-top:2px }
      .chk.done::after{ content:""; position:absolute; inset:4px; background:var(--brand); border-radius:4px }
      .subl{ color:#475569 }
      .toolbar{ display:flex; gap:10px; flex-wrap:wrap }
      button{ background:var(--brand); color:#fff; border:0; padding:12px 16px; border-radius:12px; cursor:pointer; font-weight:700 }
      button.secondary{ background:#111 }
      .muted{ color:var(--muted) }
      @media (max-width: 520px){
        .toolbar button{ width:100% }
      }
      @media (min-width: 1100px){
        .wrap{ width:min(980px, 100% - 2*var(--space)); }
      }
    </style>
  </head>
  <body>
    <!-- MENU (si tu menu_horizontal.php imprime un <nav>, se verá arriba) -->

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

      <div class="muted" style="text-align:center">© <?php echo date('Y'); ?> Tu Gimnasio</div>
    </div>

    <script>
      // Datos desde PHP
      const niveles = <?php echo json_encode($niveles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const keyBase = 'qrsteps:'+location.pathname+location.search; // por token

      const stepsBox = document.getElementById('steps');
      const selNivel = document.getElementById('nivel');

      // Cargar selección previa
      const savedNivel = localStorage.getItem(keyBase+':nivel') || 'principiante';
      if (['principiante','medio','avanzado'].includes(savedNivel)) selNivel.value = savedNivel;

      function render(){
        const nivel = selNivel.value;
        const pasos = niveles[nivel] || [];
        stepsBox.innerHTML = '';
        let n=1;
        pasos.forEach((p, i)=>{
          const row = document.createElement('div');
          row.className = 'step';
          row.dataset.i = n;
          const box = document.createElement('span');
          box.className = 'chk'; box.setAttribute('role','checkbox'); box.tabIndex = 0;

          // persistencia por nivel
          const keyNivel = keyBase+':'+nivel;
          const saved = JSON.parse(localStorage.getItem(keyNivel) || '[]');
          if (saved.includes(i)) box.classList.add('done');

          const t = document.createElement('div');
          t.innerHTML = '<div><strong>Paso '+(n++)+':</strong> '+String(p)+'</div>';

          box.addEventListener('click', ()=>{
            box.classList.toggle('done');
            saveNivel(nivel);
          });
          box.addEventListener('keydown', (ev)=>{
            if (ev.key===' '||ev.key==='Enter'){ ev.preventDefault(); box.click(); }
          });

          row.appendChild(box);
          row.appendChild(t);
          stepsBox.appendChild(row);
        });
      }

      function saveNivel(nivel){
        const keyNivel = keyBase+':'+nivel;
        const idx = [];
        document.querySelectorAll('.step .chk').forEach((el, i)=>{
          if (el.classList.contains('done')) idx.push(i);
        });
        localStorage.setItem(keyNivel, JSON.stringify(idx));
      }

      selNivel.addEventListener('change', ()=>{
        localStorage.setItem(keyBase+':nivel', selNivel.value);
        render();
      });

      document.getElementById('btnReset').onclick = ()=>{
        document.querySelectorAll('.step .chk').forEach(el=>el.classList.remove('done'));
        saveNivel(selNivel.value);
      };
      document.getElementById('btnShare').onclick = async ()=>{
        try{ await navigator.share({title:document.title, url:location.href}); }
        catch(e){ navigator.clipboard.writeText(location.href); alert('Enlace copiado al portapapeles'); }
      };

      render();
    </script>
  </body>
  </html>
  <?php
  exit;
}

/* ================= Solo ADMIN ================= */
if (!$admin) {
  http_response_code(403);
  echo "<div style='max-width:720px;margin:20px auto;font-family:system-ui,sans-serif'>
          <h2>Acceso restringido</h2>
          <p>Ingresá al panel del gimnasio para administrar máquinas y rutinas.</p>
        </div>";
  exit;
}

/* ====== POST: Crear/editar/eliminar ====== */
if ($_SERVER['REQUEST_METHOD']==='POST') {

  // Crear/editar máquina + rutina
  if (isset($_POST['save_machine'])) {
    $maquina_id = (int)($_POST['maquina_id'] ?? 0);
    $nombre     = trim($_POST['nombre'] ?? '');
    $ubicacion  = trim($_POST['ubicacion'] ?? '');
    $titulo     = trim($_POST['titulo'] ?? '');
    $pasos_raw  = trim($_POST['pasos'] ?? ''); // general (compatibilidad)
    $notas      = trim($_POST['notas'] ?? '');

    // Nuevos: por nivel
    $p_prin_raw = trim($_POST['pasos_principiante'] ?? '');
    $p_medio_raw= trim($_POST['pasos_medio'] ?? '');
    $p_avz_raw  = trim($_POST['pasos_avanzado'] ?? '');

    if ($gimnasio_id <= 0) { $_SESSION['flash'] = '❌ Sesión inválida.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }
    if ($nombre === '' || $titulo === '') {
      $_SESSION['flash'] = '⚠️ Completá Nombre de máquina y Título de la rutina.';
      header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }

    $steps_general = textarea_to_steps($pasos_raw);
    if (empty($steps_general)) $steps_general = ['Series x repeticiones', 'Descanso 60–90s', 'Registrar carga'];

    $byLevel = [
      'principiante' => textarea_to_steps($p_prin_raw),
      'medio'        => textarea_to_steps($p_medio_raw),
      'avanzado'     => textarea_to_steps($p_avz_raw),
    ];
    $byLevel = normalize_levels($byLevel, $steps_general);

    $json_general = json_encode($steps_general, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $json_levels  = json_encode($byLevel,       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($maquina_id > 0) {
      // Verificar pertenencia al gimnasio
      $chk = $conexion->prepare("SELECT id FROM maquinas_gym WHERE id=? AND gimnasio_id=?");
      $chk->bind_param('ii', $maquina_id, $gimnasio_id);
      $chk->execute(); $has = $chk->get_result()->fetch_assoc(); $chk->close();
      if (!$has) { $_SESSION['flash'] = '❌ Máquina no pertenece a tu gimnasio.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }

      // Update máquina
      $stmt = $conexion->prepare("UPDATE maquinas_gym SET nombre=?, ubicacion=? WHERE id=?");
      $stmt->bind_param('ssi', $nombre, $ubicacion, $maquina_id);
      $stmt->execute(); $stmt->close();

      // Upsert rutina con niveles
      $stmt = $conexion->prepare("UPDATE rutinas_maquina SET titulo=?, pasos_json=?, notas=?, pasos_por_nivel_json=? WHERE maquina_id=?");
      $stmt->bind_param('ssssi', $titulo, $json_general, $notas, $json_levels, $maquina_id);
      $stmt->execute();
      if ($stmt->affected_rows===0) {
        $stmt->close();
        $stmt = $conexion->prepare("INSERT INTO rutinas_maquina (maquina_id,titulo,pasos_json,notas,pasos_por_nivel_json) VALUES (?,?,?,?,?)");
        $stmt->bind_param('issss', $maquina_id, $titulo, $json_general, $notas, $json_levels);
        $stmt->execute();
      }
      $stmt->close();
      $_SESSION['flash'] = '✅ Máquina y rutina (con niveles) actualizadas.';
    } else {
      // Insert máquina (con token)
      $token = rnd_token(16);
      $stmt = $conexion->prepare("INSERT INTO maquinas_gym (gimnasio_id,nombre,ubicacion,token) VALUES (?,?,?,?)");
      $stmt->bind_param('isss', $gimnasio_id, $nombre, $ubicacion, $token);
      $stmt->execute();
      $new_id = $stmt->insert_id;
      $stmt->close();

      // Insert rutina con niveles
      $stmt = $conexion->prepare("INSERT INTO rutinas_maquina (maquina_id,titulo,pasos_json,notas,pasos_por_nivel_json) VALUES (?,?,?,?,?)");
      $stmt->bind_param('issss', $new_id, $titulo, $json_general, $notas, $json_levels);
      $stmt->execute(); $stmt->close();

      $_SESSION['flash'] = '🆕 Máquina y rutina (con niveles) creadas.';
    }

    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // Regenerar token
  if (isset($_POST['regen_token'], $_POST['maquina_id'])) {
    $mid = (int)$_POST['maquina_id'];
    $chk = $conexion->prepare("SELECT id FROM maquinas_gym WHERE id=? AND gimnasio_id=?");
    $chk->bind_param('ii', $mid, $gimnasio_id);
    $chk->execute(); $has = $chk->get_result()->fetch_assoc(); $chk->close();
    if ($has) {
      $token = rnd_token(16);
      $stmt = $conexion->prepare("UPDATE maquinas_gym SET token=? WHERE id=?");
      $stmt->bind_param('si', $token, $mid);
      $stmt->execute(); $stmt->close();
      $_SESSION['flash'] = '♻️ Token regenerado. Imprimí el nuevo QR.';
    } else {
      $_SESSION['flash'] = '❌ Máquina no pertenece a tu gimnasio.';
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // Eliminar máquina
  if (isset($_POST['delete_machine'], $_POST['maquina_id'])) {
    $mid = (int)$_POST['maquina_id'];
    $stmt = $conexion->prepare("DELETE FROM maquinas_gym WHERE id=? AND gimnasio_id=?");
    $stmt->bind_param('ii', $mid, $gimnasio_id);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    $_SESSION['flash'] = $ok ? '🗑️ Máquina eliminada.' : '❌ No se pudo eliminar (¿pertenece a otro gimnasio?).';
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
}

/* ================= Vista ADMIN ================= */

// Obtener máquina a editar (opcional, validada por gimnasio)
$edit_id = (int)($_GET['edit'] ?? 0);
$edit = null; $edit_r = null;

if ($edit_id>0) {
  $stmt = $conexion->prepare("SELECT * FROM maquinas_gym WHERE id=? AND gimnasio_id=?");
  $stmt->bind_param('ii', $edit_id, $gimnasio_id);
  $stmt->execute();
  $edit = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($edit) {
    $stmt = $conexion->prepare("SELECT * FROM rutinas_maquina WHERE maquina_id=?");
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
$public_base = base_url().'maquinas_qr.php?t=';

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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>QR de máquinas — Admin</title>
  <style>
    :root{
      --bg:#0b1220; --card:#0f172a; --muted:#94a3b8; --line:#1f2937; --ink:#e5e7eb; --acc:#22d3ee;
      --radius:16px; --space:clamp(14px, 2vw, 24px);
      --fs:clamp(.95rem, 1.4vw, 1rem); --fs-h:clamp(1.1rem, 2.2vw, 1.5rem);
    }
    *{box-sizing:border-box}
    body{ margin:0; font:400 var(--fs)/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--ink); }
    /* MENU (si tu menú imprime un <nav>, se verá arriba) */

    .wrap{ width:min(1200px, 100% - 2*var(--space)); margin:0 auto; padding:var(--space) 0 var(--space); }
    h1{ font-size:var(--fs-h); margin:0 0 8px }
    .grid{ display:grid; grid-template-columns: 1fr; gap:16px; }
    @media (min-width: 1024px){ .grid{ grid-template-columns: 1.2fr 1fr; gap:20px; } }
    .card{ background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:clamp(14px, 2vw, 18px); }
    input, textarea{ width:100%; padding:12px; border-radius:12px; border:1px solid #334155; background:#0b1220; color:var(--ink); }
    label{ font-size:.92rem; color:#cbd5e1; display:block; margin-bottom:6px }
    .row{ display:grid; grid-template-columns:1fr; gap:10px }
    @media (min-width: 720px){ .row{ grid-template-columns:1fr 1fr; } }
    button{ background:var(--acc); border:0; color:#111; font-weight:800; padding:12px 16px; border-radius:12px; cursor:pointer; }
    .danger{ background:#ef4444; color:#fff; } .warn{ background:#f59e0b; color:#111; }
    .muted{ color:var(--muted); font-size:.95rem; }
    .qr{ background:#fff; padding:6px; border-radius:8px; }
    .badge{ display:inline-block; background:#1f2937; padding:4px 8px; border-radius:999px; font-size:.85rem; }
    .actions{ display:flex; gap:8px; flex-wrap:wrap; }

    /* Tabla → tarjetas en mobile */
    .table-wrap{ width:100%; overflow:hidden; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ text-align:left; padding:10px; border-bottom:1px solid var(--line); vertical-align:top }
    th{ color:#94a3b8; font-weight:600; }
    .small{ font-size:.88rem }

    @media (max-width: 700px){
      table, thead, tbody, th, td, tr{ display:block; }
      thead{ display:none; }
      tbody tr{ border:1px solid var(--line); border-radius:12px; padding:10px; margin-bottom:12px; background:#0e1830; }
      tbody td{ border:0; padding:6px 0; }
      tbody td[data-label]::before{
        content: attr(data-label) ": "; color:#94a3b8; font-weight:600; display:inline-block; min-width:110px;
      }
      .qr{ width:100px; height:100px; }
      .actions{ justify-content:flex-start }
    }

    .lvlgrid{ display:grid; grid-template-columns:1fr; gap:10px }
    @media (min-width: 1000px){ .lvlgrid{ grid-template-columns:1fr 1fr 1fr; } }
    .subtle{ color:#94a3b8; font-size:.85rem; margin-bottom:6px }
  </style>
</head>
<body>
  <!-- MENU -->

  <div class="wrap">
    <h1>QR de máquinas — Admin</h1>
    <p class="muted">Creá máquinas, definí sus rutinas por nivel y descargá los QR para imprimir.</p>
    <?php if ($flash): ?>
      <div class="card" style="border:1px solid var(--acc); margin-bottom:12px"><?php echo h($flash); ?></div>
    <?php endif; ?>

    <div class="grid">
      <div class="card">
        <h2 style="margin-top:0"><?php echo $edit ? 'Editar máquina' : 'Nueva máquina'; ?></h2>
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

          <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap">
            <button name="save_machine" value="1"><?php echo $edit ? 'Guardar cambios' : 'Crear máquina + rutina'; ?></button>
            <?php if ($edit): ?>
              <form method="post" onsubmit="return confirm('Esto invalida el QR anterior. ¿Continuar?')" style="display:inline">
                <input type="hidden" name="maquina_id" value="<?php echo (int)$edit['id']; ?>">
                <button class="warn" name="regen_token" value="1">Regenerar token (nuevo QR)</button>
              </form>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="card">
        <h2 style="margin-top:0">Máquinas</h2>
        <?php if (empty($rows)): ?>
          <p class="muted">Todavía no cargaste máquinas.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table aria-label="Listado de máquinas">
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
                    <td data-label="#"><?php echo (int)$r['id']; ?></td>
                    <td data-label="Máquina">
                      <strong><?php echo h($r['nombre']); ?></strong>
                      <?php if ($r['ubicacion']): ?><div class="small muted"><?php echo h($r['ubicacion']); ?></div><?php endif; ?>
                      <div class="small muted">Creada: <?php echo h($r['creada_en']); ?></div>
                    </td>
                    <td data-label="QR / Enlace">
                      <div class="actions">
                        <a class="link" href="<?php echo h($url); ?>" target="_blank" rel="noopener">Ver pública</a>
                        <a class="link" href="<?php echo h($qr); ?>" target="_blank" download="QR_<?php echo (int)$r['id']; ?>.png" rel="noopener">Descargar QR</a>
                      </div>
                      <div class="center" style="margin-top:6px">
                        <img class="qr" src="<?php echo h($qr); ?>" alt="QR" width="120" height="120" loading="lazy">
                      </div>
                      <div class="small muted" style="word-break:break-all"><?php echo h($url); ?></div>
                    </td>
                    <td data-label="Scans"><span class="badge"><?php echo (int)$r['scans']; ?></span></td>
                    <td data-label="Acciones">
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
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <h3 style="margin:0 0 8px">Consejos de impresión</h3>
      <ul style="margin:0 0 4px 18px">
        <li>Imprimí el PNG del QR en alta (500×500) y plastificalo en la máquina.</li>
        <li>Si cambiás la rutina seguido, mantené el mismo QR y solo actualizá los pasos.</li>
        <li>Si un QR se filtra, regenerá el token para invalidarlo.</li>
      </ul>
    </div>
  </div>
</body>
</html>
