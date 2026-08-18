<?php

namespace Tests\Feature;

use App\Services\GeminiBusinessClassifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use UnexpectedValueException;

class GeminiBusinessClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.gemini.api_key'
                => null,

            'ai.gemini.model'
                => 'gemini-3.6-flash',

            'ai.gemini.endpoint'
                => 'https://generativelanguage.googleapis.com/v1/interactions',

            'ai.gemini.connect_timeout'
                => 2,

            'ai.gemini.timeout'
                => 8,

            'ai.gemini.max_output_tokens'
                => 900,

            'ai.gemini.thinking_level'
                => 'low',
        ]);
    }

    public function test_it_is_not_configured_without_api_key(): void
    {
        $classifier = app(
            GeminiBusinessClassifier::class
        );

        $this->assertFalse(
            $classifier->isConfigured()
        );
    }

    public function test_it_requests_structured_output_and_parses_classification(): void
    {
        config([
            'ai.gemini.api_key'
                => 'test-gemini-key',
        ]);

        $classification = $this->industrialDistributorAiResult();

        Http::fake([
            '*' => Http::response(
                [
                    'status' => 'completed',
                    'steps' => [
                        [
                            'type'
                                => 'model_output',

                            'content' => [
                                [
                                    'type'
                                        => 'text',

                                    'text'
                                        => json_encode(
                                            $classification,
                                            JSON_THROW_ON_ERROR
                                        ),
                                ],
                            ],
                        ],
                    ],
                ],
                200
            ),
        ]);

        $result = app(
            GeminiBusinessClassifier::class
        )->classify(
            $this->industrialDistributorProfile()
        );

        $this->assertSame(
            'industrial distributor and systems integrator',
            $result['business_model']
        );

        $this->assertSame(
            'Industrial Automation Distributor',
            $result['business_type']
        );

        $this->assertSame(
            'broader_physical',
            $result['discovery_mode']
        );

        Http::assertSent(
            function (Request $request): bool {
                $data = $request->data();

                return
                    $request->method() === 'POST'
                    && $request->url()
                        === 'https://generativelanguage.googleapis.com/v1/interactions'
                    && $request->hasHeader(
                        'x-goog-api-key',
                        'test-gemini-key'
                    )
                    && data_get(
                        $data,
                        'model'
                    ) === 'gemini-3.6-flash'
                    && data_get(
                        $data,
                        'store'
                    ) === false
                    && data_get(
                        $data,
                        'response_format.mime_type'
                    ) === 'application/json'
                    && data_get(
                        $data,
                        'response_format.schema.additionalProperties'
                    ) === false
                    && data_get(
                        $data,
                        'response_format.schema.properties.search_queries.maxItems'
                    ) === 4
                    && data_get(
                        $data,
                        'response_format.schema.properties.discovery_mode.enum'
                    ) === [
                        'local_physical',
                        'broader_physical',
                        'digital_global',
                        'hybrid',
                    ]
                    && in_array(
                        'discovery_mode',
                        data_get(
                            $data,
                            'response_format.schema.required',
                            []
                        ),
                        true
                    )
                    && str_contains(
                        (string) data_get(
                            $data,
                            'input',
                            ''
                        ),
                        'google_editorial_summary'
                    )
                    && str_contains(
                        (string) data_get(
                            $data,
                            'input',
                            ''
                        ),
                        'Industrial automation distributor and systems integrator serving manufacturers and OEMs.'
                    );
            }
        );
    }

    public function test_it_rejects_invalid_json(): void
    {
        config([
            'ai.gemini.api_key'
                => 'test-gemini-key',
        ]);

        Http::fake([
            '*' => Http::response(
                [
                    'status' => 'completed',
                    'steps' => [
                        [
                            'type'
                                => 'model_output',

                            'content' => [
                                [
                                    'type'
                                        => 'text',

                                    'text'
                                        => '{"business_model":',
                                ],
                            ],
                        ],
                    ],
                ],
                200
            ),
        ]);

        $this->expectException(
            UnexpectedValueException::class
        );

        app(
            GeminiBusinessClassifier::class
        )->classify(
            $this->industrialDistributorProfile()
        );
    }

    public function test_it_rejects_api_errors_without_exposing_response_body(): void
    {
        config([
            'ai.gemini.api_key'
                => 'test-gemini-key',
        ]);

        Http::fake([
            '*' => Http::response(
                [
                    'error' => [
                        'message'
                            => 'sensitive provider response text',
                    ],
                ],
                429
            ),
        ]);

        try {
            app(
                GeminiBusinessClassifier::class
            )->classify(
                $this->industrialDistributorProfile()
            );

            $this->fail(
                'Expected RuntimeException was not thrown.'
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'HTTP 429',
                $exception->getMessage()
            );

            $this->assertStringNotContainsString(
                'sensitive provider response text',
                $exception->getMessage()
            );
        }
    }

    private function industrialDistributorAiResult(): array
    {
        return [
            'business_model'
                => 'industrial distributor and systems integrator',

            'business_type'
                => 'Industrial Automation Distributor',

            'vertical'
                => 'industrial',

            'industry'
                => 'Industrial automation, motion control and fluid power',

            'market_scope'
                => 'broader',

            'discovery_mode'
                => 'broader_physical',

            'products_services' => [
                'motion control',
                'industrial automation',
                'fluid power',
                'industrial filtration',
                'hydraulics',
                'pneumatics',
                'electromechanical solutions',
            ],

            'target_customers' => [
                'manufacturers',
                'industrial facilities',
                'OEMs',
            ],

            'competitor_types' => [
                'industrial automation distributor',
                'motion control distributor',
                'fluid power distributor',
            ],

            'search_queries' => [
                'Motion Control Distributor',
                'Industrial Automation Distributor',
                'Fluid Power Distributor',
                'Industrial Filtration Distributor',
            ],

            'geography_weight'
                => 'low',

            'confidence'
                => 'high',
        ];
    }

    private function industrialDistributorProfile(): array
    {
        return [
            'classification_input' => [
                'business_name'
                    => 'Example Industrial Co',

                'website_title'
                    => 'Industrial Solutions for Engineered Systems',

                'website_description'
                    => 'Motion & Control, Industrial Filtration and Automation Solutions',

                'homepage_text'
                    => 'We distribute and integrate hydraulic, pneumatic, electromechanical and drive control systems for industrial customers.',

                'primary_type'
                    => 'manufacturer',

                'primary_type_name'
                    => 'Manufacturer',

                'google_types' => [
                    'manufacturer',
                    'establishment',
                ],

                'google_editorial_summary'
                    => 'Industrial automation distributor and systems integrator serving manufacturers and OEMs.',

                'headings' => [
                    'Our World Class Brands',
                    'Featured Products',
                ],
            ],

            'location' => [
                'latitude'
                    => 43.6334199,

                'longitude'
                    => -79.6604936,

                'address'
                    => '5789 Coopers Ave, Mississauga, ON L4Z 3S6, Canada',
            ],

            'google_business' => [
                'place_id'
                    => 'example-place',
            ],

            'website' => [
                'url'
                    => 'https://example-industrial.test/',
            ],
        ];
    }
}
