<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

require __DIR__ . '/CurlClient.php';
require __DIR__ . '/helpers.php';

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
  'amount'     => number_format($price, 2, '.', ''),
  'item_name'  => $productName,
  'currency'   => $env['service']['currency'],
];

// Output payload for inspection (dev only)
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'status'  => 'ok',
  'payload' => $paymentPayload
], JSON_PRETTY_PRINT);
