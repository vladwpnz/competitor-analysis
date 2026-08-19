<?php

namespace Tests\Feature;

use App\Services\CompetitorSearchService;
use App\Services\GooglePlacesService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CompetitorSearchServiceTest extends TestCase
{
    public function test_local_search_removes_reference_company_and_deduplicates_candidates(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([
                [
                    'query' => 'Plumber',
                    'max_results' => 15,
                    'latitude' => 43.6532,
                    'longitude' => -79.3832,
                    'radius_km' => 50,
                ],
                [
                    'query' => 'Emergency Plumbing',
                    'max_results' => 15,
                    'latitude' => 43.6532,
                    'longitude' => -79.3832,
                    'radius_km' => 50,
                ],
            ])
            ->andReturn([
                0 => [
                    [
                        'id' => 'own-place',
                        'displayName' => ['text' => 'Acme Plumbing'],
                        'location' => [
                            'latitude' => 43.6532,
                            'longitude' => -79.3832,
                        ],
                    ],
                    [
                        'id' => 'competitor-a',
                        'displayName' => ['text' => 'Alpha Plumbing'],
                        'primaryType' => 'plumber',
                        'location' => [
                            'latitude' => 43.6600,
                            'longitude' => -79.3900,
                        ],
                    ],
                ],
                1 => [
                    [
                        'id' => 'competitor-a',
                        'displayName' => ['text' => 'Alpha Plumbing'],
                        'primaryType' => 'plumber',
                        'location' => [
                            'latitude' => 43.6600,
                            'longitude' => -79.3900,
                        ],
                    ],
                    [
                        'id' => 'competitor-b',
                        'displayName' => ['text' => 'Bravo Plumbing'],
                        'primaryType' => 'plumber',
                        'location' => [
                            'latitude' => 43.7000,
                            'longitude' => -79.4200,
                        ],
                    ],
                ],
            ]);

        $service = new CompetitorSearchService($google);

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
                'address' => '100 Main St, Toronto, ON, Canada',
            ],
            'exclude' => [
                'place_id' => 'own-place',
                'business_name' => 'Acme Plumbing',
            ],
        ]);

        $this->assertCount(2, $candidates);
        $this->assertSame('competitor-a', $candidates[0]['id']);
        $this->assertSame(2, $candidates[0]['_match']['query_hits']);
        $this->assertSame(
            ['Plumber', 'Emergency Plumbing'],
            $candidates[0]['_match']['queries']
        );
        $this->assertSame(
            ['local_50km'],
            $candidates[0]['_match']['search_modes']
        );
        $this->assertNotSame('own-place', $candidates[0]['id']);
        $this->assertNotSame('own-place', $candidates[1]['id']);
        $this->assertIsFloat($candidates[0]['_match']['distance_km']);
    }

    public function test_closed_google_businesses_are_not_returned_as_accounts(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query' => 'Plumber',
                'max_results' => 15,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'operational',
                    'displayName' => [
                        'text' => 'Active Plumbing',
                    ],
                    'primaryType' => 'plumber',
                    'businessStatus' => 'OPERATIONAL',
                ],
                [
                    'id' => 'temporarily-closed',
                    'displayName' => [
                        'text' => 'Temporary Plumbing',
                    ],
                    'primaryType' => 'plumber',
                    'businessStatus' => 'CLOSED_TEMPORARILY',
                ],
                [
                    'id' => 'permanently-closed',
                    'displayName' => [
                        'text' => 'Old Plumbing',
                    ],
                    'primaryType' => 'plumber',
                    'businessStatus' => 'CLOSED_PERMANENTLY',
                ],
                [
                    'id' => 'status-unavailable',
                    'displayName' => [
                        'text' => 'Unknown Status Plumbing',
                    ],
                    'primaryType' => 'plumber',
                ]],
            ]);

        $service = new CompetitorSearchService(
            $google
        );

        $candidates = $service->findCandidates([
            'search_queries' => [
                'Plumber',
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

        $this->assertCount(
            2,
            $candidates
        );

        $this->assertSame(
            [
                'operational',
                'status-unavailable',
            ],
            array_column(
                $candidates,
                'id'
            )
        );
    }

    public function test_broader_business_uses_business_country_context(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query'
                    => 'Commercial Insurance Broker in Canada',
                'max_results' => 15,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'broker-1',
                    'displayName' => ['text' => 'Commercial Risk Group'],
                    'primaryType' => 'insurance_agency',
                ]],
            ]);

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates([
            'search_queries' => ['Commercial Insurance Broker'],
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'location' => [
                'latitude' => 43.6532,
                'longitude' => -79.3832,
                'address' => '100 Main St, Toronto, ON, Canada',
            ],
            'exclude' => [
                'place_id' => 'customer-place',
                'business_name' => 'North Star Insurance',
            ],
        ]);

        $this->assertCount(1, $candidates);
        $this->assertSame('broker-1', $candidates[0]['id']);
        $this->assertSame(
            ['broader_country_context'],
            $candidates[0]['_match']['search_modes']
        );
        $this->assertSame(
            ['Commercial Insurance Broker in Canada'],
            $candidates[0]['_match']['executed_queries']
        );
    }

    public function test_broader_search_skips_relevance_fallback_when_context_batch_has_enough_candidates(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);
        $places = [];

        for ($index = 1; $index <= 5; $index++) {
            $places[] = [
                'id' => 'broker-' . $index,
                'displayName' => [
                    'text' => 'Commercial Broker ' . $index,
                ],
                'primaryType' => 'insurance_agency',
            ];
        }

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([
                [
                    'query' => 'Commercial Insurance Broker in Canada',
                    'max_results' => 15,
                ],
                [
                    'query' => 'Business Insurance Broker in Canada',
                    'max_results' => 15,
                ],
            ])
            ->andReturn([
                0 => $places,
                1 => [],
            ]);

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates([
            'search_queries' => [
                'Commercial Insurance Broker',
                'Business Insurance Broker',
            ],
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'location' => [
                'latitude' => null,
                'longitude' => null,
                'address' => '100 Main St, Toronto, ON, Canada',
            ],
            'exclude' => [
                'place_id' => null,
                'business_name' => null,
            ],
        ]);

        $this->assertCount(5, $candidates);
        $this->assertSame(
            ['broader_country_context'],
            $candidates[0]['_match']['search_modes']
        );
    }

    public function test_it_can_exclude_reference_company_by_name_without_place_id(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query' => 'plumber',
                'max_results' => 15,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'result-1',
                    'displayName' => ['text' => 'Acme Plumbing'],
                ],
                [
                    'id' => 'result-2',
                    'displayName' => ['text' => 'Different Plumbing'],
                ]],
            ]);

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates([
            'search_queries' => ['plumber'],
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'location' => [
                'latitude' => null,
                'longitude' => null,
            ],
            'exclude' => [
                'place_id' => null,
                'business_name' => 'ACME   Plumbing!',
            ],
        ]);

        $this->assertCount(1, $candidates);
        $this->assertSame('result-2', $candidates[0]['id']);
    }

    public function test_it_limits_google_queries_to_four(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with(Mockery::on(
                static fn (array $requests): bool =>
                    count($requests) === 4
                    && array_column(
                        $requests,
                        'query'
                    ) === [
                        'query one',
                        'query two',
                        'query three',
                        'query four',
                    ]
            ))
            ->andReturn([
                0 => [],
                1 => [],
                2 => [],
                3 => [],
            ]);

        $service = new CompetitorSearchService($google);

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

        $this->assertSame([], $candidates);
    }

    public function test_empty_search_profile_makes_no_google_requests(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldNotReceive('searchBusinessesBatch');

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates([
            'search_queries' => [],
        ]);

        $this->assertSame([], $candidates);
    }

    public function test_locality_expansion_uses_explicit_locality_context(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query' => 'Plumber in Toronto, ON, Canada',
                'max_results' => 15,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'locality-competitor',
                    'displayName' => ['text' => 'Toronto Plumbing Group'],
                    'primaryType' => 'plumber',
                    'location' => [
                        'latitude' => 43.7000,
                        'longitude' => -79.4100,
                    ],
                ]],
            ]);

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates(
            [
                'search_queries' => ['Plumber'],
                'market_scope' => 'local',
                'geography_weight' => 'high',
                'location' => [
                    'latitude' => 43.6532,
                    'longitude' => -79.3832,
                    'address' => '100 Main St, Toronto, ON, Canada',
                ],
                'exclude' => [
                    'place_id' => 'own-place',
                    'business_name' => 'Acme Plumbing',
                ],
            ],
            30,
            CompetitorSearchService::STAGE_LOCALITY
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(
            ['locality_context'],
            $candidates[0]['_match']['search_modes']
        );
        $this->assertSame(
            ['Plumber in Toronto, ON, Canada'],
            $candidates[0]['_match']['executed_queries']
        );
    }

    public function test_region_expansion_uses_explicit_region_context(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query' => 'Plumber in ON, Canada',
                'max_results' => 15,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'region-competitor',
                    'displayName' => ['text' => 'Ontario Plumbing Group'],
                    'primaryType' => 'plumber',
                ]],
            ]);

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates(
            [
                'search_queries' => ['Plumber'],
                'market_scope' => 'local',
                'geography_weight' => 'high',
                'location' => [
                    'latitude' => 43.6532,
                    'longitude' => -79.3832,
                    'address' => '100 Main St, Toronto, ON, Canada',
                ],
                'exclude' => [
                    'place_id' => 'own-place',
                    'business_name' => 'Acme Plumbing',
                ],
            ],
            30,
            CompetitorSearchService::STAGE_REGION
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(
            ['region_context'],
            $candidates[0]['_match']['search_modes']
        );
        $this->assertSame(
            ['Plumber in ON, Canada'],
            $candidates[0]['_match']['executed_queries']
        );
    }

    public function test_country_expansion_uses_only_primary_query_and_country_context(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query' => 'Plumber in Canada',
                'max_results' => 15,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'country-competitor',
                    'displayName' => ['text' => 'National Plumbing Group'],
                    'primaryType' => 'plumber',
                ]],
            ]);

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates(
            [
                'search_queries' => [
                    'Plumber',
                    'Emergency Plumbing',
                    'Drain Cleaning',
                ],
                'market_scope' => 'local',
                'geography_weight' => 'high',
                'location' => [
                    'latitude' => 43.6532,
                    'longitude' => -79.3832,
                    'address' => '100 Main St, Toronto, ON, Canada',
                ],
                'exclude' => [
                    'place_id' => 'own-place',
                    'business_name' => 'Acme Plumbing',
                ],
            ],
            30,
            CompetitorSearchService::STAGE_COUNTRY
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(
            ['country_context'],
            $candidates[0]['_match']['search_modes']
        );
        $this->assertSame(
            ['Plumber'],
            $candidates[0]['_match']['queries']
        );
    }

    public function test_expansion_without_address_falls_back_to_relevance_search(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query' => 'Plumber',
                'max_results' => 15,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'fallback-competitor',
                    'displayName' => ['text' => 'Fallback Plumbing'],
                    'primaryType' => 'plumber',
                ]],
            ]);

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates(
            [
                'search_queries' => ['Plumber'],
                'market_scope' => 'local',
                'geography_weight' => 'high',
                'location' => [
                    'latitude' => 43.6532,
                    'longitude' => -79.3832,
                    'address' => null,
                ],
                'exclude' => [
                    'place_id' => null,
                    'business_name' => null,
                ],
            ],
            30,
            CompetitorSearchService::STAGE_LOCALITY
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(
            ['relevance_fallback'],
            $candidates[0]['_match']['search_modes']
        );
        $this->assertSame(
            ['Plumber'],
            $candidates[0]['_match']['executed_queries']
        );
    }
    public function test_local_search_excludes_other_locations_of_same_brand(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query' => 'Dermatology Clinic',
                'max_results' => 15,
                'latitude' => 30.2870,
                'longitude' => -97.8130,
                'radius_km' => 50,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'own-place',
                    'displayName' => [
                        'text' => 'Westlake Dermatology & Cosmetic Surgery',
                    ],
                    'location' => [
                        'latitude' => 30.2870,
                        'longitude' => -97.8130,
                    ],
                ],
                [
                    'id' => 'same-brand-branch',
                    'displayName' => [
                        'text' => 'Westlake Dermatology & Cosmetic Surgery - Southwest Parkway',
                    ],
                    'location' => [
                        'latitude' => 30.2500,
                        'longitude' => -97.8500,
                    ],
                ],
                [
                    'id' => 'real-competitor',
                    'displayName' => [
                        'text' => 'Central Texas Dermatology',
                    ],
                    'primaryType' => 'skin_care_clinic',
                    'location' => [
                        'latitude' => 30.3000,
                        'longitude' => -97.7900,
                    ],
                ]],
            ]);

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates([
            'search_queries' => [
                'Dermatology Clinic',
            ],
            'market_scope' => 'local',
            'geography_weight' => 'high',
            'location' => [
                'latitude' => 30.2870,
                'longitude' => -97.8130,
                'address' => '8825 Bee Caves Rd, Austin, TX, USA',
            ],
            'exclude' => [
                'place_id' => 'own-place',
                'business_name'
                    => 'Westlake Dermatology & Cosmetic Surgery',
                'website_host'
                    => 'westlakedermatology.com',
            ],
        ]);

        $this->assertCount(1, $candidates);
        $this->assertSame(
            'real-competitor',
            $candidates[0]['id']
        );
    }

    public function test_slow_or_unavailable_google_search_returns_no_candidates(): void
    {
        $google = Mockery::mock(GooglePlacesService::class);

        $google->shouldReceive('searchBusinessesBatch')
            ->once()
            ->andThrow(new RuntimeException(
                'Google Places request timed out.'
            ));

        $service = new CompetitorSearchService($google);

        $candidates = $service->findCandidates([
            'search_queries' => ['Plumber'],
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'location' => [
                'latitude' => null,
                'longitude' => null,
                'address' => null,
            ],
            'exclude' => [
                'place_id' => null,
                'business_name' => null,
            ],
        ]);

        $this->assertSame([], $candidates);
    }

}
