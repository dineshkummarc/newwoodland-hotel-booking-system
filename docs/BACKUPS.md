# Backups and Restore

Nothing in the application makes backups for you. This page describes what must
be backed up, a sensible default schedule, and how to restore. Who runs and
monitors backups (hotel, developer, or hosting provider) is still a **client
decision** — see `docs/CLIENT_REQUIREMENTS.md` §B14.

## What to back up

| What | Where | Why it matters |
|---|---|---|
| **MySQL database** | `DB_DATABASE` (default `newwoodland_db`) | Bookings, payments, refunds, charges, guests, staff accounts, settings, audit log. |
| **Public uploads** | `storage/app/public/` (`rooms/`, `gallery/`, `testimonials/`, `settings/`) | Room photos, gallery images, testimonial avatars, the bank-transfer QR code. Served via the `public/storage` symlink. |
| **Private uploads** | `storage/app/private/guest-documents/` | Not used: ID scans are never stored (hotel policy). Kept in backups only in case older files exist. |
| **`.env`** | project root | Contains `APP_KEY` and all credentials. Store it **separately** from the data backups, in a password manager or secrets vault. |

`APP_KEY` is critical. Guest document numbers are encrypted with it, and signed
links (invoice, guest cancellation) depend on it. A database restored without the
original `APP_KEY` cannot decrypt those numbers. Never rotate `APP_KEY` on a live
site without a re-encryption plan.

Not needed in backups: `vendor/` (reinstall with Composer), `storage/framework/`,
`storage/logs/` (keep logs per your own policy), `bootstrap/cache/`.

## Recommended schedule and retention

A reasonable default for a small hotel (confirm with the client):

| Backup | Frequency | Keep |
|---|---|---|
| Database dump | Daily (e.g. 02:30) | 14 daily, 8 weekly, 12 monthly |
| Uploads (`storage/app/public`, `storage/app/private`) | Daily, incremental (rsync/rclone) | 30 days of versions |
| `.env` | On every change | Latest + previous |

Keep at least one copy **off the server**, e.g. another machine or the hosting
provider's object storage. A backup on the same disk does not survive a disk
failure or a compromised server.

## Database backup

```bash
# Consistent dump of InnoDB tables without locking the site.
mysqldump --single-transaction --quick --routines --triggers \
  -u "$DB_USERNAME" -p"$DB_PASSWORD" -h "$DB_HOST" "$DB_DATABASE" \
  | gzip > /var/backups/newwoodland/db-$(date +%F).sql.gz
```

Use a MySQL user with read-only rights (`SELECT, SHOW VIEW, TRIGGER, LOCK TABLES`)
for backups, and keep its password in a root-only `~/.my.cnf` rather than on the
command line.

Example cron entry (root or a dedicated backup user):

```cron
30 2 * * * /usr/local/bin/newwoodland-db-backup.sh >> /var/log/newwoodland-backup.log 2>&1
```

Prune old dumps in the same script, e.g. `find /var/backups/newwoodland -name 'db-*.sql.gz' -mtime +14 -delete`,
and copy weekly and monthly dumps to their own retention folders before pruning.

## Upload backup

```bash
rsync -a --delete /var/www/newwoodland/storage/app/public/  backup-host:/backups/newwoodland/public/
rsync -a --delete /var/www/newwoodland/storage/app/private/ backup-host:/backups/newwoodland/private/
```

The **private** folder may contain ID scans. Encrypt it at rest on the backup
target (for example an encrypted volume, or `rclone crypt`), restrict access to
it, and delete it according to whatever retention period the hotel confirms for
identity documents.

## Restore procedure

1. Put the site in maintenance mode: `php artisan down`.
2. Restore `.env` (with the **original** `APP_KEY`).
3. Restore the database:
   ```bash
   gunzip < db-YYYY-MM-DD.sql.gz | mysql -u "$DB_USERNAME" -p "$DB_DATABASE"
   ```
4. Restore uploads into `storage/app/public/` and `storage/app/private/`, then
   check ownership/permissions (web server user must be able to read and write them).
5. `php artisan storage:link` (if the `public/storage` symlink is missing).
6. `php artisan migrate --force` (only applies migrations newer than the backup).
7. `php artisan optimize:clear && php artisan optimize`.
8. `php artisan queue:restart`, then `php artisan up`.
9. Spot-check: admin login, a recent booking page, a room image, an invoice PDF.

**Payments after a restore:** any eSewa/Fonepay/Khalti payment made after the
backup was taken exists only at the gateway. Reconcile those against each
gateway's merchant portal before reopening bookings for the restored dates.

## Test the backups

At least once a quarter, restore the latest dump and uploads to a separate
machine or database and run through step 9. An untested backup should be
treated as missing.
