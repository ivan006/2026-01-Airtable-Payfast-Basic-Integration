<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

require __DIR__ . '/CurlClient.php';
require __DIR__ . '/helpers.php';


function generatePayFastSignature(array $data, string $passphrase = ''): string
{
  // Remove empty values
  $data = array_filter($data, fn($v) => $v !== '');

  // Sort alphabetically by key
  ksort($data);

  // Build query string
  $pairs = [];
  foreach ($data as $key => $value) {
    $pairs[] = $key . '=' . urlencode(trim($value));
  }

  $queryString = implode('&', $pairs);

  // Append passphrase if set
  if ($passphrase !== '') {
    $queryString .= '&passphrase=' . urlencode($passphrase);
  }

  return md5($queryString);
}


/**
 * -------------------------------------------------
 * 1. Accept product_id (hardcoded for now)
 * -------------------------------------------------
 */
$productId = $_POST['product_id'] ?? $_GET['product_id'] ?? null;

if (!$productId) {
  http_response_code(400);
  echo json_encode(['error' => 'product_id is required']);
  exit();
}

/**
 * -------------------------------------------------
 * 2. Load env + secrets
 * -------------------------------------------------
 */
$envFile = __DIR__ . '/env.json';
if (!file_exists($envFile)) {
  http_response_code(500);
  echo 'env.json missing';
  exit();
}

$env = json_decode(file_get_contents($envFile), true);

/**
 * -------------------------------------------------
 * 3. Resolve authoritative price from Airtable
 * -------------------------------------------------
 */
$airtableUrl =
  $env['airtable']['base_url']
  . $env['airtable']['base_id']
  . '/'
  . rawurlencode($env['airtable']['table'])
  . '/'
  . $productId;

// Read per-host config (Airtable auth headers)
$config = readConfig($airtableUrl);
$headers = [];

if (!empty($config['headers'])) {
  foreach ($config['headers'] as $key => $value) {
    $headers[] = $key . ': ' . $value;
  }
}


$client = new CurlClient(false);

// create temp stream for response body
$bodyStream = fopen('php://temp', 'w+');

$info = $client->get($airtableUrl, $headers, $bodyStream);

if (!$info || $info['http_code'] !== 200) {
  http_response_code(502);
  echo 'Failed to fetch product from Airtable';
  exit();
}

// read body from stream written by cURL
rewind($bodyStream);
$responseBody = stream_get_contents($bodyStream);
fclose($bodyStream);

$data = json_decode($responseBody, true);

if (
  !isset($data['fields']) ||
  !isset($data['fields'][$env['airtable']['price_field']])
) {
  http_response_code(500);
  echo 'Price field missing in Airtable record';
  exit();
}

$price = $data['fields'][$env['airtable']['price_field']];
$productName = $data['fields'][$env['airtable']['name_field']] ?? 'Product';


/**
 * -------------------------------------------------
 * 4. Assemble payment payload (STOP HERE)
 * -------------------------------------------------
 */
$paymentPayload = [
  'merchant_id'  => $env['payfast']['merchant_id'],
  'merchant_key' => $env['payfast']['merchant_key'],
  'amount'       => number_format($price, 2, '.', ''),
  'item_name'    => $productName,
  'currency'     => $env['service']['currency'],
  'return_url'   => $env['service']['return_url'],
  'cancel_url'   => $env['service']['cancel_url'],
  'notify_url'   => $env['service']['notify_url'],
];

$signature = generatePayFastSignature(
  $paymentPayload,
  $env['payfast']['passphrase'] ?? ''
);

// $paymentPayload['signature'] = $signature;

$payfastEndpoint =
  ($env['payfast']['mode'] === 'sandbox')
    ? 'https://sandbox.payfast.co.za/eng/process'
    : 'https://www.payfast.co.za/eng/process';

// Output payload for inspection (dev only)
// header('Content-Type: application/json; charset=utf-8');
// echo json_encode([
//   'status'  => 'ok',
//   'payload' => $paymentPayload
// ], JSON_PRETTY_PRINT);

// exit();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Redirecting to PayFast…</title>
</head>
<body>
  <p>Redirecting to payment…</p>

  <form id="payfastForm" action="<?= htmlspecialchars($payfastEndpoint) ?>" method="post">
    <?php foreach ($paymentPayload as $key => $value): ?>
      <input type="hidden"
             name="<?= htmlspecialchars($key) ?>"
             value="<?= htmlspecialchars($value) ?>">
    <?php endforeach; ?>
  </form>

  <script>
    document.getElementById('payfastForm').submit();
  </script>
</body>
</html>
<?php
exit();
