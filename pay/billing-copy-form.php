<?php
$payload = $_POST ?? [];
$amount = $payload['amount'] ?? '0.00';
$currency = $payload['currency'] ?? 'ZAR';
$itemName = $payload['item_name'] ?? 'Product';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Checkout</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container py-5">
        <div class="row justify-content-center">

            <div class="col-12 col-lg-9">
                <div class="card shadow-sm border-0 overflow-hidden">
                    <div class="row g-0">

                        <!-- LEFT: Product summary -->
                        <div class="col-md-5 bg-dark text-white p-4 d-flex flex-column justify-content-between"
                            style="min-height: 420px;">

                            <div>
                                <div class="fw-semibold small mb-2 opacity-75">
                                    Checkout
                                </div>

                                <h4 class="fw-bold mb-2">
                                    <?= htmlspecialchars($itemName) ?>
                                </h4>

                                <div class="display-6 fw-semibold mb-3">
                                    <?= htmlspecialchars($currency) ?> <?= htmlspecialchars($amount) ?>
                                </div>

                                <div class="opacity-75 small">
                                    Secure payment powered by PayFast
                                </div>
                            </div>

                            <!-- Placeholder visual (optional) -->
                            <!-- <div class="mt-4 text-center opacity-75">
                                <div class="border border-light rounded p-3">
                                    Product summary
                                </div>
                            </div> -->

                        </div>

                        <!-- RIGHT: Billing form -->
                        <div class="col-md-7 p-4 bg-white">

                            <h5 class="fw-semibold mb-3">Enter billing details</h5>

                            <form method="post" action="billing-copy-create.php">

                                <h6 class="fw-semibold mt-4 mb-3">Contact information</h6>
                                <div class="mb-3">
                                    <!-- <label class="form-label">Full name</label> -->
                                    <input class="form-control" name="billing_name" placeholder="Full name" required>
                                </div>

                                <div class="mb-3">
                                    <!-- <label class="form-label">Email</label> -->
                                    <input class="form-control" type="email" name="billing_email" placeholder="Email" required>
                                </div>

                                <div class="mb-3">
                                    <!-- <label class="form-label">Phone number (optional)</label> -->
                                    <input class="form-control" name="billing_phone" placeholder="Phone number (optional)">
                                </div>

                                <h6 class="fw-semibold mt-4 mb-3">Billing address</h6>
                                <div class="mb-3">
                                    <!-- <label class="form-label">Street address</label> -->
                                    <input class="form-control" name="addr_street" placeholder="Street address"
                                        required>
                                </div>

                                <div class="mb-3">
                                    <!-- <label class="form-label">Apartment / Unit (optional)</label> -->
                                    <input class="form-control" name="addr_unit"
                                        placeholder="Apartment / Unit (optional)">
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <!-- <label class="form-label">City / Town</label> -->
                                        <input class="form-control" name="addr_city" placeholder="City / Town" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <!-- <label class="form-label">Province / Region</label> -->
                                        <input class="form-control" name="addr_region" placeholder="Province / Region"
                                            required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <!-- <label class="form-label">Postal / ZIP</label> -->
                                        <input class="form-control" name="addr_postcode" placeholder="Postal / ZIP"
                                            required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <!-- <label class="form-label">Country</label> -->
                                        <input class="form-control" name="addr_country" placeholder="Country" required>
                                    </div>
                                </div>

                                <!-- <div class="mb-3">
                                    <label class="form-label">VAT / Company number (optional)</label>
                                    <input class="form-control" name="billing_vat" placeholder="">
                                </div> -->

                                <!-- Forward full payment payload -->
                                <?php foreach ($payload as $key => $value): ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>"
                                        value="<?= htmlspecialchars($value) ?>">
                                <?php endforeach; ?>

                                <button class="btn btn-dark w-100 fw-semibold mt-2">
                                    Continue to payment
                                </button>

                                <div class="small text-muted text-center mt-3">
                                    You will be redirected to PayFast to complete payment
                                </div>

                            </form>

                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>

</body>

</html>