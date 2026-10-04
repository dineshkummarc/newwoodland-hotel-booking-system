# Newwoodland Hotel & Lounge

Website, online booking and hotel back office for **Newwoodland Hotel and Lounge**,
Bhadrapur-8, Jhapa, Nepal. Guests browse rooms, book and pay online (or by bank
transfer or cash). Staff run the front desk, bookings, payments, refunds and
content from an admin panel.

## Technology

- **Laravel 12** on **PHP 8.2**, **MySQL 8** (SQLite in-memory for tests)
- **Blade** views with Bootstrap 5 from a CDN; plain CSS/JS in `public/` (no Node build step)
- Database-backed **queue**, **cache** and **sessions**
- **dompdf** (`barryvdh/laravel-dompdf`) for invoice PDFs
- **libphonenumber** for phone validation; **Sparrow SMS** for one-time codes
- Payment gateways: **eSewa**, **Fonepay** (Khalti is coded but not accepted by the hotel)

## Features

**Public site:** Home, About, Rooms and room details, Facilities, Gallery,
Contact, Privacy Policy, Terms, sitemap. Hotel details, policies and bank details
come from Admin → Settings.

**Booking**
- Live availability and a server-side price estimate
- Guest checkout or customer account
- Multi-room bookings: several rooms of a type from the public form, or any mix of rooms from the staff form
- Double booking prevented with database row locks
- First-booking discount; pay-part-now (deposit) option
- Weekend, seasonal and holiday pricing rules (holiday > season > weekend > base). Each night's rate is frozen on the booking.
- Booking lifecycle: pending → confirmed → checked in → checked out → completed, plus cancelled and no-show
- Configurable cancellation window and cancellation deduction
- Unpaid holds expire automatically
- Check-in reminder email

**Payments**
- eSewa and Fonepay, verified server-to-server
- Bank transfer and cash, confirmed by staff
- Extra charges during a stay (restaurant, laundry, minibar…)
- Manual refund tracking (pending/paid, method, reference, who and when)
- Invoice/receipt PDF for guests and staff

**Accounts**
- Customer registration, "My Bookings", profile and password change
- Password reset by email link or by SMS code to a verified phone
- Staff password reset by email

**Admin**
- Roles: super_admin, admin, front_desk
- Staff activation and deactivation
- Front Desk board, walk-in/phone bookings, room moves
- Guest directory with staff-only notes and VIP flag
- Photo-ID confirmation at check-in, with optional recording of the ID type and number (encrypted)
- Cancel a stay after arrival, with a fixed 50% charge
- Reports (bookings, revenue incl. refunds and extra charges, occupancy, room performance, payments) with CSV export
- Activity log covering bookings, money, staff accounts, content, pricing and settings

**Security:** security headers (CSP, frame/sniff protection, optional HSTS),
CSRF, rate limiting on auth/booking/payment/OTP/contact routes, signed
guest links, encrypted ID numbers, private file storage.

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database (default `newwoodland_db`, see `DB_*` in `.env`), or set
`DB_CONNECTION=sqlite` for a zero-setup local database. Then:

```bash
php artisan migrate
php artisan db:seed        # rooms, facilities, FAQs, settings + a super_admin
php artisan storage:link
php artisan serve          # http://127.0.0.1:8000  — admin at /admin/login
```

The seeder prints the generated admin password **once**. Admin email:
`admin@newwoodlandhotel.com`.

Safe defaults in `.env.example` for development:

- `MAIL_MAILER=log` and `SMS_DRIVER=log`: emails and SMS codes are written to `storage/logs/laravel.log`.
- eSewa uses eSewa's public **sandbox** test credentials.
- Fonepay stays hidden until merchant credentials are set.
- `QUEUE_CONNECTION=database`: run `php artisan queue:work` to actually send queued emails locally.
- `php artisan schedule:work` runs the scheduler locally.

## Configuration reference

| Area | Where |
|---|---|
| All environment variables | `.env.example` (commented) |
| Payment methods, split payment | `config/payments.php`, `config/esewa.php`, `config/fonepay.php`, `config/khalti.php`, `config/bank_transfer.php` — see [docs/PAYMENTS.md](docs/PAYMENTS.md) |
| SMS / OTP | `config/sms.php` — see [docs/SMS.md](docs/SMS.md) |
| Check-in reminders, guest ID capture | `config/bookings.php` |
| HSTS | `config/security.php` |
| Hotel details, bank details, policies, discounts | Admin → Settings (stored in the database) |
| Pricing rules | Admin → Pricing Rules |

Never commit real credentials. `.env` is git-ignored.

## Testing

```bash
php artisan test
```

Feature tests cover booking, pricing, payments, refunds, invoices, auth,
roles, reports and security. They run against in-memory SQLite with fake
mail, SMS and HTTP, so they never contact a real gateway or send real messages.

## Scheduler and queue

Production needs both:

- **cron:** `* * * * * php artisan schedule:run`. Expires abandoned bookings, reconciles Khalti, sends check-in reminders, prunes OTP codes.
- **queue worker:** `php artisan queue:work` under Supervisor or systemd. Sends booking, cancellation, contact and reminder emails.

## Deployment

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for server requirements, the
production `.env`, HTTPS/HSTS, SMTP, SMS, payment credentials, the worker and
cron, health checks, updates and rollback. Backups are covered in
**[docs/BACKUPS.md](docs/BACKUPS.md)**.

Open client decisions (photos, branding, policies, payment methods, ID
requirements, backup ownership…) are tracked in
[docs/CLIENT_REQUIREMENTS.md](docs/CLIENT_REQUIREMENTS.md) and
[NOTES.md](NOTES.md).
