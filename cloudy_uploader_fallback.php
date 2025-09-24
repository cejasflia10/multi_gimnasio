<?php
/**
 * Fallback de subida a Cloudinary sin SDK (vía cURL firmado).
 * Requiere que existan las constantes: CLOUD_NAME, CLOUD_API_KEY, CLOUD_API_SECRET, CLOUD_FOLDER_ROOT.
 * NO usa Composer.
 */

if (!defined('CLOUD_NAME') || !defined('CLOUD_API_KEY') || !defined('CLOUD_API_SECRET')) {
  throw new \RuntimeException('Cloudinary fallback: faltan constantes');
}

/**
 * Sube un archivo local (tmp) a Cloudinary.
 * @param string $tmp_path Ruta local del archivo (por ej. $_FILES['x']['tmp_name'])
 * @param string $folder   Subcarpeta bajo CLOUD_FOLDER_ROOT
 * @param array  $opts     Opcionales: ['resource_type'=>'image'|'auto', 'public_id'=>'...']
 * @return array [secure_url|null, public_id|null]
 */
function cloudy_upload_fallback(string $tmp_path, string $folder, array $opts = []): array {
  if (!is_file($tmp_path)) return [null, null];

  $cloud   = CLOUD_NAME;
  $api_key = CLOUD_API_KEY;
  $secret  = CLOUD_API_SECRET;

  $root = defined('CLOUD_FOLDER_ROOT') ? CLOUD_FOLDER_ROOT : 'ROOT';
  $dest = rtrim($root, '/').'/'.ltrim($folder, '/');

  $resource = $opts['resource_type'] ?? 'auto';
  $publicId = $opts['public_id']     ?? null;

  $timestamp = time();

  // Parámetros a firmar (alfabético, sin valores vacíos)
  $toSign = [
    'folder'          => $dest,
    'overwrite'       => 'false',
    'timestamp'       => (string)$timestamp,
    'unique_filename' => 'true',
    'use_filename'    => 'true',
  ];
  if ($publicId) { $toSign['public_id'] = $publicId; }

  ksort($toSign);
  $signStr = [];
  foreach ($toSign as $k=>$v) { $signStr[] = $k.'='.$v; }
  $signature = sha1(implode('&', $signStr) . $secret);

  $url = "https://api.cloudinary.com/v1_1/{$cloud}/{$resource}/upload";

  // Construir body multipart
  $post = [
    'api_key'         => $api_key,
    'timestamp'       => $timestamp,
    'signature'       => $signature,
    'folder'          => $dest,
    'overwrite'       => 'false',
    'unique_filename' => 'true',
    'use_filename'    => 'true',
    'file'            => class_exists('CURLFile') ? new \CURLFile($tmp_path) : '@'.$tmp_path,
  ];
  if ($publicId) { $post['public_id'] = $publicId; }

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_TIMEOUT        => 60,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp === false || $code >= 400) {
    // error_log("Cloudinary fallback error ($code): $err :: $resp");
    return [null, null];
  }

  $json = json_decode($resp, true);
  return [$json['secure_url'] ?? null, $json['public_id'] ?? null];
}
