
# Payment Integration Service (PayFast + Airtable)

A lightweight, deploy-anywhere **payment integration service** designed to:

* Resolve **authoritative pricing** from Airtable
* Create optional **billing copies / orders** for invoicing and compliance
* Hand off payments securely to **PayFast**
* Keep PayFast as the **source of truth** for transactions

This service is intentionally minimal, transparent, and framework-free.

---

## Goals & Philosophy

* **PayFast is the payment authority**
* This service **augments**, not replaces, PayFast records
* Orders / billing copies are **administrative artifacts**, not payment truth
* Automation (webhooks, status sync) is optional and deferred until needed

> Think of this service as a *payment coordinator*, not a full e-commerce engine.

---

## Tech Stack

* **PHP** (plain PHP, no framework)
* **Airtable API** (authoritative product pricing, order storage)
* **PayFast** (payments)
* **Bootstrap 5 (CDN)** for UI
* **cURL** for outbound API calls

Designed to run in **any webroot or subdirectory**:

```
example.com/pay/
example.com/services/pay/
```

---

## Repository Structure

```
/pay
├── index.php                     # Price resolution + flow router
├── billing-copy-form.php          # Stripe-style billing form (UI)
├── billing-copy-create.php        # Creates billing copy + returns PayFast payload (AJAX)
├── billing-copy-pull-update.php   # (Planned) Pull status from PayFast
├── CurlClient.php                 # cURL wrapper
├── helpers.php                    # Config helpers
├── config.json.example            # API config example (committed)
├── env.json.example               # Environment config example (committed)
├── .htaccess                      # Blocks access to sensitive files
```

> `config.json` and `env.json` are **not committed**.

---

## Configuration Files

### `config.json` (not committed)

Used for host-based API auth (e.g. Airtable).

```json
{
  "api.airtable.com": {
    "headers": {
      "Authorization": "Bearer YOUR_AIRTABLE_TOKEN"
    }
  }
}
```

### `env.json` (not committed)

Controls environment-specific behavior.

```json
{
  "airtable": {
    "base_id": "appXXXXXXXX",
    "table": "Products",
    "price_field": "Price",
    "name_field": "Name"
  },
  "payfast": {
    "merchant_id": "XXXX",
    "merchant_key": "XXXX",
    "passphrase": "OPTIONAL",
    "mode": "live"
  },
  "flow": "billing"
}
```

---

## Security

* Sensitive files (`config.json`, examples) are blocked via `.htaccess`
* **PayFast passphrase** protects payload integrity
* Pricing is **never accepted from the client**
* Airtable is the **authoritative price source**

---

## Core Concepts

### Authoritative Pricing

* Price is fetched server-side from Airtable
* Client never supplies amount
* Prevents price tampering

### Orders / Billing Copies

* Optional
* Created **before redirecting to PayFast**
* Used for:

  * invoices
  * B2B compliance
  * customer references

### Payment Authority

* PayFast is the **only** source of truth for payment status
* Local order status (if used) is a **cache**, not authority

---

## Flows

The service supports **three flows**, controlled in `env.json`.

### 1. `debug`

* Returns JSON only
* No redirects
* Used for development / inspection

```json
{
  "status": "ok",
  "payload": { ... }
}
```

---

### 2. `no_billing`

* Fastest path
* No local order creation
* Autosubmits directly to PayFast

**Flow:**

1. Resolve price
2. Generate PayFast payload + signature
3. Redirect to PayFast

Use when:

* No invoice required
* Minimal friction checkout

---

### 3. `billing` (primary flow)

Stripe-style UX with billing copy.

**Flow:**

1. Resolve price (`index.php`)
2. Show billing form (`billing-copy-form.php`)
3. AJAX → create billing copy (`billing-copy-create.php`)
4. Receive PayFast payload
5. Auto-submit to PayFast

Billing info is stored **before** payment starts.

---

## UI

* Stripe-inspired two-column layout
* Left: product summary
* Right: billing details
* No card details collected locally
* Fully Bootstrap-based (utilities + inline styles only)

---

## What’s Implemented ✅

* Airtable price resolution
* PayFast payload + signature generation
* Multiple flow routing
* Stripe-style billing UI
* Secure config handling
* Autosubmit to PayFast
* AJAX-based billing copy creation (designed)

---

## What’s Pending ⏳

* `billing-copy-create.php` implementation (AJAX endpoint)
* Optional `billing-copy-pull-update.php`

  * Manual “refresh status”
  * Or webhook-triggered pull
* Optional webhook (ITN) integration
* Optional invoice PDF generation
* Optional accounting export (Xero, etc.)

---

## Compliance Notes

* **Invoices:** Supported via billing copies
* **Payment records:** PayFast dashboard is authoritative
* **Order status:** Optional, cached, non-authoritative
* **B2B readiness:** Billing fields support legal requirements

Webhook integration is **not required for compliance**, only for automation.

---

## Design Intent (Important)

This service intentionally avoids:

* Complex state machines
* Duplicating PayFast logic
* Premature automation

Everything can be **added later without breaking the architecture**.

---

## Summary

This payment service is:

* Secure
* Minimal
* Transparent
* Deployable anywhere
* Easy to reason about
* Easy to extend

It solves **today’s needs** without locking you into tomorrow’s complexity.
