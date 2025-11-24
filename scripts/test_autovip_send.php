<?php
/**
 * Test AutomatizadoVIP: envía WhatsApp probando:
 *  - Endpoint: BIG (https://big.automatizadovip.com/api/whatsapp/send)
 *  - Endpoint: CORE (https://api.automatizado.vip/api/whatsapp/send)
 *  - Auth: Api-key (header "Api-key") o Bearer (header "Authorization: Bearer ...")
 *
 * Control por ENV (con defaults seguros):
 *   AUTOVIP_URL=...                (si NO se define, se prueban AMBOS endpoints)
 *   AUTOVIP_AUTH=api-key|bearer    (default: api-key)
 *   AUTOVIP_KEY=...                (OBLIGATORIO)
 *   AUTOVIP_INSTANCE=...           (opcional)
 *   AUTOVIP_SENDER=...             (opcional)
 *   AUTOVIP_TIMEOUT=20             (opcional)
 *
 * Uso:
 *   AUTOVIP_KEY=xxxxx php scripts/test_autovip_send.php 942415211 "MENSAJE"
 *   # Forzar CORE+Bearer:
 *   AUTOVIP_KEY=xxxxx AUTOVIP_URL=https://api.automatizado.vip/api/whatsapp/send AUTOVIP_AUTH=bearer php scripts/test_autovip_send.php 942415211 "MENSAJE"
 */

$cliNumber = $argv[1] ?? '';
$msg       = $argv[2] ?? 'PRUEBA: aviso para actividad {ID}';
if (!$cliNumber) { fwrite(STDERR, "Uso: php scripts/test_autovip_send.php 942415211 \"MENSAJE\"\n"); exit(1); }

$key  = getenv('AUTOVIP_KEY') ?: '';
if (!$key) { fwrite(STDERR, "Falta AUTOVIP_KEY.\n"); exit(1); }

$cfgUrl  = getenv('AUTOVIP_URL') ?: '';
$auth    = strtolower(getenv('AUTOVIP_AUTH') ?: 'api-key'); // api-key | bearer
$inst    = getenv('AUTOVIP_INSTANCE') ?: null;
$sender  = getenv('AUTOVIP_SENDER') ?: null;
$timeout = (int)(getenv('AUTOVIP_TIMEOUT') ?: 20);

$endpoints = $cfgUrl ? [ $cfgUrl ] : [
  'https://big.automatizadovip.com/api/whatsapp/send',   // DOC OFICIAL
  'https://api.automatizado.vip/api/whatsapp/send',      // CORE (según soporte)
];

function cl_targets(string $raw): array {
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === '') return [];
    if (strpos($d, '56') === 0) {
        if (strlen($d) !== 11) return [];
        $e164 = $d;
    } elseif (strlen($d) === 9 && $d[0] === '9') {
        $e164 = '56'.$d;
    } else {
        return [];
    }
    return ['+'.$e164, $e164]; // +569..., 569...
}
$targets = cl_targets($cliNumber);
if (!$targets) { fwrite(STDERR, "Número inválido para CL: $cliNumber\n"); exit(1); }

foreach ($endpoints as $url) {
  echo "================ ENDPOINT: {$url} | AUTH: {$auth} ================\n";

  $headers = [
    'Content-Type: application/json',
    'Accept: application/json',
  ];
  if ($auth === 'bearer') {
    $headers[] = 'Authorization: Bearer '.$key;
  } else {
    $headers[] = 'Api-key: '.$key;
  }

  foreach ($targets as $n) {
    $payload = [
      'contact' => [
        ['number' => $n, 'message' => $msg.' / '.$n],
      ],
      // sin 'verify' para no quedar solo en validación
    ];
    if ($inst)   $payload['instance'] = $inst;
    if ($sender) $payload['sender']   = $sender;

    echo "=== INTENTANDO => {$n} ===\n";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => true,
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_TIMEOUT        => $timeout,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
      echo "❌ CURL: ".curl_error($ch)."\n";
      curl_close($ch);
      continue;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $respHeaders = substr($raw, 0, $hs);
    $respBody    = substr($raw, $hs);
    curl_close($ch);

    echo "HTTP: {$code}\n";
    echo "HEADERS:\n{$respHeaders}\n";
    echo "BODY:\n{$respBody}\n";
    echo "---------------------------\n";
  }
}
