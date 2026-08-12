<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class GeminiCompetitorDiscoveryService
{
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
                'Gemini competitor discovery is not configured.'
            );
        }

        if (
            data_get(
                $classification,
                'discovery_mode'
            ) !== 'digital_global'
        ) {
            throw new UnexpectedValueException(
                'Semantic AI competitor discovery is only available for digital_global businesses.'
            );
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'x-goog-api-key'
                    => $this->configString('api_key'),
            ])
            ->connectTimeout(
                $this->configInt(
                    'connect_timeout',
                    2,
                    1,
                    10
                )
            )
            ->timeout(
                $this->configInt(
                    'discovery_timeout',
                    12,
                    3,
                    30
                )
            )
            ->post(
                $this->configString('endpoint'),
                $this->requestPayload(
                    $businessProfile,
                    $classification
                )
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gemini competitor discovery request failed with HTTP '
                . $response->status()
                . '.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException(
                'Gemini returned an invalid competitor discovery response.'
            );
        }

        if (
            ($payload['status'] ?? null)
            !== 'completed'
        ) {
            throw new RuntimeException(
                'Gemini competitor discovery did not complete successfully.'
            );
        }

        $text = $this->extractOutputText(
            $payload
        );

        if ($text === null) {
            throw new UnexpectedValueException(
                'Gemini returned no structured competitor discovery output.'
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
                'Gemini returned invalid competitor discovery JSON.',
                0,
                $exception
            );
        }

        if (! is_array($decoded)) {
            throw new UnexpectedValueException(
                'Gemini competitor discovery output must be a JSON object.'
            );
        }

        $competitors = $this->normalizeCompetitors(
            $decoded['competitors'] ?? [],
            $businessProfile
        );

        if ($competitors === []) {
            throw new UnexpectedValueException(
                'Gemini competitor discovery returned no usable competitors.'
            );
        }

        return $competitors;
    }

    /**
     * Search for a manually requested direct competitor inside the same
     * digital/global competitive set. An empty result is valid when the
     * query does not confidently identify a direct competitor.
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
                'Gemini competitor discovery is not configured.'
            );
        }

        if (
            data_get(
                $classification,
                'discovery_mode'
            ) !== 'digital_global'
        ) {
            throw new UnexpectedValueException(
                'Semantic AI competitor search is only available for digital_global businesses.'
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
                'Competitor search query must contain at least 3 characters.'
            );
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'x-goog-api-key'
                    => $this->configString('api_key'),
            ])
            ->connectTimeout(
                $this->configInt(
                    'connect_timeout',
                    2,
                    1,
                    10
                )
            )
            ->timeout(
                $this->configInt(
                    'discovery_timeout',
                    12,
                    3,
                    30
                )
            )
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
                'Gemini competitor search request failed with HTTP '
                . $response->status()
                . '.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException(
                'Gemini returned an invalid competitor search response.'
            );
        }

        if (
            ($payload['status'] ?? null)
            !== 'completed'
        ) {
            throw new RuntimeException(
                'Gemini competitor search did not complete successfully.'
            );
        }

        $text = $this->extractOutputText(
            $payload
        );

        if ($text === null) {
            throw new UnexpectedValueException(
                'Gemini returned no structured competitor search output.'
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
                'Gemini returned invalid competitor search JSON.',
                0,
                $exception
            );
        }

        if (! is_array($decoded)) {
            throw new UnexpectedValueException(
                'Gemini competitor search output must be a JSON object.'
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
                    . ' For manual competitor search, follow the user search text closely. If it names or strongly identifies a specific company or domain, only return it when it is a real direct competitor of the subject. For partial searches, return only direct competitors that genuinely match the text. Return an empty competitors array when there is no confident direct competitor match; never substitute an unrelated company.',

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
                'You are the direct competitor discovery component of a market-intelligence application.',
                'This request is for businesses already classified for semantic, non-local competitor discovery.',
                'Identify direct competing companies whose core product, service, or business model is a realistic substitute for the subject business for similar target customers.',
                'Prefer established companies with substantial overlap in the subject business core offering, customer type, and operating model.',
                'Exclude agencies, consultants, implementation partners, review sites, directories, publishers, and adjacent businesses unless the subject itself operates in that same business model. Do not exclude distributors, brokers, lenders, insurers, marketplaces, or service firms when that is the subject business core model.',
                'Never return the subject company itself.',
                'Do not pad the list with weak or obscure matches just to reach a fixed count; omit uncertain candidates rather than guessing.',
                'Return each company official domain as a hostname only, without protocol, path, query string, or marketing URL.',
                'Keep the reason concise and explain the direct competitive overlap.',
            ]
        );
    }

    private function buildInput(
        array $businessProfile,
        array $classification
    ): string {
        $context = [
            'business_name'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.business_name'
                    ),
                    160
                ),

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
                    800
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
            "Identify up to 8 direct competitors for this non-local/global business.\n"
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
            'business_name'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.business_name'
                    ),
                    160
                ),

            'website_url'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'website.url'
                    ),
                    500
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
        ];

        $context = array_filter(
            $context,
            static fn (mixed $value): bool =>
                $value !== null
                && $value !== []
        );

        return
            'Manual competitor search query: '
            . $query
            . "\nFind up to 6 direct competitors for the subject business that specifically match this query. "
            . "If the query identifies one exact competitor, return that company only. Return an empty list if there is no confident direct competitor match.\n"
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
            = 'Direct product/platform competitors matching the manual search text, ordered by confidence.';

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
                        => 'Direct product/platform competitors ordered from strongest match to weaker match.',
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
                                    => 'Short explanation of direct competitive overlap.',
                            ],
                        ],
                        'required' => [
                            'name',
                            'domain',
                            'reason',
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

            if (
                $name === null
                || $domain === null
                || $reason === null
            ) {
                continue;
            }

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
