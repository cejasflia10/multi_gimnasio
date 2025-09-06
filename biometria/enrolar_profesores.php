<?php
// /biometria/enrolar_profesores.php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../conexion.php';

/* ===== Conexión ===== */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helper ===== */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ===== Validación ===== */
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) {
  http_response_code(403);
  echo "Gimnasio no definido en la sesión.";
  exit;
}

/* ===== Datos ===== */
$sql = "
  SELECT 
    p.id,
    TRIM(CONCAT(COALESCE(p.nombre,''), ' ', COALESCE(p.apellido,''))) AS nombre,
    CASE WHEN h.id IS NULL THEN 0 ELSE 1 END AS tiene_huella
  FROM profesores p
  LEFT JOIN huellas h
    ON h.persona_tipo='profesor' AND h.persona_id=p.id AND h.gimnasio_id=?
  WHERE p.gimnasio_id = ?
  ORDER BY nombre ASC, p.id ASC
";
$stmt = $conexion->prepare($sql);
if (!$stmt) { http_response_code(500); exit('❌ Error preparando consulta de profesores.'); }
$stmt->bind_param('ii', $gimnasio_id, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();
$profes = [];
if ($res) { while ($row = $res->fetch_assoc()) $profes[] = $row; }
$stmt->close();

/* ===== API KEY opcional ===== */
$API_KEY = getenv('API_KEY_BIOMETRIA') ?: '';

/* ===== Base del proyecto ===== */
$scriptName = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir  = rtrim(dirname($scriptName), '/');          // /multi_gimnasio/biometria
$app_base   = rtrim(dirname($scriptDir), '/');           // /multi_gimnasio
if ($app_base === '.' || $app_base === '/') { $app_base = ''; }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Enrolar huella - Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root{
      --bg:#000; --fg:gold; --pri:#a00; --ok:#1e9e49; --err:#d33; --muted:#bbb; --card:#111; --line:#222;
      --overlay:rgba(0,0,0,.72); --chip:#1b1b1b; --bar:#2a2a2a;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--fg);font-family:Arial,Helvetica,sans-serif;}
    .wrap{max-width:1100px;margin:0 auto;padding:16px;}
    h1{margin:8px 0 12px 0;font-size:22px}
    .note{background:#0a0a0a;border:1px solid var(--line);padding:10px;border-radius:6px;margin-bottom:12px;color:#ddd}
    .table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:8px;overflow:hidden}
    .table th,.table td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}
    .table th{background:#0f0f0f;font-weight:bold;position:sticky;top:0}
    .badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px}
    .badge-ok{background:var(--ok);color:#fff}
    .badge-no{background:var(--err);color:#fff}
    .btn{background:var(--pri);color:gold;border:none;border-radius:6px;padding:8px 10px;cursor:pointer;font-weight:bold}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .btn-sec{background:#333;color:#ddd}
    .row-actions{display:flex;align-items:center;gap:8px}
    .muted{color:var(--muted);font-size:12px}
    .pill{display:inline-block;padding:2px 8px;border:1px solid var(--line);border-radius:999px;font-size:12px;background:var(--chip)}
    .topbar{display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center;margin-bottom:10px}
    .status{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .spinner{display:inline-block;width:14px;height:14px;border:2px solid var(--fg);border-top-color:transparent;border-radius:50%;animation:spin .8s linear infinite;vertical-align:middle}
    .hide{display:none}
    @keyframes spin{to{transform:rotate(360deg)}}
    .searchbar{display:flex;gap:8px;align-items:center;margin:8px 0}
    .searchbar input{flex:1;padding:8px;border:1px solid var(--line);background:#0c0c0c;color:#f3f3f3;border-radius:6px}
    .empty{padding:12px;border:1px dashed var(--line);border-radius:8px;background:#0b0b0b}
    pre.err{white-space:pre-wrap;background:#140b0b;color:#ffd7d7;border:1px solid #411;padding:8px;border-radius:6px;margin:6px 0}
    .debug{margin:10px 0;padding:8px;border:1px dashed var(--line);background:#0b0b0b;border-radius:8px;color:#ccc;font-size:12px}
    .debug code{color:#fff}

    /* Modal */
    .overlay{position:fixed;inset:0;background:var(--overlay);display:none;align-items:center;justify-content:center;z-index:99}
    .overlay.show{display:flex}
    .modal{width:min(920px,97vw);background:#0b0b0b;border:1px solid var(--line);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.5);padding:16px}
    .modal h2{margin:0 0 8px 0;font-size:20px}
    .hint{color:#ddd;margin-bottom:12px}

    /* Layout de captura */
    .capture-grid{display:grid;grid-template-columns: 340px 1fr; gap:12px}
    .preview{background:#0e0e0e;border:1px solid var(--line);border-radius:10px;padding:10px}
    .preview h3{margin:0 0 8px 0;font-size:14px;color:#ddd}
    .preview .frame{width:100%;height:260px;background:#000;border:1px solid #333;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden}
    .preview .frame img{max-width:100%;max-height:100%;display:block}
    .controls{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
    .samples{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    .card{background:#0e0e0e;border:1px solid var(--line);border-radius:10px;padding:10px}
    .card h4{margin:0 0 6px 0;font-size:13px}
    .thumb{width:100%;height:120px;background:#000;border:1px solid #333;border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:8px}
    .thumb img{max-width:100%;max-height:100%;display:block}
    .kv{display:grid;grid-template-columns:90px 1fr;gap:4px 8px;font-size:12px;margin-bottom:6px;color:#ddd}
    .bar{height:8px;background:var(--bar);border-radius:999px;overflow:hidden}
    .bar > span{display:block;height:100%;background:linear-gradient(90deg,#a00,#e0a800,#1e9e49);width:0%}
    .sel{outline:2px solid gold}
    .actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <h1>🖐️ Enrolar huella (profesores)</h1>
      <div class="status">
        <span class="pill">Gimnasio ID: <?= (int)$gimnasio_id ?></span>
        <span class="pill">Servicio local: <strong id="svc-status">verificando…</strong></span>
        <button class="btn" id="btn-probar" type="button">Probar lector</button>
        <button class="btn" id="btn-config" type="button" title="Cambiar URL/puerto del lector">Configurar lector</button>
        <button class="btn" id="btn-probar-api" type="button">Probar API</button>
      </div>
    </div>

    <div class="note">
      Flujo con <strong>3 capturas</strong> y <strong>vista previa/imagen</strong> si tu servicio local la provee.
      <div class="muted">Requisitos del servicio local: <code>/snapshot</code> (preview) y <code>/enroll?single=1</code> (o <code>repeats=1</code>) devolviendo <code>template_b64</code> y, si es posible, <code>image_b64</code> y <code>quality</code>. Todo se llama vía <code>/biometria/local_proxy.php</code>.</div>
    </div>

    <div class="debug">
      <div>APP_BASE: <code id="dbg-base"></code></div>
      <div>SERVER_ENROLL_URL: <code id="dbg-url"></code></div>
      <div>LOCAL_BASE: <code id="dbg-localbase"></code></div>
    </div>

    <div class="searchbar">
      <input id="q" type="text" placeholder="🔎 Filtrar por nombre o ID…" autocomplete="off">
    </div>

    <?php if (!count($profes)): ?>
      <div class="empty">No hay profesores cargados aún.</div>
    <?php else: ?>
    <table class="table" id="tbl">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Profesor</th>
          <th style="width:140px">Huella</th>
          <th style="width:260px">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($profes as $p): 
          $pid = (int)$p['id'];
          $pname = trim((string)$p['nombre']) !== '' ? $p['nombre'] : 'Sin nombre';
          $tiene = (int)$p['tiene_huella'] === 1;
        ?>
          <tr id="row-<?= $pid ?>" data-id="<?= $pid ?>" data-name="<?= h($pname) ?>">
            <td><?= $pid ?></td>
            <td><?= h($pname) ?></td>
            <td class="cell-estado">
              <?php if ($tiene): ?>
                <span class="badge badge-ok">Cargada</span>
              <?php else: ?>
                <span class="badge badge-no">No cargada</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button class="btn btn-enrolar" data-id="<?= $pid ?>" type="button">
                  Capturar / Actualizar
                </button>
                <span class="spinner hide" id="sp-<?= $pid ?>"></span>
                <span class="muted hide" id="ok-<?= $pid ?>">✔ Guardado</span>
                <span class="muted hide" id="er-<?= $pid ?>"></span>
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

    <pre id="debug-out" class="err hide"></pre>
  </div>

  <!-- Modal de captura / preview / confirmación -->
  <div class="overlay" id="cap-ov">
    <div class="modal">
      <h2>Captura de huella</h2>
      <div class="hint" id="cap-hint">Colocá el dedo cuando se te indique…</div>

      <div class="capture-grid">
        <!-- Lado Izquierdo: PREVIEW -->
        <div class="preview">
          <h3>Vista previa (si disponible)</h3>
          <div class="frame"><img id="live-img" alt="preview" /></div>
          <div class="controls">
            <button class="btn-sec" id="pv-start" type="button">Iniciar preview</button>
            <button class="btn-sec" id="pv-stop"  type="button">Detener preview</button>
          </div>
          <div class="muted" id="pv-state" style="margin-top:6px">Estado: —</div>
        </div>

        <!-- Lado Derecho: SAMPLES -->
        <div>
          <div class="samples">
            <div class="card" id="card-0">
              <h4>Muestra 1</h4>
              <div class="thumb"><img id="img-0" alt="m1"/></div>
              <div class="kv">
                <div>Bytes</div><div id="bytes-0">—</div>
                <div>Calidad</div>
                <div><div class="bar"><span id="qbar-0"></span></div></div>
                <div>Hash</div><div id="hash-0">—</div>
              </div>
              <button class="btn-sec" id="rec-0" type="button">Recapturar</button>
            </div>
            <div class="card" id="card-1">
              <h4>Muestra 2</h4>
              <div class="thumb"><img id="img-1" alt="m2"/></div>
              <div class="kv">
                <div>Bytes</div><div id="bytes-1">—</div>
                <div>Calidad</div>
                <div><div class="bar"><span id="qbar-1"></span></div></div>
                <div>Hash</div><div id="hash-1">—</div>
              </div>
              <button class="btn-sec" id="rec-1" type="button">Recapturar</button>
            </div>
            <div class="card" id="card-2">
              <h4>Muestra 3</h4>
              <div class="thumb"><img id="img-2" alt="m3"/></div>
              <div class="kv">
                <div>Bytes</div><div id="bytes-2">—</div>
                <div>Calidad</div>
                <div><div class="bar"><span id="qbar-2"></span></div></div>
                <div>Hash</div><div id="hash-2">—</div>
              </div>
              <button class="btn-sec" id="rec-2" type="button">Recapturar</button>
            </div>
          </div>

          <div class="actions">
            <button class="btn-sec" id="cap-cancel" type="button">Cancelar</button>
            <button class="btn"     id="cap-next"   type="button">Capturar muestra 1</button>
            <button class="btn"     id="cap-save"   type="button" disabled>Guardar seleccionada</button>
          </div>

          <div class="muted" id="cap-status" style="margin-top:6px">Listo.</div>
        </div>
      </div>
    </div>
  </div>

<script>
/* ===== Config desde PHP ===== */
const APP_BASE      = <?= json_encode($app_base) ?>; // ej "/multi_gimnasio" ó ""
const GIMNASIO_ID   = <?= (int)$gimnasio_id ?>;
const API_KEY       = <?= json_encode($API_KEY, JSON_UNESCAPED_SLASHES) ?>;

/* ===== LOCAL_BASE configurable (persistido en localStorage) ===== */
function getLocalBase(){ return localStorage.getItem('ZK_LOCAL_BASE') || 'http://127.0.0.1:5177'; }
function setLocalBase(url){
  localStorage.setItem('ZK_LOCAL_BASE', url);
  document.getElementById('dbg-localbase').textContent = url;
}
setLocalBase(getLocalBase()); // init visual

/* ===== Proxy SAME-ORIGIN ===== */
const PROXY_BASE = (APP_BASE || '') + '/biometria/local_proxy.php';
function proxyUrl(path, qs=''){
  const base = encodeURIComponent(getLocalBase());
  return `${PROXY_BASE}?p=${encodeURIComponent(path)}&base=${base}${qs ? '&'+qs : ''}`;
}

/* ===== Endpoints derivados ===== */
function LOCAL_HEALTH_URL(){   return proxyUrl('health'); }
function LOCAL_RESCAN_URL(){   return proxyUrl('rescan'); }
function LOCAL_ENROLL1_URL(n){ return proxyUrl('enroll', `single=1&nonce=${encodeURIComponent(n||'')}`); } // captura 1 muestra
function LOCAL_ENROLL3_URL(n){ return proxyUrl('enroll', `repeats=1&nonce=${encodeURIComponent(n||'')}`); } // fallback a 1 muestra
function LOCAL_PREVIEW_START(){return proxyUrl('preview/start'); }
function LOCAL_PREVIEW_STOP(){ return proxyUrl('preview/stop');  }
function LOCAL_SNAPSHOT(){     return proxyUrl('snapshot');      }
const SERVER_ENROLL_URL = (APP_BASE || '') + '/api/biometria/enrolar.php';

/* ===== Parámetros de validación ===== */
const MIN_TEMPLATE_BYTES = 200;
const MIN_QUALITY = 40; // si tu servicio devuelve quality 0..100

/* ===== Debug visible ===== */
document.getElementById('dbg-base').textContent = APP_BASE || '(vacía)';
document.getElementById('dbg-url').textContent  = SERVER_ENROLL_URL;

/* ===== Utils ===== */
function delay(ms){ return new Promise(r=>setTimeout(r, ms)); }
function showDebug(msg){ const box = document.getElementById('debug-out'); box.textContent = msg; box.classList.remove('hide'); }
async function fetchConTimeout(url, options={}, timeoutMs=10000){
  const ctrl = new AbortController(); const id = setTimeout(()=>ctrl.abort(), timeoutMs);
  try { return await fetch(url, { ...options, signal: ctrl.signal }); } finally { clearTimeout(id); }
}
async function fetchJSON(url, options={}, timeoutMs=12000){
  const res  = await fetchConTimeout(url, options, timeoutMs);
  const text = await res.text(); let data = null; try { data = JSON.parse(text); } catch {}
  if (!res.ok || !data) { const snippet = text ? text.slice(0,300) : '(cuerpo vacío)'; throw new Error(`HTTP ${res.status} en ${url}\n${snippet}`); }
  if (data.ok === false) throw new Error(data.error || 'Error'); return data;
}
function b64ToBytes(b64){ try { const m = b64.startsWith('data:') ? b64.split(',')[1] : b64; const bin = atob(m); const len = bin.length; const arr = new Uint8Array(len); for (let i=0;i<len;i++) arr[i]=bin.charCodeAt(i); return arr; } catch { return null; } }
async function sha256Hex(uint8){ const buf = await crypto.subtle.digest('SHA-256', uint8); return Array.from(new Uint8Array(buf)).map(b=>b.toString(16).padStart(2,'0')).join(''); }
function randNonce(){ const a = crypto.getRandomValues(new Uint8Array(16)); return Array.from(a).map(b=>b.toString(16).padStart(2,'0')).join(''); }
function pctClamp(x){ return Math.max(0, Math.min(100, Math.round(x))); }

/* ===== Servicio local: check ===== */
async function checkService() {
  const badge = document.getElementById('svc-status');
  try {
    const h = await fetchJSON(LOCAL_HEALTH_URL(), { method:'GET' }, 5000);
    if (typeof h.lastRc !== 'undefined') badge.textContent = `OK (lastRc=${h.lastRc})`;
    else badge.textContent = 'OK (responde)';
  } catch { badge.textContent = 'sin respuesta'; }
}
document.getElementById('btn-probar').addEventListener('click', checkService);
window.addEventListener('DOMContentLoaded', checkService);

/* ===== Configurar lector (URL/puerto) ===== */
document.getElementById('btn-config').addEventListener('click', async ()=>{
  const curr = getLocalBase();
  const val = prompt('URL base del lector (ej: http://127.0.0.1:5177)', curr);
  if (!val) return;
  try { const url = new URL(val); setLocalBase(url.origin); await checkService(); alert('Configuración guardada: ' + url.origin); }
  catch { alert('URL inválida. Ejemplo válido: http://127.0.0.1:5177'); }
});

/* ===== Probar API servidor ===== */
document.getElementById('btn-probar-api').addEventListener('click', async ()=>{
  const headers = { 'Content-Type':'application/json' }; if (API_KEY) headers['X-API-KEY'] = API_KEY;
  const body = JSON.stringify({ persona_tipo:'profesor', persona_id: 2, gimnasio_id:GIMNASIO_ID, template_b64: btoa('TEST_TEMPLATE'), version:'ZKFinger10' });
  try { const r = await fetchJSON(SERVER_ENROLL_URL, { method:'POST', headers, body }, 12000); alert('API OK: ' + JSON.stringify(r)); }
  catch(e) { showDebug(String(e)); alert('API no-JSON/Err (ver panel debug)'); }
});

/* ===== Filtro en vivo ===== */
const q = document.getElementById('q');
q?.addEventListener('input', () => {
  const term = q.value.toLowerCase().trim();
  document.querySelectorAll('#tbl tbody tr').forEach(tr => {
    const id = (tr.getAttribute('data-id')||'').toLowerCase();
    const name = (tr.getAttribute('data-name')||'').toLowerCase();
    tr.style.display = (id.includes(term) || name.includes(term)) ? '' : 'none';
  });
});

/* ===== Referencias UI modal ===== */
const ov = document.getElementById('cap-ov');
const capHint = document.getElementById('cap-hint');
const capStatus = document.getElementById('cap-status');
const pvStart = document.getElementById('pv-start');
const pvStop  = document.getElementById('pv-stop');
const pvState = document.getElementById('pv-state');
const liveImg = document.getElementById('live-img');

const recBtns = [0,1,2].map(i=>document.getElementById(`rec-${i}`));
const imgEls  = [0,1,2].map(i=>document.getElementById(`img-${i}`));
const bytesEls= [0,1,2].map(i=>document.getElementById(`bytes-${i}`));
const qbarEls = [0,1,2].map(i=>document.getElementById(`qbar-${i}`));
const hashEls = [0,1,2].map(i=>document.getElementById(`hash-${i}`));
const cardEls = [0,1,2].map(i=>document.getElementById(`card-${i}`));

const btnCancel = document.getElementById('cap-cancel');
const btnNext   = document.getElementById('cap-next');
const btnSave   = document.getElementById('cap-save');

let previewTimer = null;
let currentIdx = 0;
let selectedIdx = 0;
let capturing = false;
let profesorCtx = { id:null, nombre:'' };
let samples = [
  { tpl:null, bytes:0, hash:null, img:null, quality:null, version:null, nonce:null },
  { tpl:null, bytes:0, hash:null, img:null, quality:null, version:null, nonce:null },
  { tpl:null, bytes:0, hash:null, img:null, quality:null, version:null, nonce:null },
];

/* ===== Preview helpers ===== */
async function previewStart(){
  pvState.textContent = 'Estado: iniciando…';
  try { await fetchConTimeout(LOCAL_PREVIEW_START(), { method:'POST' }, 3000); } catch {}
  clearInterval(previewTimer);
  previewTimer = setInterval(async ()=>{
    try {
      const r = await fetchJSON(LOCAL_SNAPSHOT(), { method:'GET' }, 2000);
      const b64 = r.image_b64 || r.img_b64 || r.bmp_b64 || r.frame_b64 || null;
      if (b64) liveImg.src = b64.startsWith('data:') ? b64 : ('data:image/png;base64,'+b64);
      pvState.textContent = 'Estado: preview activo';
    } catch(e){
      pvState.textContent = 'Estado: preview no disponible';
    }
  }, 250);
}
async function previewStop(){
  clearInterval(previewTimer); previewTimer = null;
  liveImg.removeAttribute('src');
  pvState.textContent = 'Estado: detenido';
  try { await fetchConTimeout(LOCAL_PREVIEW_STOP(), { method:'POST' }, 2000); } catch {}
}
pvStart.addEventListener('click', previewStart);
pvStop .addEventListener('click', previewStop);

/* ===== Modal ===== */
function openModal(nombre){
  capHint.textContent = `Profesor: ${nombre}. Se capturarán 3 muestras.`;
  capStatus.textContent = 'Listo.';
  currentIdx = 0; selectedIdx = 0; capturing = false;
  samples = samples.map(()=>({ tpl:null, bytes:0, hash:null, img:null, quality:null, version:null, nonce:null }));
  [0,1,2].forEach(i=>{
    imgEls[i].removeAttribute('src'); bytesEls[i].textContent='—'; hashEls[i].textContent='—';
    qbarEls[i].style.width='0%'; cardEls[i].classList.remove('sel');
  });
  cardEls[0].classList.add('sel');
  btnNext.textContent = 'Capturar muestra 1';
  btnSave.disabled = true;
  ov.classList.add('show');
  previewStart();
}
function closeModal(){ ov.classList.remove('show'); previewStop(); }

/* ===== Selección de muestra por click ===== */
cardEls.forEach((el, idx)=>{
  el.addEventListener('click', ()=>{
    cardEls[selectedIdx].classList.remove('sel');
    selectedIdx = idx;
    el.classList.add('sel');
    btnSave.disabled = !samples[selectedIdx].tpl;
  });
});

/* ===== Captura de una muestra ===== */
async function captureOne(targetIdx, isRecapture=false){
  if (capturing) return;
  capturing = true;
  btnNext.disabled = true;
  recBtns.forEach(b=>b.disabled = true);
  let ok = false;

  try {
    capStatus.textContent = isRecapture ? `Recapturando muestra ${targetIdx+1}…` : `Capturando muestra ${targetIdx+1}…`;
    try { await fetchConTimeout(LOCAL_RESCAN_URL(), { method:'POST' }, 3000); } catch{}
    await delay(800);

    const nonce = randNonce();

    let r = null;
    try { r = await fetchJSON(LOCAL_ENROLL1_URL(nonce), { method:'GET' }, 15000); }
    catch(e1){
      try { r = await fetchJSON(LOCAL_ENROLL3_URL(nonce), { method:'GET' }, 15000); }
      catch(e2){ throw e1; }
    }

    const tplB64 = String(r.template_b64 || '');
    if (!tplB64) throw new Error('El servicio local no devolvió template_b64.');

    const bytesArr = b64ToBytes(tplB64);
    const bytesLen = bytesArr ? bytesArr.length : 0;
    if (!bytesArr || bytesLen < MIN_TEMPLATE_BYTES) {
      throw new Error(`Plantilla demasiado corta (${bytesLen} bytes). Recapturá.`);
    }
    if (r.nonce && r.nonce !== nonce) throw new Error('Nonce distinto (posible respuesta cacheada).');

    const imgB64 = r.image_b64 || r.img_b64 || r.bmp_b64 || r.frame_b64 || null;
    const quality = (typeof r.quality === 'number') ? r.quality
                    : (typeof r.score === 'number') ? r.score
                    : null;
    const hashHex = await sha256Hex(bytesArr);

    samples[targetIdx] = {
      tpl: tplB64,
      bytes: bytesLen,
      hash: hashHex,
      img: imgB64 ? (imgB64.startsWith('data:') ? imgB64 : ('data:image/png;base64,'+imgB64)) : null,
      quality: quality,
      version: r.version || 'ZKFinger10',
      nonce
    };

    if (samples[targetIdx].img) imgEls[targetIdx].src = samples[targetIdx].img;
    bytesEls[targetIdx].textContent = `${bytesLen}`;
    hashEls[targetIdx].textContent  = `${hashHex.slice(0,12)}…`;
    if (quality != null){
      const pct = pctClamp(quality);
      qbarEls[targetIdx].style.width = pct + '%';
      qbarEls[targetIdx].title = `Calidad: ${pct}`;
    } else {
      qbarEls[targetIdx].style.width = '0%';
      qbarEls[targetIdx].title = 'Calidad: N/D';
    }

    ok = true;
    capStatus.textContent = `✅ Muestra ${targetIdx+1} capturada.`;

  } catch(e) {
    capStatus.textContent = '✖ ' + (e.message || 'Error de captura');
  } finally {
    capturing = false;
    btnNext.disabled = false;
    recBtns.forEach((b,i)=> b.disabled = !samples[i].tpl);
    if (ok){
      btnSave.disabled = !samples[selectedIdx].tpl;
      if (!isRecapture){
        if (currentIdx < 2) {
          currentIdx++;
          btnNext.textContent = `Capturar muestra ${currentIdx+1}`;
        } else {
          btnNext.textContent = `Capturar (opcional)`;
        }
      }
    }
  }
}

/* Recaptura por tarjeta */
recBtns.forEach((btn, idx)=> btn.addEventListener('click', ()=> captureOne(idx, true)));

/* Botones modal */
btnCancel.addEventListener('click', closeModal);
btnNext  .addEventListener('click', ()=> captureOne(currentIdx, false));
btnSave  .addEventListener('click', async ()=>{
  const s = samples[selectedIdx];
  if (!s || !s.tpl) return;
  if (s.quality != null && s.quality < MIN_QUALITY){
    alert(`La calidad (${s.quality}) es baja (<${MIN_QUALITY}). Recapturá o elegí otra muestra.`);
    return;
  }
  await guardarEnServidor({
    profesorId: profesorCtx.id,
    tplB64: s.tpl,
    version: s.version,
    hashHex: s.hash
  });
});

/* ===== Guardar ===== */
function storageKeyFor(profesorId){ return `huella_last_hash_prof_${profesorId}`; }

async function guardarEnServidor(p){
  const sp = document.getElementById('sp-'+p.profesorId);
  const ok = document.getElementById('ok-'+p.profesorId);
  const er = document.getElementById('er-'+p.profesorId);
  const rowEstado = document.querySelector('#row-'+p.profesorId+' .cell-estado');

  ok.classList.add('hide'); er.classList.add('hide'); er.textContent = '';
  sp.classList.remove('hide');
  capStatus.textContent = 'Guardando en el servidor…';
  btnSave.disabled = true;

  try {
    const headers = { 'Content-Type':'application/json' };
    if (API_KEY) headers['X-API-KEY'] = API_KEY;
    const body = JSON.stringify({
      persona_tipo: 'profesor',
      persona_id: p.profesorId,
      gimnasio_id: GIMNASIO_ID,
      template_b64: p.tplB64,
      version: p.version
    });
    await fetchJSON('<?= h(($app_base ?: '') . "/api/biometria/enrolar.php") ?>', { method:'POST', headers, body }, 12000);

    localStorage.setItem(storageKeyFor(p.profesorId), p.hashHex);

    ok.textContent = '✔ Guardado'; ok.classList.remove('hide');
    if (rowEstado) rowEstado.innerHTML = '<span class="badge badge-ok">Cargada</span>';
    capStatus.textContent = '✔ Guardado OK';
    setTimeout(()=>closeModal(), 700);

  } catch (e) {
    er.textContent = '✖ ' + (e.message || 'Error inesperado');
    er.classList.remove('hide');
    capStatus.textContent = '✖ ' + (e.message || 'Error al guardar');
    btnSave.disabled = false;
  } finally {
    sp.classList.add('hide');
  }
}

/* ===== Enrolar (abre modal) ===== */
async function enrolar(profesorId, nombre){
  const sp = document.getElementById('sp-'+profesorId);
  const ok = document.getElementById('ok-'+profesorId);
  const er = document.getElementById('er-'+profesorId);
  ok.classList.add('hide'); er.classList.add('hide'); er.textContent = '';
  sp.classList.remove('hide');

  try {
    profesorCtx = { id: profesorId, nombre };
    openModal(nombre);
  } catch (e) {
    console.error(e);
  } finally {
    sp.classList.add('hide');
  }
}

/* Bind botones en la tabla */
document.addEventListener('click', (ev) => {
  const btn = ev.target.closest('.btn-enrolar');
  if (!btn) return;
  const profesorId = parseInt(btn.getAttribute('data-id'), 10);
  const row = document.getElementById('row-'+profesorId);
  const nombre = row?.getAttribute('data-name') || '';
  if (Number.isFinite(profesorId) && profesorId > 0) enrolar(profesorId, nombre);
});
</script>
</body>
</html>
