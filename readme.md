

# Payment Integration Service

**PayFast + Airtable (Authoritative Pricing, Delivery-Ready, Frontend-Agnostic)**

A lightweight, deploy-anywhere **payment coordination service** designed to:

* Resolve **authoritative pricing** from Airtable at signing time
* Capture **delivery details** (not billing / B2B)
* Create a **local order reference** *before* payment
* Securely hand off payments to **PayFast**
* Keep **PayFast as the sole payment authority**

This service is intentionally minimal, transparent, and framework-free on the backend, while supporting **modern frontend frameworks (Vue / Quasar)**.

---

## Goals & Philosophy

* **PayFast is the payment authority**
* Pricing is **never trusted from the client**
* Local records are **administrative references**, not financial truth
* Payment signing happens **only after authoritative price resolution**
* Frontend UX and backend payment logic are **strictly separated**

> Think of this as a *payment coordinator*, not an e-commerce engine.

---

## Tech Stack

### Backend

* **PHP** (plain PHP, no framework)
* **Airtable API** (products + order copies)
* **PayFast** (payments)
* **cURL** (outbound API calls)

### Frontend

* **Vue / Quasar**
* Framework-agnostic (can also be used from any JS frontend)

Runs anywhere:

```
/pay/
example.com/pay/
example.com/services/pay/
```

---

## Repository Structure (Current)

```
/pay
├── confirm-price.php              # Fetch price + sign PayFast payload (JSON only)
├── generate-order-number.php      # Create order shell (delivery data only)
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

Per-host API authentication.

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

Controls Airtable mapping and PayFast behavior.

```json
{
  "airtable": {
    "base_url": "https://api.airtable.com/v0/",
    "base_id": "appXXXXXXXX",
    "table": "Art",
    "price_field": "Price",
    "name_field": "Title",
    "description_field": "Name (from Artist)",
    "paymentCopy": {
      "table": "Payments",
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

## Security Model (Critical)

* The client **never provides price**
* The client **never provides signed fields**
* **Price is fetched server-side at signing time**
* Signature is generated **last**, over the final payload
* Only **whitelisted PayFast extras** are accepted
* Sensitive configs are protected via `.htaccess`

> If the server signs it, the server must own it.

---

## Core Concepts

### Authoritative Pricing

* Price is resolved from Airtable **inside `confirm-price.php`**
* Prevents tampering, replay, and mismatch
* Display prices in the UI are **informational only**

### Delivery Details (not Billing)

* Checkout captures **delivery / contact information**
* No VAT, company, or B2B compliance required
* Can be extended later without breaking the flow

### Order Shell (Local Reference)

* Created **before** payment
* Stores:

  * delivery details
  * operational status
* Stored in Airtable (`Payments` table)
* Referenced in PayFast as `m_payment_id`

### Payment Authority

* **PayFast is the only source of truth**
* Local status is optional, cached, and administrative only

---

## Current Flow (Final, Correct)

### 1. Show Checkout (Frontend – Vue / Quasar)

* Product ID comes from the route
* Product summary shown on the left (informational only)
* Delivery form shown on the right

---

### 2. Generate Order Number

`POST /pay/generate-order-number.php`

* Accepts delivery details
* Creates Airtable order shell
* Returns:

  ```json
  {
    "ok": true,
    "order_id": "recXXXXXXXX"
  }
  ```

No price, no product, no signing.

---

### 3. Confirm Price & Sign

`POST /pay/confirm-price.php`

* Accepts:

  * `product_id`
  * `payload_extras[m_payment_id]`
* Fetches authoritative price from Airtable
* Builds PayFast payload
* Applies **whitelisted PayFast extras**
* Generates signature
* Returns JSON only:

```json
{
  "ok": true,
  "payfast_url": "https://www.payfast.co.za/eng/process",
  "fields": {
    "...": "..."
  }
}
```

---

### 4. Redirect to PayFast (Frontend)

* Frontend builds a form
* Auto-submits to PayFast
* No further mutation allowed

---

## PayFast Extras (Whitelisted)

Accepted via `payload_extras[...]`:

* `m_payment_id`
* `custom_str1` → `custom_str5`
* `custom_int1` → `custom_int5`
* `name_first`
* `name_last`
* `email_address`
* `cell_number`
* `language`
* `country`

**Never accepted from client:**

* `amount`
* `merchant_id`
* `currency`
* `notify_url`
* `signature`

---

## Notify URL (ITN)

* Server-to-server callback from PayFast
* Authoritative payment status
* Optional initially, recommended later

> Return / cancel URLs are UX only
> Notify URL is the truth channel

---

## Fulfillment (Delivery) Data

This service stores **references**, not courier logs.

### Minimal fulfillment model

* Fulfillment Method
* Fulfillment Reference (tracking number or URL)
* Fulfillment Status

Courier systems remain the evidence authority.

---

## Buy-Now Links

Products can be purchased directly via frontend routes such as:

```
/checkout/:productId
```

The backend never relies on query-string pricing.

---

## UI / UX

* Stripe-inspired two-column layout
* Unified card with soft shadow and rounded corners
* Left: product summary
* Right: delivery form
* No card data handled locally
* Progressive loading states:

  * “Generating order number”
  * “Confirming price”

---

## What’s Implemented ✅

* Airtable price resolution
* Delivery details capture
* Order shell creation
* PayFast payload + signature generation
* `m_payment_id` mapping
* Vue-based checkout UX
* Secure config handling
* Tamper-proof pricing flow

---

## Optional / Pending ⏳

* PayFast ITN handler
* Status sync into Airtable
* Invoice / receipt generation
* Accounting exports
* Courier integrations

All can be added **without changing the checkout flow**.

---

## Design Intent

This service intentionally avoids:

* Full cart systems
* Duplicating PayFast logic
* Client-side pricing authority
* Complex state machines

It solves **today’s needs cleanly**, while remaining extensible.

---

## Summary

This payment service is:

* Secure
* Minimal
* Delivery-compliant
* PayFast-correct
* Frontend-agnostic
* Easy to deploy
* Easy to extend

It delivers **Stripe-like UX**, **PayFast authority**, and **Airtable transparency** — without unnecessary complexity.
