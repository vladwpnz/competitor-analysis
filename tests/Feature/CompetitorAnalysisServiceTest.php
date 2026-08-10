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
    public function test_it_runs_complete_analysis_pipeline_and_returns_top_five(): void
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
                30
            )
            ->andReturn(
                $this->plumberCandidates()
            );

        $service = new CompetitorAnalysisService(
            app(BusinessProfileBuilder::class),
            app(BusinessClassifier::class),
            app(SearchProfileBuilder::class),
            $competitorSearch,
            app(CompetitorRelevanceScorer::class)
        );

        $websiteScan = [
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

        $googlePlace = [
            'id' => 'customer-place',

            'displayName' => [
                'text' => 'Acme Plumbing',
                'languageCode' => 'en',
            ],

            'formattedAddress'
                => '100 Main St, Toronto, ON',

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

        $result = $service->analyze(
            $websiteScan,
            $googlePlace
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

        $this->assertTrue(
            $result['has_competitors']
        );

        /*
         * The best result must be a genuinely relevant plumber.
         * We intentionally do not require a specific competitor ID,
         * because service relevance can legitimately change the
         * order between otherwise strong plumbing competitors.
         */
        $this->assertSame(
            'plumber',
            $result['top_competitors'][0]['primaryType']
        );

        /*
         * competitor-6 is geographically very close, but it is a
         * home goods business rather than a real plumbing competitor.
         * Distance alone must not make it the best match.
         */
        $this->assertNotSame(
            'competitor-6',
            $result['top_competitors'][0]['id']
        );

        $this->assertGreaterThanOrEqual(
            70,
            $result[
                'top_competitors'
            ][0]['_relevance']['score']
        );

        $this->assertGreaterThanOrEqual(
            1,
            $result['strong_match_count']
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
        }
    }

    public function test_it_handles_empty_candidate_results(): void
    {
        $competitorSearch = Mockery::mock(
            CompetitorSearchService::class
        );

        $competitorSearch
            ->shouldReceive('findCandidates')
            ->once()
            ->andReturn([]);

        $service = new CompetitorAnalysisService(
            app(BusinessProfileBuilder::class),
            app(BusinessClassifier::class),
            app(SearchProfileBuilder::class),
            $competitorSearch,
            app(CompetitorRelevanceScorer::class)
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
    }

    private function plumberCandidates(): array
    {
        return [
            $this->candidate(
                'competitor-1',
                'Metro Plumbing',
                'plumber',
                3,
                [
                    'Plumber',
                    'plumbing',
                    'emergency plumbing',
                ],
                8
            ),

            $this->candidate(
                'competitor-2',
                'Toronto Emergency Plumbing',
                'plumber',
                3,
                [
                    'Plumber',
                    'emergency plumbing',
                    'drain cleaning',
                ],
                15
            ),

            $this->candidate(
                'competitor-3',
                'Rapid Drain & Plumbing',
                'plumber',
                2,
                [
                    'Plumber',
                    'drain cleaning',
                ],
                22
            ),

            $this->candidate(
                'competitor-4',
                'City Plumbing Solutions',
                'plumber',
                2,
                [
                    'Plumber',
                    'plumbing',
                ],
                35
            ),

            $this->candidate(
                'competitor-5',
                'North Plumbing Services',
                'plumber',
                1,
                [
                    'Plumber',
                ],
                45
            ),

            $this->candidate(
                'competitor-6',
                'General Home Services',
                'home_goods_store',
                1,
                [
                    'Plumber',
                ],
                4
            ),
        ];
    }

    private function candidate(
        string $id,
        string $name,
        string $primaryType,
        int $queryHits,
        array $queries,
        float $distanceKm
    ): array {
        return [
            'id' => $id,

            'displayName' => [
                'text' => $name,
            ],

            'primaryType'
                => $primaryType,

            'primaryTypeDisplayName' => [
                'text' => $primaryType === 'plumber'
                    ? 'Plumber'
                    : 'Home Goods Store',
            ],

            'types' => [
                $primaryType,
            ],

            '_match' => [
                'query_hits'
                    => $queryHits,

                'queries'
                    => $queries,

                'search_modes' => [
                    'local_50km',
                ],

                'distance_km'
                    => $distanceKm,
            ],
        ];
    }
}