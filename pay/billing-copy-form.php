<?php
// Receive payload from index.php
$payload = $_POST ?? [];

// Safe defaults
$amount = $payload['amount'] ?? '0.00';
$currency = $payload['currency'] ?? 'ZAR';
$itemName = $payload['item_name'] ?? 'Product';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Billing Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">

                        <h5 class="fw-semibold mb-1">Billing details</h5>
                        <div class="text-muted mb-3">
                            <?= htmlspecialchars($itemName) ?>
                        </div>

                        <div class="fw-bold text-primary mb-4">
                            <?= htmlspecialchars($currency) ?> <?= htmlspecialchars($amount) ?>
                        </div>

                        <form method="post" action="billing-copy-create.php">

                            <!-- Billing fields -->
                            <div class="mb-3">
                                <label class="form-label">Full name</label>
                                <input class="form-control" name="billing_name" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email address</label>
                                <input class="form-control" type="email" name="billing_email" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone number (optional)</label>
                                <input class="form-control" name="billing_phone">
                            </div>



                            <!-- Address -->
                            <div class="mb-3">
                                <label class="form-label">Street address</label>
                                <input class="form-control" name="addr_street" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Apartment / Unit (optional)</label>
                                <input class="form-control" name="addr_unit">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City / Town</label>
                                    <input class="form-control" name="addr_city" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Province / Region</label>
                                    <input class="form-control" name="addr_region" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Postal / ZIP Code</label>
                                    <input class="form-control" name="addr_postcode" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country</label>
                                    <input class="form-control" name="addr_country" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">VAT / Company number (optional)</label>
                                <input class="form-control" name="billing_vat">
                            </div>

                            <!-- Carry full payment payload forward -->
                            <?php foreach ($payload as $key => $value): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>"
                                    value="<?= htmlspecialchars($value) ?>">
                            <?php endforeach; ?>

                            <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                Continue to payment
                            </button>
                        </form>

                        <div class="text-center small text-muted mt-3">
                            You’ll be redirected to PayFast to complete payment
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

</body>

</html>