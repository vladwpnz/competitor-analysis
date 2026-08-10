<?php

namespace Tests\Feature;

use App\Services\BusinessClassifier;
use App\Services\BusinessProfileBuilder;
use App\Services\CompetitorAnalysisService;
use App\Services\CompetitorRelevanceScorer;
use App\Services\CompetitorSearchService;
use App\Services\SearchProfileBuilder;
use Mockery;
use Tests\TestCase;

class CompetitorAnalysisServiceTest extends TestCase
{
    public function test_it_runs_complete_analysis_pipeline_and_stops_when_initial_stage_has_five_strong_matches(): void
    {
        $competitorSearch = Mockery::mock(
            CompetitorSearchService::class
        );

        $competitorSearch
            ->shouldReceive('findCandidates')
            ->once()
            ->with(
                Mockery::on(
                    function (array $searchProfile): bool {
                        return
                            $searchProfile['business_type']
                                === 'Plumber'
                            && $searchProfile['market_scope']
                                === 'local'
                            && $searchProfile[
                                'exclude'
                            ]['place_id']
                                === 'customer-place';
                    }
                ),
                30,
                CompetitorSearchService::STAGE_INITIAL
            )
            ->andReturn(
                $this->strongPlumberCandidates(
                    'local_50km'
                )
            );

        $service = $this->service(
            $competitorSearch
        );

        $result = $service->analyze(
            $this->plumberWebsiteScan(),
            $this->plumberGooglePlace()
        );

        $this->assertSame(
            'home_services',
            $result['classification']['vertical']
        );

        $this->assertSame(
            'local',
            $result['classification']['market_scope']
        );

        $this->assertSame(
            'Plumber',
            $result['search_profile']['business_type']
        );

        $this->assertSame(
            'customer-place',
            $result[
                'search_profile'
            ]['exclude']['place_id']
        );

        $this->assertSame(
            6,
            $result['candidate_count']
        );

        $this->assertCount(
            5,
            $result['top_competitors']
        );

        $this->assertSame(
            5,
            $result['strong_match_count']
        );

        $this->assertTrue(
            $result['has_competitors']
        );

        $this->assertSame(
            [
                CompetitorSearchService::STAGE_INITIAL,
            ],
            $result['search_stages']
        );

        $this->assertSame(
            1,
            $result['search_stage_count']
        );

        $this->assertFalse(
            $result['search_exhausted']
        );

        $this->assertSame(
            'plumber',
            $result['top_competitors'][0]['primaryType']
        );

        $this->assertNotSame(
            'competitor-6',
            $result['top_competitors'][0]['id']
        );

        foreach (
            $result['top_competitors']
            as $competitor
        ) {
            $this->assertNotSame(
                'customer-place',
                $competitor['id']
            );

            $this->assertArrayHasKey(
                '_relevance',
                $competitor
            );

            $this->assertTrue(
                $competitor[
                    '_relevance'
                ]['strong_match']
            );
        }
    }

    public function test_local_analysis_expands_until_five_strong_matches_are_available(): void
    {
        $competitorSearch = Mockery::mock(
            CompetitorSearchService::class
        );

        $profileMatcher = Mockery::on(
            static fn (array $searchProfile): bool =>
                $searchProfile['market_scope'] === 'local'
                && $searchProfile['business_type'] === 'Plumber'
        );

        $competitorSearch
            ->shouldReceive('findCandidates')
            ->once()
            ->ordered()
            ->with(
                $profileMatcher,
                30,
                CompetitorSearchService::STAGE_INITIAL
            )
            ->andReturn([
                $this->strongCandidate(
                    'competitor-1',
                    'Metro Plumbing',
                    8,
                    'local_50km'
                ),
                $this->strongCandidate(
                    'competitor-2',
                    'Rapid Plumbing',
                    12,
                    'local_50km'
                ),
            ]);

        $competitorSearch
            ->shouldReceive('findCandidates')
            ->once()
            ->ordered()
            ->with(
                $profileMatcher,
                30,
                CompetitorSearchService::STAGE_LOCALITY
            )
            ->andReturn([
                $this->strongCandidate(
                    'competitor-2',
                    'Rapid Plumbing',
                    12,
                    'locality_context'
                ),
                $this->strongCandidate(
                    'competitor-3',
                    'City Plumbing',
                    18,
                    'locality_context'
                ),
                $this->strongCandidate(
                    'competitor-4',
                    'Drain Experts Plumbing',
                    24,
                    'locality_context'
                ),
            ]);

        $competitorSearch
            ->shouldReceive('findCandidates')
            ->once()
            ->ordered()
            ->with(
                $profileMatcher,
                30,
                CompetitorSearchService::STAGE_REGION
            )
            ->andReturn([
                $this->strongCandidate(
                    'competitor-5',
                    'Ontario Plumbing Group',
                    70,
                    'region_context'
                ),
                $this->strongCandidate(
                    'competitor-6',
                    'Regional Emergency Plumbing',
                    95,
                    'region_context'
                ),
            ]);

        $service = $this->service(
            $competitorSearch
        );

        $result = $service->analyze(
            $this->plumberWebsiteScan(),
            $this->plumberGooglePlace()
        );

        $this->assertSame(
            [
                CompetitorSearchService::STAGE_INITIAL,
                CompetitorSearchService::STAGE_LOCALITY,
                CompetitorSearchService::STAGE_REGION,
            ],
            $result['search_stages']
        );

        $this->assertSame(
            3,
            $result['search_stage_count']
        );

        $this->assertSame(
            6,
            $result['candidate_count']
        );

        $this->assertCount(
            5,
            $result['top_competitors']
        );

        $this->assertSame(
            5,
            $result['strong_match_count']
        );

        $this->assertFalse(
            $result['search_exhausted']
        );

        $competitorTwo = collect(
            $result['top_competitors']
        )->firstWhere(
            'id',
            'competitor-2'
        );

        $this->assertIsArray(
            $competitorTwo
        );

        $this->assertSame(
            [
                'local_50km',
                'locality_context',
            ],
            $competitorTwo[
                '_match'
            ]['search_modes']
        );
    }

    public function test_it_handles_empty_candidate_results_without_geographic_expansion(): void
    {
        $competitorSearch = Mockery::mock(
            CompetitorSearchService::class
        );

        $competitorSearch
            ->shouldReceive('findCandidates')
            ->once()
            ->with(
                Mockery::type('array'),
                30,
                CompetitorSearchService::STAGE_INITIAL
            )
            ->andReturn([]);

        $service = $this->service(
            $competitorSearch
        );

        $result = $service->analyze([
            'final_url'
                => 'https://example.com',

            'status' => 200,

            'title'
                => 'Example Company',

            'meta_description'
                => 'Professional business services.',

            'h1' => [
                'Professional Services',
            ],

            'h2' => [],

            'text'
                => 'Example Company provides professional business services.',
        ]);

        $this->assertSame(
            0,
            $result['candidate_count']
        );

        $this->assertSame(
            [],
            $result['top_competitors']
        );

        $this->assertSame(
            0,
            $result['strong_match_count']
        );

        $this->assertFalse(
            $result['has_competitors']
        );

        $this->assertSame(
            [
                CompetitorSearchService::STAGE_INITIAL,
            ],
            $result['search_stages']
        );

        $this->assertTrue(
            $result['search_exhausted']
        );
    }

    private function service(
        CompetitorSearchService $competitorSearch
    ): CompetitorAnalysisService {
        return new CompetitorAnalysisService(
            app(BusinessProfileBuilder::class),
            app(BusinessClassifier::class),
            app(SearchProfileBuilder::class),
            $competitorSearch,
            app(CompetitorRelevanceScorer::class)
        );
    }

    private function plumberWebsiteScan(): array
    {
        return [
            'final_url'
                => 'https://acmeplumbing.com',

            'status' => 200,

            'title'
                => 'Acme Plumbing | Emergency Plumbing Services',

            'meta_description'
                => 'Local plumber providing emergency plumbing, drain cleaning and water heater repairs.',

            'h1' => [
                'Professional Plumbing Services',
            ],

            'h2' => [
                'Emergency Plumbing',
                'Drain Cleaning',
                'Water Heater Repair',
            ],

            'text'
                => 'Acme Plumbing provides plumbing, emergency plumbing, drain cleaning, pipe repair and water heater services.',
        ];
    }

    private function plumberGooglePlace(): array
    {
        return [
            'id' => 'customer-place',

            'displayName' => [
                'text' => 'Acme Plumbing',
                'languageCode' => 'en',
            ],

            'formattedAddress'
                => '100 Main St, Toronto, ON, Canada',

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
                'latitude' => 43.6532,
                'longitude' => -79.3832,
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
    }

    private function strongPlumberCandidates(
        string $searchMode
    ): array {
        return [
            $this->strongCandidate(
                'competitor-1',
                'Metro Plumbing',
                8,
                $searchMode
            ),

            $this->strongCandidate(
                'competitor-2',
                'Toronto Emergency Plumbing',
                15,
                $searchMode
            ),

            $this->strongCandidate(
                'competitor-3',
                'Rapid Drain & Plumbing',
                22,
                $searchMode
            ),

            $this->strongCandidate(
                'competitor-4',
                'City Plumbing Solutions',
                30,
                $searchMode
            ),

            $this->strongCandidate(
                'competitor-5',
                'North Plumbing Services',
                40,
                $searchMode
            ),

            [
                'id' => 'competitor-6',

                'displayName' => [
                    'text' => 'General Home Services',
                ],

                'primaryType'
                    => 'home_goods_store',

                'primaryTypeDisplayName' => [
                    'text' => 'Home Goods Store',
                ],

                'types' => [
                    'home_goods_store',
                ],

                '_match' => [
                    'query_hits' => 1,
                    'queries' => [
                        'Plumber',
                    ],
                    'executed_queries' => [
                        'Plumber',
                    ],
                    'search_modes' => [
                        $searchMode,
                    ],
                    'distance_km' => 4.0,
                ],
            ],
        ];
    }

    private function strongCandidate(
        string $id,
        string $name,
        float $distanceKm,
        string $searchMode
    ): array {
        return [
            'id' => $id,

            'displayName' => [
                'text' => $name,
            ],

            'primaryType'
                => 'plumber',

            'primaryTypeDisplayName' => [
                'text' => 'Plumber',
            ],

            'types' => [
                'plumber',
            ],

            '_match' => [
                'query_hits' => 4,

                'queries' => [
                    'Plumber',
                    'Emergency Plumbing',
                    'Drain Cleaning',
                    'Water Heater Repair',
                ],

                'executed_queries' => [
                    'Plumber',
                    'Emergency Plumbing',
                    'Drain Cleaning',
                    'Water Heater Repair',
                ],

                'search_modes' => [
                    $searchMode,
                ],

                'distance_km'
                    => $distanceKm,
            ],
        ];
    }
}
