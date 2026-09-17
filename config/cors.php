<?php

declare(strict_types=1);

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
     * Only the frontend origin. Never '*' — the API is consumed from a different
     * domain and a wildcard would let any site drive it with a stolen token.
     *
     * array_filter() only ever removes an empty FRONTEND_URL — it never
     * substitutes the default above, since env() already applies that default
     * before array_filter() runs. env()'s default only fires when the variable
     * is entirely unset; if it is set to an empty string, env() returns that
     * empty string, and array_filter() drops it, leaving allowed_origins as
     * []. An empty allow-list rejects every origin, including the real
     * frontend. That fails closed, which is the right direction, but it is a
     * silent total outage rather than a startup error — if the frontend
     * suddenly can't reach the API, check for an empty (not just missing)
     * FRONTEND_URL first.
     */
    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    /*
     * False: authentication is by bearer token, not cookies. Credentialed CORS
     * would only be needed for cookie-based sessions.
     */
    'supports_credentials' => false,
];
