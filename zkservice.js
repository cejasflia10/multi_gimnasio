// zkservice.js - Servicio local mínimo para enrolar huellas (demo)
// Modo demo: NO accede aún al SDK, solo devuelve una plantilla dummy en base64.
// Endpoints: /health (GET), /rescan (POST), /enroll (GET)

const express = require('express');
const cors = require('cors');
const app = express();
app.use(express.json({limit: '2mb'}));
app.use(cors({ origin: '*', methods: ['GET','POST','OPTIONS'], allowedHeaders: ['Content-Type','X-API-KEY'] }));

let lastRc = 0;
let lastDeviceCount = 1;

// Health
app.get('/health', (req, res) => {
  res.set('Access-Control-Allow-Origin', '*');
  res.json({ ok: true, err: '', lastRc, lastDeviceCount });
});

// Rescan (simulado)
app.post('/rescan', (req, res) => {
  lastDeviceCount = 1; // aquí escanearías dispositivos reales
  lastRc = 0;
  res.set('Access-Control-Allow-Origin', '*');
  res.json({ ok: true, err: '', lastRc, lastDeviceCount });
});

// Enroll (DEMO) - genera "plantilla" base64 falsa para probar tu UI
app.get('/enroll', async (req, res) => {
  const repeats = parseInt(req.query.repeats || '3', 10);
  if (lastDeviceCount < 1) {
    lastRc = -8; // sin dispositivo / no abierto
    return res.status(503).json({ ok: false, error: 'No hay dispositivo', rc: lastRc });
  }
  // Aquí iría: open() → capturar N veces → merge→ template → close()
  // DEMO: devolvemos base64 de una cadena
  const fake = Buffer.from(`FAKE_TEMPLATE_${Date.now()}`).toString('base64');
  lastRc = 0;
  res.set('Access-Control-Allow-Origin', '*');
  res.json({ ok: true, template_b64: fake, version: 'ZKFinger10', rc: lastRc, repeats });
});

const PORT = 5177;
app.listen(PORT, '127.0.0.1', () => {
  console.log(`ZK local service demo escuchando en http://127.0.0.1:${PORT}`);
});
