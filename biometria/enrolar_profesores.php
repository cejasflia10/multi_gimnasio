<?php
// /biometria/enrolar_profesores.php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../conexion.php';

// Validaciones básicas
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
  http_response_code(403);
  echo "Gimnasio no definido en la sesión.";
  exit;
}

// Traer profesores + estado de huella
$sql = "
  SELECT p.id,
         CONCAT(COALESCE(p.nombre,''), ' ', COALESCE(p.apellido,'')) AS nombre,
         CASE WHEN h.id IS NULL THEN 0 ELSE 1 END AS tiene_huella
  FROM profesores p
  LEFT JOIN huellas h
    ON h.persona_tipo='profesor' AND h.persona_id=p.id AND h.gimnasio_id=?
  ORDER BY nombre ASC
";
$stmt = $conexion->prepare($sql);
$stmt->bind_param('i', $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();

$profes = [];
while ($row = $res->fetch_assoc()) $profes[] = $row;

// (opcional) si protegiste la API con header X-API-KEY
$API_KEY = getenv('API_KEY_BIOMETRIA') ?: '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Enrolar huella - Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Estilos mínimos (sin dependencias) -->
  <style>
    :root{
      --bg:#000; --fg:gold; --pri:#a00; --ok:#1e9e49; --err:#d33; --muted:#777; --card:#111; --line:#222;
    }
    body{margin:0;background:var(--bg);color:var(--fg);font-family:Arial,sans-serif;}
    .wrap{max-width:1100px;margin:0 auto;padding:16px;}
    h1{margin:8px 0 12px 0;font-size:22px}
    .note{background:#0a0a0a;border:1px solid var(--line);padding:10px;border-radius:6px;margin-bottom:12px;color:#ddd}
    .table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:8px;overflow:hidden}
    .table th,.table td{padding:10px;border-bottom:1px solid var(--line);text-align:left}
    .table th{background:#0f0f0f;font-weight:bold}
    .badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
    .badge-ok{background:var(--ok);color:#fff}
    .badge-no{background:var(--err);color:#fff}
    .btn{background:var(--pri);color:gold;border:none;border-radius:6px;padding:8px 10px;cursor:pointer;font-weight:bold}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .row-actions{display:flex;align-items:center;gap:8px}
    .muted{color:var(--muted);font-size:12px}
    .pill{display:inline-block;padding:2px 8px;border:1px solid var(--line);border-radius:999px;font-size:12px}
    .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
    .status{display:flex;gap:8px;align-items:center}
    .spinner{display:inline-block;width:14px;height:14px;border:2px solid var(--fg);border-top-color:transparent;border-radius:50%;animation:spin .8s linear infinite;vertical-align:-2px}
    .hide{display:none}
    @keyframes spin{to{transform:rotate(360deg)}}
    a.link{color:gold}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <h1>🖐️ Enrolar huella (profesores)</h1>
      <div class="status">
        <span class="pill">Gimnasio ID: <?= (int)$gimnasio_id ?></span>
        <span class="pill">Servicio local: <strong id="svc-status">sin comprobar</strong></span>
        <button class="btn" id="btn-probar">Probar lector</button>
      </div>
    </div>

    <div class="note">
      Conectá el <strong>ZK4500</strong> a esta PC. Esta pantalla llama al servicio local en
      <code>http://127.0.0.1:5177/enroll</code> para capturar la huella y luego la guarda en tu servidor.
      <div class="muted">Tip: si falla, asegurate de tener corriendo la app local del lector.</div>
    </div>

    <?php if (!count($profes)): ?>
      <p>No hay profesores cargados aún.</p>
    <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Profesor</th>
          <th style="width:140px">Huella</th>
          <th style="width:260px">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($profes as $p): ?>
          <tr id="row-<?= (int)$p['id'] ?>">
            <td><?= (int)$p['id'] ?></td>
            <td><?= htmlspecialchars($p['nombre'] ?: 'Sin nombre') ?></td>
            <td class="cell-estado">
              <?php if ($p['tiene_huella']): ?>
                <span class="badge badge-ok">Cargada</span>
              <?php else: ?>
                <span class="badge badge-no">No cargada</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button class="btn" onclick="enrolar(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($p['nombre']) ?>')">
                  Capturar / Actualizar
                </button>
                <span class="spinner hide" id="sp-<?= (int)$p['id'] ?>"></span>
                <span class="muted hide" id="ok-<?= (int)$p['id'] ?>">✔ Guardado</span>
                <span class="muted hide" id="er-<?= (int)$p['id'] ?>"></span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <p class="muted" style="margin-top:10px">
      ¿No ves esta página en el menú? Agregá un ítem que linkee a
      <code>/biometria/enrolar_profesores.php</code>.
    </p>
  </div>

<script>
const GIMNASIO_ID = <?= (int)$gimnasio_id ?>;
const API_KEY = <?= json_encode($API_KEY) ?>;
const LOCAL_ENROLL_URL = 'http://127.0.0.1:5177/enroll?repeats=3';
const SERVER_ENROLL_URL = '/api/biometria/enrolar.php';

// Probar lector (solo comprueba si responde el servicio local)
document.getElementById('btn-probar').addEventListener('click', async () => {
  const badge = document.getElementById('svc-status');
  badge.textContent = 'verificando...';
  try {
    const r = await fetch(LOCAL_ENROLL_URL, { method:'GET' });
    badge.textContent = r.ok ? 'OK (responde)' : 'sin respuesta';
  } catch (e) {
    badge.textContent = 'sin respuesta';
  }
});

async function enrolar(profesorId, nombre){
  const sp = document.getElementById('sp-'+profesorId);
  const ok = document.getElementById('ok-'+profesorId);
  const er = document.getElementById('er-'+profesorId);
  const rowEstado = document.querySelector('#row-'+profesorId+' .cell-estado');

  ok.classList.add('hide');
  er.classList.add('hide');
  er.textContent = '';
  sp.classList.remove('hide');

  try {
    // 1) Captura local con el ZK4500
    const localResp = await fetch(LOCAL_ENROLL_URL, { method:'GET' });
    if (!localResp.ok) throw new Error('No responde el servicio local del lector.');
    const localJson = await localResp.json();
    if (!localJson.ok || !localJson.template_b64) {
      throw new Error(localJson.error || 'No se pudo capturar la huella (reintente).');
    }

    // 2) Enviar al servidor para guardar la huella
    const body = {
      persona_tipo: 'profesor',
      persona_id: profesorId,
      gimnasio_id: GIMNASIO_ID,
      template_b64: localJson.template_b64,
      version: localJson.version || 'ZKFinger10'
    };

    const headers = { 'Content-Type':'application/json' };
    if (API_KEY) headers['X-API-KEY'] = API_KEY;

    const srv = await fetch(SERVER_ENROLL_URL, {
      method:'POST',
      headers,
      body: JSON.stringify(body)
    });
    const data = await srv.json();
    if (!srv.ok || !data.ok) {
      throw new Error(data.error || 'Error guardando huella en servidor.');
    }

    // 3) Feedback UI
    ok.textContent = '✔ Guardado';
    ok.classList.remove('hide');
    if (rowEstado) rowEstado.innerHTML = '<span class="badge badge-ok">Cargada</span>';

  } catch (e) {
    er.textContent = '✖ ' + (e.message || 'Error inesperado');
    er.classList.remove('hide');
    console.error(e);
  } finally {
    sp.classList.add('hide');
  }
}
</script>
</body>
</html>
