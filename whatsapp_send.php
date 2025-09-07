<?php
// whatsapp_send.php
require_once __DIR__ . '/config_whatsapp.php';

function wa_send_text(string $to_e164, string $body): array {
  if (!defined('WA_ACCESS_TOKEN') || !WA_ACCESS_TOKEN || !defined('WA_PHONE_NUMBER_ID')) {
    return ['ok'=>false,'error'=>'WA config faltante'];
  }
  $url = "https://graph.facebook.com/".(defined('WA_API_VERSION')?WA_API_VERSION:'v20.0')."/".WA_PHONE_NUMBER_ID."/messages";
  $payload = [
    'messaging_product' => 'whatsapp',
    'to' => $to_e164,
    'type' => 'text',
    'text' => ['preview_url'=>true, 'body' => $body],
  ];
  return wa_curl_post_json($url, $payload);
}

function wa_send_document_by_link(string $to_e164, string $doc_url, string $filename = 'comprobante.pdf', string $caption = ''): array {
  if (!defined('WA_ACCESS_TOKEN') || !WA_ACCESS_TOKEN || !defined('WA_PHONE_NUMBER_ID')) {
    return ['ok'=>false,'error'=>'WA config faltante'];
  }
  $url = "https://graph.facebook.com/".(defined('WA_API_VERSION')?WA_API_VERSION:'v20.0')."/".WA_PHONE_NUMBER_ID."/messages";
  $payload = [
    'messaging_product' => 'whatsapp',
    'to' => $to_e164,
    'type' => 'document',
    'document' => [
      'link' => $doc_url,
      'filename' => $filename,
      'caption' => $caption,
    ],
  ];
  return wa_curl_post_json($url, $payload);
}

function wa_curl_post_json(string $url, array $payload): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer '.WA_ACCESS_TOKEN,
    ],
    CURLOPT_TIMEOUT => 15,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($err) return ['ok'=>false, 'http'=>$code, 'error'=>$err];
  $j = json_decode((string)$res, true);
  if ($code>=200 && $code<300) return ['ok'=>true, 'http'=>$code, 'res'=>$j];
  return ['ok'=>false, 'http'=>$code, 'res'=>$j];
}

/**
 * Normaliza a E.164 rápido para AR/latam si te pasan 11 dígitos, etc.
 * Idealmente guardalo ya en E.164 en tu BD.
 */
function wa_to_e164(string $raw, string $defaultCountryCode = '54'): string {
  $s = preg_replace('/\D+/', '', $raw);
  if ($s==='') return '';
  if ($s[0] !== '+') {
    if (strpos($s, $defaultCountryCode) !== 0) $s = $defaultCountryCode.$s;
    $s = '+'.$s;
  }
  return $s;
}
