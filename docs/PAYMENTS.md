# Payments

Online payment for room bookings. Three gateways are wired into the existing
booking → checkout flow:

| Gateway | Flow | Verified by |
|---|---|---|
| **eSewa** | Signed form POST to eSewa, return on success/failure URLs | eSewa status API (server-to-server) + redirect signature |
| **Khalti** | Server calls Khalti *initiate*, browser redirected to `payment_url` | Khalti *lookup* API (server-to-server) |
| **Fonepay** | Browser redirected to a signed Fonepay URL | Fonepay *verificationMerchant* API (server-to-server) |

**Bank Transfer** is a manual method (no gateway): the guest transfers to the
hotel's account and staff confirm receipt in the admin — see [Bank Transfer](#bank-transfer).
Cash is recorded by staff in the admin too (`BookingService::recordManualPayment`).

## Architecture

```
booking (pending) ──► /booking/{code}/pay  (checkout: PaymentController@show)
                          │  customer picks a gateway (POST, CSRF-protected)
                          ▼
   EsewaPaymentService / KhaltiPaymentService / FonepayPaymentService
        initiate()  → creates a `pending` Payment (amount = booking.total_price)
                          │  redirect to the gateway
                          ▼
   gateway → customer's browser → our return route (GET)
        handleReturn() → asks the gateway server-to-server whether it was paid
                          │  only a verified success continues
                          ▼
   BookingService::confirmAfterVerifiedPayment()  → booking `confirmed`
   PaymentController@respondToVerifiedPayment()   → confirmation email + page
```

All three services implement `App\Contracts\PaymentGatewayInterface`, share the
single `payments` table (`provider` column = `esewa` | `khalti` | `fonepay`) and
share the booking-confirmation step. Which methods appear on the checkout is
driven by `config/payments.php`.

### Routes

| Purpose | Method + URL | Route name |
|---|---|---|
| Checkout | GET `/booking/{code}/pay` | `booking.pay` |
| eSewa initiate | POST `/booking/{code}/pay` | `booking.pay.initiate` |
| eSewa success / failure | GET `/payment/esewa/success`, `/payment/esewa/failure` | `payment.esewa.success`, `payment.esewa.failure` |
| Khalti initiate | POST `/booking/{code}/pay/khalti` | `booking.pay.khalti.initiate` |
| Khalti return | GET `/payment/khalti/return` | `payment.khalti.return` |
| Fonepay initiate | POST `/booking/{code}/pay/fonepay` | `booking.pay.fonepay.initiate` |
| Fonepay return | GET `/payment/fonepay/return` | `payment.fonepay.return` |
| Bank Transfer choose / instructions | POST / GET `/booking/{code}/pay/bank-transfer` | `booking.pay.bank.initiate`, `booking.pay.bank.show` |

There are no webhook/IPN routes: all three gateways return the customer's own
browser to the site, so there is no unauthenticated POST callback to protect
with CSRF exemptions. The return routes are GET, rate-limited
(`payment-return`) and never change state on the strength of their query string.

### Database

No schema change was needed. The existing `payments` table already carries what
each gateway needs:

| Column | eSewa | Khalti | Fonepay |
|---|---|---|---|
| `provider` | `esewa` | `khalti` | `fonepay` |
| `transaction_uuid` (unique) | `<code>-<random>` | `purchase_order_id` | PRN (`<code without dashes><8 random>`, 20 chars) |
| `reference_id` | eSewa ref id (on success) | `pidx` | Fonepay trace id `UID` (on success) |
| `amount`, `currency` | booking total, NPR | booking total, NPR | booking total, NPR |
| `gateway_response` | verification + redirect data | initiate / lookup response | verification + non-secret return params |

The payer's user is reachable through `payments.booking_id → bookings.user_id`
(guest bookings have no user).

### Payment states

`Payment.status` is one of:

| State | Meaning |
|---|---|
| `pending` | Initiated, held by the gateway (eSewa `PENDING`/`AMBIGUOUS`, or `NOT_FOUND` while young; Khalti `Pending`/`Initiated`/`Partially Refunded`), or verification could not be completed yet (gateway unreachable/timeout). No money confirmed. Re-checked by `payments:reconcile`. |
| `completed` | Verified **server-side** with the gateway. The only state that confirms a booking. |
| `failed` | The gateway was reachable and did not confirm the payment (Khalti `Expired`/`Refunded`, or the amount/reference did not match). |
| `cancelled` | The customer cancelled: Fonepay before any transaction existed; Khalti `User canceled`. |

A `failed` or `cancelled` payment is **not final**: if the
gateway later reports it as paid and the return URL is hit again, it is
re-verified and can complete. `completed` is never downgraded.

## Security model

- **Amount is server-side.** Every gateway reads the amount from
  `bookings.total_price`; nothing amount-like from the request is read.
- **The browser is never trusted.** `status=Completed`, `PS=true`,
  `RC=successful`, etc. in a return URL are ignored; each service asks the
  gateway itself. Fonepay's verification request carries our own amount.
- **Idempotent.** The payment is re-read under a row lock before completing; a
  replayed callback for a completed payment returns immediately without
  re-verifying or re-confirming. `transaction_uuid` is unique. A Fonepay trace
  id (`UID`) already settled on one payment is refused on another.
- **Booking access.** The `confirmation_code` is a reference, **not** a
  credential. Every booking page and payment action is behind
  `booking.access` (`App\Support\BookingAccess`). It lets in:
  - the signed-in owner;
  - the browser session that created the booking, or that came back from a gateway with the attempt's high-entropy transaction reference;
  - a signed, expiring link from the guest's email.

  Cancelled and already-paid bookings are refused.
- **Production safety.** With `APP_ENV=production`, a gateway is usable only when
  explicitly configured for production with non-public credentials
  (`App\Services\Payments\GatewayEnvironmentGuard`). Otherwise it is hidden,
  refused, and its returns/reconciliation never confirm anything. Check with
  `php artisan payments:check-config`.
- **Reconciliation.** Payment never depends on the guest's browser returning.
  `payments:reconcile` settles eSewa (status API) and Fonepay (stored trace
  ids) attempts every 5 minutes.
  - `bookings:expire-abandoned` asks the gateway before releasing a booking, and waits while money may be in flight.
  - A payment completed after its booking expired, or for a room taken meanwhile, is **never auto-confirmed**. It is flagged for staff (admin dashboard + email); see `App\Services\Payments\PaymentReconciler`.
  - A booking paid twice is flagged for a refund.
- **Duplicate attempts.** Before a new eSewa/Fonepay attempt, earlier ones are
  re-checked. Paid → the booking is confirmed instead of charging again. Money
  possibly in flight → a new attempt is refused.
- **Confirmation email** is sent only after verification.
- **Secrets** come from `.env` only, are never rendered in Blade/JS, never put
  in redirect URLs (Fonepay sends only the HMAC), never logged, and never
  stored in `gateway_response`.
- **CSP:** `form-action` allows the gateways' domains (`*.khalti.com`,
  `*.fonepay.com`, eSewa) because browsers also check the redirect that answers
  the checkout form's POST.

## Environment variables

Names only — never commit real values. See `.env.example`.

```
# eSewa (unchanged)
ESEWA_ENABLED  ESEWA_MODE  ESEWA_MERCHANT_CODE  ESEWA_SECRET_KEY

# Khalti
KHALTI_ENABLED  KHALTI_MODE  KHALTI_SECRET_KEY

# Fonepay
FONEPAY_ENABLED  FONEPAY_MODE  FONEPAY_MERCHANT_CODE  FONEPAY_SECRET_KEY  FONEPAY_CURRENCY

# Bank Transfer
BANK_TRANSFER_ENABLED  BANK_TRANSFER_BANK_NAME  BANK_TRANSFER_ACCOUNT_NAME
BANK_TRANSFER_ACCOUNT_NUMBER  BANK_TRANSFER_BRANCH  BANK_TRANSFER_INSTRUCTIONS
BANK_TRANSFER_HOLD_HOURS
```

`*_MODE` is `sandbox` or `production`. A gateway is offered at checkout only when
`*_ENABLED=true` **and** its credentials are set. Khalti's e-Payment API needs
only the secret key (its public key belongs to the older widget flow and is not
used here).

### Local / sandbox

```
APP_ENV=local
ESEWA_MODE=sandbox        # eSewa's public UAT credentials are already in .env.example
KHALTI_MODE=sandbox       KHALTI_SECRET_KEY=<key from test-admin.khalti.com, or Khalti's public docs example key live_secret_key_68791341fdd94846a146f0457ff7b455 (sandbox only; refused in production mode)>
FONEPAY_MODE=sandbox      FONEPAY_MERCHANT_CODE / FONEPAY_SECRET_KEY = <dev portal credentials>
```

### Production

```
APP_ENV=production   APP_DEBUG=false   APP_URL=https://<your real https domain>
ESEWA_MODE=production     + your real eSewa merchant code and secret
KHALTI_MODE=production    KHALTI_SECRET_KEY=<live secret key from admin.khalti.com>
FONEPAY_MODE=production   FONEPAY_MERCHANT_CODE / FONEPAY_SECRET_KEY = <live credentials>
```

Return/redirect URLs are generated from `APP_URL`, so it must be the public
HTTPS address the gateways can send customers back to.

## Khalti

Khalti e-Payment (web checkout) — <https://docs.khalti.com/khalti-epayment/>

- Sandbox base `https://dev.khalti.com/api/v2/`, production `https://khalti.com/api/v2/`.
- Auth header: `Authorization: Key <secret>`.
- **Initiate** `POST epayment/initiate/` with `return_url`, `website_url`,
  `amount` (**paisa**, minimum 1000 = NPR 10), `purchase_order_id`,
  `purchase_order_name`, `customer_info`. Response: `pidx`, `payment_url`.
- Customer returns to `/payment/khalti/return?pidx=…&status=…`.
- **Lookup** `POST epayment/lookup/` `{pidx}` → `status`, `total_amount`,
  `transaction_id`. Only `Completed` **and** `total_amount` equal to the charged
  amount (in paisa) **and** a matching `pidx` is treated as paid.

| Khalti lookup status | `payments.status` | Booking |
|---|---|---|
| `Completed` (amount matches) | `completed` | confirmed (availability re-checked) |
| `Completed` (amount differs) | `failed` | unchanged; logged as error |
| `Pending`, `Initiated`, `Partially Refunded` | `pending` (hold) | unchanged |
| `User canceled` | `cancelled` | unchanged, can retry |
| `Expired`, `Refunded` | `failed` | unchanged, can retry |
| network error / 5xx / 401 / malformed | unchanged | unchanged, nothing decided |

- **Amount units:** DB stores NPR `decimal(10,2)`; Khalti uses integer paisa.
  `KhaltiPaymentService::toPaisa()` is the single conversion, used for both
  initiate and lookup comparison.
- **Checkout:** the payment page posts the chosen method to
  `POST /booking/{code}/pay/continue` (`booking.pay.continue`), which hands it to
  that method's existing initiate action.
- **No double charge:** before starting a new Khalti session for a booking, any
  open Khalti payment is re-checked: `Completed` → confirmed (no new charge),
  `Pending` → blocked, still-valid `Initiated` → same Khalti link reused.
- **Customer never returns:** `payments:reconcile-khalti` (scheduled every 5
  minutes) looks up pending Khalti payments and confirms/fails them, emailing the
  confirmation once. `bookings:expire-abandoned` also asks Khalti first and never
  cancels a booking that Khalti has paid or is still processing.
- **Ownership:** a booking linked to a customer account can only be paid by that
  signed-in customer. Guest bookings can be paid from the browser that made them,
  or via the signed link in the guest's email (`booking.access` middleware).
- **Receipt:** confirmation page, confirmation email and admin booking page show
  the Khalti transaction ID (`Payment::gatewayTransactionId()`) next to the pidx.
- **Keys:** only the secret key is used (server-side). There is no public key in
  the current Web Checkout API.
- Sandbox test wallet: Khalti IDs `9800000000`–`9800000005`, MPIN `1111`, OTP `987654`.

## Fonepay

Fonepay web payment (redirect). Implemented from Fonepay's own published
integration code — <https://github.com/fonepay/Fonepay-Java> (URLs and signing
strings) and <https://github.com/fonepay/Fonepay-Spring-Boot-Integration-Sample>
(hosts and return handling). Fonepay's PDF/Google-Doc spec is no longer publicly
reachable, so **confirm these details against the documentation your Fonepay
onboarding contact provides**.

- Hosts: sandbox `https://dev-clientapi.fonepay.com`, production `https://clientapi.fonepay.com`.
- **Request** (browser redirect, GET) `/api/merchantRequest?PID&MD&AMT&CRN&R1&R2&DT&PRN&RU&DV`
  - `PID` merchant code · `MD=P` · `AMT` amount in rupees (`4500.00`) · `CRN` currency
  - `DT` date `MM/dd/yyyy` (Nepal time) · `PRN` our unique reference (3–25 chars)
  - `R1` remarks · `R2=N/A` · `RU` return URL (`/payment/fonepay/return`)
  - `DV` = HMAC-SHA512 (upper-case hex, keyed with the secret) of `PID,MD,PRN,AMT,CRN,DT,R1,R2,RU`
- **Return**: Fonepay redirects to `RU` with `PRN`, `PID`, `PS`, `RC`, `UID`,
  `BC`/`BID`, `INI`, `P_AMT`, `R_AMT`, `DV`. These are *not* trusted.
- **Verification** (server-to-server, GET) `/api/merchantRequest/verificationMerchant?PRN&PID&BID&AMT&UID&DV`
  - `AMT` is **our** stored amount; `DV` = HMAC-SHA512 of `PID,AMT,PRN,BID,UID`
  - The reply is read in **either** format. Fonepay's library documents XML
    (`<response>`: paid only when `response_code` is `successful`); the dev server
    was observed replying with JSON (`{"message":…,"isSuccess":…}`, e.g. HTTP 409
    `No data found for verification` = definitively not paid). JSON counts as paid
    only with an explicit `isSuccess: true` that no `response_code` contradicts.
    In both formats `uniqueId` (if present) must match `UID` and `txnAmount` (if
    present) must not be below the booking total. Anything else never confirms.
  - **Verified live against `dev-clientapi.fonepay.com`:** the signed request is
    accepted (303 to the Fonepay payment page), while a wrong DV or a tampered
    amount is rejected (409 `Data Validation Failed`). **Not yet observed:** the
    JSON body of a *successful* verification and the exact query the browser
    returns after a real sandbox payment — complete one dev payment and check
    `payments.gateway_response` to confirm both.
- Return with no `UID`/`BID` (customer backed out) → payment `cancelled`, nothing
  to verify. Fonepay unreachable/unreadable during verification → payment stays
  `pending`, the customer is told not to pay again, and the same return URL can
  be retried.
- `FONEPAY_CURRENCY` (Fonepay's `CRN`) defaults to `NPR`; Fonepay's own sample
  library defaults to `NP`. Use whichever your merchant account expects.

## Bank Transfer

No external service, so nothing here can be "verified" by code — a human checks
the bank statement.

1. Guest picks **Bank Transfer** → a `pending` `bank_transfer` payment is recorded
   (amount = booking total; picking it again reuses the same row) and the guest
   sees the hotel's account details, the exact amount, and the booking code to
   write in the transfer remarks. The booking stays `pending`, never confirmed.
2. **Hold:** a booking with a fresh pending bank-transfer payment is held for
   `BANK_TRANSFER_HOLD_HOURS` (default 24) instead of the normal 30 minutes — in
   the availability check, `bookings:expire-abandoned`, and
   `BookingService::expireIfAbandoned`. After that it is released like any other
   abandoned booking.
3. Staff open the booking in the admin, and once the money is in the account use
   **Confirm Bank Transfer Received**. That completes the same payment row
   (amount is always the booking total, the optional reference is the bank's
   transaction number), confirms the booking through the normal
   `confirmAfterVerifiedPayment` availability re-check, and emails the guest.

Offered at checkout only when `BANK_TRANSFER_ENABLED=true` **and** the account name
and number are set. The details are shown to guests, so they are not secrets, but
keep them in `.env` so they can change without a deploy.

## eSewa

ePay v2 — signed form POST. The base64 `data` on the return is used **only** to
find our payment row (by its high-entropy `transaction_uuid`). Its signature is
checked and logged, but whether money was taken is decided **only** by eSewa's
server-to-server status API, for exactly our transaction uuid, product code and
amount. The failure URL is verified the same way. A forged "failure" can't cancel
a payment eSewa completed.

| eSewa status | Result |
|---|---|
| `COMPLETE` (exact match) | `completed`, booking confirmed (or flagged for staff if it expired / the room was taken) |
| `PENDING`, `AMBIGUOUS` | stays `pending`; the guest is told not to pay again |
| `NOT_FOUND` | stays `pending` while younger than `esewa.abandon_after_minutes` (180), then `failed` |
| `CANCELED` | `cancelled` |
| `FULL_REFUND`, `PARTIAL_REFUND` | `failed` + flagged for staff |
| timeout / 5xx / unreadable | unchanged — retried by `payments:reconcile` |

Sandbox uses eSewa's public test credentials in `.env.example`. These are
refused when `APP_ENV=production`.

## Testing

```
php artisan test
php artisan test --filter=FonepayPaymentTest
php artisan test --filter=KhaltiPaymentTest
php artisan test --filter=KhaltiGatewayFailureTest
php artisan test --filter=PaymentTest          # eSewa
```

External APIs are faked with `Http::fake()`; no real credentials or network
are used. These prove the code follows each gateway's documented protocol — they
are not a substitute for a real sandbox transaction.

Manual sandbox check for each gateway: set its env vars, `*_ENABLED=true`, make a
booking, choose the gateway on `/booking/{code}/pay`, complete/cancel/abandon the
payment, and confirm the booking page, the `payments` row and the email.

## Common errors

| Symptom | Likely cause |
|---|---|
| Gateway not shown as "Available" | `*_ENABLED` not `true`, or credentials missing (Khalti/Fonepay hide themselves). |
| "…could not start your payment right now" | Gateway rejected/could not receive the initiate call — check `storage/logs` (status only, no secrets), the secret key, and `*_MODE`. |
| Khalti 401 / "Invalid token" | Wrong key for the mode (sandbox key with `production`, or vice-versa). |
| Fonepay redirect rejected / signature error | Wrong `FONEPAY_SECRET_KEY`/`MERCHANT_CODE`, wrong `FONEPAY_MODE`, `RU` not whitelisted for the merchant, or `FONEPAY_CURRENCY` mismatch. |
| "We could not confirm your payment with Fonepay just yet" | Fonepay's verification API was unreachable; the payment is left `pending`. Check with Fonepay before re-charging the customer. |
| Payment `completed` but booking still `pending` | The room was taken by another booking while the payment was in flight — existing rule in `confirmAfterVerifiedPayment`; needs staff to refund or reassign. |
| Customer returns to the homepage | Return URL carried no known reference and the session had no pending attempt. |

## Deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` is the public HTTPS domain
- [ ] Real credentials set in the server `.env` only; `*_MODE=production`
- [ ] Return URLs registered/whitelisted in each merchant dashboard (below)
- [ ] `php artisan migrate --force` (no new migrations for payments)
- [ ] One real low-value transaction per gateway: success, cancel, and a replay of the return URL
- [ ] Mail is configured — the confirmation email is only sent after verification
- [ ] The scheduler is running (`ExpireAbandonedBookings`) so unpaid holds are released
- [ ] Logs and `payments.gateway_response` checked for secrets (none should appear)

### Merchant dashboard setup (cannot be done from code)

- **Khalti** — create a merchant at admin.khalti.com (sandbox: test-admin.khalti.com),
  copy the *live secret key*; make sure the website/return domain is allowed.
- **Fonepay** — obtain the merchant code and secret key (dev portal
  dev-merchant-login.fonepay.com; live portal login.fonepay.com), register the
  return URL `https://<domain>/payment/fonepay/return`, and confirm the accepted
  `CRN` value and that your live merchant is enabled for web payments.
- **eSewa** — unchanged; production needs your live merchant code and secret.
