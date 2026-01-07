<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

/**
 * -------------------------------------------------
 * PayFast ITN endpoint
 * -------------------------------------------------
 * This implementation follows PayFast docs LITERALLY:
 * - Uses posted order
 * - Uses urlencode()
 * - Includes empty fields
 * - Stops at signature
 * - Appends passphrase AFTER param string
 * -------------------------------------------------
 */

// PayFast expects HTTP 200 immediately
header('HTTP/1.0 200 OK');
flush();

require __DIR__ . '/CurlClient.php';
require __DIR__ . '/helpers.php';

/**
 * -------------------------------------------------
 * 1. Capture POST payload
 * -------------------------------------------------
 */
$pfData = $_POST;

if (empty($pfData) || !is_array($pfData)) {
    exit('No POST data');
}

/**
 * -------------------------------------------------
 * 2. Load env.json
 * -------------------------------------------------
 */
$envFile = __DIR__ . '/env.json';
if (!file_exists($envFile)) {
    exit('env.json missing');
}

$env = json_decode(file_get_contents($envFile), true);

/**
 * -------------------------------------------------
 * 3. Build param string EXACTLY as per PayFast docs
 * -------------------------------------------------
 */
$pfParamString = '';

foreach ($pfData as $key => $val) {
    if ($key !== 'signature') {
        // IMPORTANT:
        // - urlencode (not rawurlencode)
        // - do NOT trim unless PayFast trimmed
        $pfParamString .= $key . '=' . urlencode($val) . '&';
    } else {
        break;
    }
}

// Remove trailing &
$pfParamString = substr($pfParamString, 0, -1);

/**
 * -------------------------------------------------
 * 4. Verify signature (DOC FUNCTION)
 * -------------------------------------------------
 */
if (!pfValidSignature(
    $pfData,
    $pfParamString,
    $env['payfast']['passphrase'] ?? null
)) {
    http_response_code(400);
    exit('Invalid signature');
}

/**
 * -------------------------------------------------
 * 5. Basic merchant validation
 * -------------------------------------------------
 */
if (
    empty($pfData['merchant_id']) ||
    $pfData['merchant_id'] !== $env['payfast']['merchant_id']
) {
    http_response_code(403);
    exit('Invalid merchant');
}

/**
 * -------------------------------------------------
 * 6. Optional: payment amount validation
 * (Recommended later)
 * -------------------------------------------------
 */
// Example (disabled for now):
// if (!pfValidPaymentData($expectedAmount, $pfData)) {
//     exit('Amount mismatch');
// }

/**
 * -------------------------------------------------
 * 7. Map PayFast status → Airtable status
 * -------------------------------------------------
 */
$statusMap = [
    'COMPLETE'  => 'Paid',
    'FAILED'    => 'Failed',
    'CANCELLED' => 'Cancelled'
];

$paymentStatus = $pfData['payment_status'] ?? 'UNKNOWN';
$airtableStatus = $statusMap[$paymentStatus] ?? 'Unknown';

$orderId = $pfData['m_payment_id'] ?? null;
if (!$orderId) {
    exit('Missing m_payment_id');
}

/**
 * -------------------------------------------------
 * 8. Update Airtable order record
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

if (!$info || $info['http_code'] !== 200) {
    http_response_code(502);
    exit('Failed to update Airtable');
}

/**
 * -------------------------------------------------
 * 9. Done
 * -------------------------------------------------
 */
echo 'OK';
