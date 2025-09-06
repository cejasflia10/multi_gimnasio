<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id<=0){ http_response_code(400); exit('Falta evento_id'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Escanear tickets — Evento #<?= (int)$evento_id ?></title>
<style>
  body{background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;margin:0}
  .wrap{max-width:900px;margin:10px auto;padding:12px}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:14px}
  video{width:100%;border-radius:10px;border:1px solid #222}
  .log{margin-top:10px}
  .ok{background:#0f251b;border:1px solid #164b31;color:#b6f3d1;border-radius:10px;padding:10px}
  .bad{background:#2a1414;border:1px solid #5e2626;color:#ffb4b4;border-radius:10px;padding:10px}
  input{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4;margin-top:8px}
  .btn{padding:10px 12px;border-radius:8px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none;cursor:pointer;margin-right:6px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 6px">🔎 Escanear tickets — Evento #<?= (int)$evento_id ?></h2>
    <video id="vid" playsinline></video>
    <div style="margin-top:8px">
      <button class="btn" id="start">Iniciar cámara</button>
      <button class="btn" id="stop">Detener</button>
    </div>
    <input id="manual" placeholder="Pegá aquí el código del ticket si no podés escanear">
    <div id="log" class="log"></div>
  </div>
</div>

<!-- ZXing browser library -->
<script src="https://unpkg.com/@zxing/browser@latest"></script>
<script>
const eventoId = <?= (int)$evento_id ?>;
const log = (html,ok=false)=> {
  const div=document.getElementById('log');
  div.innerHTML = `<div class="${ok?'ok':'bad'}">${html}</div>` + div.innerHTML;
};
let codeReader; let controls;

async function start() {
  try{
    codeReader = new ZXing.BrowserMultiFormatReader();
    const video = document.getElementById('vid');
    controls = await codeReader.decodeFromVideoDevice(null, video, async (res,err)=>{
      if(res){
        const txt=res.getText();
        await validar(txt);
      }
    });
  }catch(e){ log('No se pudo iniciar la cámara: '+e, false); }
}
function stop(){
  if(controls){ controls.stop(); controls=null; }
}

async function validar(texto){
  // Acepta que el QR traiga una URL con ?code=XXXX
  let code=texto;
  try{ const u=new URL(texto); const c=u.searchParams.get('code'); if(c) code=c; }catch(_){}
  code = (code||'').trim();
  if(!code){ log('Código vacío',false); return; }
  const form=new FormData(); form.append('evento_id',eventoId); form.append('code',code);
  try{
    const r=await fetch('validar_ticket.php',{method:'POST',body:form});
    const j=await r.json();
    if(j.ok){
      log(`✅ ${j.msg} · Tipo: ${j.tipo??'-'} · Code: <b>${code}</b>`,true);
    }else{
      log(`⛔ ${j.msg} · Code: <b>${code}</b>`,false);
    }
  }catch(e){ log('Error de red: '+e,false); }
}

document.getElementById('start').addEventListener('click', start);
document.getElementById('stop').addEventListener('click', stop);
document.getElementById('manual').addEventListener('keydown', (e)=>{
  if(e.key==='Enter'){ validar(e.target.value); e.target.value=''; }
});
</script>
</body>
</html>
