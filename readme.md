
# Payment Integration Service (PayFast)

## Overview

This service is a **minimal, server-side PayFast payment integration** designed to:

* Resolve **authoritative product pricing** from Airtable
* Generate **PayFast-compliant signatures**
* Redirect users to PayFast using the **official auto-submit form flow**
* Keep all secrets **server-side**
* Avoid premature complexity (orders, carts, webhooks)

It is intentionally **not** a full e-commerce system.

---

## Core Principles

* **Authoritative pricing**
  Prices are resolved server-side from Airtable. The client never supplies amounts.

* **Server-signed payments**
  All PayFast payloads are signed using the merchant passphrase.

* **Browser-based handoff**
  Payments are initiated via an auto-submitting HTML form (PayFast-recommended flow).

* **Minimal surface area**
  No SDKs, no client secrets, no duplicate order sources (yet).

---

## Current Capabilities

* ✅ Airtable record lookup by `product_id`
* ✅ Price and product name resolution
* ✅ PayFast payload assembly
* ✅ Live & sandbox endpoint switching
* ✅ Correct RFC1738 signature generation
* ✅ Auto-redirect to PayFast checkout

---

## What This Service Does *Not* Do (Yet)

* ❌ Order creation or persistence
* ❌ Customer or billing data storage
* ❌ IPN (Instant Payment Notification) handling
* ❌ Invoice generation
* ❌ Refunds or reconciliation automation

These are **explicitly out of scope** for the current milestone.

---

## Flow (High Level)

1. Request received (product ID)
2. Environment + secrets loaded
3. Airtable queried for authoritative product data
4. PayFast payload assembled
5. Signature generated server-side
6. Browser auto-redirected to PayFast

---

## Configuration

### `env.json` (not committed)

```json
{
  "airtable": {
    "base_id": "appXXXXXXXXXXXX",
    "table": "Products",
    "price_field": "Price",
    "name_field": "Name",
    "description_field": "Description"
  },
  "payfast": {
    "mode": "live",
    "merchant_id": "XXXXXXX",
    "merchant_key": "XXXXXXXX",
    "passphrase": "your-passphrase-here"
  }
}
```

### `config.json` (not committed)

Used for host-based auth headers (e.g. Airtable API tokens).

---

## Security Notes

* `env.json` and `config.json` **must not be publicly accessible**
* `.htaccess` blocks access to sensitive JSON files
* Merchant credentials and passphrase **never leave the server**
* Anyone cloning this repo must supply **their own credentials**

---

## Why Auto-Submit Form?

PayFast does **not** support server-to-server payment creation.

The browser POST flow is:

* Required
* Secure
* Officially supported
* Compatible with 3-D Secure and redirects

---

## Intended Next Milestones (Not Implemented)

* Minimal order creation (for physical goods & invoicing)
* IPN validation
* Accounting / invoice integration
* Optional billing info collection

These will be added **incrementally**, not all at once.

---

## Philosophy

> This service is a **payment rail**, not a storefront, CRM, or accounting system.

It does one thing well:
**move a user from price → payment correctly and safely.**
