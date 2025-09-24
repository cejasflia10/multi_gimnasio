<?php
// cloudy_boot_constants.php — Inicializa Cloudinary desde constantes (fallback a CLOUDINARY_URL / settings)
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  // No es requisito tener $conexion, solo avisamos
}

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

/* === TUS CONSTANTES (se dejan tal cual las pasaste) === */
if (!defined('CLOUD_ENABLED'))      define('CLOUD_ENABLED', true);
if (!defined('CLOUD_NAME'))         define('CLOUD_NAME', 'ddfugds9b');
if (!defined('CLOUD_API_KEY'))      define('CLOUD_API_KEY', '657814174747186');
if (!defined('CLOUD_API_SECRET'))   define('CLOUD_API_SECRET', 'TKo5BRiKCEjxSLFzn2DLbz_ji4c');
if (!defined('CLOUD_FOLDER_ROOT'))  define('CLOUD_FOLDER_ROOT', 'ROOT'); // ej: ROOT / produccion / multi_gimnasio

/**
 * Inicializa Cloudinary. Prioriza CLOUDINARY_URL si existe.
 * @return array ['ok'=>bool, 'mode'=>'url'|'constants', 'reason'?, 'hint'?]
 */
function cloudy_constants_init(): array {
  // Deshabilitado explícito
  if (!CLOUD_ENABLED) return ['ok'=>false, 'reason'=>'disabled'];

  // Cargar autoload del SDK
  $autoloads = [
    __DIR__.'/vendor/autoload.php',
    dirname(__DIR__).'/vendor/autoload.php'
  ];
  $loaded=false;
  foreach($autoloads as $a){
    if (is_file($a)) { require_once $a; $loaded=true; break; }
  }
  if (!$loaded) return ['ok'=>false, 'reason'=>'sdk_missing', 'hint'=>'composer require cloudinary/cloudinary_php'];

  // Si existe CLOUDINARY_URL en el entorno, lo priorizamos
  $url = getenv('CLOUDINARY_URL') ?: '';
  if ($url !== '') {
    // Con CLOUDINARY_URL no hace falta configurar manualmente
    return ['ok'=>true, 'mode'=>'url'];
  }

  // Caso por constantes sueltas
  if (CLOUD_NAME==='' || CLOUD_API_KEY==='' || CLOUD_API_SECRET==='') {
    return ['ok'=>false, 'reason'=>'no_credentials'];
  }

  // SDK v2 (Configuration) o v1 (Cloudinary::config)
  if (class_exists('\\Cloudinary\\Configuration\\Configuration')) {
    \Cloudinary\Configuration\Configuration::instance([
      'cloud' => [
        'cloud_name' => CLOUD_NAME,
        'api_key'    => CLOUD_API_KEY,
        'api_secret' => CLOUD_API_SECRET,
      ],
      'url' => ['secure' => true],
    ]);
  } elseif (class_exists('\\Cloudinary')) {
    \Cloudinary::config([
      'cloud_name' => CLOUD_NAME,
      'api_key'    => CLOUD_API_KEY,
      'api_secret' => CLOUD_API_SECRET,
      'secure'     => true,
    ]);
  } else {
    return ['ok'=>false, 'reason'=>'sdk_not_loaded'];
  }

  return ['ok'=>true, 'mode'=>'constants'];
}
