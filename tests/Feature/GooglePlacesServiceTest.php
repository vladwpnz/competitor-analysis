<?php

namespace Tests\Feature;

use App\Services\GooglePlacesService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GooglePlacesServiceTest extends TestCase
{
    public function test_service_is_not_configured_without_api_key(): void
    {
        config([
            'services.google_places.key' => null,
        ]);

        $service = app(GooglePlacesService::class);

        $this->assertFalse($service->isConfigured());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Google Places API is not configured.'
        );

        $service->searchBusinesses('test');
    }

    public function test_search_businesses_returns_places(): void
    {
        config([
            'services.google_places.key' => 'test-api-key',
            'services.google_places.base_url'
                => 'https://places.googleapis.com/v1',
        ]);

        Http::preventStrayRequests();

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText'
                => Http::response([
                    'places' => [
                        [
                            'id' => 'test-place-1',
                            'displayName' => [
                                'text' => 'Acme Plumbing',
                                'languageCode' => 'en',
                            ],
                            'formattedAddress'
                                => '100 Main St, Example City',
                            'primaryType' => 'plumber',
                            'types' => [
                                'plumber',
                                'home_goods_store',
                            ],
                            'location' => [
                                'latitude' => 40.7128,
                                'longitude' => -74.0060,
                            ],
                            'businessStatus'
                                => 'OPERATIONAL',
                            'pureServiceAreaBusiness'
                                => false,
                        ],
                    ],
                ], 200),
        ]);

        $service = app(GooglePlacesService::class);

        $places = $service->searchBusinesses(
            'plumber Example City',
            5
        );

        $this->assertCount(1, $places);
        $this->assertSame(
            'test-place-1',
            $places[0]['id']
        );
        $this->assertSame(
            'Acme Plumbing',
            $places[0]['displayName']['text']
        );
        $this->assertSame(
            'plumber',
            $places[0]['primaryType']
        );

        Http::assertSent(function (Request $request): bool {
            return
                $request->url()
                    === 'https://places.googleapis.com/v1/places:searchText'
                && $request->hasHeader(
                    'X-Goog-Api-Key',
                    'test-api-key'
                )
                && $request->hasHeader(
                    'X-Goog-FieldMask'
                )
                && $request['textQuery']
                    === 'plumber Example City'
                && $request['maxResultCount'] === 5
                && $request[
                    'includePureServiceAreaBusinesses'
                ] === true;
        });
    }

    public function test_place_details_returns_business_data(): void
    {
        config([
            'services.google_places.key' => 'test-api-key',
            'services.google_places.base_url'
                => 'https://places.googleapis.com/v1',
        ]);

        Http::preventStrayRequests();

        Http::fake([
            'https://places.googleapis.com/v1/places/test-place-1'
                => Http::response([
                    'id' => 'test-place-1',
                    'displayName' => [
                        'text' => 'Acme Plumbing',
                        'languageCode' => 'en',
                    ],
                    'formattedAddress'
                        => '100 Main St, Example City',
                    'primaryType' => 'plumber',
                    'types' => [
                        'plumber',
                    ],
                    'location' => [
                        'latitude' => 40.7128,
                        'longitude' => -74.0060,
                    ],
                    'businessStatus' => 'OPERATIONAL',
                    'websiteUri'
                        => 'https://example.test',
                    'rating' => 4.8,
                    'userRatingCount' => 127,
                ], 200),
        ]);

        $service = app(GooglePlacesService::class);

        $place = $service->getPlaceDetails(
            'test-place-1'
        );

        $this->assertSame(
            'test-place-1',
            $place['id']
        );
        $this->assertSame(
            'Acme Plumbing',
            $place['displayName']['text']
        );
        $this->assertSame(
            4.8,
            $place['rating']
        );
        $this->assertSame(
            127,
            $place['userRatingCount']
        );

        Http::assertSent(function (Request $request): bool {
            return
                $request->url()
                    === 'https://places.googleapis.com/v1/places/test-place-1'
                && $request->hasHeader(
                    'X-Goog-Api-Key',
                    'test-api-key'
                )
                && $request->hasHeader(
                    'X-Goog-FieldMask'
                );
        });
    }
}