<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI business classifier
    |--------------------------------------------------------------------------
    |
    | The application remains fully usable without an AI key. When Gemini is
    | not configured, BusinessIntelligenceService falls back to the existing
    | deterministic BusinessClassifier.
    |
    */

    'provider' => env(
        'AI_CLASSIFIER_PROVIDER',
        'gemini'
    ),

    'gemini' => [
        'api_key' => env(
            'GEMINI_API_KEY'
        ),

        /*
         * Keep the model configurable so we can switch models without
         * touching business logic.
         */
        'model' => env(
            'GEMINI_MODEL',
            'gemini-3.6-flash'
        ),

        /*
         * Interactions API. The endpoint is configurable in case Google
         * promotes/changes an API version later.
         */
        'endpoint' => env(
            'GEMINI_API_ENDPOINT',
            'https://generativelanguage.googleapis.com/v1/interactions'
        ),

        'connect_timeout' => (int) env(
            'GEMINI_CONNECT_TIMEOUT',
            2
        ),

        'timeout' => (int) env(
            'GEMINI_TIMEOUT',
            6
        ),

        /*
         * Direct digital lookalike discovery can require a little more
         * generation time than classification. These remain optional env
         * overrides and do not affect the existing classifier timeout.
         */
        'discovery_timeout' => (int) env(
            'GEMINI_DISCOVERY_TIMEOUT',
            8
        ),

        'discovery_max_output_tokens' => (int) env(
            'GEMINI_DISCOVERY_MAX_OUTPUT_TOKENS',
            1400
        ),

        'max_output_tokens' => (int) env(
            'GEMINI_MAX_OUTPUT_TOKENS',
            900
        ),

        'thinking_level' => env(
            'GEMINI_THINKING_LEVEL',
            'low'
        ),
    ],
];
