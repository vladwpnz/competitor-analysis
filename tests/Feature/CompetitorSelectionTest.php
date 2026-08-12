<?php

namespace Tests\Feature;

use App\Services\GeminiCompetitorDiscoveryService;
use App\Services\GooglePlacesService;
use Mockery;
use Tests\TestCase;

class CompetitorSelectionTest extends TestCase
{
    public function test_manual_competitor_search_filters_own_and_already_selected_businesses(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $googlePlaces
            ->shouldReceive('searchBusinesses')
            ->once()
            ->with(
                'Metro Plumbing',
                6
            )
            ->andReturn([
                $this->place(
                    'customer-place',
                    'Customer Plumbing'
                ),
                $this->place(
                    'existing-place',
                    'Existing Plumbing'
                ),
                $this->place(
                    'new-place',
                    'Metro Plumbing'
                ),
            ]);

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this
            ->withSession(
                $this->analysisSession([
                    $this->place(
                        'existing-place',
                        'Existing Plumbing'
                    ),
                ])
            )
            ->getJson(
                route(
                    'competitors.search',
                    [
                        'q'
                            => 'Metro Plumbing',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'suggestions'
            )
            ->assertJsonPath(
                'suggestions.0.place_id',
                'new-place'
            )
            ->assertJsonPath(
                'suggestions.0.name',
                'Metro Plumbing'
            );
    }

    public function test_digital_global_manual_search_reuses_cached_ai_candidates_before_live_api(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces->shouldNotReceive(
            'isConfigured'
        );

        $googlePlaces->shouldNotReceive(
            'searchBusinesses'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $digitalDiscovery = Mockery::mock(
            GeminiCompetitorDiscoveryService::class
        );

        $digitalDiscovery->shouldNotReceive(
            'isConfigured'
        );

        $digitalDiscovery->shouldNotReceive(
            'search'
        );

        $this->app->instance(
            GeminiCompetitorDiscoveryService::class,
            $digitalDiscovery
        );

        $salesforce = $this->digitalCompetitor(
            'salesforce.com',
            'Salesforce'
        );

        $pipedrive = $this->digitalCompetitor(
            'pipedrive.com',
            'Pipedrive'
        );

        $session = $this->digitalAnalysisSession([
            $salesforce,
        ]);

        $session['analysis.result']['digital_candidate_pool'] = [
            $salesforce,
            $pipedrive,
        ];

        $response = $this
            ->withSession($session)
            ->getJson(
                route(
                    'competitors.search',
                    [
                        'q' => 'Pipedrive',
                    ]
                )
            );

        $expectedId = (string) data_get(
            $pipedrive,
            'id'
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'suggestions'
            )
            ->assertJsonPath(
                'suggestions.0.place_id',
                $expectedId
            )
            ->assertJsonPath(
                'suggestions.0.name',
                'Pipedrive'
            )
            ->assertJsonPath(
                'suggestions.0.category',
                'Direct Product Competitor'
            );

        $response->assertSessionHas(
            'analysis.manual_competitor_candidates',
            function (array $candidates) use ($expectedId): bool {
                return
                    isset($candidates[$expectedId])
                    && data_get(
                        $candidates[$expectedId],
                        'websiteUri'
                    ) === 'https://pipedrive.com'
                    && data_get(
                        $candidates[$expectedId],
                        '_manual_selection'
                    ) === true;
            }
        );
    }

    public function test_digital_global_manual_search_falls_back_to_live_ai_when_cache_has_no_match(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces->shouldNotReceive(
            'isConfigured'
        );

        $googlePlaces->shouldNotReceive(
            'searchBusinesses'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $digitalDiscovery = Mockery::mock(
            GeminiCompetitorDiscoveryService::class
        );

        $digitalDiscovery
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $digitalDiscovery
            ->shouldReceive('search')
            ->once()
            ->with(
                Mockery::type('array'),
                Mockery::on(
                    fn (array $classification): bool =>
                        data_get(
                            $classification,
                            'discovery_mode'
                        ) === 'digital_global'
                ),
                'Copper CRM'
            )
            ->andReturn([
                [
                    'name' => 'Copper CRM',
                    'domain' => 'copper.com',
                    'reason'
                        => 'Direct CRM platform competitor.',
                ],
            ]);

        $this->app->instance(
            GeminiCompetitorDiscoveryService::class,
            $digitalDiscovery
        );

        $salesforce = $this->digitalCompetitor(
            'salesforce.com',
            'Salesforce'
        );

        $session = $this->digitalAnalysisSession([
            $salesforce,
        ]);

        $session['analysis.result']['digital_candidate_pool'] = [
            $salesforce,
        ];

        $response = $this
            ->withSession($session)
            ->getJson(
                route(
                    'competitors.search',
                    [
                        'q' => 'Copper CRM',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'suggestions'
            )
            ->assertJsonPath(
                'suggestions.0.name',
                'Copper CRM'
            );
    }

    public function test_digital_global_manual_competitor_can_be_added_without_google_place_details(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces->shouldNotReceive(
            'isConfigured'
        );

        $googlePlaces->shouldNotReceive(
            'getPlaceDetails'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $pipedrive = $this->digitalCompetitor(
            'pipedrive.com',
            'Pipedrive',
            true
        );

        $competitorId = (string) data_get(
            $pipedrive,
            'id'
        );

        $session = $this->digitalAnalysisSession([
            $this->digitalCompetitor(
                'salesforce.com',
                'Salesforce'
            ),
        ]);

        $session['analysis.manual_competitor_candidates']
            = [
                $competitorId => $pipedrive,
            ];

        $response = $this
            ->withSession($session)
            ->postJson(
                route('competitors.add'),
                [
                    'place_id'
                        => $competitorId,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'competitor.place_id',
                $competitorId
            )
            ->assertJsonPath(
                'competitor.name',
                'Pipedrive'
            )
            ->assertJsonPath(
                'competitor.category',
                'Direct Product Competitor'
            )
            ->assertJsonPath(
                'competitor.show_distance',
                false
            )
            ->assertJsonPath(
                'count',
                2
            );

        $response->assertSessionHas(
            'analysis.selected_competitors',
            function (array $competitors) use ($competitorId): bool {
                return
                    count($competitors) === 2
                    && data_get(
                        $competitors,
                        '1.id'
                    ) === $competitorId
                    && data_get(
                        $competitors,
                        '1._manual_selection'
                    ) === true
                    && data_get(
                        $competitors,
                        '1._discovery.source'
                    ) === 'ai_manual';
            }
        );
    }

    public function test_manual_competitor_can_be_added_and_is_saved_in_session(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $googlePlaces
            ->shouldReceive('getPlaceDetails')
            ->once()
            ->with('new-place')
            ->andReturn(
                $this->place(
                    'new-place',
                    'Metro Plumbing',
                    43.70,
                    -79.40
                )
            );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this
            ->withSession(
                $this->analysisSession([
                    $this->place(
                        'existing-place',
                        'Existing Plumbing'
                    ),
                ])
            )
            ->postJson(
                route('competitors.add'),
                [
                    'place_id'
                        => 'new-place',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'competitor.place_id',
                'new-place'
            )
            ->assertJsonPath(
                'competitor.name',
                'Metro Plumbing'
            )
            ->assertJsonPath(
                'count',
                2
            );

        $response->assertSessionHas(
            'analysis.selected_competitors',
            function (array $competitors): bool {
                return
                    count($competitors) === 2
                    && data_get(
                        $competitors,
                        '1.id'
                    ) === 'new-place'
                    && data_get(
                        $competitors,
                        '1._manual_selection'
                    ) === true;
            }
        );
    }

    public function test_manual_add_rejects_own_business(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces->shouldNotReceive(
            'isConfigured'
        );

        $googlePlaces->shouldNotReceive(
            'getPlaceDetails'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this
            ->withSession(
                $this->analysisSession([])
            )
            ->postJson(
                route('competitors.add'),
                [
                    'place_id'
                        => 'customer-place',
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Your own business cannot be added as a competitor.'
            );
    }

    public function test_manual_add_rejects_duplicate_business(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces->shouldNotReceive(
            'isConfigured'
        );

        $googlePlaces->shouldNotReceive(
            'getPlaceDetails'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this
            ->withSession(
                $this->analysisSession([
                    $this->place(
                        'existing-place',
                        'Existing Plumbing'
                    ),
                ])
            )
            ->postJson(
                route('competitors.add'),
                [
                    'place_id'
                        => 'existing-place',
                ]
            );

        $response
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'This competitor is already in your list.'
            );
    }

    public function test_selected_competitor_can_be_removed_and_remains_removed_in_session(): void
    {
        $response = $this
            ->withSession(
                $this->analysisSession([
                    $this->place(
                        'first-place',
                        'First Plumbing'
                    ),
                    $this->place(
                        'second-place',
                        'Second Plumbing'
                    ),
                ])
            )
            ->deleteJson(
                route('competitors.remove'),
                [
                    'place_id'
                        => 'first-place',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'count',
                1
            );

        $response->assertSessionHas(
            'analysis.selected_competitors',
            function (array $competitors): bool {
                return
                    count($competitors) === 1
                    && data_get(
                        $competitors,
                        '0.id'
                    ) === 'second-place';
            }
        );
    }

    public function test_step_three_requires_at_least_one_selected_competitor(): void
    {
        $response = $this
            ->withSession(
                $this->analysisSession([])
            )
            ->get(
                route('analysis.email')
            );

        $response
            ->assertRedirect(
                route('competitors')
            )
            ->assertSessionHasErrors([
                'competitors',
            ]);
    }

    public function test_step_three_collects_and_saves_report_email(): void
    {
        $session = $this->analysisSession([
            $this->place(
                'existing-place',
                'Existing Plumbing'
            ),
        ]);

        $page = $this
            ->withSession($session)
            ->get(
                route('analysis.email')
            );

        $page
            ->assertOk()
            ->assertSee(
                'STEP 3 OF 3'
            )
            ->assertSee(
                'Get My Free Report'
            )
            ->assertSee(
                'Customer Plumbing',
                false
            );

        $response = $this
            ->withSession($session)
            ->post(
                route(
                    'analysis.email.store'
                ),
                [
                    'email'
                        => 'owner@example.com',
                ]
            );

        $response
            ->assertRedirect(
                route('analysis.email')
            )
            ->assertSessionHas(
                'analysis.contact_email',
                'owner@example.com'
            )
            ->assertSessionHas(
                'analysis_email_saved',
                true
            );
    }

    private function analysisSession(
        array $selectedCompetitors
    ): array {
        return [
            'analysis.website'
                => 'https://customer.example',

            'analysis.google_business'
                => 'Customer Plumbing, Toronto, ON',

            'analysis.google_place_id'
                => 'customer-place',

            'analysis.google_place' => [
                'id'
                    => 'customer-place',

                'displayName' => [
                    'text'
                        => 'Customer Plumbing',
                ],

                'location' => [
                    'latitude'
                        => 43.6532,

                    'longitude'
                        => -79.3832,
                ],
            ],

            'analysis.result' => [
                'search_profile' => [
                    'market_scope'
                        => 'local',
                ],

                'top_competitors'
                    => $selectedCompetitors,
            ],

            'analysis.selected_competitors'
                => $selectedCompetitors,
        ];
    }

    private function digitalAnalysisSession(
        array $selectedCompetitors
    ): array {
        return [
            'analysis.website'
                => 'https://www.hubspot.com/',

            'analysis.google_business'
                => 'HubSpot, Cambridge, MA',

            'analysis.google_place_id'
                => 'hubspot-place',

            'analysis.google_place' => [
                'id' => 'hubspot-place',
                'displayName' => [
                    'text' => 'HubSpot',
                ],
            ],

            'analysis.result' => [
                'business_profile' => [
                    'website' => [
                        'url'
                            => 'https://www.hubspot.com/',
                    ],
                    'classification_input' => [
                        'business_name'
                            => 'HubSpot',
                    ],
                ],

                'classification' => [
                    'business_model'
                        => 'SaaS company',
                    'business_type'
                        => 'CRM and Marketing Automation Software Provider',
                    'vertical'
                        => 'technology',
                    'industry'
                        => 'CRM & Marketing Technology',
                    'market_scope'
                        => 'broader',
                    'discovery_mode'
                        => 'digital_global',
                    'service_keywords' => [
                        'CRM',
                        'marketing automation',
                    ],
                ],

                'search_profile' => [
                    'market_scope'
                        => 'broader',
                ],

                'top_competitors'
                    => $selectedCompetitors,
            ],

            'analysis.selected_competitors'
                => $selectedCompetitors,
        ];
    }

    private function digitalCompetitor(
        string $domain,
        string $name,
        bool $manual = false
    ): array {
        $id =
            'ai-'
            . substr(
                hash(
                    'sha256',
                    $domain
                ),
                0,
                24
            );

        return [
            'id' => $id,
            'displayName' => [
                'text' => $name,
            ],
            'primaryType'
                => 'digital_platform',
            'primaryTypeDisplayName' => [
                'text'
                    => 'Direct Product Competitor',
            ],
            'types' => [
                'digital_platform',
            ],
            'websiteUri'
                => 'https://' . $domain,
            '_manual_selection'
                => $manual,
            '_match' => [
                'queries' => [],
                'executed_queries' => [],
                'query_hits' => 0,
                'search_modes' => [
                    $manual
                        ? 'ai_direct_manual'
                        : 'ai_direct',
                ],
                'distance_km' => null,
            ],
            '_discovery' => [
                'source'
                    => $manual
                        ? 'ai_manual'
                        : 'ai',
                'domain' => $domain,
                'reason'
                    => 'Direct product competitor.',
            ],
        ];
    }

    private function place(
        string $placeId,
        string $name,
        float $latitude = 43.66,
        float $longitude = -79.38
    ): array {
        return [
            'id' => $placeId,

            'displayName' => [
                'text' => $name,
            ],

            'formattedAddress'
                => '100 Main St, Toronto, ON, Canada',

            'primaryType'
                => 'plumber',

            'primaryTypeDisplayName' => [
                'text' => 'Plumber',
            ],

            'types' => [
                'plumber',
            ],

            'location' => [
                'latitude'
                    => $latitude,

                'longitude'
                    => $longitude,
            ],

            'businessStatus'
                => 'OPERATIONAL',

            'websiteUri'
                => 'https://'
                . $placeId
                . '.example.com',

            'googleMapsUri'
                => 'https://maps.google.com/?cid='
                . $placeId,

            'rating' => 4.8,

            'userRatingCount'
                => 120,
        ];
    }
}
