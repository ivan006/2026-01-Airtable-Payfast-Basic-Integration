<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/CurlClient.php';
require __DIR__ . '/helpers.php';

/**
 * -------------------------------------------------
 * Helpers
 * -------------------------------------------------
 */
function generateApiSignature(array $pfData, ?string $passPhrase = null): string
{
    if ($passPhrase) {
        $pfData['passphrase'] = $passPhrase;
    }

    ksort($pfData);
    return md5(http_build_query($pfData, '', '&', PHP_QUERY_RFC1738));
}

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
    'addr_country',
    'amount',
    'item_name'
];

foreach ($required as $key) {
    if (empty($_POST[$key])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "$key is required"]);
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

// 🔐 Host-scoped auth (MANDATORY)
$config = readConfig($airtableUrl);
$headers = [];

if (!empty($config['headers'])) {
    foreach ($config['headers'] as $key => $value) {
        $headers[] = $key . ': ' . $value;
    }
}
$headers[] = 'Content-Type: application/json';

// Map billing fields → Airtable
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
$airtableResponse = json_decode(stream_get_contents($bodyStream), true);
fclose($bodyStream);

$billingRecordId = $airtableResponse['id'] ?? null;

/**
 * -------------------------------------------------
 * 4. Build PayFast payload
 * -------------------------------------------------
 */
$payfastFields = [
    'merchant_id' => $env['payfast']['merchant_id'],
    'merchant_key' => $env['payfast']['merchant_key'],
    'amount' => number_format($_POST['amount'], 2, '.', ''),
    'item_name' => $_POST['item_name'],
    'currency' => $env['service']['currency'],
    'return_url' => $env['service']['return_url'],
    'cancel_url' => $env['service']['cancel_url'],
    'notify_url' => $env['service']['notify_url'],
];

// Optional reference linkage
if ($billingRecordId) {
    $payfastFields['m_payment_id'] = $billingRecordId;
}

$payfastFields['signature'] = generateApiSignature(
    $payfastFields,
    $env['payfast']['passphrase'] ?? ''
);

$payfastUrl =
    ($env['payfast']['mode'] === 'sandbox')
    ? 'https://sandbox.payfast.co.za/eng/process'
    : 'https://www.payfast.co.za/eng/process';

/**
 * -------------------------------------------------
 * 5. Return JSON (client will submit to PayFast)
 * -------------------------------------------------
 */
echo json_encode([
    'ok' => true,
    'payfast_url' => $payfastUrl,
    'fields' => $payfastFields
], JSON_PRETTY_PRINT);
