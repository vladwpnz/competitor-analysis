<?php

namespace Tests\Feature;

use App\Services\GooglePlacesService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class GooglePlacesAutocompleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.google_places.key',
            'test-google-key'
        );

        config()->set(
            'services.google_places.base_url',
            'https://places.googleapis.com/v1'
        );
    }

    public function test_autocomplete_returns_business_predictions(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        Http::fake([
            'https://places.googleapis.com/v1/places:autocomplete'
                => Http::response([
                    'suggestions' => [
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
                                    'point_of_interest',
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
                    ],
                ], 200),
        ]);

        $service = app(
            GooglePlacesService::class
        );

        $suggestions =
            $service->autocompleteBusinesses(
                'Acme Plumbing',
                $sessionToken
            );

        $this->assertCount(
            2,
            $suggestions
        );

        $this->assertSame(
            'place-acme-1',
            data_get(
                $suggestions,
                '0.placePrediction.placeId'
            )
        );

        $this->assertSame(
            'Acme Plumbing',
            data_get(
                $suggestions,
                '0.placePrediction.structuredFormat.mainText.text'
            )
        );

        $this->assertSame(
            'Toronto, ON, Canada',
            data_get(
                $suggestions,
                '0.placePrediction.structuredFormat.secondaryText.text'
            )
        );

        Http::assertSent(
            function (Request $request) use (
                $sessionToken
            ): bool {
                $payload =
                    $request->data();

                return
                    $request->method() === 'POST'
                    && $request->url()
                        === 'https://places.googleapis.com/v1/places:autocomplete'
                    && data_get(
                        $payload,
                        'input'
                    ) === 'Acme Plumbing'
                    && data_get(
                        $payload,
                        'sessionToken'
                    ) === $sessionToken
                    && data_get(
                        $payload,
                        'includePureServiceAreaBusinesses'
                    ) === true
                    && data_get(
                        $payload,
                        'includeQueryPredictions'
                    ) === false;
            }
        );
    }

    public function test_autocomplete_rejects_invalid_session_token(): void
    {
        Http::fake();

        $service = app(
            GooglePlacesService::class
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->autocompleteBusinesses(
            'Acme Plumbing',
            'invalid token with spaces'
        );
    }

    public function test_place_details_can_receive_autocomplete_session_token(): void
    {
        $sessionToken =
            '550e8400-e29b-41d4-a716-446655440000';

        Http::fake([
            'https://places.googleapis.com/v1/places/customer-place*'
                => Http::response([
                    'id'
                        => 'customer-place',

                    'displayName' => [
                        'text'
                            => 'Acme Plumbing',
                    ],

                    'formattedAddress'
                        => '100 Main St, Toronto, ON',

                    'primaryType'
                        => 'plumber',

                    'location' => [
                        'latitude'
                            => 43.6532,

                        'longitude'
                            => -79.3832,
                    ],
                ], 200),
        ]);

        $service = app(
            GooglePlacesService::class
        );

        $place =
            $service->getPlaceDetails(
                'customer-place',
                $sessionToken
            );

        $this->assertSame(
            'customer-place',
            $place['id']
        );

        $this->assertSame(
            'Acme Plumbing',
            data_get(
                $place,
                'displayName.text'
            )
        );

        Http::assertSent(
            function (Request $request) use (
                $sessionToken
            ): bool {
                return
                    $request->method() === 'GET'
                    && str_starts_with(
                        $request->url(),
                        'https://places.googleapis.com/v1/places/customer-place'
                    )
                    && str_contains(
                        $request->url(),
                        'sessionToken='
                        . $sessionToken
                    );
            }
        );
    }
}