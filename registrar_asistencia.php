<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/conexion.php';

date_default_timezone_set('America/Argentina/San_Luis');
$hoy         = date('Y-m-d');
$hora_actual = date('H:i:s');

$advertencia    = "";
$tipo_resultado = "";   // "ok" | "alerta"
$gimnasio_id    = (int)($_SESSION['gimnasio_id'] ?? 0);

// ===== Datos del gimnasio =====
$nombre_gimnasio = 'Gimnasio';
$logo_gimnasio   = 'logo.png';
if ($gimnasio_id > 0) {
    $res = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id = {$gimnasio_id} LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['nombre'])) $nombre_gimnasio = $row['nombre'];
        if (!empty($row['logo']))   $logo_gimnasio   = $row['logo'];
    }
}

// ===== Lógica principal empaquetada (profesores / clientes) =====
function procesar_codigo(mysqli $db, int $gymId, string $codigo, string $hoy, string $hora_actual): array {
    $mensaje = "";
    $tipo    = "ok"; // "ok" o "alerta"

    // ---- Profesor por DNI ----
    $prof_stmt = $db->prepare("SELECT id, apellido, nombre FROM profesores WHERE dni = ? AND gimnasio_id = ?");
    $prof_stmt->bind_param("si", $codigo, $gymId);
    $prof_stmt->execute();
    $prof = $prof_stmt->get_result()->fetch_assoc();
    $prof_stmt->close();

    if ($prof) {
        $prof_id     = (int)$prof['id'];
        $nombre_prof = trim(($prof['apellido'] ?? '') . ' ' . ($prof['nombre'] ?? ''));

        $q = $db->query("
            SELECT id, hora_entrada, hora_salida
            FROM asistencias_profesores
            WHERE profesor_id = {$prof_id}
              AND fecha = '{$db->real_escape_string($hoy)}'
              AND gimnasio_id = {$gymId}
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($q && $r = $q->fetch_assoc()) {
            if (empty($r['hora_salida'])) {
                $db->query("UPDATE asistencias_profesores
                            SET hora_salida = '{$db->real_escape_string($hora_actual)}'
                            WHERE id = {$r['id']} LIMIT 1");
                $mensaje = "✅ Salida registrada para {$nombre_prof} a las {$hora_actual}.";
            } else {
                $db->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_entrada, gimnasio_id, hora)
                            VALUES ({$prof_id}, '{$db->real_escape_string($hoy)}', '{$db->real_escape_string($hora_actual)}', {$gymId}, '{$db->real_escape_string($hora_actual)}')");
                $mensaje = "✅ Nuevo ingreso registrado para {$nombre_prof} a las {$hora_actual}.";
            }
        } else {
            $db->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_entrada, gimnasio_id, hora)
                        VALUES ({$prof_id}, '{$db->real_escape_string($hoy)}', '{$db->real_escape_string($hora_actual)}', {$gymId}, '{$db->real_escape_string($hora_actual)}')");
            $mensaje = "✅ Ingreso registrado para {$nombre_prof} a las {$hora_actual}.";
        }
        return [$mensaje, $tipo]; // ok
    }

    // ---- Cliente por DNI ----
    $stmt = $db->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $codigo, $gymId);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cliente) {
        $id_cliente = (int)$cliente['id'];

        $stmt2 = $db->prepare("SELECT clases_disponibles, fecha_vencimiento
                               FROM membresias
                               WHERE cliente_id = ? AND gimnasio_id = ?
                               ORDER BY fecha_vencimiento DESC
                               LIMIT 1");
        $stmt2->bind_param("ii", $id_cliente, $gymId);
        $stmt2->execute();
        $membresia = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if ($membresia) {
            $clases      = (int)$membresia['clases_disponibles'];
            $vencimiento = $membresia['fecha_vencimiento'];

            if ($clases > 0 && $vencimiento >= $hoy) {
                $db->query("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id)
                            VALUES ({$id_cliente}, '{$db->real_escape_string($hoy)}', '{$db->real_escape_string($hora_actual)}', {$gymId})");
                $db->query("UPDATE membresias
                            SET clases_disponibles = clases_disponibles - 1
                            WHERE cliente_id = {$id_cliente}
                              AND fecha_vencimiento = '{$db->real_escape_string($vencimiento)}'
                              AND gimnasio_id = {$gymId}
                            LIMIT 1");
                $mensaje = "✅ Asistencia registrada para cliente a las {$hora_actual}.";
                $tipo    = "ok";
            } else {
                $mensaje = "❌ ¡Membresía vencida o sin clases disponibles!";
                $tipo    = "alerta";
            }
        } else {
            $mensaje = "❌ ¡El cliente no tiene membresía registrada!";
            $tipo    = "alerta";
        }
    } else {
        $mensaje = "❌ ¡Cliente/Profesor no encontrado!";
        $tipo    = "alerta";
    }

    return [$mensaje, $tipo];
}

// ===== Respuesta AJAX =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo']) && isset($_GET['ajax'])) {
    $codigo = trim((string)$_POST['codigo']);
    header('Content-Type: application/json; charset=utf-8');

    if ($gimnasio_id <= 0 || $codigo === '') {
        echo json_encode(['ok' => false, 'mensaje' => '❌ Acceso denegado o código vacío.', 'tipo' => 'alerta', 'sonido' => true]);
        exit;
    }

    [$advertencia, $tipo_resultado] = procesar_codigo($conexion, $gimnasio_id, $codigo, $hoy, $hora_actual);
    echo json_encode([
        'ok'      => true,
        'mensaje' => $advertencia,
        'tipo'    => $tipo_resultado,                  // "ok" | "alerta"
        'sonido'  => ($tipo_resultado === 'alerta'),   // compat con boolean anterior
    ]);
    exit;
}

// ===== Flujo no-AJAX (primera carga o submit directo) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    $codigo = trim((string)$_POST['codigo']);
    if ($gimnasio_id > 0 && $codigo !== '') {
        [$advertencia, $tipo_resultado] = procesar_codigo($conexion, $gimnasio_id, $codigo, $hoy, $hora_actual);
    } else {
        $advertencia    = '❌ Acceso denegado o código vacío.';
        $tipo_resultado = 'alerta';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registro de Asistencia</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    :root { --bg:#111; --fg:#ffd700; --ok:#7CFC00; --err:#ff5757; --muted:#444; }
    * { box-sizing: border-box }
    body { margin:0; background:var(--bg); color:var(--fg); font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; }
    .contenedor { padding: 16px; max-width: 1100px; margin: 0 auto; }
    .encabezado { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .encabezado h1 { margin:0; font-size: clamp(18px, 4vw, 28px); letter-spacing:.5px; }
    .clock { font-size: clamp(12px, 2.5vw, 14px); opacity:.8 }

    .scan { margin: 14px 0; }
    .scan input[type="text"]{
      font-size: clamp(18px, 4.5vw, 22px);
      line-height: 1.2;
      padding: 14px 16px;
      width: 100%;
      border: 1px solid var(--muted);
      border-radius: 12px;
      outline: none;
      background:#000; color: var(--fg);
      min-height: 52px;
    }

    /* Tablas en contenedor scrollable para móvil */
    .table-wrap{
      background:#0e0e0e;
      border:1px solid #1f1f1f;
      border-radius: 12px;
      overflow: auto;                   /* clave en móvil */
      -webkit-overflow-scrolling: touch;
    }
    table{ width:100%; border-collapse: collapse; min-width: 520px; }
    thead th{ background:#1a1a1a; position: sticky; top: 0; z-index: 1; }
    table th, table td{
      border-bottom: 1px solid #1f1f1f;
      padding: clamp(8px, 2.2vw, 12px);
      text-align: center;
      font-size: clamp(13px, 3.3vw, 15px);
      white-space: nowrap;
    }

    .advertencia{ font-size: clamp(16px, 3.8vw, 18px); margin: 12px 0; }
    .advertencia.ok{ color: var(--ok); }
    .advertencia.err{ color: var(--err); }

    .row{ display:grid; grid-template-columns: 1fr; gap:16px; }
    @media (min-width: 900px){ .row{ grid-template-columns: 1fr 1fr; gap:24px; } }

    @media (max-width: 599px){
      .contenedor{ padding: 12px; }
      img[alt="logo"]{ height: 56px; }
    }
  </style>
  <script>
    let polling = null;

    function actualizarListados() {
      fetch('ajax_ingresos_profesores.php', {cache: 'no-store'})
        .then(res => res.text())
        .then(html => { const t = document.getElementById('tabla_profesores'); if (t) t.innerHTML = html; })
        .catch(()=>{});

      fetch('ajax_ingresos_clientes.php', {cache: 'no-store'})
        .then(res => res.text())
        .then(html => { const t = document.getElementById('tabla_clientes'); if (t) t.innerHTML = html; })
        .catch(()=>{});
    }

    function tickClock(){
      const el = document.getElementById('clock');
      if (!el) return;
      const now = new Date();
      const pad = n => String(n).padStart(2,'0');
      el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    }

    function focusInput(){
      const i = document.getElementById('codigo');
      if (i) i.focus({preventScroll:true});
    }

    function enviarCodigo(e){
      e.preventDefault();
      const inp = document.getElementById('codigo');
      const val = (inp.value || '').trim();
      if (!val) { focusInput(); return; }

      const fd = new FormData();
      fd.append('codigo', val);

      fetch('?ajax=1', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
          const adv = document.getElementById('adv');
          if (j && adv) {
            adv.textContent = j.mensaje || '';
            const clase = (j.tipo === 'alerta' || j.sonido) ? 'err' : 'ok';
            adv.className = 'advertencia ' + clase;
          }

          // Sonidos (ok y alerta). Requiere interacción previa (el submit cuenta).
          const okAudio     = document.getElementById('snd-ok');
          const alertaAudio = document.getElementById('snd-alerta');

          if (j && (j.tipo === 'ok') && okAudio) {
            okAudio.currentTime = 0;
            okAudio.play().catch(()=>{});
          }
          if (j && (j.tipo === 'alerta' || j.sonido) && alertaAudio) {
            alertaAudio.currentTime = 0;
            alertaAudio.play().catch(()=>{});
          }

          inp.value = '';
          focusInput();
          actualizarListados();
        })
        .catch(()=>{
          const adv = document.getElementById('adv');
          if (adv) {
            adv.textContent = '⚠️ Error enviando el código. Revisá la conexión.';
            adv.className = 'advertencia err';
          }
        });
    }

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        actualizarListados();
        focusInput();
      }
    });

    window.addEventListener('load', () => {
      tickClock();
      setInterval(tickClock, 1000);
      focusInput();

      actualizarListados();
      polling = setInterval(actualizarListados, 10000);

      const form = document.getElementById('form-scan');
      if (form) form.addEventListener('submit', enviarCodigo);

      // mantener foco para lector de barras
      document.addEventListener('click', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLInputElement) && !(t instanceof HTMLTextAreaElement)) {
          focusInput();
        }
      });
    });
  </script>
</head>
<body>
  <div class="contenedor">
    <div class="encabezado">
      <img src="<?= htmlspecialchars($logo_gimnasio) ?>" height="70" alt="logo">
      <div>
        <h1><?= strtoupper(htmlspecialchars($nombre_gimnasio)) ?></h1>
        <div class="clock">Hora: <span id="clock"></span></div>
      </div>
    </div>

    <form id="form-scan" class="scan" method="POST" action="">
      <input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="off" placeholder="Ingresar DNI..." autofocus>
    </form>

    <?php if ($advertencia): ?>
      <div id="adv" class="advertencia <?= ($tipo_resultado === 'alerta') ? 'err' : 'ok' ?>"><?= htmlspecialchars($advertencia) ?></div>
    <?php else: ?>
      <div id="adv" class="advertencia" style="min-height: 24px;"></div>
    <?php endif; ?>

    <!-- Sonidos -->
    <audio id="snd-ok" preload="auto">
      <source src="ok.mp3" type="audio/mpeg">
    </audio>
    <audio id="snd-alerta" preload="auto">
      <source src="alerta.mp3" type="audio/mpeg">
    </audio>

    <div class="row">
      <section>
        <h2>👨‍🏫 Profesores Hoy</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Apellido</th><th>Ingreso</th><th>Salida</th></tr></thead>
            <tbody id="tabla_profesores"></tbody>
          </table>
        </div>
      </section>

      <section>
        <h2>🏋️ Clientes Hoy</h2>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Apellido</th><th>Hora</th><th>Clases</th><th>Vencimiento</th></tr></thead>
            <tbody id="tabla_clientes"></tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</body>
</html>
