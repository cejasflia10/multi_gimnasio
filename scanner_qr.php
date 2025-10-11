<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR o DNI para Ingreso</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tema unificado como el index -->
  <link rel="stylesheet" href="estilo_unificado.css">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <style>
    /* ===== Maqueta alineada al index ===== */
    .wrap{ max-width: 900px; margin: 24px auto; padding: 0 16px 40px; }
    .page-card{
      background: var(--card); border: 1px solid var(--stroke);
      border-radius: 18px; box-shadow: var(--shadow); padding: 16px;
    }
    .page-title{
      margin: 0 0 12px 0; text-align: center; font-weight: 900; letter-spacing: .4px;
      background: linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }

    /* Reader QR dentro de una card sutil */
    .reader-wrap{
      display: grid; place-items: center;
      background: #fff; border: 1px solid var(--stroke);
      border-radius: 16px; padding: 12px; box-shadow: var(--shadow);
    }
    #reader{ width: 320px; max-width: 100%; }
    /* Qrbox y borde los maneja la lib; solo evitamos estilos viejos */
    
    /* Formulario manual (usa look del sistema) */
    .form-manual{
      display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 14px;
    }
    .form-manual input[type="text"],
    .form-manual button{
      padding: 10px 12px; border-radius: 12px; border: 1px solid var(--stroke);
      background: linear-gradient(180deg,#fff,#f7fafc); color: var(--ink); font-weight: 700;
    }
    .form-manual input[type="text"]{
      min-width: 240px; font-size: 18px;
    }
    .form-manual button{ cursor: pointer; }
    .form-manual button:hover{ box-shadow: 0 6px 16px rgba(2,6,23,.06); }

    /* Mensajes */
    #resultado{
      margin-top: 14px; text-align: center; font-weight: 800;
    }
    .msg-ok{ color: #16a34a; }
    .msg-err{ color: #b91c1c; }

    /* Acciones extra */
    .actions{
      display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:10px;
    }
    .actions button{
      padding: 8px 12px; border-radius: 12px; border:1px solid var(--stroke);
      background: linear-gradient(180deg,#fff,#f7fafc); color: var(--ink); font-weight: 700; cursor: pointer;
    }
    .actions button:hover{ box-shadow: 0 6px 16px rgba(2,6,23,.06); }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="page-card">
      <h2 class="page-title">📷 Escaneo QR o DNI</h2>

      <div class="reader-wrap">
        <div id="reader" aria-label="Escáner de QR"></div>
      </div>

      <form class="form-manual" onsubmit="enviarDNIManual(event)">
        <input type="text" id="dni_manual" placeholder="Ingresar DNI manual" required pattern="\d+">
        <button type="submit">Registrar Ingreso</button>
      </form>

      <div class="actions">
        <button type="button" id="btn-reiniciar" onclick="reiniciarEscaneo()">Reiniciar cámara</button>
        <button type="button" id="btn-cambiar" onclick="cambiarCamara()">Cambiar cámara</button>
        <button type="button" id="btn-linterna" onclick="toggleTorch()">Linterna</button>
      </div>

      <div id="resultado" role="status" aria-live="polite"></div>
    </div>
  </div>

  <script>
    let scanner;           // instancia Html5Qrcode
    let cameras = [];      // lista de cámaras
    let currentCamId = null;
    let torchOn = false;

    function setMensaje(html, ok=true){
      const el = document.getElementById('resultado');
      el.className = ok ? 'msg-ok' : 'msg-err';
      el.innerHTML = html;
    }

    async function listarCamaras(){
      try{
        cameras = await Html5Qrcode.getCameras();
        if (cameras && cameras.length){
          // Preferimos la de atrás si existe
          const back = cameras.find(c => /back|trasera|environment/i.test(c.label));
          currentCamId = (back ? back.id : cameras[0].id);
        }else{
          currentCamId = { facingMode: "environment" };
        }
      }catch(e){
        currentCamId = { facingMode: "environment" };
      }
    }

    function registrarAsistencia(dni) {
      fetch("registrar_asistencia_qr.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "dni=" + encodeURIComponent(dni)
      })
      .then(r => r.text())
      .then(data => {
        setMensaje(data, true);
        setTimeout(() => { setMensaje(''); iniciarEscaneo(); }, 4000);
      })
      .catch(() => {
        setMensaje("❌ Error al registrar asistencia.", false);
        setTimeout(() => { setMensaje(''); iniciarEscaneo(); }, 4000);
      });
    }

    async function iniciarEscaneo() {
      try{
        if (!scanner) scanner = new Html5Qrcode("reader");
        await listarCamaras();

        await scanner.start(
          currentCamId,
          { fps: 10, qrbox: { width: 260, height: 260 } },
          (decodedText) => {
            if (/^\d+$/.test(decodedText)) {
              scanner.stop().then(() => registrarAsistencia(decodedText));
            } else {
              setMensaje("❌ Código inválido. Debe ser solo números.", false);
              setTimeout(() => { setMensaje(''); iniciarEscaneo(); }, 3000);
            }
          },
          () => { /* silencioso */ }
        );
      }catch(err){
        setMensaje("❌ Error al acceder a la cámara", false);
      }
    }

    function reiniciarEscaneo(){
      if (!scanner) return iniciarEscaneo();
      scanner.stop().finally(iniciarEscaneo);
    }

    function cambiarCamara(){
      if (!cameras || cameras.length < 2){ setMensaje('⚠️ No hay otra cámara disponible.', false); return; }
      const idx = cameras.findIndex(c => c.id === currentCamId);
      const next = cameras[(idx + 1) % cameras.length];
      currentCamId = next.id;
      reiniciarEscaneo();
    }

    async function toggleTorch(){
      // La API de la lib expone capabilities vía track (algunas cámaras lo soportan)
      try{
        const stream = await navigator.mediaDevices.getUserMedia({ video: { deviceId: currentCamId } });
        const track = stream.getVideoTracks()[0];
        const caps = track.getCapabilities?.() || {};
        if (!('torch' in caps)) { setMensaje('⚠️ La linterna no está disponible en esta cámara.', false); track.stop(); return; }
        torchOn = !torchOn;
        await track.applyConstraints({ advanced: [{ torch: torchOn }] });
        // paramos el stream temporal de control, el de html5-qrcode sigue activo
        track.stop();
      }catch(e){
        setMensaje('⚠️ No se pudo alternar la linterna.', false);
      }
    }

    function enviarDNIManual(e) {
      e.preventDefault();
      const dni = (document.getElementById("dni_manual").value || '').trim();
      if (/^\d+$/.test(dni)) {
        registrarAsistencia(dni);
        document.getElementById("dni_manual").value = "";
      } else {
        setMensaje("❌ El DNI debe contener solo números.", false);
      }
    }

    window.onload = iniciarEscaneo;
  </script>
</body>
</html>
