# Deployment Guide — Newwoodland Hotel & Lounge

How to put this repository on a production server. It covers only what this
application actually needs. Related docs: [PAYMENTS.md](PAYMENTS.md),
[SMS.md](SMS.md), [BACKUPS.md](BACKUPS.md).

> Passing automated tests does not make a deployment production-ready. Do the
> **manual verification** checklist at the end with real credentials before
> taking real bookings.

## 1. Server requirements

| Requirement | Notes |
|---|---|
| **PHP 8.2+** | `composer.json` requires `^8.2`; developed on PHP 8.2. |
| **PHP extensions** | `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zlib`. Recommended: `intl`, `gd` (dompdf only needs GD to embed images; invoices currently have none). |
| **MySQL 8.0** | Developed against MySQL 8.0. InnoDB is required (row locks prevent double bookings). |
| **Composer 2** | For installing dependencies. |
| **Web server** | Nginx or Apache with PHP-FPM; document root = `public/`. |
| **HTTPS certificate** | e.g. Let's Encrypt. Required for secure cookies and HSTS. |
| **Process supervisor** | systemd or Supervisor, to keep the queue worker running. |
| **cron** | For Laravel's scheduler. |
| Node / npm | **Not required.** The Vite build pipeline was removed; CSS/JS are plain files in `public/`. |

## 2. Get the code and install dependencies

```bash
git clone <repository-url> /var/www/newwoodland
cd /var/www/newwoodland
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate        # only on FIRST install — never on an existing site
```

Back up the generated `APP_KEY` immediately (see [BACKUPS.md](BACKUPS.md)). It
encrypts guest document numbers and signs invoice and cancellation links.

## 3. Environment configuration (`.env`)

Every variable is documented in `.env.example`. Production-sensitive values:

| Variable | Production value | Why |
|---|---|---|
| `APP_ENV` | `production` | Enables production safeguards (e.g. eSewa refuses test credentials; the `log` SMS driver and `log` mailer send nothing, log an error and never write codes or reset links to the log). |
| `APP_DEBUG` | `false` | `true` leaks stack traces and config to visitors. |
| `APP_URL` | `https://your-domain` | Used for links in queued emails (built by the worker, not a web request) and signed URLs. Must be exact. |
| `APP_TIMEZONE` | `Asia/Kathmandu` | Booking dates, reminders and the scheduler use it. |
| `LOG_LEVEL` | `warning` or `error` | `debug` is noisy in production. |
| `DB_*` | real MySQL host/database/user/password | Use a dedicated MySQL user with rights on this database only. |
| `SESSION_DRIVER` | `database` (default) | |
| `SESSION_SECURE_COOKIE` | `true` | Only once HTTPS works. Cookies are never sent over plain HTTP. |
| `QUEUE_CONNECTION` | `database` (default) | Booking, cancellation, contact and reminder emails are queued. A worker must run (§7). |
| `CACHE_STORE` | `database` (default) | |
| `MAIL_*` | real SMTP | See §9. |
| `HSTS_ENABLED` | `true` once HTTPS is stable | See §8. |
| `ESEWA_*`, `FONEPAY_*`, `KHALTI_*` | real merchant credentials | See §11. |
| `SMS_DRIVER`, `SPARROW_SMS_*` | `sparrow` + real token/sender | See §10. |
| `CHECKIN_REQUIRES_PHOTO_ID`, `GUEST_DOCUMENTS_ENABLED` | `true` (defaults) | Hotel policy: photo ID at check-in (§13). |

Never commit `.env`. Never put real values in `.env.example`.

## 4. Database

```bash
php artisan migrate --force
```

**First install only:** seed the catalogue, settings and the first admin:

```bash
php artisan db:seed --force
```

`AdminSeeder` creates `admin@newwoodlandhotel.com` as **super_admin** with a random
16-character password that is printed **once** to the console. Store it, sign
in, and change it (Admin → Profile). The seeders are idempotent, so re-running
them never resets an existing admin. Then fill in **Admin → Settings**: hotel
details, bank transfer details, cancellation policy and discounts.

## 5. Storage and permissions

```bash
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

- Public uploads (room photos, gallery, testimonials, bank QR) go to
  `storage/app/public` and are served via the `public/storage` symlink.
- Guest ID scans (if enabled) go to `storage/app/private`. That folder is
  **never** web-served and is only streamed to signed-in staff.
- dompdf caches fonts in `storage/fonts`, which must be writable.
- The web server must not be able to write anywhere outside `storage/` and `bootstrap/cache/`.

## 6. Caches (on every deploy)

```bash
php artisan optimize          # config, route, view and event caches
```

After changing `.env`, run `php artisan config:cache` again. With config
cached, `env()` is only read from config files.

## 7. Queue worker (required)

These emails are queued and only send while a worker runs: booking confirmation,
new-booking notice to admin, both cancellation emails, contact-form notices and
check-in reminders. Each mail job retries 3 times (after 1 min, then 5 min), then
lands in `failed_jobs`.

Supervisor example (`/etc/supervisor/conf.d/newwoodland-worker.conf`):

```ini
[program:newwoodland-worker]
command=php /var/www/newwoodland/artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/www/newwoodland/storage/logs/worker.log
```

- On every deploy: `php artisan queue:restart`. Workers keep old code in memory until restarted.
- Monitor failures: `php artisan queue:failed`. Retry with `php artisan queue:retry all` once SMTP is fixed.
- Password-reset emails (customer and staff) are sent immediately, not queued.

## 8. Scheduler (required)

One cron entry runs every scheduled task (defined in `routes/console.php`):

```cron
* * * * * cd /var/www/newwoodland && php artisan schedule:run >> /dev/null 2>&1
```

| Task | Frequency | Purpose |
|---|---|---|
| `bookings:expire-abandoned` | every 15 min | Cancels unpaid pending bookings past their hold, after first asking the gateway about any online payment on them. |
| `payments:reconcile` | every 5 min | **Payment-critical.** Settles eSewa/Fonepay payments whose customer never returned to the site and retries gateway checks that timed out. Without the cron, such guests are charged but never confirmed. |
| `payments:reconcile-khalti` | every 5 min | Confirms Khalti payments whose customer never returned. Does nothing without a Khalti key. |
| `bookings:send-checkin-reminders` | hourly | Queues one pre-arrival reminder per confirmed booking. |
| `model:prune` (PhoneOtp) | daily | Deletes old SMS one-time-code rows. |

Check with `php artisan schedule:list`.

## 9. HTTPS and HSTS

1. Serve the site over HTTPS and redirect HTTP to HTTPS **at the web server or
   proxy** (nginx `return 301 https://…`, or Cloudflare "Always Use HTTPS"). The
   app itself does not redirect, to avoid redirect loops on a misconfigured proxy.
2. Set `APP_URL=https://your-domain`. With `APP_ENV=production` the app then
   generates only `https://` links, and session cookies are HTTPS-only by default
   (`SESSION_SECURE_COOKIE` can still override).
3. Enable HSTS (`config/security.php`):
   ```dotenv
   HSTS_ENABLED=true
   HSTS_MAX_AGE=300          # start small; raise to 31536000 after a week without problems
   ```
   The header is only ever sent on HTTPS requests. Leave `HSTS_INCLUDE_SUBDOMAINS`
   and `HSTS_PRELOAD` off unless every subdomain is HTTPS. They are hard to undo.
4. **Behind a reverse proxy, load balancer or Cloudflare (`TRUSTED_PROXIES`, `config/trustedproxy.php`):**
   the app trusts **no** proxy by default. That is correct when PHP faces the
   internet directly. If something in front terminates HTTPS, set
   `TRUSTED_PROXIES` to that proxy's addresses only:

   | Setup | `TRUSTED_PROXIES` |
   |---|---|
   | PHP/nginx directly on the internet | *(empty)* |
   | nginx/Apache reverse proxy on the same server | `127.0.0.1` |
   | Load balancer | its private IP(s)/CIDR(s) |
   | Cloudflare | Cloudflare's current ranges from https://www.cloudflare.com/ips/, comma-separated |

   Never use `*` unless the server's firewall only accepts traffic from the proxy.
   If this is wrong:
   - every visitor shares the proxy's IP, so one abuser can rate-limit everyone out of login, booking and OTP;
   - HTTPS isn't detected, so HSTS isn't sent;
   - the signed "view booking", cancellation and invoice links in emails stop validating.

   **Verify after deploying:**
   - Open a "View my booking" link from a real confirmation email. It must show the booking, not the access page.
   - `curl -I https://your-domain` must show `Strict-Transport-Security`.
   - `storage/logs/laravel.log` must show real visitor IPs, not the proxy's.

## 10. Email (SMTP)

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587             # STARTTLS; use 465 with MAIL_SCHEME=smtps for implicit TLS
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=bookings@your-domain.com
MAIL_FROM_NAME="Newwoodland Hotel and Lounge"
```

- Admin notifications go to **Settings → Email** (`site_email`).
- Set up SPF/DKIM/DMARC for the sending domain, or confirmations may land in spam.
- Local development keeps `MAIL_MAILER=log`; tests use the `array` mailer.

## 11. Payments

See [PAYMENTS.md](PAYMENTS.md) for the flows. For production:

| Gateway | Settings | Notes |
|---|---|---|
| eSewa | `ESEWA_MODE=production`, `ESEWA_MERCHANT_CODE`, `ESEWA_SECRET_KEY` | With `APP_ENV=production`, eSewa is **switched off** (hidden, refused if posted to, never confirms anything) unless the mode is `production` and neither the public `EPAYTEST` code nor the public sandbox secret is in use. |
| Fonepay | `FONEPAY_MODE=production`, `FONEPAY_MERCHANT_CODE`, `FONEPAY_SECRET_KEY` | Same rule: production requires `FONEPAY_MODE=production` and both credentials. Use **live** portal credentials. Set `FONEPAY_ENABLED=false` if not offered yet. |
| Khalti | — | Not accepted by the hotel; hard-disabled in `config/payments.php`. |
| Bank transfer | `BANK_TRANSFER_ENABLED=true` + **Admin → Settings → Bank details** | Staff confirm receipt manually. |
| Pay at Hotel | — | Full payment at the hotel is **not offered** (hotel policy). The 50% balance of a split booking is recorded by staff at the front desk. |

**Before go-live, and after every `.env` change, run:**

```bash
php artisan payments:check-config   # exits non-zero if an enabled gateway is unsafe; never prints secrets
```

Gateway callbacks (`/payment/esewa/success|failure`, `/payment/fonepay/return`)
must be reachable over HTTPS on `APP_URL`. Every payment is re-verified
server-to-server and the amount is checked against the booking. Payment does
**not** depend on the guest's browser coming back: `payments:reconcile` (every 5
minutes) asks eSewa about every pending attempt, and retries Fonepay checks that
timed out. A Fonepay checkout the guest never returned from **cannot** be
checked automatically. Fonepay's published protocol has no status lookup without
the return data. Such attempts are flagged for staff when the booking is released.

**Payment review queue:** these appear on the admin dashboard and the booking
page, and staff are emailed at the Settings site email:
- money received after a booking expired;
- money received for a room that was taken meanwhile;
- a booking paid twice;
- an attempt the gateway can't settle.

Resolve each one (refund, re-confirm through the status override, which
re-checks the room, or rebook), then click **Mark resolved** with a note.
Refunds are **manual**: return money through the gateway's merchant portal or
bank, then record it on the booking page (Admin → Booking → Refunds).

## 12. SMS (Sparrow)

See [SMS.md](SMS.md). Production: `SMS_DRIVER=sparrow`, `SPARROW_SMS_TOKEN`
(secret), `SPARROW_SMS_FROM` (approved sender identity). Whitelist the server's
outbound IP in Sparrow if IP whitelisting is on. Sparrow delivers to Nepali
numbers only.

## 13. Guest photo ID and cancellation after arrival

**Photo ID (hotel policy):** every guest presents a photo ID at check-in:
Citizenship, Student ID or Office/Company ID. With `CHECKIN_REQUIRES_PHOTO_ID=true` (default),
staff must tick "Guest presented an official photo ID" before check-in, unless
an ID is already recorded. With `GUEST_DOCUMENTS_ENABLED=true` (default),
staff may also record the ID type and number. Numbers are encrypted with
`APP_KEY`. ID scans are never stored: hotel policy, hard-wired off in
`config/bookings.php`.

**Cancellation after arrival (hotel policy):** staff use **Cancel Stay (50%
charge)** on a checked-in booking. 50% of the booking amount is charged
(`config/bookings.php` → `after_arrival_cancellation_percent`), extra charges
stay payable, and the rate is frozen on the booking. Cancellations **before**
arrival are unchanged and use Admin → Settings → Cancellation deduction.

## 14. Health checks and monitoring

- `GET /up` returns HTTP 200 when the app boots (Laravel's built-in health route). Point uptime monitoring at it.
- Watch `storage/logs/laravel.log` for `Payment completed but room is no longer available` and `Pending booking has a completed payment` errors. Both need staff action.
- Watch `php artisan queue:failed` (emails) and the worker log.
- Run `composer audit` periodically. Known advisories at the time of writing: `laravel/framework` < 12.69 and `league/commonmark`. Update them with a full test run.

## 15. Tests before deploying

```bash
php artisan test
```

Tests run on in-memory SQLite (`phpunit.xml`) and never touch the real
database, mail, SMS or gateways. Every outgoing HTTP request in tests must be
faked (`Http::preventStrayRequests()`).

**Real MySQL locking:** SQLite has no row locks, so double-booking protection
under genuinely simultaneous requests is only exercised by
`tests/Feature/MysqlBookingConcurrencyTest.php`. It runs a second PHP process
that races the app for the same room, under both REPEATABLE READ and READ
COMMITTED. Run it against a **throwaway** MySQL database (its tables are
dropped; the name must contain "test") on the same MySQL version as production:

```bash
MYSQL_CONCURRENCY_TEST_DATABASE=newwoodland_concurrency_test php artisan test --filter MysqlBookingConcurrencyTest
```

## 16. Deploying an update

```bash
php artisan down --retry=60
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Take a database backup before `migrate` (see [BACKUPS.md](BACKUPS.md)).

## 17. Rollback considerations

- **Code:** check out the previous release tag/commit, `composer install`, `php artisan optimize`, `php artisan queue:restart`.
- **Migrations:** all new migrations have `down()` methods, but rolling back
  a table that already holds data (refunds, charges, booking_rooms,
  guest_documents, room_rates) **deletes that data**. Prefer restoring code
  while leaving the additive schema in place. Old code ignores the extra
  tables and columns.
- **Never** roll back by restoring an old database over a live one without first
  reconciling gateway payments made since (see [BACKUPS.md](BACKUPS.md)).
- Keep the previous release directory (or tag) until the new one has run cleanly for a day.

## Manual verification before go-live

These need real credentials, real devices or server access. Automated tests cannot cover them:

- [ ] `php artisan payments:check-config` passes on the production server.
- [ ] `MysqlBookingConcurrencyTest` passes against the production MySQL version (throwaway database).
- [ ] Real eSewa payment end-to-end in production mode (pay, return, booking confirmed, email received).
- [ ] Real eSewa payment where the browser is closed right after paying: booking confirmed within ~5 minutes by `payments:reconcile`, and the confirmation email arrives.
- [ ] A "View my booking" link from a real email opens the booking over HTTPS (proves `TRUSTED_PROXIES`/HTTPS are right).
- [ ] Real Fonepay payment end-to-end (if offered).
- [ ] A refund made through each gateway's merchant portal, then recorded in the admin.
- [ ] Bank transfer details correct in Settings; one real transfer confirmed by staff.
- [ ] SMTP delivery of confirmation, cancellation, reminder and both password-reset emails (check spam folders).
- [ ] Sparrow SMS code received on a real Nepali number; phone verification and SMS password reset.
- [ ] Queue worker and cron running after a server reboot.
- [ ] HTTPS, secure cookies and HSTS header present (`curl -I https://your-domain`).
- [ ] Invoice PDF downloads and prints correctly; enter the hotel's PAN/VAT number in Admin → Settings once provided.
- [ ] Social-media links entered in Admin → Settings once the hotel provides them (the footer hides empty ones).
- [ ] Any personal test credentials/OTPs used for payment testing removed from `.env` before go-live.
- [ ] A backup restored successfully on a separate machine.
