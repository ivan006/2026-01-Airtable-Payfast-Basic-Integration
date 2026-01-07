<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

// PayFast sends form-encoded POST
$pfData = $_POST;

require __DIR__ . '/CurlClient.php';
require __DIR__ . '/helpers.php';

/**
 * -------------------------------------------------
 * 1. Load env.json
 * -------------------------------------------------
 */
$envFile = __DIR__ . '/env.json';
if (!file_exists($envFile)) {
    http_response_code(500);
    exit('env.json missing');
}

$env = json_decode(file_get_contents($envFile), true);

/**
 * -------------------------------------------------
 * 2. Verify PayFast signature
 * -------------------------------------------------
 */
$pfSignature = $pfData['signature'] ?? '';
unset($pfData['signature']);







$calculated = pfValidSignature(
    $pfData,
    $env['payfast']['passphrase'] ?? ''
);

if ($pfSignature !== $calculated) {
    http_response_code(400);
    exit('Invalid signature');
}

/**
 * -------------------------------------------------
 * 3. Validate required PayFast fields
 * -------------------------------------------------
 */
$required = [
    'm_payment_id',
    'payment_status',
    'amount_gross',
    'merchant_id'
];

foreach ($required as $key) {
    if (empty($pfData[$key])) {
        http_response_code(400);
        exit("Missing $key");
    }
}

// Merchant safety check
if ($pfData['merchant_id'] !== $env['payfast']['merchant_id']) {
    http_response_code(403);
    exit('Invalid merchant');
}

$orderId = $pfData['m_payment_id'];
$status = $pfData['payment_status'];

/**
 * -------------------------------------------------
 * 4. Map PayFast status → Airtable status
 * -------------------------------------------------
 */
$statusMap = [
    'COMPLETE' => 'Paid',
    'FAILED' => 'Failed',
    'CANCELLED' => 'Cancelled'
];

$airtableStatus = $statusMap[$status] ?? 'Unknown';

/**
 * -------------------------------------------------
 * 5. Update order record in Airtable
 * -------------------------------------------------
 */
$airtableUrl =
    $env['airtable']['base_url']
    . $env['airtable']['base_id']
    . '/'
    . rawurlencode($env['airtable']['paymentCopy']['table'])
    . '/'
    . $orderId;

$config = readConfig($airtableUrl);
$headers = [];

if (!empty($config['headers'])) {
    foreach ($config['headers'] as $k => $v) {
        $headers[] = "$k: $v";
    }
}
$headers[] = 'Content-Type: application/json';

$payload = json_encode([
    'fields' => [
        $env['airtable']['paymentCopy']['status_field'] => $airtableStatus
    ]
]);

$client = new CurlClient(false);
$bodyStream = fopen('php://temp', 'w+');
$info = $client->patch($airtableUrl, $headers, $payload, $bodyStream);

if (!$info || !in_array($info['http_code'], [200], true)) {
    http_response_code(502);
    exit('Failed to update order');
}

/**
 * -------------------------------------------------
 * 6. Acknowledge ITN
 * -------------------------------------------------
 */
http_response_code(200);
echo 'OK';
