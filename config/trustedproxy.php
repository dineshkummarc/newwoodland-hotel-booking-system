<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Read by Laravel's TrustProxies middleware. When the site sits behind a
    | reverse proxy / load balancer / Cloudflare that terminates HTTPS, the
    | app only sees the proxy's IP and plain HTTP unless it trusts that proxy's
    | X-Forwarded-* headers. Getting this wrong breaks, silently:
    |   - rate limits (every visitor shares the proxy's IP, so one abuser can
    |     lock everyone out of login/booking/OTP);
    |   - HTTPS detection (secure cookies, HSTS, https links);
    |   - signed links in emails (view booking / cancel / invoice), whose
    |     signature includes the https:// URL.
    |
    | Default: trust NO proxy (correct when PHP faces the internet directly).
    | Set TRUSTED_PROXIES to the proxy addresses ONLY — never a blanket value
    | unless the origin server's firewall accepts traffic from the proxy alone:
    |   - a reverse proxy on the same server:   TRUSTED_PROXIES=127.0.0.1
    |   - a load balancer:                      its private IP(s)/CIDR(s)
    |   - Cloudflare:                           Cloudflare's published ranges
    |                                           (https://www.cloudflare.com/ips/),
    |                                           comma-separated
    | See docs/DEPLOYMENT.md §9.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
