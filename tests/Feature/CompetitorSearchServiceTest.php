<?php

namespace Tests\Feature;

use App\Services\CompetitorSearchService;
use App\Services\GooglePlacesService;
use Mockery;
use Tests\TestCase;

class CompetitorSearchServiceTest extends TestCase
{
    public function test_local_search_removes_own_business_and_deduplicates_candidates(): void
    {
        $google = Mockery::mock(
            GooglePlacesService::class
        );

        $google->shouldReceive(
            'searchBusinessesNear'
        )
            ->once()
            ->with(
                'Plumber',
                43.6532,
                -79.3832,
                50,
                15
            )
            ->andReturn([
                [
                    'id' => 'own-place',

                    'displayName' => [
                        'text' => 'Acme Plumbing',
                    ],

                    'location' => [
                        'latitude' => 43.6532,
                        'longitude' => -79.3832,
                    ],
                ],

                [
                    'id' => 'competitor-a',

                    'displayName' => [
                        'text' => 'Alpha Plumbing',
                    ],

                    'primaryType'
                        => 'plumber',

                    'location' => [
                        'latitude' => 43.6600,
                        'longitude' => -79.3900,
                    ],
                ],
            ]);

        $google->shouldReceive(
            'searchBusinessesNear'
        )
            ->once()
            ->with(
                'Emergency Plumbing',
                43.6532,
                -79.3832,
                50,
                15
            )
            ->andReturn([
                [
                    'id' => 'competitor-a',

                    'displayName' => [
                        'text' => 'Alpha Plumbing',
                    ],

                    'primaryType'
                        => 'plumber',

                    'location' => [
                        'latitude' => 43.6600,
                        'longitude' => -79.3900,
                    ],
                ],

                [
                    'id' => 'competitor-b',

                    'displayName' => [
                        'text' => 'Bravo Plumbing',
                    ],

                    'primaryType'
                        => 'plumber',

                    'location' => [
                        'latitude' => 43.7000,
                        'longitude' => -79.4200,
                    ],
                ],
            ]);

        $service = new CompetitorSearchService(
            $google
        );

        $candidates = $service->findCandidates([
            'search_queries' => [
                'Plumber',
                'Emergency Plumbing',
            ],

            'market_scope' => 'local',

            'geography_weight' => 'high',

            'location' => [
                'latitude' => 43.6532,
                'longitude' => -79.3832,
            ],

            'exclude' => [
                'place_id' => 'own-place',
                'business_name'
                    => 'Acme Plumbing',
            ],
        ]);

        $this->assertCount(
            2,
            $candidates
        );

        $this->assertSame(
            'competitor-a',
            $candidates[0]['id']
        );

        $this->assertSame(
            2,
            $candidates[0]['_match']['query_hits']
        );

        $this->assertSame(
            [
                'Plumber',
                'Emergency Plumbing',
            ],
            $candidates[0]['_match']['queries']
        );

        $this->assertNotSame(
            'own-place',
            $candidates[0]['id']
        );

        $this->assertNotSame(
            'own-place',
            $candidates[1]['id']
        );

        $this->assertIsFloat(
            $candidates[0]['_match']['distance_km']
        );
    }

    public function test_broader_business_uses_relevance_search(): void
    {
        $google = Mockery::mock(
            GooglePlacesService::class
        );

        $google->shouldReceive(
            'searchBusinesses'
        )
            ->once()
            ->with(
                'Commercial Insurance Broker',
                15
            )
            ->andReturn([
                [
                    'id' => 'broker-1',

                    'displayName' => [
                        'text'
                            => 'Commercial Risk Group',
                    ],

                    'primaryType'
                        => 'insurance_agency',
                ],
            ]);

        $google->shouldNotReceive(
            'searchBusinessesNear'
        );

        $service = new CompetitorSearchService(
            $google
        );

        $candidates = $service->findCandidates([
            'search_queries' => [
                'Commercial Insurance Broker',
            ],

            'market_scope' => 'broader',

            'geography_weight' => 'low',

            'location' => [
                'latitude' => 43.6532,
                'longitude' => -79.3832,
            ],

            'exclude' => [
                'place_id'
                    => 'customer-place',
                'business_name'
                    => 'North Star Insurance',
            ],
        ]);

        $this->assertCount(
            1,
            $candidates
        );

        $this->assertSame(
            'broker-1',
            $candidates[0]['id']
        );

        $this->assertSame(
            ['relevance'],
            $candidates[0]['_match']['search_modes']
        );
    }

    public function test_it_can_exclude_own_business_by_name_without_place_id(): void
    {
        $google = Mockery::mock(
            GooglePlacesService::class
        );

        $google->shouldReceive(
            'searchBusinesses'
        )
            ->once()
            ->andReturn([
                [
                    'id' => 'result-1',

                    'displayName' => [
                        'text' => 'Acme Plumbing',
                    ],
                ],

                [
                    'id' => 'result-2',

                    'displayName' => [
                        'text' => 'Different Plumbing',
                    ],
                ],
            ]);

        $service = new CompetitorSearchService(
            $google
        );

        $candidates = $service->findCandidates([
            'search_queries' => [
                'plumber',
            ],

            'market_scope' => 'broader',

            'geography_weight' => 'low',

            'location' => [
                'latitude' => null,
                'longitude' => null,
            ],

            'exclude' => [
                'place_id' => null,
                'business_name'
                    => 'ACME   Plumbing!',
            ],
        ]);

        $this->assertCount(
            1,
            $candidates
        );

        $this->assertSame(
            'result-2',
            $candidates[0]['id']
        );
    }

    public function test_it_limits_google_queries_to_four(): void
    {
        $google = Mockery::mock(
            GooglePlacesService::class
        );

        $google->shouldReceive(
            'searchBusinesses'
        )
            ->times(4)
            ->andReturn([]);

        $service = new CompetitorSearchService(
            $google
        );

        $candidates = $service->findCandidates([
            'search_queries' => [
                'query one',
                'query two',
                'query three',
                'query four',
                'query five',
                'query six',
            ],

            'market_scope' => 'broader',

            'geography_weight' => 'low',

            'location' => [
                'latitude' => null,
                'longitude' => null,
            ],

            'exclude' => [
                'place_id' => null,
                'business_name' => null,
            ],
        ]);

        $this->assertSame(
            [],
            $candidates
        );
    }

    public function test_empty_search_profile_makes_no_google_requests(): void
    {
        $google = Mockery::mock(
            GooglePlacesService::class
        );

        $google->shouldNotReceive(
            'searchBusinesses'
        );

        $google->shouldNotReceive(
            'searchBusinessesNear'
        );

        $service = new CompetitorSearchService(
            $google
        );

        $candidates = $service->findCandidates([
            'search_queries' => [],
        ]);

        $this->assertSame(
            [],
            $candidates
        );
    }
}