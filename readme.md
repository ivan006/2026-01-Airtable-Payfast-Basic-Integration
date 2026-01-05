

# Payment Integration Service

**PayFast + Airtable (Authoritative Pricing, Delivery-Ready)** 

A lightweight, deploy-anywhere **payment integration service** designed to:

* Resolve **authoritative pricing** from Airtable
* Capture **delivery details** (formerly “billing”) for compliance
* Create a **local order / payment copy** before payment
* Hand off payments securely to **PayFast**
* Keep PayFast as the **source of truth** for transactions

This service is intentionally minimal, transparent, and framework-free.

---

## Goals & Philosophy

* **PayFast is the payment authority**
* This service **does not duplicate** PayFast state
* Local records are **administrative snapshots**, not truth
* Automation (ITN, status sync) is optional and additive

> Think of this as a *payment coordinator*, not an e-commerce engine.

---

## Tech Stack

* **PHP** (plain PHP, no framework)
* **Airtable API** (products + local payment copies)
* **PayFast** (payments)
* **Bootstrap 5 (CDN)** for UI
* **jQuery** (AJAX only)
* **cURL** for outbound API calls

Runs anywhere:

```
/pay/
example.com/pay/
example.com/services/pay/
```

---

## Repository Structure

```
/pay
├── index.php                     # Entry point + flow router
├── billing-copy-form.php          # Delivery details form (UI)
├── billing-copy-create.php        # Creates order + signs PayFast payload (AJAX)
├── billing-copy-pull-update.php   # (Planned) Manual / webhook sync
├── CurlClient.php                 # cURL wrapper (GET + POST)
├── helpers.php                    # Config + signature helpers
├── config.json.example            # API auth config (committed)
├── env.json.example               # Environment config (committed)
├── .htaccess                      # Protects sensitive files
```

> `config.json` and `env.json` are **never committed**.

---

## Configuration

### `config.json` (not committed)

Used for per-host API authentication.

```json
{
  "api.airtable.com": {
    "headers": {
      "Authorization": "Bearer YOUR_AIRTABLE_TOKEN"
    }
  }
}
```

---

### `env.json` (not committed)

Controls pricing, PayFast, and flow behavior.

```json
{
  "flow": "billing",

  "airtable": {
    "base_url": "https://api.airtable.com/v0/",
    "base_id": "appXXXXXXXX",
    "table": "Art",
    "price_field": "Price",
    "name_field": "Title",
    "description_field": "Name (from Artist)",
    "paymentCopy": {
      "table": "Payments",
      "auto_id_field": "ID",
      "status_field": "Status"
    }
  },

  "payfast": {
    "mode": "live",
    "merchant_id": "XXXX",
    "merchant_key": "XXXX",
    "passphrase": "OPTIONAL"
  },

  "service": {
    "currency": "ZAR",
    "return_url": "https://example.com/pay/return",
    "cancel_url": "https://example.com/pay/cancel",
    "notify_url": "https://example.com/pay/notify"
  }
}
```

---

## Security Model

* Pricing is **never trusted from the client**
* PayFast payloads are **signed server-side**
* Signature is generated **last**, over the final payload
* Sensitive configs blocked via `.htaccess`

---

## Core Concepts

### Authoritative Pricing

* Price is resolved server-side from Airtable
* Prevents tampering and mismatch

### Delivery Details (not Billing)

* The checkout form collects **delivery/shipping details**
* No VAT / B2B fields required at this stage
* Same data can be extended later if B2B is needed

### Local Payment Copy

* Created **before** redirecting to PayFast
* Used for:

  * delivery compliance
  * reconciliation
  * internal ops
* Stored in Airtable (`Payments` table)

### Payment Authority

* **PayFast is the only source of truth**
* Local status is optional and cached only

---

## Supported Flows

Controlled via `env.json → flow`.

### 1. `debug`

* No redirects
* JSON output only
* Development / inspection

---

### 2. `no_billing`

* Fastest path
* No local order
* Direct PayFast handoff

**Flow**

1. Resolve price
2. Sign PayFast payload
3. Redirect to PayFast

---

### 3. `billing` (primary)

Stripe-style UX with delivery capture.

**Flow**

1. Resolve price (`index.php`)
2. Show delivery form (`billing-copy-form.php`)
3. AJAX → `billing-copy-create.php`

   * creates Airtable record
   * adds `m_payment_id`
   * generates **final PayFast signature**
4. Auto-submit to PayFast

No client-side mutation after signing.

---

## PayFast Integration Details

### `m_payment_id`

* Set to the **Airtable record ID**
* Used for reconciliation and ITN matching
* Not visible/searchable in PayFast UI
* Present in **ITN payloads and CSV exports**

### Notify URL (ITN)

* Server-to-server callback from PayFast
* Authoritative payment status
* Optional initially, recommended later

> Return / cancel URLs are UX only.
> Notify URL is the truth channel.

---

## Fulfillment (Delivery) Data

This service stores **references**, not courier logs.

### Minimal fulfillment model (3 fields)

* **Fulfillment Method** (Courier / Collection)
* **Fulfillment Reference** (tracking number or URL)
* **Fulfillment Status** (Pending / Shipped / Delivered)

Courier systems remain the evidence authority.

---

## Buy-Now Links (Pay Links)

Products can be purchased directly via:

```
/pay/?product_id=RECORD_ID
```

Used by website CTAs (e.g. “Buy Now” buttons).

---

## UI

* Stripe-inspired two-column layout
* Left: product summary
* Right: delivery details
* No card data collected locally
* Bootstrap utilities + inline styles only
* AJAX submit with loading state

---

## What’s Implemented ✅

* Airtable price resolution
* Delivery details capture
* Airtable payment copy creation
* PayFast payload + signature generation
* `m_payment_id` mapping
* AJAX-driven checkout
* Buy-now pay links
* Secure config handling

---

## What’s Optional / Pending ⏳

* PayFast ITN (notify URL) handler
* Manual “pull payment status”
* Invoice / receipt PDF
* Accounting exports
* Courier integrations

All can be added **without changing the checkout flow**.

---

## Design Intent

This service intentionally avoids:

* Complex state machines
* Full cart systems
* Duplicating PayFast logic
* Premature automation

It solves **today’s needs cleanly**, while staying extensible.

---

## Summary

This payment service is:

* Secure
* Minimal
* Delivery-compliant
* PayFast-correct
* Easy to deploy
* Easy to extend

It provides **Stripe-like UX** with **PayFast authority** and **Airtable transparency**, without locking you into complexity.
