<?php

namespace App\Services;

use App\Contracts\AiBusinessClassifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class GeminiBusinessClassifier implements AiBusinessClassifier
{
    private const MAX_HOMEPAGE_TEXT = 5000;

    private const MAX_HEADINGS = 10;

    private const MAX_GOOGLE_TYPES = 12;

    public function name(): string
    {
        return 'gemini';
    }

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
    public function classify(array $businessProfile): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Gemini business classifier is not configured.'
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
                    'timeout',
                    8,
                    2,
                    20
                )
            )
            ->post(
                $this->configString('endpoint'),
                $this->requestPayload(
                    $businessProfile
                )
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gemini classification request failed with HTTP '
                . $response->status()
                . '.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException(
                'Gemini returned an invalid response body.'
            );
        }

        if (
            ($payload['status'] ?? null)
            !== 'completed'
        ) {
            throw new RuntimeException(
                'Gemini classification did not complete successfully.'
            );
        }

        $text = $this->extractOutputText(
            $payload
        );

        if ($text === null) {
            throw new UnexpectedValueException(
                'Gemini returned no structured classification.'
            );
        }

        try {
            $classification = json_decode(
                $text,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new UnexpectedValueException(
                'Gemini returned invalid structured JSON.',
                0,
                $exception
            );
        }

        if (! is_array($classification)) {
            throw new UnexpectedValueException(
                'Gemini classification must be a JSON object.'
            );
        }

        return $classification;
    }

    private function requestPayload(
        array $businessProfile
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
                $businessProfile
            ),

            'system_instruction'
                => $this->systemInstruction(),

            'response_format' => [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $this->schema(),
            ],

            /*
             * Classification is stateless. There is no reason to retain an
             * interaction server-side for a later conversation turn.
             */
            'store' => false,

            'generation_config' => [
                'max_output_tokens'
                    => $this->configInt(
                        'max_output_tokens',
                        900,
                        300,
                        2000
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
                'You are the business classification component of a competitor-discovery application.',
                'Use only the supplied public website and Google Business Profile context.',
                'A Google Business category can be broad, incomplete, or misleading, so determine the actual operating business model from all available evidence.',
                'Explicitly distinguish distributor, manufacturer, systems integrator, SaaS company, local service provider, agency, retailer, wholesaler, marketplace, and other business models.',
                'Choose discovery_mode by how true competitors should be discovered, not merely by industry: local_physical for geographically local businesses; broader_physical for real-world companies competing across a region or country where business/location directories are still useful; digital_global for digital products, SaaS, cloud/API products, online platforms, marketplaces, or other businesses whose true competitors are brands/products rather than nearby offices; hybrid only when both physical/local and digital/broader competitor discovery are genuinely important.',
                'Focus on companies that sell a substitutable solution to similar target customers.',
                'Search queries must be generic category or service phrases suitable for Google Places.',
                'Never put the subject company name, a competitor company name, a domain, or a URL in search_queries.',
                'Do not invent or return a list of competitor companies.',
                'Keep products_services and search_queries focused on the business core rather than incidental catalog items.',
            ]
        );
    }

    private function buildInput(
        array $businessProfile
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

            'headings'
                => $this->boundedStringList(
                    data_get(
                        $businessProfile,
                        'classification_input.headings',
                        []
                    ),
                    self::MAX_HEADINGS,
                    240
                ),

            'homepage_text'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.homepage_text'
                    ),
                    self::MAX_HOMEPAGE_TEXT
                ),

            'google_primary_type'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.primary_type'
                    ),
                    160
                ),

            'google_primary_type_name'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.primary_type_name'
                    ),
                    160
                ),

            'google_types'
                => $this->boundedStringList(
                    data_get(
                        $businessProfile,
                        'classification_input.google_types',
                        []
                    ),
                    self::MAX_GOOGLE_TYPES,
                    160
                ),

            'google_editorial_summary'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'classification_input.google_editorial_summary'
                    ),
                    1200
                ),

            'formatted_address'
                => $this->boundedString(
                    data_get(
                        $businessProfile,
                        'location.address'
                    ),
                    500
                ),
        ];

        $context = array_filter(
            $context,
            static fn (mixed $value): bool =>
                $value !== null
                && $value !== []
        );

        return
            "Classify this business for competitor discovery.\n"
            . "Business context:\n"
            . json_encode(
                $context,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRETTY_PRINT
                | JSON_THROW_ON_ERROR
            );
    }

    private function schema(): array
    {
        $shortString = static fn (
            string $description
        ): array => [
            'type' => 'string',
            'description' => $description,
        ];

        $stringArray = static fn (
            string $description,
            int $minItems,
            int $maxItems
        ): array => [
            'type' => 'array',
            'description' => $description,
            'items' => [
                'type' => 'string',
            ],
            'minItems' => $minItems,
            'maxItems' => $maxItems,
        ];

        return [
            'type' => 'object',

            'properties' => [
                'business_model'
                    => $shortString(
                        'Actual operating model, for example industrial distributor and systems integrator, manufacturer, SaaS company, local service provider, agency, retailer, or wholesaler.'
                    ),

                'business_type'
                    => $shortString(
                        'A concise competitor category suitable for matching and Google Places, for example Industrial Automation Distributor, Plumber, or CRM Software Company.'
                    ),

                'vertical' => [
                    'type' => 'string',
                    'description'
                        => 'Broad vertical used by the application.',
                    'enum' => [
                        'home_services',
                        'healthcare_local',
                        'beauty_wellness',
                        'financial_services',
                        'industrial',
                        'technology',
                        'retail',
                        'professional_services',
                        'hospitality',
                        'other',
                    ],
                ],

                'industry'
                    => $shortString(
                        'Specific industry or market vertical in plain language.'
                    ),

                'market_scope' => [
                    'type' => 'string',
                    'description'
                        => 'Whether meaningful competitors are primarily local, broader/national/global, or a hybrid of both.',
                    'enum' => [
                        'local',
                        'broader',
                        'hybrid',
                    ],
                ],

                'discovery_mode' => [
                    'type' => 'string',
                    'description'
                        => 'How competitors should be discovered: local physical businesses, broader physical businesses, global digital/product competitors, or a genuine hybrid of physical and digital discovery.',
                    'enum' => [
                        'local_physical',
                        'broader_physical',
                        'digital_global',
                        'hybrid',
                    ],
                ],

                'products_services'
                    => $stringArray(
                        'Core products, services, or solution categories that define the business. Exclude incidental catalog items.',
                        1,
                        10
                    ),

                'target_customers'
                    => $stringArray(
                        'Main customer or buyer groups.',
                        0,
                        6
                    ),

                'competitor_types'
                    => $stringArray(
                        'Generic types of businesses that would be true competitors. Do not give company names.',
                        1,
                        6
                    ),

                'search_queries'
                    => $stringArray(
                        'Two to four focused generic Google Places search phrases. No company names, domains, or URLs.',
                        2,
                        4
                    ),

                'geography_weight' => [
                    'type' => 'string',
                    'description'
                        => 'How strongly physical distance should affect competitor relevance.',
                    'enum' => [
                        'high',
                        'medium',
                        'low',
                    ],
                ],

                'confidence' => [
                    'type' => 'string',
                    'description'
                        => 'Confidence in the classification.',
                    'enum' => [
                        'high',
                        'medium',
                        'low',
                    ],
                ],
            ],

            'required' => [
                'business_model',
                'business_type',
                'vertical',
                'industry',
                'market_scope',
                'discovery_mode',
                'products_services',
                'target_customers',
                'competitor_types',
                'search_queries',
                'geography_weight',
                'confidence',
            ],

            'additionalProperties' => false,
        ];
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
