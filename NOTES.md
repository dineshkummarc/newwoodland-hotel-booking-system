# Remaining Work

- Uncommitted: a large body of work (password reset flow, Register nav link, robots.txt fix, OG image fix, and everything since — see `git status`) is still uncommitted on `main`
- Deliberately not implemented: email verification — architecture doesn't currently require it (no `verified` middleware anywhere); adding it would add friction to the booking flow without being requested elsewhere. Revisit if the client wants it.
- Unresolved: hero blur report (unconfirmed, no CSS cause found)
- Dependency advisories: resolved 2026-10-04 (`laravel/framework` 12.69.3, `league/commonmark` 2.10.3, `league/flysystem` 3.36.0; `composer audit` clean).
- Not yet run: `MysqlBookingConcurrencyTest` against a real MySQL server (no local MySQL was available). Run it before go-live — see docs/DEPLOYMENT.md §15.

## Client decisions — confirmed

- Photo ID: guests present an official photo ID at check-in (citizenship, office/company ID with photo, or other official photo ID). Staff confirm it at check-in; recording the number is optional.
- After arrival: 50% cancellation charge (staff "Cancel Stay").
- Colours, Terms and Privacy are final — do not change.

## ⚠️ CONTRADICTION — cancellation before arrival (must be confirmed by the hotel)

Two sources both claim to be "client-confirmed" and disagree:

- This file previously said: Settings deduction **50%**, **24-hour** online notice window.
- `database/seeders/SettingSeeder.php` and migration `2026_10_03_000001_apply_confirmed_hotel_policies_to_settings` say: **free** (0%), **no** notice window — and that migration overwrites the Settings row with 0% / 0h when it runs.

The live values come from ONE place: Admin → Settings (`cancellation_deduction_percent`, `cancellation_window_hours`); the Terms page, cancellation pages and emails all read them. Nothing has been changed while this is unresolved. Once the hotel confirms the deduction %, the notice window and the deposit policy, set them in Admin → Settings (after running migrations) and correct whichever source is wrong.
- Payment methods are final; credentials live only in `.env`.

## Client decisions — still pending

- Real hotel photographs and final logo. All stock photos were removed 2026-10-04 (migration `2026_10_04_000004_remove_stock_photos`); every photo position now shows a labelled "Client Photo Required" placeholder until the real photo is uploaded. Fixed slots: `config/photos.php` (home) and `config/about.php` (About); room and gallery photos are uploaded in the admin. The favicon (`public/favicon.ico`) is an empty file.
- Testimonials: the three seeded reviews (Syliv Shrestha, Alyra Shrestha, Bikesh Dhakal) appear on the home page and feed the `aggregateRating` structured data. Confirm they are genuine guest reviews before go-live, or delete them in Admin → Testimonials.
- Social-media links. Enter them in Admin → Settings; empty ones are hidden. No placeholders are seeded.
- PAN/VAT number. Enter it in Admin → Settings → Invoice / Tax Details; it prints on invoices once set. No tax calculation is implemented.
- Deposit policy: amount/percentage, when it is collected, whether it is refundable, and how it interacts with cancellation and refunds. The existing 50% split-payment option is unchanged until then.
- Note: the finalized Terms page shows the Settings deduction for cancellations "before arrival or during the stay". If the pre-arrival deduction is ever changed from 50%, the after-arrival 50% rule stays fixed and the Terms wording would need review.
- Booking limits: how far ahead guests may book and the longest stay bookable online. Technical safety defaults are in place (730 days / 90 nights, `BOOKING_MAX_ADVANCE_DAYS` / `BOOKING_MAX_NIGHTS`) until the hotel decides.
- First-booking discount (5%): now also matched on phone number, which stops the easy "new email" repeat claim. It can still be claimed by a guest using both a new email and a different phone; and a family member who shares a phone with an earlier guest won't get it. If that trade-off is wrong, decide whether the discount should require a signed-in account instead.
- Bank-transfer holds: one guest can hold at most 5 unpaid rooms (one full group booking); staff are emailed on every bank-transfer hold and can cancel bogus ones. Whether 24 hours is the right hold for bank transfers is the hotel's call (`BANK_TRANSFER_HOLD_HOURS`).
