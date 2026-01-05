<?php
$amount   = $_GET['amount']   ?? '0.00';
$currency = $_GET['currency'] ?? 'ZAR';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Billing details</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="bg-light">

  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-5">

        <div class="card shadow-sm border-0 rounded-3">
          <div class="card-body p-4">

            <h5 class="fw-semibold mb-1">Billing details</h5>
            <div class="text-primary fw-semibold mb-4">
              <?= htmlspecialchars($currency) ?> <?= htmlspecialchars($amount) ?>
            </div>

            <form method="post" action="billing-copy-create.php">

              <div class="mb-3">
                <label class="form-label small">Full name</label>
                <input class="form-control" name="name" required>
              </div>

              <div class="mb-3">
                <label class="form-label small">Email address</label>
                <input type="email" class="form-control" name="email" required>
              </div>

              <div class="mb-3">
                <label class="form-label small">Phone number (optional)</label>
                <input class="form-control" name="phone">
              </div>

              <div class="mb-3">
                <label class="form-label small">Billing address</label>
                <textarea class="form-control" name="address" rows="3"></textarea>
              </div>

              <div class="mb-4">
                <label class="form-label small">VAT / Company number (optional)</label>
                <input class="form-control" name="vat">
              </div>

              <!-- carry pricing forward -->
              <input type="hidden" name="amount" value="<?= htmlspecialchars($amount) ?>">
              <input type="hidden" name="currency" value="<?= htmlspecialchars($currency) ?>">

              <button
                type="submit"
                class="btn btn-primary w-100 fw-semibold"
                style="background-color:#6772e5;border-color:#6772e5"
              >
                Continue to payment
              </button>

            </form>

            <div class="text-center text-muted small mt-3">
              You’ll be redirected to PayFast to complete payment
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>

</body>
</html>
