<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security (HSTS)
    |--------------------------------------------------------------------------
    |
    | Tells browsers to only ever reach this site over HTTPS. Off by default
    | so local HTTP development is never affected; enable it in production
    | only once HTTPS is working for the whole domain. Even when enabled, the
    | header is only sent on HTTPS requests (browsers ignore it over HTTP).
    |
    | Behind a reverse proxy / load balancer that terminates TLS, Laravel
    | must trust that proxy or every request looks like plain HTTP and the
    | header is never sent — see docs/DEPLOYMENT.md.
    |
    | Browsers remember HSTS for `max_age` seconds, so start small (e.g. 300)
    | when first enabling it and raise it to a year once HTTPS is stable.
    | `include_subdomains` and `preload` are hard to undo — only turn them on
    | when every subdomain is served over HTTPS.
    |
    */

    'hsts' => [
        'enabled' => (bool) env('HSTS_ENABLED', false),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => (bool) env('HSTS_PRELOAD', false),
    ],

];
