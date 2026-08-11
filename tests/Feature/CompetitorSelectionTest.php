<?php

namespace Tests\Feature;

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
