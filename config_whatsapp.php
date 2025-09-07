<?php
// config_whatsapp.php
// ⚠️ NO subas este archivo al repositorio público.
// Rellena con tus credenciales de WhatsApp Cloud API (Meta).

if (!defined('WA_CFG_LOADED')) {
  define('WA_CFG_LOADED', true);

  // Ejemplos:
  // - WA_PHONE_NUMBER_ID: el "phone number ID" que te da Meta
  // - WA_ACCESS_TOKEN: el token de acceso (largo)
  // - WA_API_VERSION: opcional (v20.0 actualmente)
  define('WA_PHONE_NUMBER_ID', 'YOUR_PHONE_NUMBER_ID');  // p.ej: 123456789012345
  define('WA_ACCESS_TOKEN',    'YOUR_LONG_ACCESS_TOKEN');
  define('WA_API_VERSION',     'v20.0');
}
