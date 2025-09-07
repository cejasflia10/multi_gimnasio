<?php
// brand_strip.php — Barra/encabezado con imagen global para todo el sitio
// Lee config de assets/brand/brand_config.json y muestra la imagen.
// Si no hay config, intenta assets/brand/brand_header.(png|jpg|jpeg|webp|gif|svg)

if (!defined('BRAND_STRIP_LOADED')) {
  define('BRAND_STRIP_LOADED', true);

  $BASE_DIR  = __DIR__;
  $BASE_URL  = '';
  // Detecta si estamos en subcarpetas (por si lo necesitas adaptar)
  // $BASE_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/';

  $cfgPath   = $BASE_DIR.'/assets/brand/brand_config.json';
  $imgPath   = null;           // ruta relativa para <img src="">
  $linkHref  = 'index.php';    // link por defecto
  $heightCss = '64px';         // alto por defecto

  // 1) Config JSON si existe
  if (is_file($cfgPath)) {
    $raw = @file_get_contents($cfgPath);
    if ($raw !== false) {
      $cfg = json_decode($raw, true);
      if (is_array($cfg)) {
        if (!empty($cfg['filename']))   $imgPath   = 'assets/brand/'.$cfg['filename'];
        if (!empty($cfg['link']))       $linkHref  = (string)$cfg['link'];
        if (!empty($cfg['height']))     $heightCss = (string)$cfg['height'];
      }
    }
  }

  // 2) Si no hay config, intentar assets/brand/brand_header.*
  if (!$imgPath) {
    foreach (['png','jpg','jpeg','webp','gif','svg'] as $ext) {
      $guess = $BASE_DIR.'/assets/brand/brand_header.'.$ext;
      if (is_file($guess)) {
        $imgPath = 'assets/brand/brand_header.'.$ext;
        break;
      }
    }
  }

  // 3) Si no hay imagen, no renderizamos nada (así no rompe layout si aún no configuraste)
  if ($imgPath) {
    ?>
    <style>
      .brand-strip{display:flex;align-items:center;justify-content:center;gap:.6rem;
        padding:.35rem 0; background:#0f0f0f; border-bottom:1px solid #222;}
      .brand-strip a{display:inline-flex;align-items:center;text-decoration:none}
      .brand-strip img{height:<?= htmlspecialchars($heightCss,ENT_QUOTES,'UTF-8') ?>; width:auto; display:block}
      @media (max-width: 720px){ .brand-strip img{height:48px} }
    </style>
    <header class="brand-strip" role="banner" aria-label="Marca del sitio">
      <a href="<?= htmlspecialchars($linkHref,ENT_QUOTES,'UTF-8') ?>">
        <img src="<?= htmlspecialchars($imgPath,ENT_QUOTES,'UTF-8') ?>" alt="Marca / evento" loading="lazy">
      </a>
    </header>
    <?php
  }
}
