<?php

namespace Tests\Feature;

use App\Services\GeminiCompetitorDiscoveryService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use UnexpectedValueException;

class GeminiCompetitorDiscoveryServiceTest extends TestCase
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

            'ai.gemini.discovery_timeout'
                => 12,

            'ai.gemini.discovery_max_output_tokens'
                => 1400,

            'ai.gemini.thinking_level'
                => 'low',
        ]);
    }

    public function test_it_is_not_configured_without_api_key(): void
    {
        $service = app(
            GeminiCompetitorDiscoveryService::class
        );

        $this->assertFalse(
            $service->isConfigured()
        );
    }

    public function test_it_requests_structured_direct_competitors_and_normalizes_output(): void
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
                            'type' => 'model_output',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode(
                                        [
                                            'competitors' => [
                                                [
                                                    'name' => 'HubSpot',
                                                    'domain' => 'hubspot.com',
                                                    'reason' => 'Subject company.',
                                                ],
                                                [
                                                    'name' => 'Salesforce',
                                                    'domain' => 'https://www.salesforce.com/products/crm/',
                                                    'reason' => 'Competes across CRM, sales, service and marketing software.',
                                                ],
                                                [
                                                    'name' => 'Zoho',
                                                    'domain' => 'zoho.com',
                                                    'reason' => 'Competes with an integrated CRM and business software suite.',
                                                ],
                                                [
                                                    'name' => 'Freshworks',
                                                    'domain' => 'freshworks.com',
                                                    'reason' => 'Competes across CRM and customer service software.',
                                                ],
                                                [
                                                    'name' => 'Salesforce',
                                                    'domain' => 'salesforce.com',
                                                    'reason' => 'Duplicate.',
                                                ],
                                                [
                                                    'name' => 'Invalid Agency',
                                                    'domain' => 'not a domain',
                                                    'reason' => 'Invalid domain.',
                                                ],
                                            ],
                                        ],
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
            GeminiCompetitorDiscoveryService::class
        )->discover(
            $this->hubspotProfile(),
            $this->hubspotClassification()
        );

        $this->assertSame(
            [
                [
                    'name' => 'Salesforce',
                    'domain' => 'salesforce.com',
                    'reason' => 'Competes across CRM, sales, service and marketing software.',
                ],
                [
                    'name' => 'Zoho',
                    'domain' => 'zoho.com',
                    'reason' => 'Competes with an integrated CRM and business software suite.',
                ],
                [
                    'name' => 'Freshworks',
                    'domain' => 'freshworks.com',
                    'reason' => 'Competes across CRM and customer service software.',
                ],
            ],
            $result
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
                        'response_format.schema.properties.competitors.maxItems'
                    ) === 8
                    && data_get(
                        $data,
                        'response_format.schema.additionalProperties'
                    ) === false
                    && str_contains(
                        (string) data_get(
                            $data,
                            'input',
                            ''
                        ),
                        'HubSpot'
                    )
                    && str_contains(
                        (string) data_get(
                            $data,
                            'input',
                            ''
                        ),
                        'CRM and Marketing Automation Software Provider'
                    );
            }
        );
    }

    public function test_manual_search_returns_matching_direct_competitor_and_allows_empty_results(): void
    {
        config([
            'ai.gemini.api_key'
                => 'test-gemini-key',
        ]);

        Http::fake([
            '*' => Http::sequence()
                ->push(
                    [
                        'status' => 'completed',
                        'steps' => [
                            [
                                'type' => 'model_output',
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => json_encode(
                                            [
                                                'competitors' => [
                                                    [
                                                        'name' => 'Pipedrive',
                                                        'domain' => 'pipedrive.com',
                                                        'reason' => 'Direct sales CRM competitor.',
                                                    ],
                                                ],
                                            ],
                                            JSON_THROW_ON_ERROR
                                        ),
                                    ],
                                ],
                            ],
                        ],
                    ],
                    200
                )
                ->push(
                    [
                        'status' => 'completed',
                        'steps' => [
                            [
                                'type' => 'model_output',
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => json_encode(
                                            [
                                                'competitors' => [],
                                            ],
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

        $service = app(
            GeminiCompetitorDiscoveryService::class
        );

        $this->assertSame(
            [
                [
                    'name' => 'Pipedrive',
                    'domain' => 'pipedrive.com',
                    'reason' => 'Direct sales CRM competitor.',
                ],
            ],
            $service->search(
                $this->hubspotProfile(),
                $this->hubspotClassification(),
                'Pipedrive'
            )
        );

        $this->assertSame(
            [],
            $service->search(
                $this->hubspotProfile(),
                $this->hubspotClassification(),
                'unrelated agency'
            )
        );

        Http::assertSent(
            function (Request $request): bool {
                $data = $request->data();

                return
                    data_get(
                        $data,
                        'response_format.schema.properties.competitors.minItems'
                    ) === 0
                    && data_get(
                        $data,
                        'response_format.schema.properties.competitors.maxItems'
                    ) === 6
                    && str_contains(
                        (string) data_get(
                            $data,
                            'input',
                            ''
                        ),
                        'Pipedrive'
                    );
            }
        );
    }

    public function test_it_allows_broader_physical_classification_for_website_discovery(): void
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
                            'type' => 'model_output',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => json_encode(
                                        [
                                            'competitors' => [
                                                [
                                                    'name' => 'Salesforce',
                                                    'domain' => 'salesforce.com',
                                                    'reason'
                                                        => 'Direct company-level competitor.',
                                                ],
                                            ],
                                        ],
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

        $classification = $this->hubspotClassification();
        $classification['discovery_mode']
            = 'broader_physical';

        $result = app(
            GeminiCompetitorDiscoveryService::class
        )->discover(
            $this->hubspotProfile(),
            $classification
        );

        $this->assertCount(
            1,
            $result
        );

        $this->assertSame(
            'Salesforce',
            $result[0]['name']
        );

        $this->assertSame(
            'salesforce.com',
            $result[0]['domain']
        );

        Http::assertSent(
            function (Request $request): bool {
                $data = $request->data();

                return
                    str_contains(
                        (string) data_get(
                            $data,
                            'system_instruction',
                            ''
                        ),
                        'company-level competitors'
                    )
                    && str_contains(
                        (string) data_get(
                            $data,
                            'input',
                            ''
                        ),
                        'hubspot.com'
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
                            'type' => 'model_output',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => '{"competitors":',
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
            GeminiCompetitorDiscoveryService::class
        )->discover(
            $this->hubspotProfile(),
            $this->hubspotClassification()
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
                GeminiCompetitorDiscoveryService::class
            )->discover(
                $this->hubspotProfile(),
                $this->hubspotClassification()
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

    private function hubspotProfile(): array
    {
        return [
            'website' => [
                'url' => 'https://www.hubspot.com/',
            ],
            'classification_input' => [
                'business_name' => 'HubSpot',
                'website_title' => 'HubSpot | Software & Tools for your Business',
                'website_description'
                    => 'Customer platform with CRM, marketing, sales and customer service software.',
            ],
        ];
    }

    private function hubspotClassification(): array
    {
        return [
            'business_model' => 'SaaS company',
            'business_type'
                => 'CRM and Marketing Automation Software Provider',
            'vertical' => 'technology',
            'industry' => 'CRM & Marketing Technology',
            'market_scope' => 'broader',
            'discovery_mode' => 'digital_global',
            'service_keywords' => [
                'CRM',
                'marketing automation',
                'sales software',
                'customer service software',
            ],
            'target_customers' => [
                'SMBs',
                'mid-market businesses',
            ],
            'competitor_types' => [
                'CRM Software Providers',
                'Marketing Automation Platforms',
            ],
            '_classification_source' => 'ai',
        ];
    }
}