<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Synchronous analysis request budget
    |--------------------------------------------------------------------------
    |
    | Keep the live analysis inside a normal 30-second PHP request while
    | reserving enough time to persist the session and return a response.
    |
    */

    'request_budget_seconds' => (float) env(
        'ANALYSIS_REQUEST_BUDGET_SECONDS',
        24
    ),

    'response_reserve_seconds' => (float) env(
        'ANALYSIS_RESPONSE_RESERVE_SECONDS',
        2
    ),

    'minimum_provider_window_seconds' => (float) env(
        'ANALYSIS_MINIMUM_PROVIDER_WINDOW_SECONDS',
        1.5
    ),

    'provider_fallback_reserve_seconds' => (float) env(
        'ANALYSIS_PROVIDER_FALLBACK_RESERVE_SECONDS',
        6
    ),

    'website_connect_timeout' => (float) env(
        'WEBSITE_SCAN_CONNECT_TIMEOUT',
        2
    ),

    'website_timeout' => (float) env(
        'WEBSITE_SCAN_TIMEOUT',
        8
    ),
];
