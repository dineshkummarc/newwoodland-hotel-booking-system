# Remaining Work

- Uncommitted: today's changes (password reset flow, Register nav link, robots.txt fix, OG image fix) still on `master`
- Deliberately not implemented: email verification — architecture doesn't currently require it (no `verified` middleware anywhere); adding it would add friction to the booking flow without being requested elsewhere. Revisit if the client wants it.
- Client-dependent items (see prior audit): real photos, real logo, real social links, real testimonials, exact map pin, domain, hosting, production SMTP
- Unresolved: hero blur report (unconfirmed, no CSS cause found)
