<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/CurlClient.php';
require __DIR__ . '/helpers.php';

/**
 * -------------------------------------------------
 * 1. Accept required inputs (JSON ONLY)
 * -------------------------------------------------
 */
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid JSON body'
    ]);
    exit;
}

$productId = $input['product_id'] ?? null;

if (!$productId) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'product_id is required'
    ]);
    exit;
}

// Optional payload extras
$extras = [];
if (isset($input['payload_extras']) && is_array($input['payload_extras'])) {
    $extras = $input['payload_extras'];
}



/**
 * -------------------------------------------------
 * 2. Load env.json
 * -------------------------------------------------
 */
$envFile = __DIR__ . '/env.json';
if (!file_exists($envFile)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'env.json missing'
    ]);
    exit;
}

$env = json_decode(file_get_contents($envFile), true);

/**
 * -------------------------------------------------
 * 3. Fetch authoritative product + price from Airtable
 * -------------------------------------------------
 */
$airtableUrl =
    $env['airtable']['base_url']
    . $env['airtable']['base_id']
    . '/'
    . rawurlencode($env['airtable']['table'])
    . '/'
    . $productId;

// Airtable auth headers
$config = readConfig($airtableUrl);
$headers = [];

if (!empty($config['headers'])) {
    foreach ($config['headers'] as $key => $value) {
        $headers[] = $key . ': ' . $value;
    }
}

$client = new CurlClient(false);
$bodyStream = fopen('php://temp', 'w+');

$info = $client->get($airtableUrl, $headers, $bodyStream);

if (!$info || $info['http_code'] !== 200) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to fetch product from Airtable'
    ]);
    exit;
}

rewind($bodyStream);
$data = json_decode(stream_get_contents($bodyStream), true);
fclose($bodyStream);

if (
    empty($data['fields']) ||
    !isset($data['fields'][$env['airtable']['price_field']])
) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Price field missing in Airtable record'
    ]);
    exit;
}

/**
 * -------------------------------------------------
 * 4. Resolve authoritative fields
 * -------------------------------------------------
 */
$price = (float) $data['fields'][$env['airtable']['price_field']];

$productNameRaw = $data['fields'][$env['airtable']['name_field']] ?? 'Product';
$productName = is_array($productNameRaw)
    ? implode(', ', $productNameRaw)
    : $productNameRaw;

$productDescRaw = $data['fields'][$env['airtable']['description_field']] ?? '';
$productDesc = is_array($productDescRaw)
    ? implode(', ', $productDescRaw)
    : $productDescRaw;

/**
 * -------------------------------------------------
 * 5. Build base PayFast payload (authoritative)
 * -------------------------------------------------
 */
$payfastFields = [
    'merchant_id' => $env['payfast']['merchant_id'],
    'merchant_key' => $env['payfast']['merchant_key'],
    'amount' => number_format($price, 2, '.', ''),
    'item_name' => $productName,
    'item_description' => $productDesc,
    'currency' => $env['service']['currency'],
    'return_url' => $env['service']['return_url'],
    'cancel_url' => $env['service']['cancel_url'],
    'notify_url' => $env['service']['notify_url'],
];

/**
 * -------------------------------------------------
 * 6. Apply whitelisted payload extras
 * -------------------------------------------------
 */
$allowedExtras = [
    // Primary merchant reference
    'm_payment_id',

    // Merchant custom fields
    'custom_str1',
    'custom_str2',
    'custom_str3',
    'custom_str4',
    'custom_str5',
    'custom_int1',
    'custom_int2',
    'custom_int3',
    'custom_int4',
    'custom_int5',

    // Customer prefills
    'name_first',
    'name_last',
    'email_address',
    'cell_number',

    // UX / locale
    'language',
    'country'
];



foreach ($extras as $key => $value) {
    if (!in_array($key, $allowedExtras, true)) {
        continue;
    }

    $payfastFields[$key] = $value;
}

// 🔒 Normalize payload BEFORE signing
// $payfastFields = array_filter(
//     $payfastFields,
//     fn ($v) => $v !== '' && $v !== null
// );

/**
 * -------------------------------------------------
 * 7. Generate signature (LAST)
 * -------------------------------------------------
 */
$payfastFields['signature'] = generateApiSignature(
    $payfastFields,
    $env['payfast']['passphrase'] ?? ''
);

/**
 * -------------------------------------------------
 * 8. Resolve PayFast endpoint
 * -------------------------------------------------
 */
$payfastEndpoint =
    ($env['payfast']['mode'] === 'sandbox')
    ? 'https://sandbox.payfast.co.za/eng/process'
    : 'https://www.payfast.co.za/eng/process';

/**
 * -------------------------------------------------
 * 9. Return JSON only
 * -------------------------------------------------
 */
echo json_encode([
    'ok' => true,
    'payfast_url' => $payfastEndpoint,
    'fields' => $payfastFields
], JSON_PRETTY_PRINT);
