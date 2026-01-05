<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/CurlClient.php';
require __DIR__ . '/helpers.php';

/**
 * -------------------------------------------------
 * 1. Validate required billing fields
 * -------------------------------------------------
 */
$required = [
    'billing_name',
    'billing_email',
    'addr_street',
    'addr_city',
    'addr_region',
    'addr_postcode',
    'addr_country'
];

foreach ($required as $key) {
    if (empty($_POST[$key])) {
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
    echo json_encode(['ok' => false, 'error' => 'env.json missing']);
    exit;
}

$env = json_decode(file_get_contents($envFile), true);

/**
 * -------------------------------------------------
 * 3. Create billing copy in Airtable
 * -------------------------------------------------
 */
$airtableUrl =
    $env['airtable']['base_url']
    . $env['airtable']['base_id']
    . '/'
    . rawurlencode($env['airtable']['paymentCopy']['table']);

// 🔐 REQUIRED: host-scoped auth
$config = readConfig($airtableUrl);
$headers = [];

if (!empty($config['headers'])) {
    foreach ($config['headers'] as $key => $value) {
        $headers[] = $key . ': ' . $value;
    }
}
$headers[] = 'Content-Type: application/json';

// Map form → Airtable fields
$fields = [
    'Billing Name' => $_POST['billing_name'],
    'Billing Email' => $_POST['billing_email'],
    'Billing Phone' => $_POST['billing_phone'] ?? '',

    'Street' => $_POST['addr_street'],
    'Unit' => $_POST['addr_unit'] ?? '',
    'City' => $_POST['addr_city'],
    'Region' => $_POST['addr_region'],
    'Postcode' => $_POST['addr_postcode'],
    'Country' => $_POST['addr_country'],

    // Snapshot (safe)
    'Amount' => $_POST['amount'],
    'Currency' => $env['service']['currency'],
    'Product' => $_POST['item_name'],

    $env['airtable']['paymentCopy']['status_field'] => 'Pending'
];


$payload = json_encode(['fields' => $fields]);

$client = new CurlClient(false);
$bodyStream = fopen('php://temp', 'w+');

$info = $client->post($airtableUrl, $headers, $payload, $bodyStream);

if (!$info || !in_array($info['http_code'], [200, 201])) {
    rewind($bodyStream);
    $err = stream_get_contents($bodyStream);
    fclose($bodyStream);

    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to create billing copy',
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
        'error' => 'Billing copy ID missing from Airtable response'
    ]);
    exit;
}

/**
 * -------------------------------------------------
 * 4. Return ONLY the billing copy ID
 * -------------------------------------------------
 */
echo json_encode([
    'ok' => true,
    'billing_copy_id' => $response['id']
], JSON_PRETTY_PRINT);
