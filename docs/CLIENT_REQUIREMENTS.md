# Newwoodland Hotel & Lounge — Client Requirements & Questions Document

**Purpose:** This document has two parts. **Part A** is a factual audit of what is already built in the codebase today (read directly from the source, not assumed). **Part B** is a structured list of questions to take to the actual hotel client before the system is considered production-ready. Use Part A to avoid asking the client things the system already handles, and Part B to drive the remaining decisions.

**Audit date:** 2026-09-22. Audit method: full read-through of routes, controllers, models, migrations, services, middleware, views, config, and tests — not inferred from file/route names.

---

## PART A — CURRENT SYSTEM AUDIT

### A0. Headline finding

This is **considerably more built-out than a typical demo project**. It has a real booking state machine, database-locked double-booking prevention, three genuinely server-verified payment gateway integrations (not "trust the redirect"), role-based admin access, an audit log, and 31 feature test files. The gaps that remain are specific and listed below — they are mostly **business-decision gaps** (what should the cancellation fee be, what roles should staff have) and a handful of **production-hardening gaps** (backups, deployment docs, email queueing), not fundamental architecture problems.

### A1. What fully EXISTS and works end-to-end

| Area | Detail |
|---|---|
| Public booking flow | Search → room detail → date/availability check → guest info form → create booking → pay → confirmation. Works with or without an account (guest checkout supported). |
| Double-booking prevention | Row-locked (`lockForUpdate`) DB transaction checks date-overlap against confirmed/checked-in bookings, fresh pending holds (30 min), and active bank-transfer holds (24h) before creating a booking. |
| Booking lifecycle | Real state machine: `pending → confirmed → checked_in → checked_out → completed`, plus `cancelled`/`no_show`, with enforced legal transitions (`Booking::TRANSITIONS`). |
| Admin booking management | Manual/walk-in booking creation, modify, reassign room, record cash/bank payment, check-in, check-out, mark no-show, mark completed, cancel — all logged to an audit trail, all re-validated server-side. |
| eSewa payment | Full integration: HMAC-signed initiation, **mandatory server-to-server status verification** (never trusts the browser redirect alone), amount cross-checked against `booking.total_price`, idempotent under concurrent/duplicate callbacks. |
| Khalti payment | Fully coded to the same standard as eSewa (server-side lookup verification, no client trust). **Feature-flagged off** — no live merchant credentials configured yet. Not broken, just dormant. |
| Fonepay payment | Also fully coded (HMAC-SHA512, JSON+XML response handling, trace-ID reuse protection) — **not mentioned in your brief**, but it exists and is dormant for the same reason as Khalti (no credentials). Worth deciding if you want to offer it. |
| Bank transfer (manual) | Guest is shown the hotel's bank details (pulled from Settings) and told to transfer; admin manually marks it received. Booking held 24h pending confirmation. |
| Cash payment | Admin can record a cash payment at the front desk via the same "record payment" action as bank transfer. |
| Cancellation (guest) | Signed, expiring URL for guest (no-account) bookings; authenticated "My Bookings" page for account holders. Enforces a configurable cancellation window (admin-editable, default 24h before check-in). Sends email to guest + admin on cancellation. |
| Room catalog | Room type, price/night, max guests, bed type, size, images (with reorder/primary), amenities, active/inactive, availability status (`available`/`maintenance`/`out_of_service`). |
| Admin roles | Three fixed roles — `super_admin`, `admin`, `front_desk` — enforced server-side per route group, not just hidden nav. `front_desk` gets full booking/check-in operations but is blocked from rooms/gallery/reports/settings/admin-user management. |
| Admin account safety | Can't deactivate yourself; can't deactivate the last active super_admin (prevents total lockout); deactivation force-logs-out an active session mid-request. |
| Reports & dashboard | Real aggregation (not placeholders): occupancy %, monthly revenue from actual completed payments, revenue by provider, bookings-over-time chart, room performance, CSV export for bookings/revenue/payments. |
| Security headers, CSRF, rate limiting | CSP/X-Frame-Options/nosniff set globally; CSRF on all state-changing routes; per-route throttles on login, booking, payment, cancellation, contact form. |
| Password handling | Bcrypt (cost 12), no plaintext logging found anywhere. |
| Tests | 31 feature test files (~5,235 lines) covering auth, bookings, all 3 gateways, bank transfer, cancellation, admin CRUD, roles, reports. |

### A2. What is PARTIAL (built, but incomplete or needs a decision before launch)

| Area | What's there | What's missing / needs a decision |
|---|---|---|
| Guest management | List/search of registered users + grouped-by-email view of guest (no-account) bookers, with totals and booking history. | Not a real CRM — no staff notes, no editable guest profile, no VIP/loyalty flag, no persisted "guest" record for anonymous bookers. |
| Settings → public site | Phone, email, address, hours, cancellation policy text, social links, bank details all live-update the site from the admin Settings screen. | Hotel name, hotel description, page `<title>`/meta description/SEO tags, and homepage/about marketing copy are **hardcoded** in Blade files — changing them in Settings does nothing. |
| Room capacity | `max_guests` (single number) + free-text `bed_type`/`guests_label` (e.g. "2 Adults", "Family"). | No structured adults/children split, no extra-bed concept, no floor/physical room-number identifier — a "Room" record is a **room type**, not a specific bookable unit. |
| Pricing | Flat price per night. | No weekend/seasonal/holiday pricing, no rate calendar. |
| Refunds | Cancellation changes booking status; guest is shown "our team will contact you regarding a refund." Reference/notes can be logged in the audit trail. | **No refund database fields at all** (no refund amount/date/reference, no `refunded` payment status) and **no gateway refund API calls** — 100% manual/offline process today. |
| Audit log | Wired into all booking actions and admin-user management. | **Not** wired into room/gallery/facility/FAQ/testimonial/settings edits — those changes leave no audit trail. |
| Email delivery | Confirmation, admin-notification, cancellation, and contact-form emails are all genuinely sent (not dead code). | Sent **synchronously** (not queued) even though a queue is configured — a slow mail server would delay the booking/payment response to the guest. |
| Session cookie security | `SESSION_SECURE_COOKIE` support exists. | **Off by default**, must be manually set in production `.env` — easy to forget. |
| Customer self-service | Registration, login, logout, "My Bookings," self-cancellation. | No profile-edit/change-password page for customers after registration (admins have this; customers don't). No email verification — any address can be used to register without proving ownership. |
| Admin password recovery | Super_admin can reset another admin's password via Admin User Management. | No self-service "forgot password" flow for admins at all — a lone locked-out super_admin has no recovery path except direct database access. |

### A3. What is MISSING entirely

- **OTP / SMS verification** — not implemented anywhere, for customers or admins. Password reset is email-link only.
- **Guest ID/passport capture or upload** — no field, no upload, nothing in the booking form or check-in flow.
- **Deposits and incidental charges** — no concept of a deposit, or of folio-style extra charges (restaurant, laundry, room service, minibar) attached to a stay.
- **Invoice/receipt PDF generation** — no PDF library in the project at all. The only "receipt" is the browser's own print button on the confirmation page (prints the HTML, not a generated document).
- **Multi-room bookings** — one room per booking; no cart/multi-room flow.
- **Backup/recovery strategy** — no backup package, scheduled command, or documented process of any kind for the database or uploaded files.
- **Deployment documentation** — `README.md` is still the unmodified Laravel starter file. Code comments reference "the deployment checklist" but no such document exists. Queue worker, cron (`schedule:run`), `SESSION_SECURE_COOKIE`, and `APP_DEBUG=false` are all required manual production steps that are currently undocumented.
- **HSTS header** — not set (relevant once the site is served over HTTPS in production).
- **Granular permissions** — the 3 admin roles are hardcoded route-group gates, not a configurable permission matrix; adding a role or changing what `front_desk` can see requires a code change.

### A4. Pre-launch technical checklist (not client questions — flag to your dev process)

- [ ] Set `APP_DEBUG=false`, `APP_ENV=production` in real `.env`
- [ ] Enable `SESSION_SECURE_COOKIE=true` once HTTPS is live
- [ ] Replace eSewa's public sandbox test credentials with real merchant credentials
- [ ] Decide on Khalti/Fonepay: obtain real merchant credentials and enable, or leave off
- [ ] Set up a queue worker (`php artisan queue:work`) and process supervisor, or convert Mailables to run synchronously by design
- [ ] Set up the cron entry for `php artisan schedule:run` (needed for abandoned-booking expiry)
- [ ] Configure real SMTP mail credentials (currently defaults to `log` driver)
- [ ] Put a database + file backup strategy in place
- [ ] Write an actual deployment/runbook doc

---

## PART B — QUESTIONS FOR THE CLIENT

Each section is organized so you can go through it with hotel staff/ownership. Where the system already has a working default, it's noted — you're confirming or overriding it, not starting from nothing.

### B1. Hotel Basic Information

Already in the system (currently placeholder/seed data — **must be confirmed/replaced**, not assumed correct):
- Name: "Newwoodland Hotel and Lounge"
- Address: "Bhadrapur-8, Jhapa, Nepal"
- Email: "info@newwoodlandhotel.com"
- Description: generic placeholder text

Confirm with client:
1. Official legal/registered hotel name (for invoices/contracts, if different from the trading name)
2. Full address, plus Google Maps pin/link
3. Primary phone, secondary phone, WhatsApp/Viber number if used
4. Public contact email vs. bookings/reservations email (same or different?)
5. Website domain (already live or to be registered?)
6. Social media links to feature (Facebook/Instagram/YouTube/TikTok — fields already exist for these four)
7. Hotel description/story for marketing copy (currently hardcoded placeholder — will become editable once Settings→site wiring is completed, see A2)
8. Number of floors, total room count
9. Facilities/amenities list (restaurant, lounge, parking, WiFi, etc. — admin panel already supports listing these)
10. Restaurant/lounge operating hours and menu, if applicable
11. Reception operating hours (24-hour desk, or limited hours?)
12. Standard check-in / check-out times
13. Early check-in / late check-out policy — allowed? Fee?
14. Emergency contact number
15. Logo file (vector/high-res) and brand colors
16. Professional photos of rooms, exteriors, facilities
17. Terms & conditions, privacy policy, cancellation policy — do these exist as documents, or do they need to be drafted? (There is currently no dedicated Terms/Privacy page in the site.)

### B2. Room Management

The system models a **room type** (e.g. "Deluxe Room"), not an individual physical room — this is an important distinction to raise with the client:
1. Should the system track individual physical rooms (e.g. Room 101, 102 vs. just "Deluxe Room" as a category with a count)? This is a structural decision — the current schema does not support per-unit tracking or per-unit status.
2. What room types/categories exist, and how many rooms of each?
3. If individual room numbers are needed, what are they?
4. Maximum occupancy per room type — and should this be a structured adults/children split (not currently supported — currently a single number + free text)?
5. Is an extra bed offered? At what price? (Not currently modeled at all.)
6. Base price per night, per room type.
7. Do you want weekend, seasonal, or holiday pricing? (Not currently supported — flat rate only today.)
8. Are there planned promotional/discount codes or special offers?
9. Does the listed price include VAT/tax and service charge, or are these added at checkout/billing?
10. What amenities does each room type have (AC, TV, WiFi, minibar, etc.)?
11. Room statuses: the system already supports **Available / Maintenance / Out of Service**. Do you also need **Occupied / Reserved / Cleaning** as distinct statuses, and if so, should any of these change automatically based on booking/check-in/check-out, or should staff always set them manually? (Today, room status is 100% manual — booking activity never changes it automatically.)
12. Who should be allowed to change room status — front desk, admin only, or both?

### B3. Booking Rules

Confirmed working today: online booking (with or without an account), staff-created bookings, admin-created bookings, one room per booking, guest checkout.

Ask the client:
1. Should guests be required to create an account, or is guest checkout (current default) preferred long-term?
2. Should a single booking be allowed to cover multiple rooms? (Not supported today.)
3. Can a guest hold multiple simultaneous bookings?
4. Should guests be able to modify their own booking (dates, etc.) after creating it, or only cancel? (Currently: guests can only cancel; only staff can modify dates/reassign rooms.)
5. What guest information is actually required at booking? Confirm which of: full name, email, phone, address, nationality, ID/passport number, emergency contact, special requests, arrival time, number of adults/children are mandatory vs. optional. (Currently collected: name, email, phone, country (optional), special requests (optional), a free-text guest count. No ID/passport, no emergency contact.)
6. Should bookings be transferable to another guest's name?
7. How long should a "pending" (unpaid) booking be held before auto-cancelling? (Currently: 30 minutes for online-gateway bookings, 24 hours for bank-transfer bookings — both configurable in code, not yet in the admin panel.)

### B4. Check-in / Check-out & On-site Operations

The system has real check-in/check-out state tracking and a front-desk dashboard (today's arrivals/departures/in-house guests), but no billing-beyond-room-price functionality. Ask:
1. Is ID/passport verification required at check-in? Should the document be scanned/photographed and stored? (This would be new functionality — currently nothing captures ID at all.)
2. Should a booking be checkable-in without full payment received? What deposit amount/percentage, if any? (No deposit concept exists today — this is a structural gap to design.)
3. Do guests incur on-property charges (restaurant, room service, laundry, minibar, damage) that need to be added to their final bill? (No such "folio"/incidental-charges feature exists today — the system only tracks the original room booking total.)
4. Should the system generate a formal invoice/receipt (PDF, downloadable, emailable)? (Today there is no PDF generation of any kind — this would be new functionality, and should include whether invoices need a VAT/tax breakdown for Nepali tax compliance.)
5. Who is authorized to check guests in/out — front desk only, or also admin/manager? (Both currently have access in the system's role model.)

### B5. Payments

Confirmed working: eSewa (live-ready, needs production merchant credentials), Khalti (fully coded, needs live credentials), Fonepay (fully coded, needs live credentials — wasn't in your original list, worth a decision on whether to offer it), bank transfer (manual, needs your bank details entered in Settings), cash (front-desk only).

Ask the client:
1. Which methods should be live at launch — eSewa only, or also Khalti/Fonepay/bank transfer/cash?
2. Full payment required at booking, or is a deposit (e.g., 20-30%) acceptable with balance due at check-in? (System currently assumes full payment; partial/deposit payments are not modeled.)
3. For bank transfer: what are the official account name, account number, bank name, and branch? (These go directly into the admin Settings screen.)
4. How should staff verify a bank transfer actually arrived — is a plain confirmation button sufficient (current behavior), or do you want guests to be able to upload a screenshot/proof of payment for staff to review? (Not currently supported — worth deciding since currently it's "staff attests money arrived" with no attached evidence.)
5. What is the actual refund policy — timeframe, deductions, method of returning money? Today refunds are entirely manual/offline (no gateway refund API integration, no refund records in the database) — is that acceptable, or is automated/tracked refunding a requirement?
6. Should admins be able to manually mark a payment as refunded (with a note/reference), even though no money actually moves through the system? This would need to be built — no "mark refunded" action or database field exists today.
7. Should payment receipts be emailed automatically? (Booking confirmation email already exists and is sent; a dedicated payment receipt is not separate from it today.)

### B6. Authentication & Accounts

Confirmed working: email+password registration/login for guests, separate email+password login for admin/staff (fully separate system from guest accounts), "remember me," rate-limited login attempts, email-link password reset (60-minute expiry) for guests.

Ask the client:
1. Is email-link password reset acceptable, or is SMS/OTP required? (Not implemented at all today — Nepali guests may prefer phone-based reset; this would be new functionality requiring an SMS gateway.)
2. Should new accounts require email verification before they can book? (Not enforced today — any email can register without proving ownership.)
3. Should customers be able to edit their own profile/change their password after registering? (Not currently available — only admins can edit their own profile today.)
4. Does staff need a way to recover a forgotten admin password without another super_admin's help? (No self-service path exists for admins today.)
5. Is social login (Google/Facebook) desired, or is email/password sufficient?

### B7. User Roles & Permissions

The system already has three hardcoded roles enforced at the route level (not just hidden buttons):

| Role | Can do today |
|---|---|
| **Front Desk** | Full booking management (create/modify/cancel/check-in/check-out/no-show/payment recording), guest directory. Cannot touch rooms, gallery, reports, settings, or staff accounts. |
| **Admin** | Everything Front Desk can do, plus rooms, images, gallery, facilities, testimonials, FAQs, reports, contact messages, settings. Cannot manage other staff accounts. |
| **Super Admin** | Everything, including creating/deactivating other admin/staff accounts. |

Ask the client:
1. Do these three roles map to your actual staffing (e.g., receptionist = Front Desk, manager = Admin, owner = Super Admin)?
2. Is a more granular permission system needed (e.g., an Accountant role that sees payments/reports but not bookings; a Housekeeping role that only sees room status)? This is a real limitation today — adding a new role or changing what an existing role can access requires a code change, not an admin-panel setting. Worth deciding now whether 3 fixed roles are sufficient long-term.
3. Who should be allowed to issue refunds (once that feature exists)?
4. Who should be able to see financial reports vs. just operational/booking data?

### B8. Guest Management

Today this is a list/search view derived from booking history, not a true CRM (no notes field, no editable guest profile beyond name/email/phone, no VIP/loyalty flag, no persisted record for guests who book without an account). Ask:
1. Do you need staff to be able to leave internal notes on a guest (e.g., "prefers top floor," "VIP," "previous complaint")?
2. Do you want a loyalty/repeat-guest program, or simple repeat-guest recognition?
3. Is guest booking/stay history sufficient as currently shown, or do you need a dedicated guest profile page?

### B9. Cancellation & Refunds

Already working: configurable cancellation window (admin-editable, default 24 hours before check-in), self-service cancellation via signed link (guest) or account page, automatic email to guest and admin on cancellation.

Ask:
1. Is 24 hours the right cancellation window, or should it vary by room type/season/rate plan?
2. Is cancellation free within the window, or is there always a processing fee?
3. What happens outside the window — no cancellation allowed, or allowed with a penalty (e.g., forfeit first night)?
4. See B5 Q5-Q6 for the underlying refund-process questions — this is the biggest process gap in the system today.

### B10. Notifications & Communications

Already working and actually sending mail (not just defined-but-unused code): booking confirmation, cancellation notice (to guest and admin), new-booking notice (to admin), contact-form submissions (to admin).

Ask:
1. Should any of these also go to a phone number via SMS, given email may be less reliably checked by some guests? (No SMS integration exists today.)
2. Do you want a reminder email sent before check-in (e.g., 24-48 hours prior)? Not implemented today.
3. Who should receive the "new booking" and "contact form" admin notifications — a shared front-desk inbox, the manager, or both?

### B11. Reporting

Already working: dashboard with occupancy %, monthly revenue, revenue by payment method, 30-day booking trend, plus exportable bookings/revenue/occupancy/room-performance/payments reports.

Ask:
1. Are these the reports management actually needs, or are there specific KPIs/formats (e.g., for accounting/tax filing) that should be added?
2. Who should have access to revenue reports vs. just occupancy/operational reports? (Ties into B7.)

### B12. Content, Branding & SEO

1. Confirm final hotel name/description for the website (currently placeholder text hardcoded into the site — needs both the real copy and, separately, a small amount of dev work to make it editable from Settings rather than hardcoded).
2. Who will supply/approve the "About" page narrative and homepage marketing copy?
3. Any specific SEO keywords/target search terms for Jhapa/Bhadrapur hotel searches?

### B13. Legal & Policy Documents

1. Terms and conditions — does a draft exist, or does it need to be written (ideally by/with a lawyer familiar with Nepali hospitality regulations)?
2. Privacy policy — required given the system stores guest names, emails, phone numbers, and booking history. Does a draft exist?
3. Any regulatory requirement in Nepal to record guest ID/passport details for hotel stays? (This would directly drive the check-in/ID-capture decision in B4.)

### B14. Backups, Support & Maintenance

No backup strategy of any kind currently exists in the system. Ask:
1. Who is responsible for ongoing hosting, backups, and server maintenance after launch — you, the client, or a separate arrangement?
2. What's an acceptable data-loss window if something goes wrong (i.e., how often should backups run — daily is a reasonable default for a booking system)?
3. Who is the technical point of contact if something breaks after launch (payment failures, site downtime)?

### B15. Go-Live Decisions Summary

A short list to settle before flipping the site to production:
- [ ] Final payment methods to enable (B5.1)
- [ ] Full payment vs. deposit policy (B5.2)
- [ ] Cancellation/refund policy specifics (B9, B5.5)
- [ ] ID/passport capture requirement (B4.1, B13.3)
- [ ] Deposit/incidental-charges/invoicing needs (B4.2-B4.4) — these are the largest *new-feature* asks in this document if required
- [ ] Final role/permission mapping (B7)
- [ ] Backup and support ownership (B14)
- [ ] Final brand assets, copy, and legal documents (B1, B12, B13)
