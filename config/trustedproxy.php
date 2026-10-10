<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies / load balancers whose X-Forwarded-* headers are trusted
    | (comma separated IPs or CIDR ranges, or "*" to trust the calling proxy,
    | e.g. behind Cloudflare). Read by Laravel's TrustProxies middleware; kept
    | in a config file so it also works after "php artisan config:cache".
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1'),

];
