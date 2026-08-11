<?php

namespace Tests\Feature;

use App\Services\CompetitorAnalysisService;
use App\Services\GooglePlacesService;
use App\Services\WebsiteScanner;
use Mockery;
use Tests\TestCase;

class AnalysisFlowTest extends TestCase
{
    public function test_selected_google_business_runs_analysis_and_populates_step_two(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $websiteScan = [
            'final_url'
                => 'https://acmeplumbing.com',

            'status' => 200,

            'title'
                => 'Acme Plumbing',

            'meta_description'
                => 'Emergency plumbing and drain cleaning.',

            'h1' => [
                'Professional Plumbing Services',
            ],

            'h2' => [
                'Emergency Plumbing',
                'Drain Cleaning',
            ],

            'text'
                => 'Acme Plumbing provides emergency plumbing and drain cleaning.',
        ];

        $googlePlace = [
            'id' => 'customer-place',

            'displayName' => [
                'text'
                    => 'Acme Plumbing',

                'languageCode'
                    => 'en',
            ],

            'formattedAddress'
                => '100 Main St, Toronto, ON',

            'primaryType'
                => 'plumber',

            'primaryTypeDisplayName' => [
                'text'
                    => 'Plumber',

                'languageCode'
                    => 'en',
            ],

            'types' => [
                'plumber',
            ],

            'location' => [
                'latitude'
                    => 43.6532,

                'longitude'
                    => -79.3832,
            ],

            'businessStatus'
                => 'OPERATIONAL',

            'pureServiceAreaBusiness'
                => false,

            'websiteUri'
                => 'https://acmeplumbing.com',

            'googleMapsUri'
                => 'https://maps.google.com/acme',

            'rating' => 4.7,

            'userRatingCount' => 140,
        ];

        $analysisResult = [
            'business_profile'
                => [],

            'classification' => [
                'vertical'
                    => 'home_services',

                'market_scope'
                    => 'local',
            ],

            'search_profile' => [
                'business_type'
                    => 'Plumber',
            ],

            'candidate_count'
                => 6,

            'top_competitors' => [
                [
                    'id'
                        => 'competitor-1',

                    'displayName' => [
                        'text'
                            => 'Metro Plumbing',
                    ],

                    'primaryType'
                        => 'plumber',

                    '_relevance' => [
                        'score'
                            => 91.5,

                        'quality'
                            => 'high',

                        'strong_match'
                            => true,
                    ],
                ],
            ],

            'strong_match_count'
                => 1,

            'has_competitors'
                => true,
        ];

        $websiteScanner =
            Mockery::mock(
                WebsiteScanner::class
            );

        $websiteScanner
            ->shouldReceive('scan')
            ->once()
            ->with(
                'https://acmeplumbing.com'
            )
            ->andReturn(
                $websiteScan
            );

        $this->app->instance(
            WebsiteScanner::class,
            $websiteScanner
        );

        $googlePlaces =
            Mockery::mock(
                GooglePlacesService::class
            );

        $googlePlaces
            ->shouldReceive(
                'isConfigured'
            )
            ->once()
            ->andReturn(true);

        $googlePlaces
            ->shouldReceive(
                'getPlaceDetails'
            )
            ->once()
            ->with(
                'customer-place',
                $sessionToken,
                true
            )
            ->andReturn(
                $googlePlace
            );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $competitorAnalysis =
            Mockery::mock(
                CompetitorAnalysisService::class
            );

        $competitorAnalysis
            ->shouldReceive(
                'analyze'
            )
            ->once()
            ->with(
                $websiteScan,
                $googlePlace
            )
            ->andReturn(
                $analysisResult
            );

        $this->app->instance(
            CompetitorAnalysisService::class,
            $competitorAnalysis
        );

        $response =
            $this->post(
                route(
                    'analysis.start'
                ),
                [
                    'website'
                        => 'https://acmeplumbing.com',

                    'google_business'
                        => 'Acme Plumbing',

                    'google_place_id'
                        => 'customer-place',

                    'google_places_session_token'
                        => $sessionToken,
                ]
            );

        $response->assertRedirect(
            route('competitors')
        );

        $response->assertSessionHas(
            'analysis.website',
            'https://acmeplumbing.com'
        );

        $response->assertSessionHas(
            'analysis.google_business',
            'Acme Plumbing'
        );

        $response->assertSessionHas(
            'analysis.google_place_id',
            'customer-place'
        );

        $response->assertSessionHas(
            'analysis.google_places_session_token',
            $sessionToken
        );

        $response->assertSessionHas(
            'analysis.website_scan',
            $websiteScan
        );

        $response->assertSessionHas(
            'analysis.google_place',
            $googlePlace
        );

        $response->assertSessionHas(
            'analysis.result',
            $analysisResult
        );

        $page =
            $this->get(
                route(
                    'competitors'
                )
            );

        $page->assertOk();

        $page->assertViewHas(
            'topCompetitors',
            function (
                array $competitors
            ): bool {
                return
                    count(
                        $competitors
                    ) === 1
                    && $competitors[0]['id']
                        === 'competitor-1'
                    && data_get(
                        $competitors,
                        '0._relevance.score'
                    ) === 91.5;
            }
        );
    }

    public function test_website_only_flow_still_works_before_google_selector_is_connected(): void
    {
        $websiteScan = [
            'final_url'
                => 'https://example.com',

            'status' => 200,

            'title'
                => 'Example Company',

            'meta_description'
                => null,

            'h1' => [
                'Example Company',
            ],

            'h2' => [],

            'text'
                => 'Example Company website.',
        ];

        $websiteScanner =
            Mockery::mock(
                WebsiteScanner::class
            );

        $websiteScanner
            ->shouldReceive('scan')
            ->once()
            ->with(
                'https://example.com'
            )
            ->andReturn(
                $websiteScan
            );

        $this->app->instance(
            WebsiteScanner::class,
            $websiteScanner
        );

        $googlePlaces =
            Mockery::mock(
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

        $competitorAnalysis =
            Mockery::mock(
                CompetitorAnalysisService::class
            );

        $competitorAnalysis->shouldNotReceive(
            'analyze'
        );

        $this->app->instance(
            CompetitorAnalysisService::class,
            $competitorAnalysis
        );

        $response =
            $this->post(
                route(
                    'analysis.start'
                ),
                [
                    'website'
                        => 'https://example.com',

                    'google_business'
                        => 'Example Business',
                ]
            );

        $response->assertRedirect(
            route('competitors')
        );

        $response->assertSessionHas(
            'analysis.website_scan',
            $websiteScan
        );

        $this->assertTrue(
            session()->exists(
                'analysis.google_place_id'
            )
        );

        $this->assertNull(
            session(
                'analysis.google_place_id'
            )
        );

        $this->assertTrue(
            session()->exists(
                'analysis.google_places_session_token'
            )
        );

        $this->assertNull(
            session(
                'analysis.google_places_session_token'
            )
        );

        $this->assertTrue(
            session()->exists(
                'analysis.result'
            )
        );

        $this->assertNull(
            session(
                'analysis.result'
            )
        );

        $page =
            $this->get(
                route(
                    'competitors'
                )
            );

        $page->assertOk();

        $page->assertViewHas(
            'topCompetitors',
            []
        );
    }

    public function test_selected_google_business_requires_configured_places_api(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $websiteScan = [
            'final_url'
                => 'https://example.com',

            'status' => 200,

            'title'
                => 'Example Company',

            'meta_description'
                => null,

            'h1' => [
                'Example Company',
            ],

            'h2' => [],

            'text'
                => 'Example Company website.',
        ];

        $websiteScanner =
            Mockery::mock(
                WebsiteScanner::class
            );

        $websiteScanner
            ->shouldReceive('scan')
            ->once()
            ->andReturn(
                $websiteScan
            );

        $this->app->instance(
            WebsiteScanner::class,
            $websiteScanner
        );

        $googlePlaces =
            Mockery::mock(
                GooglePlacesService::class
            );

        $googlePlaces
            ->shouldReceive(
                'isConfigured'
            )
            ->once()
            ->andReturn(false);

        $googlePlaces->shouldNotReceive(
            'getPlaceDetails'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $competitorAnalysis =
            Mockery::mock(
                CompetitorAnalysisService::class
            );

        $competitorAnalysis->shouldNotReceive(
            'analyze'
        );

        $this->app->instance(
            CompetitorAnalysisService::class,
            $competitorAnalysis
        );

        $response =
            $this->from(
                route('home')
            )->post(
                route(
                    'analysis.start'
                ),
                [
                    'website'
                        => 'https://example.com',

                    'google_business'
                        => 'Example Business',

                    'google_place_id'
                        => 'example-place',

                    'google_places_session_token'
                        => $sessionToken,
                ]
            );

        $response->assertRedirect(
            route('home')
        );

        $response->assertSessionHasErrors([
            'google_business'
                => 'Google Business lookup is not configured yet.',
        ]);
    }

    public function test_selected_google_business_requires_autocomplete_session_token(): void
    {
        $response =
            $this->from(
                route('home')
            )->post(
                route(
                    'analysis.start'
                ),
                [
                    'website'
                        => 'https://example.com',

                    'google_business'
                        => 'Example Business',

                    'google_place_id'
                        => 'example-place',
                ]
            );

        $response->assertRedirect(
            route('home')
        );

        $response->assertSessionHasErrors([
            'google_places_session_token',
        ]);
    }

    public function test_change_website_prefills_existing_analysis_and_keeps_google_selection(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $response = $this
            ->withSession([
                'analysis.website'
                    => 'https://acmeplumbing.com',

                'analysis.google_business'
                    => 'Acme Plumbing',

                'analysis.google_place_id'
                    => 'customer-place',

                'analysis.google_places_session_token'
                    => $sessionToken,
            ])
            ->get(
                route(
                    'home',
                    ['edit' => 'website']
                )
            );

        $response->assertOk();

        $response->assertSee(
            'value="https://acmeplumbing.com"',
            false
        );

        $response->assertSee(
            'value="Acme Plumbing"',
            false
        );

        $response->assertSee(
            'value="customer-place"',
            false
        );

        $response->assertSee(
            'value="'.$sessionToken.'"',
            false
        );

        $response->assertSee(
            'name="edit_mode"',
            false
        );

        $response->assertSee(
            'value="website"',
            false
        );
    }

    public function test_change_google_business_prefills_existing_website_and_selection(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $response = $this
            ->withSession([
                'analysis.website'
                    => 'https://acmeplumbing.com',

                'analysis.google_business'
                    => 'Acme Plumbing',

                'analysis.google_place_id'
                    => 'customer-place',

                'analysis.google_places_session_token'
                    => $sessionToken,
            ])
            ->get(
                route(
                    'home',
                    ['edit' => 'google_business']
                )
            );

        $response->assertOk();

        $response->assertSee(
            'value="https://acmeplumbing.com"',
            false
        );

        $response->assertSee(
            'value="Acme Plumbing"',
            false
        );

        $response->assertSee(
            'value="google_business"',
            false
        );
    }

    public function test_selected_google_business_continues_when_website_returns_http_403(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $fallbackWebsiteScan = [
            'final_url'
                => 'https://blocked.example',

            'status' => 403,

            'title' => null,

            'meta_description' => null,

            'h1' => [],

            'h2' => [],

            'text' => '',

            'available' => false,
        ];

        $googlePlace = [
            'id' => 'blocked-customer-place',

            'displayName' => [
                'text' => 'Blocked Website Plumbing',
                'languageCode' => 'en',
            ],

            'formattedAddress'
                => '100 Main St, Austin, TX',

            'primaryType'
                => 'plumber',

            'primaryTypeDisplayName' => [
                'text' => 'Plumber',
                'languageCode' => 'en',
            ],

            'types' => [
                'plumber',
            ],

            'location' => [
                'latitude' => 30.2672,
                'longitude' => -97.7431,
            ],
        ];

        $analysisResult = [
            'business_profile' => [],

            'classification' => [
                'vertical' => 'home_services',
                'market_scope' => 'local',
            ],

            'search_profile' => [
                'business_type' => 'Plumber',
            ],

            'candidate_count' => 5,

            'top_competitors' => [],

            'strong_match_count' => 0,

            'has_competitors' => false,
        ];

        $websiteScanner =
            Mockery::mock(
                WebsiteScanner::class
            );

        $websiteScanner
            ->shouldReceive('scan')
            ->once()
            ->with(
                'https://blocked.example'
            )
            ->andThrow(
                new \RuntimeException(
                    'Website returned HTTP status 403.'
                )
            );

        $this->app->instance(
            WebsiteScanner::class,
            $websiteScanner
        );

        $googlePlaces =
            Mockery::mock(
                GooglePlacesService::class
            );

        $googlePlaces
            ->shouldReceive(
                'isConfigured'
            )
            ->once()
            ->andReturn(true);

        $googlePlaces
            ->shouldReceive(
                'getPlaceDetails'
            )
            ->once()
            ->with(
                'blocked-customer-place',
                $sessionToken,
                true
            )
            ->andReturn(
                $googlePlace
            );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $competitorAnalysis =
            Mockery::mock(
                CompetitorAnalysisService::class
            );

        $competitorAnalysis
            ->shouldReceive(
                'analyze'
            )
            ->once()
            ->with(
                $fallbackWebsiteScan,
                $googlePlace
            )
            ->andReturn(
                $analysisResult
            );

        $this->app->instance(
            CompetitorAnalysisService::class,
            $competitorAnalysis
        );

        $response =
            $this->post(
                route(
                    'analysis.start'
                ),
                [
                    'website'
                        => 'https://blocked.example',

                    'google_business'
                        => 'Blocked Website Plumbing',

                    'google_place_id'
                        => 'blocked-customer-place',

                    'google_places_session_token'
                        => $sessionToken,
                ]
            );

        $response->assertRedirect(
            route('competitors')
        );

        $response->assertSessionHas(
            'analysis.website_scan',
            $fallbackWebsiteScan
        );

        $response->assertSessionHas(
            'analysis.website_scan_warning',
            'We couldn’t read this website directly, so these matches are based mainly on the Google Business Profile.'
        );
    }

    public function test_non_http_website_scan_failure_is_not_bypassed(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $websiteScanner =
            Mockery::mock(
                WebsiteScanner::class
            );

        $websiteScanner
            ->shouldReceive('scan')
            ->once()
            ->andThrow(
                new \RuntimeException(
                    'Local or internal websites cannot be scanned.'
                )
            );

        $this->app->instance(
            WebsiteScanner::class,
            $websiteScanner
        );

        $googlePlaces =
            Mockery::mock(
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

        $competitorAnalysis =
            Mockery::mock(
                CompetitorAnalysisService::class
            );

        $competitorAnalysis->shouldNotReceive(
            'analyze'
        );

        $this->app->instance(
            CompetitorAnalysisService::class,
            $competitorAnalysis
        );

        $response =
            $this->from(
                route('home')
            )->post(
                route(
                    'analysis.start'
                ),
                [
                    'website'
                        => 'https://example.com',

                    'google_business'
                        => 'Example Business',

                    'google_place_id'
                        => 'example-place',

                    'google_places_session_token'
                        => $sessionToken,
                ]
            );

        $response->assertRedirect(
            route('home')
        );

        $response->assertSessionHasErrors([
            'website'
                => 'Local or internal websites cannot be scanned.',
        ]);
    }


}
