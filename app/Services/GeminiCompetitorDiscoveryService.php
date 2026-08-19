<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class GeminiCompetitorDiscoveryService
{
    private readonly AnalysisDeadline $analysisDeadline;

    public function __construct(
        ?AnalysisDeadline $analysisDeadline = null
    ) {
        $this->analysisDeadline = $analysisDeadline
            ?? new AnalysisDeadline();
    }

    private const MAX_COMPETITORS = 8;

    private const MAX_SEARCH_RESULTS = 6;

    public function isConfigured(): bool
    {
        return
            $this->configString('api_key') !== null
            && $this->configString('model') !== null
            && $this->configString('endpoint') !== null;
    }

    /**
     * @throws ConnectionException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function discover(
        array $businessProfile,
        array $classification
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Gemini account discovery is not configured.'
            );
        }

        $requestTimeout = $this->discoveryRequestTimeout();
        $connectTimeout = $this->connectTimeout(
            $requestTimeout
        );

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'x-goog-api-key'
                    => $this->configString('api_key'),
            ])
            ->connectTimeout($connectTimeout)
            ->timeout($requestTimeout)
            ->post(
                $this->configString('endpoint'),
                $this->requestPayload(
                    $businessProfile,
                    $classification
                )
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gemini account discovery request failed with HTTP '
                . $response->status()
                . '.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException(
                'Gemini returned an invalid account discovery response.'
            );
        }

        if (
            ($payload['status'] ?? null)
            !== 'completed'
        ) {
            throw new RuntimeException(
                'Gemini account discovery did not complete successfully.'
            );
        }

        $text = $this->extractOutputText(
            $payload
        );

        if ($text === null) {
            throw new UnexpectedValueException(
                'Gemini returned no structured account discovery output.'
            );
        }

        try {
            $decoded = json_decode(
                $text,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new UnexpectedValueException(
                'Gemini returned invalid account discovery JSON.',
                0,
                $exception
            );
        }

        if (! is_array($decoded)) {
            throw new UnexpectedValueException(
                'Gemini account discovery output must be a JSON object.'
            );
        }

        $competitors = $this->normalizeCompetitors(
            $decoded['competitors'] ?? [],
            $businessProfile
        );

        if ($competitors === []) {
            throw new UnexpectedValueException(
                'Gemini account discovery returned no usable accounts.'
            );
        }

        return $competitors;
    }

    /**
     * Search for a manually requested account inside the same reference
     * profile. An empty result is valid when the query does not confidently
     * identify a real company.
     *
     * @throws ConnectionException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function search(
        array $businessProfile,
        array $classification,
        string $query
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Gemini account discovery is not configured.'
            );
        }

        $query = $this->boundedString(
            $query,
            120
        );

        if (
            $query === null
            || mb_strlen($query) < 3
        ) {
            throw new UnexpectedValueException(
                'Account search query must contain at least 3 characters.'
            );
        }

        $requestTimeout = $this->discoveryRequestTimeout();
        $connectTimeout = $this->connectTimeout(
            $requestTimeout
        );

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'x-goog-api-key'
                    => $this->configString('api_key'),
            ])
            ->connectTimeout($connectTimeout)
            ->timeout($requestTimeout)
            ->post(
                $this->configString('endpoint'),
                $this->manualSearchRequestPayload(
                    $businessProfile,
                    $classification,
                    $query
                )
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gemini account search request failed with HTTP '
                . $response->status()
                . '.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException(
                'Gemini returned an invalid account search response.'
            );
        }

        if (
            ($payload['status'] ?? null)
            !== 'completed'
        ) {
            throw new RuntimeException(
                'Gemini account search did not complete successfully.'
            );
        }

        $text = $this->extractOutputText(
            $payload
        );

        if ($text === null) {
            throw new UnexpectedValueException(
                'Gemini returned no structured account search output.'
            );
        }

        try {
            $decoded = json_decode(
                $text,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new UnexpectedValueException(
                'Gemini returned invalid account search JSON.',
                0,
                $exception
            );
        }

        if (! is_array($decoded)) {
            throw new UnexpectedValueException(
                'Gemini account search output must be a JSON object.'
            );
        }

        return $this->normalizeCompetitors(
            $decoded['competitors'] ?? [],
            $businessProfile
        );
    }

    private function requestPayload(
        array $businessProfile,
        array $classification
    ): array {
        $thinkingLevel = mb_strtolower(
            trim(
                (string) config(
                    'ai.gemini.thinking_level',
                    'low'
                )
            )
        );

        if (
            ! in_array(
                $thinkingLevel,
                [
                    'minimal',
                    'low',
                    'medium',
                    'high',
                ],
                true
            )
        ) {
            $thinkingLevel = 'low';
        }

        return [
            'model' => $this->configString(
                'model'
            ),

            'input' => $this->buildInput(
                $businessProfile,
                $classification
            ),

            'system_instruction'
                => $this->systemInstruction(),

            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $this->schema(),
            ],

            'store' => false,

            'generation_config' => [
                'max_output_tokens'
                    => $this->configInt(
                        'discovery_max_output_tokens',
                        1400,
                        500,
                        2500
                    ),

                'thinking_level'
                    => $thinkingLevel,

                'thinking_summaries'
                    => 'none',
            ],
        ];
    }

    private function manualSearchRequestPayload(
        array $businessProfile,
        array $classification,
        string $query
    ): array {
        $thinkingLevel = mb_strtolower(
            trim(
                (string) config(
                    'ai.gemini.thinking_level',
                    'low'
                )
            )
        );

        if (
            ! in_array(
                $thinkingLevel,
                [
                    'minimal',
                    'low',
                    'medium',
                    'high',
                ],
                true
            )
        ) {
            $thinkingLevel = 'low';
        }

        return [
            'model' => $this->configString(
                'model'
            ),

            'input' => $this->buildManualSearchInput(
                $businessProfile,
                $classification,
                $query
            ),

            'system_instruction'
                => $this->systemInstruction()
                    . ' For manual account search, follow the user search text closely. If it names or strongly identifies a specific company or domain, return only that real company when it exists. For partial searches, return only real companies that genuinely match the text and plausibly fit the reference profile. Return an empty competitors array when there is no confident company match; never substitute an unrelated company.',

            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $this->manualSearchSchema(),
            ],

            'store' => false,

            'generation_config' => [
                'max_output_tokens'
                    => $this->configInt(
                        'discovery_max_output_tokens',
                        1400,
                        500,
                        2500
                    ),

                'thinking_level'
                    => $thinkingLevel,

                'thinking_summaries'
                    => 'none',
            ],
        ];
    }

    private function systemInstruction(): string
    {
        return implode(
            ' ',
            [
                'You are the lookalike-account discovery component of an account-intelligence application.',
                'The reference website is the primary source of truth for company identity, products and services, business model, target customers, market scope, and operating model.',
                'If homepage content is unavailable or blocked, a valid reference website URL or domain is still primary identity evidence. Use that domain together with the supporting business name and classification to understand the overall company. Do not switch to branch-local matches just because page text is missing.',
                'Compare company to company and website to website. Identify real companies that resemble the reference account across commercially meaningful characteristics.',
                'Matching should weigh business-model similarity, industry, core offering, target customers, B2B or B2C orientation, commercial position, operating-market scope, physical or digital operating model, and geography where it matters.',
                'A returned company does not need to sell a directly substitutable solution or compete with the reference company.',
                'Use classification fields as supporting interpretation of the website. A Google Business Profile or branch name may help identify the reference company, but it must not make discovery location-first.',
                'Do not prefer a company merely because it is geographically close to the selected Google Business location.',
                'For a multi-location, regional, national, or global company, return company-level lookalikes to the overall website and business, not nearby branches chosen only by distance.',
                'For a genuinely local business, geographic context is useful when finding similar physical businesses.',
                'Prefer established companies with substantial overlap in operating model, core offering, customer type, and market scope.',
                'Exclude review sites, directories, publishers, and companies with a materially different operating model unless the reference company itself uses that model.',
                'Never return the reference company itself, one of its branches, subsidiaries presented as the same brand, or duplicate locations of the same recommended company.',
                'Do not pad the list with weak or obscure matches just to reach a fixed count; omit uncertain candidates rather than guessing.',
                'Return each company official domain as a hostname only, without protocol, path, query string, or marketing URL.',
                'Use a calibrated fit_score from 0 to 100. Reserve scores above 85 for unusually close matches and do not make every recommendation strong.',
                'Keep the reason concise and explain the account-profile similarities supported by the supplied evidence.',
            ]
        );
    }

    private function buildInput(
        array $businessProfile,
        array $classification
    ): string {
        $context = [
            'website_url'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'website.url'
                    ),
                    500
                ),

            'website_title'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.website_title'
                    ),
                    300
                ),

            'website_description'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.website_description'
                    ),
                    1000
                ),

            'website_headings'
                => $this->boundedStringList(
                    data_get(
                        $businessProfile,
                        'classification_input.headings',
                        []
                    ),
                    12,
                    220
                ),

            'website_homepage_text'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.homepage_text'
                    ),
                    5000
                ),

            'supporting_business_name'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.business_name'
                    ),
                    160
                ),

            'business_model'
                => $this->boundedString(
                    data_get(
                        $classification,
                        'business_model'
                    ),
                    160
                ),

            'business_type'
                => $this->boundedString(
                    data_get(
                        $classification,
                        'business_type'
                    ),
                    140
                ),

            'vertical'
                => $this->boundedString(
                    data_get(
                        $classification,
                        'vertical'
                    ),
                    80
                ),

            'industry'
                => $this->boundedString(
                    data_get(
                        $classification,
                        'industry'
                    ),
                    180
                ),

            'products_services'
                => $this->boundedStringList(
                    data_get(
                        $classification,
                        'service_keywords',
                        []
                    ),
                    10,
                    120
                ),

            'target_customers'
                => $this->boundedStringList(
                    data_get(
                        $classification,
                        'target_customers',
                        []
                    ),
                    6,
                    140
                ),

            'competitor_types'
                => $this->boundedStringList(
                    data_get(
                        $classification,
                        'competitor_types',
                        []
                    ),
                    6,
                    140
                ),
        ];

        $context = array_filter(
            $context,
            static fn (mixed $value): bool =>
                $value !== null
                && $value !== []
        );

        return
            "Identify up to 8 real company-level lookalike accounts for the reference website.\n"
            . "Treat the website fields as primary evidence. Use the classification as supporting interpretation.\n"
            . "Rank companies by reference-account fit, not by how directly they compete.\n"
            . "Do not use proximity to a selected Google Business location as the sole reason for a match.\n"
            . "Business context:\n"
            . json_encode(
                $context,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRETTY_PRINT
                | JSON_THROW_ON_ERROR
            );
    }

    private function buildManualSearchInput(
        array $businessProfile,
        array $classification,
        string $query
    ): string {
        $context = [
            'website_url'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'website.url'
                    ),
                    500
                ),

            'website_title'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.website_title'
                    ),
                    300
                ),

            'website_description'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.website_description'
                    ),
                    1000
                ),

            'website_headings'
                => $this->boundedStringList(
                    data_get(
                        $businessProfile,
                        'classification_input.headings',
                        []
                    ),
                    12,
                    220
                ),

            'website_homepage_text'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.homepage_text'
                    ),
                    4000
                ),

            'supporting_business_name'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.business_name'
                    ),
                    160
                ),

            'business_model'
                => $this->boundedString(
                    data_get(
                        $classification,
                        'business_model'
                    ),
                    160
                ),

            'business_type'
                => $this->boundedString(
                    data_get(
                        $classification,
                        'business_type'
                    ),
                    140
                ),

            'industry'
                => $this->boundedString(
                    data_get(
                        $classification,
                        'industry'
                    ),
                    180
                ),

            'products_services'
                => $this->boundedStringList(
                    data_get(
                        $classification,
                        'service_keywords',
                        []
                    ),
                    10,
                    120
                ),

            'target_customers'
                => $this->boundedStringList(
                    data_get(
                        $classification,
                        'target_customers',
                        []
                    ),
                    6,
                    140
                ),
        ];

        $context = array_filter(
            $context,
            static fn (mixed $value): bool =>
                $value !== null
                && $value !== []
        );

        return
            'Manual account search query: '
            . $query
            . "\nFind up to 6 real companies that specifically match this query and are plausible additions to the reference-account shortlist. "
            . "Treat the website as the primary source of truth and compare company to company, not branch to nearby branch. "
            . "If the query identifies one exact company, return that company only. Return an empty list if there is no confident company match.\n"
            . "Business context:\n"
            . json_encode(
                $context,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRETTY_PRINT
                | JSON_THROW_ON_ERROR
            );
    }

    private function manualSearchSchema(): array
    {
        $schema = $this->schema();

        $schema['properties']['competitors']['minItems']
            = 0;

        $schema['properties']['competitors']['maxItems']
            = self::MAX_SEARCH_RESULTS;

        $schema['properties']['competitors']['description']
            = 'Real companies matching the manual search text, ordered by confidence.';

        return $schema;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',

            'properties' => [
                'competitors' => [
                    'type' => 'array',
                    'description'
                        => 'Lookalike accounts ordered from strongest reference-account fit to weaker fit.',
                    'minItems' => 1,
                    'maxItems' => self::MAX_COMPETITORS,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description'
                                    => 'Canonical company or product-company name.',
                            ],
                            'domain' => [
                                'type' => 'string',
                                'description'
                                    => 'Official company hostname only, for example salesforce.com.',
                            ],
                            'reason' => [
                                'type' => 'string',
                                'description'
                                    => 'Short explanation of the account-profile similarities.',
                            ],
                            'fit_score' => [
                                'type' => 'integer',
                                'description'
                                    => 'Calibrated reference-account fit score from 0 to 100.',
                                'minimum' => 0,
                                'maximum' => 100,
                            ],
                        ],
                        'required' => [
                            'name',
                            'domain',
                            'reason',
                            'fit_score',
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],

            'required' => [
                'competitors',
            ],

            'additionalProperties' => false,
        ];
    }

    private function normalizeCompetitors(
        mixed $competitors,
        array $businessProfile
    ): array {
        if (! is_array($competitors)) {
            return [];
        }

        $subjectName = $this->normalizeName(
            data_get(
                $businessProfile,
                'classification_input.business_name'
            )
        );

        $subjectDomain = $this->normalizeDomain(
            data_get(
                $businessProfile,
                'website.url'
            )
        );

        $result = [];
        $seenNames = [];
        $seenDomains = [];

        foreach ($competitors as $competitor) {
            if (! is_array($competitor)) {
                continue;
            }

            $name = $this->boundedString(
                $competitor['name'] ?? null,
                120
            );

            $domain = $this->normalizeDomain(
                $competitor['domain'] ?? null
            );

            $reason = $this->boundedString(
                $competitor['reason'] ?? null,
                360
            );

            $fitScore = $competitor['fit_score'] ?? null;

            if (
                $name === null
                || $domain === null
                || $reason === null
                || ! is_numeric($fitScore)
            ) {
                continue;
            }

            $fitScore = (int) round(
                max(
                    0,
                    min(100, (float) $fitScore)
                )
            );

            $normalizedName = $this->normalizeName(
                $name
            );

            if ($normalizedName === null) {
                continue;
            }

            if (
                $subjectName !== null
                && $normalizedName === $subjectName
            ) {
                continue;
            }

            if (
                $subjectDomain !== null
                && $domain === $subjectDomain
            ) {
                continue;
            }

            if (
                isset($seenNames[$normalizedName])
                || isset($seenDomains[$domain])
            ) {
                continue;
            }

            $seenNames[$normalizedName] = true;
            $seenDomains[$domain] = true;

            $result[] = [
                'name' => $name,
                'domain' => $domain,
                'reason' => $reason,
                'fit_score' => $fitScore,
            ];

            if (
                count($result)
                >= self::MAX_COMPETITORS
            ) {
                break;
            }
        }

        return $result;
    }

    private function normalizeDomain(
        mixed $value
    ): ?string {
        $value = $this->boundedString(
            $value,
            500
        );

        if ($value === null) {
            return null;
        }

        $candidate = str_contains(
            $value,
            '://'
        )
            ? $value
            : 'https://' . $value;

        $host = parse_url(
            $candidate,
            PHP_URL_HOST
        );

        if (! is_string($host)) {
            return null;
        }

        $host = mb_strtolower(
            trim(
                $host,
                ". \t\n\r\0\x0B"
            )
        );

        if (str_starts_with($host, 'www.')) {
            $host = substr(
                $host,
                4
            );
        }

        if (
            $host === ''
            || filter_var(
                $host,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) === false
        ) {
            return null;
        }

        return $host;
    }

    private function normalizeName(
        mixed $value
    ): ?string {
        $value = $this->boundedString(
            $value,
            160
        );

        if ($value === null) {
            return null;
        }

        $value = mb_strtolower(
            $value
        );

        $value = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            $value
        ) ?? $value;

        $value = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? $value
        );

        return $value === ''
            ? null
            : $value;
    }

    private function extractOutputText(
        array $payload
    ): ?string {
        $steps = $payload['steps'] ?? [];

        if (! is_array($steps)) {
            return null;
        }

        $chunks = [];

        foreach ($steps as $step) {
            if (
                ! is_array($step)
                || ($step['type'] ?? null)
                    !== 'model_output'
            ) {
                continue;
            }

            $content = $step['content'] ?? [];

            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $item) {
                if (
                    ! is_array($item)
                    || ($item['type'] ?? null)
                        !== 'text'
                    || ! is_string(
                        $item['text'] ?? null
                    )
                ) {
                    continue;
                }

                $text = trim(
                    $item['text']
                );

                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        if ($chunks === []) {
            return null;
        }

        return implode(
            '',
            $chunks
        );
    }

    private function boundedString(
        mixed $value,
        int $maxLength
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? $value
        );

        if ($value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            $maxLength
        );
    }

    private function boundedStringList(
        mixed $values,
        int $maxItems,
        int $maxLength
    ): array {
        if (! is_array($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $value) {
            $value = $this->boundedString(
                $value,
                $maxLength
            );

            if ($value === null) {
                continue;
            }

            $key = mb_strtolower(
                $value
            );

            if (isset($result[$key])) {
                continue;
            }

            $result[$key] = $value;

            if (
                count($result)
                >= $maxItems
            ) {
                break;
            }
        }

        return array_values(
            $result
        );
    }

    private function configString(
        string $key
    ): ?string {
        $value = config(
            'ai.gemini.' . $key
        );

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function discoveryRequestTimeout(): float
    {
        return $this->analysisDeadline
            ->timeoutFor(
                $this->configInt(
                    'discovery_timeout',
                    8,
                    2,
                    30
                ),
                0.5,
                (float) config(
                    'analysis.provider_fallback_reserve_seconds',
                    6
                )
            );
    }

    private function connectTimeout(
        float $requestTimeout
    ): float {
        return min(
            $requestTimeout,
            (float) $this->configInt(
                'connect_timeout',
                2,
                1,
                10
            )
        );
    }

    private function configInt(
        string $key,
        int $default,
        int $minimum,
        int $maximum
    ): int {
        $value = config(
            'ai.gemini.' . $key,
            $default
        );

        if (! is_numeric($value)) {
            return $default;
        }

        return max(
            $minimum,
            min(
                (int) $value,
                $maximum
            )
        );
    }
}
