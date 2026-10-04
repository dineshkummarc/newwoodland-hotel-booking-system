# SMS: phone verification and password recovery

Customers can reset their password by SMS code as well as by email. Only a
phone number the customer has **verified** can be used for recovery. They verify
it at **My Account → Phone Number**, at `/my-account/phone`.

## How it works

1. **Forgot Password → Phone Number.** The customer enters a country and a number,
   which is normalized to E.164 (for example `+9779812345678`).
2. If an account has that verified number, a random 6-digit code is texted to it.
   The response is always the same, whether or not the number is registered.
3. The customer enters the code. Codes expire after **5 minutes**. Each number gets
   **5 wrong attempts** per code, and then the code stops working.
4. After a correct code, the customer has **10 minutes** to set a new password.
   The code is single-use. Setting the password rotates `remember_token`, fires
   `PasswordReset`, invalidates other codes, and deletes any pending email reset link.

Other limits:
- Resend cooldown: 60 seconds per number.
- Codes per number: at most 5 per hour.
- Code requests per IP: at most 3 per minute and 20 per hour.
- Code guesses per IP: at most 10 per minute.

Codes are stored only as HMAC-SHA256 hashes keyed with `APP_KEY` (table `phone_otps`).
Rows older than a day are pruned by the scheduler (`model:prune`).

The existing email reset-link flow is unchanged.

## Environment variables

| Variable | Purpose |
|---|---|
| `SMS_DRIVER` | `log` (local dev), `sparrow` (production), `array` (tests only) |
| `SMS_DEFAULT_REGION` | Country assumed when a number has no `+code` (default `NP`) |
| `SPARROW_SMS_TOKEN` | Sparrow API token (**secret**, never commit it) |
| `SPARROW_SMS_FROM` | Sender identity Sparrow approved for your account |
| `SPARROW_SMS_URL` | Optional override (default `https://api.sparrowsms.com/v2/sms/`) |

With `SMS_DRIVER=log`, codes appear in `storage/logs/laravel.log`, so the whole
flow can be tested without a provider. **Nothing is actually texted** with
this driver. In production the log driver never records message bodies and
treats every send as failed. Phone verification then shows "We could not
send the SMS", and the error log records `SMS_DRIVER=log is in use in production`.

## Sparrow SMS setup

1. Create an account at https://web.sparrowsms.com and buy SMS credits.
2. Request a **sender identity** (for example your hotel's name). Sparrow must
   approve it; that value becomes `SPARROW_SMS_FROM`.
3. Copy the API **token** from the dashboard into `SPARROW_SMS_TOKEN` on the server's `.env` only.
4. If your account has IP whitelisting enabled, whitelist the server's outbound IP.
   Otherwise requests fail with error `1001 Invalid IP Address`.
5. Set `SMS_DRIVER=sparrow`, then run `php artisan config:cache`.
6. Test: verify your own number at `/my-account/phone`, then run a phone reset.

API reference: https://docs.sparrowsms.com/sms/outgoing_sendsms/
Failures are logged with a masked phone number and Sparrow's `response_code`
(for example 1002 invalid token, 1008 invalid sender, 1012/1013 no credits).
Tokens and message text are never logged.

**Sparrow only delivers to Nepali numbers.** Guests with foreign numbers are told
their country isn't supported yet when they try to verify. To support them, add
a driver for an international provider. Implement `App\Contracts\SmsSenderInterface`,
add a `match` arm in `AppServiceProvider::register()`, and optionally route
numbers by country.

## Deployment checklist

- `php artisan migrate`. This adds `users.phone_e164` and `users.phone_verified_at`
  (both nullable, non-destructive) and creates the `phone_otps` table.
- Check that the scheduler cron (`* * * * * php artisan schedule:run`) is running.
- Existing customers keep their old free-form `phone` value but have no verified
  phone until they verify at `/my-account/phone`.
- If an old number is later reassigned to someone else, staff can clear the
  account's `phone_e164` and `phone_verified_at`.
