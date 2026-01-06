<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/CurlClient.php';
require __DIR__ . '/helpers.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

/**
 * -------------------------------------------------
 * 1. Validate required delivery fields
 * -------------------------------------------------
 */
$required = [
    'delivery_name',
    'delivery_email',
    'addr_street',
    'addr_city',
    'addr_region',
    'addr_postcode',
    'addr_country'
];


foreach ($required as $key) {
    if (
        !array_key_exists($key, $input) ||
        trim((string)$input[$key]) === ''
    ) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => "$key is required"
        ]);
        exit;
    }
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
 * 3. Create order shell in Airtable
 * -------------------------------------------------
 */
$airtableUrl =
    $env['airtable']['base_url']
    . $env['airtable']['base_id']
    . '/'
    . rawurlencode($env['airtable']['paymentCopy']['table']);

// Host-scoped auth
$config = readConfig($airtableUrl);
$headers = [];

if (!empty($config['headers'])) {
    foreach ($config['headers'] as $key => $value) {
        $headers[] = $key . ': ' . $value;
    }
}
$headers[] = 'Content-Type: application/json';

// Delivery-only fields (NO product economics)

$fields = [];

$mapping = $env['airtable']['paymentCopy']['fields'] ?? [];

foreach ($mapping as $inputKey => $airtableField) {
    if (array_key_exists($inputKey, $input) && $input[$inputKey] !== '') {
        $fields[$airtableField] = $input[$inputKey];
    }
}

// System fields
$fields[$env['airtable']['paymentCopy']['status_field']] = 'Pending';


$payload = json_encode(['fields' => $fields]);

$client = new CurlClient(false);
$bodyStream = fopen('php://temp', 'w+');

$info = $client->post($airtableUrl, $headers, $payload, $bodyStream);

if (!$info || !in_array($info['http_code'], [200, 201], true)) {
    rewind($bodyStream);
    $err = stream_get_contents($bodyStream);
    fclose($bodyStream);

    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to create order',
        'details' => $err
    ]);
    exit;
}

rewind($bodyStream);
$response = json_decode(stream_get_contents($bodyStream), true);
fclose($bodyStream);

if (empty($response['id'])) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Order ID missing from Airtable response'
    ]);
    exit;
}

/**
 * -------------------------------------------------
 * 4. Return order ID only
 * -------------------------------------------------
 */
echo json_encode([
    'ok' => true,
    'order_id' => $response['id']
]);
