<?php

namespace Tests\Feature;

use App\Http\Controllers\CompetitorSelectionController;
use App\Services\GeminiCompetitorDiscoveryService;
use App\Services\GooglePlacesService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class CompetitorSelectionTest extends TestCase
{
    public function test_manual_account_search_filters_reference_and_already_selected_businesses(): void
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
                    'accounts.search',
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

    public function test_ai_website_manual_search_reuses_cached_candidates_for_broader_physical_analysis(): void
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

        $session['analysis.result']['classification']['discovery_mode']
            = 'broader_physical';

        $session['analysis.result']['digital_candidate_pool'] = [
            $salesforce,
            $pipedrive,
        ];

        $response = $this
            ->withSession($session)
            ->getJson(
                route(
                    'accounts.search',
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
                'Lookalike Account'
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
                        => 'Similar CRM platform company.',
                    'fit_score' => 74,
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
                    'accounts.search',
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

    public function test_ai_website_manual_account_can_be_added_for_broader_physical_analysis_without_google_place_details(): void
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

        $session['analysis.result']['classification']['discovery_mode']
            = 'broader_physical';

        $session['analysis.manual_competitor_candidates']
            = [
                $competitorId => $pipedrive,
            ];

        $response = $this
            ->withSession($session)
            ->postJson(
                route('accounts.add'),
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
                'Lookalike Account'
            )
            ->assertJsonPath(
                'competitor.show_distance',
                false
            )
            ->assertJsonPath(
                'competitor.relevance_score',
                null
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

    public function test_manual_account_can_be_added_without_fit_score_and_is_saved_in_session(): void
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
                route('accounts.add'),
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
                'competitor.is_manual',
                true
            )
            ->assertJsonPath(
                'competitor.relevance_score',
                null
            )
            ->assertJsonPath(
                'competitor.evidence',
                'Added manually to the shortlist'
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

    public function test_automatically_discovered_frontend_account_uses_neutral_evidence_fallback(): void
    {
        $presenter = new ReflectionMethod(
            CompetitorSelectionController::class,
            'frontendCompetitor'
        );

        $competitor = $presenter->invoke(
            app(CompetitorSelectionController::class),
            $this->place(
                'automatic-place',
                'Automatic Plumbing'
            )
        );

        $this->assertFalse(
            $competitor['is_manual']
        );

        $this->assertSame(
            'Included from the current analysis',
            $competitor['evidence']
        );
    }

    public function test_manual_add_rejects_reference_company(): void
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
                route('accounts.add'),
                [
                    'place_id'
                        => 'customer-place',
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'The reference company cannot be added as a recommended account.'
            );
    }

    public function test_manual_add_rejects_duplicate_account(): void
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
                route('accounts.add'),
                [
                    'place_id'
                        => 'existing-place',
                ]
            );

        $response
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'This account is already in your shortlist.'
            );
    }

    public function test_selected_account_can_be_removed_and_remains_removed_in_session(): void
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
                route('accounts.remove'),
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

    public function test_step_three_requires_at_least_one_selected_account(): void
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
                route('accounts')
            )
            ->assertSessionHasErrors([
                'competitors',
            ]);
    }

    public function test_results_page_distinguishes_fit_ranked_and_manual_accounts(): void
    {
        $rankedAccount = $this->place(
            'ranked-place',
            'Ranked Plumbing'
        );

        $rankedAccount['_relevance'] = [
            'score' => 86,
            'quality' => 'high',
            'strong_match' => true,
            'type_compatible' => true,
            'evidence' => [
                'matched_queries' => [
                    'plumber',
                ],
            ],
        ];

        $manualAccount = $this->place(
            'manual-place',
            'Manual Plumbing'
        );
        $manualAccount['_manual_selection'] = true;

        $this->withSession(
            $this->analysisSession([
                $rankedAccount,
                $manualAccount,
            ])
        )
            ->get(route('accounts'))
            ->assertOk()
            ->assertSee('Reference company')
            ->assertSee('Recommended accounts')
            ->assertSee('Fit score')
            ->assertSee('86')
            ->assertSee('Manual add')
            ->assertSee('No Fit score')
            ->assertDontSee('Relevance score');
    }

    public function test_step_three_collects_and_saves_analysis_email(): void
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
                'Save contact email'
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

            'analysis.website_scan' => [
                'final_url'
                    => 'https://customer.example',
                'status' => 200,
                'title'
                    => 'Customer Plumbing',
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

                'discovery_source'
                    => 'ai_direct',

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
                => 'lookalike_account',
            'primaryTypeDisplayName' => [
                'text'
                    => 'Lookalike Account',
            ],
            'types' => [
                'lookalike_account',
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
                    => 'Similar B2B product company.',
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
