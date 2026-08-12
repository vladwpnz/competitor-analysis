<?php

namespace Tests\Feature;

use App\Services\GooglePlacesService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleBusinessSearchTest extends TestCase
{
    public function test_it_returns_google_business_suggestions(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $googlePlaces
            ->shouldReceive('autocompleteBusinesses')
            ->once()
            ->with(
                'Acme Plumbing',
                $sessionToken
            )
            ->andReturn([
                [
                    'placePrediction' => [
                        'placeId'
                            => 'place-acme-1',

                        'text' => [
                            'text'
                                => 'Acme Plumbing, Toronto, ON, Canada',
                        ],

                        'structuredFormat' => [
                            'mainText' => [
                                'text'
                                    => 'Acme Plumbing',
                            ],

                            'secondaryText' => [
                                'text'
                                    => 'Toronto, ON, Canada',
                            ],
                        ],

                        'types' => [
                            'plumber',
                            'establishment',
                        ],
                    ],
                ],

                [
                    'placePrediction' => [
                        'placeId'
                            => 'place-acme-2',

                        'text' => [
                            'text'
                                => 'Acme Plumbing Services, Mississauga, ON, Canada',
                        ],

                        'structuredFormat' => [
                            'mainText' => [
                                'text'
                                    => 'Acme Plumbing Services',
                            ],

                            'secondaryText' => [
                                'text'
                                    => 'Mississauga, ON, Canada',
                            ],
                        ],

                        'types' => [
                            'plumber',
                            'establishment',
                        ],
                    ],
                ],
            ]);

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this->getJson(
            route(
                'google-business.search',
                [
                    'q'
                        => 'Acme Plumbing',

                    'session_token'
                        => $sessionToken,
                ]
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'suggestions'
            )
            ->assertJsonPath(
                'suggestions.0.place_id',
                'place-acme-1'
            )
            ->assertJsonPath(
                'suggestions.0.name',
                'Acme Plumbing'
            )
            ->assertJsonPath(
                'suggestions.0.secondary_text',
                'Toronto, ON, Canada'
            )
            ->assertJsonPath(
                'suggestions.0.types.0',
                'plumber'
            )
            ->assertJsonPath(
                'suggestions.1.place_id',
                'place-acme-2'
            );
    }

    public function test_it_filters_address_only_predictions_from_business_search(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $googlePlaces
            ->shouldReceive('autocompleteBusinesses')
            ->once()
            ->with(
                '6263 McNeil Dr',
                $sessionToken
            )
            ->andReturn([
                [
                    'placePrediction' => [
                        'placeId'
                            => 'address-only',

                        'text' => [
                            'text'
                                => '6263 McNeil Dr, Austin, TX, USA',
                        ],

                        'structuredFormat' => [
                            'mainText' => [
                                'text'
                                    => '6263 McNeil Dr',
                            ],

                            'secondaryText' => [
                                'text'
                                    => 'Austin, TX, USA',
                            ],
                        ],

                        'types' => [
                            'street_address',
                            'geocode',
                        ],
                    ],
                ],

                [
                    'placePrediction' => [
                        'placeId'
                            => 'real-business',

                        'text' => [
                            'text'
                                => 'Example Plumbing, Austin, TX, USA',
                        ],

                        'structuredFormat' => [
                            'mainText' => [
                                'text'
                                    => 'Example Plumbing',
                            ],

                            'secondaryText' => [
                                'text'
                                    => 'Austin, TX, USA',
                            ],
                        ],

                        'types' => [
                            'plumber',
                            'establishment',
                        ],
                    ],
                ],
            ]);

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this->getJson(
            route(
                'google-business.search',
                [
                    'q'
                        => '6263 McNeil Dr',

                    'session_token'
                        => $sessionToken,
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
                'real-business'
            );
    }

    public function test_it_rejects_short_search_query(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces->shouldNotReceive(
            'isConfigured'
        );

        $googlePlaces->shouldNotReceive(
            'autocompleteBusinesses'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this->getJson(
            route(
                'google-business.search',
                [
                    'q' => 'Ac',

                    'session_token'
                        => '550e8400-e29b-41d4-a716-446655440000',
                ]
            )
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'q',
            ]);
    }

    public function test_it_requires_valid_session_token(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces->shouldNotReceive(
            'isConfigured'
        );

        $googlePlaces->shouldNotReceive(
            'autocompleteBusinesses'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this->getJson(
            route(
                'google-business.search',
                [
                    'q'
                        => 'Acme Plumbing',

                    'session_token'
                        => 'not-a-valid-uuid',
                ]
            )
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'session_token',
            ]);
    }

    public function test_it_returns_service_unavailable_when_google_is_not_configured(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(false);

        $googlePlaces->shouldNotReceive(
            'autocompleteBusinesses'
        );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this->getJson(
            route(
                'google-business.search',
                [
                    'q'
                        => 'Acme Plumbing',

                    'session_token'
                        => '550e8400-e29b-41d4-a716-446655440000',
                ]
            )
        );

        $response
            ->assertStatus(503)
            ->assertJson([
                'message'
                    => 'Google Business search is not configured yet.',

                'suggestions'
                    => [],
            ]);
    }

    public function test_it_handles_google_places_failure_gracefully(): void
    {
        $googlePlaces = Mockery::mock(
            GooglePlacesService::class
        );

        $googlePlaces
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $googlePlaces
            ->shouldReceive('autocompleteBusinesses')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Google Places request failed.'
                )
            );

        $this->app->instance(
            GooglePlacesService::class,
            $googlePlaces
        );

        $response = $this->getJson(
            route(
                'google-business.search',
                [
                    'q'
                        => 'Acme Plumbing',

                    'session_token'
                        => '550e8400-e29b-41d4-a716-446655440000',
                ]
            )
        );

        $response
            ->assertStatus(502)
            ->assertJson([
                'message'
                    => 'Google Business search is temporarily unavailable.',

                'suggestions'
                    => [],
            ]);
    }
}