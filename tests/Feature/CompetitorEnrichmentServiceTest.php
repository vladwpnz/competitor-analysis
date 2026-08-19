<?php

namespace Tests\Feature;

use App\Exceptions\AnalysisDeadlineExceeded;
use App\Services\CompetitorEnrichmentService;
use App\Services\GooglePlacesService;
use Mockery;
use Tests\TestCase;

class CompetitorEnrichmentServiceTest extends TestCase
{
    public function test_it_enriches_only_the_final_top_five_competitors(): void
    {
        $google = Mockery::mock(
            GooglePlacesService::class
        );

        $google->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $google->shouldReceive('getPlaceDetailsBatch')
            ->once()
            ->andReturnUsing(
                function (array $placeIds): array {
                    $details = [];

                    foreach ($placeIds as $placeId) {
                        $details[$placeId] = [
                            'id' => $placeId,

                            'displayName' => [
                                'text' => 'Detailed ' . $placeId,
                            ],

                            'formattedAddress'
                                => '100 Example St, Toronto, ON, Canada',

                            'primaryType'
                                => 'plumber',

                            'primaryTypeDisplayName' => [
                                'text' => 'Plumber',
                            ],

                            'websiteUri'
                                => 'https://' . $placeId . '.example.com',

                            'googleMapsUri'
                                => 'https://maps.google.com/?cid=' . $placeId,

                            'rating' => 4.8,

                            'userRatingCount' => 120,
                        ];
                    }

                    return $details;
                }
            );

        $service = new CompetitorEnrichmentService(
            $google
        );

        $competitors = [];

        for ($index = 1; $index <= 6; $index++) {
            $competitors[] = [
                'id' => 'competitor-' . $index,

                'displayName' => [
                    'text' => 'Candidate ' . $index,
                ],

                '_match' => [
                    'distance_km' => (float) $index,
                ],

                '_relevance' => [
                    'score' => 90 - $index,
                    'strong_match' => true,
                ],
            ];
        }

        $result = $service->enrich(
            $competitors
        );

        $this->assertCount(
            5,
            $result
        );

        $this->assertSame(
            'competitor-1',
            $result[0]['id']
        );

        $this->assertSame(
            'Detailed competitor-1',
            $result[0]['displayName']['text']
        );

        $this->assertSame(
            4.8,
            $result[0]['rating']
        );

        $this->assertSame(
            120,
            $result[0]['userRatingCount']
        );

        $this->assertSame(
            'https://competitor-1.example.com',
            $result[0]['websiteUri']
        );

        $this->assertSame(
            89,
            $result[0]['_relevance']['score']
        );

        $this->assertSame(
            1.0,
            $result[0]['_match']['distance_km']
        );
    }

    public function test_it_keeps_existing_candidate_data_when_place_details_time_out(): void
    {
        $google = Mockery::mock(
            GooglePlacesService::class
        );

        $google->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);

        $google->shouldReceive('getPlaceDetailsBatch')
            ->once()
            ->with(['competitor-1'])
            ->andThrow(
                new AnalysisDeadlineExceeded(
                    'The analysis time budget was exhausted.'
                )
            );

        $service = new CompetitorEnrichmentService(
            $google
        );

        $result = $service->enrich([
            [
                'id' => 'competitor-1',

                'displayName' => [
                    'text' => 'Existing Competitor',
                ],

                'primaryType'
                    => 'plumber',

                '_relevance' => [
                    'score' => 82,
                    'strong_match' => true,
                ],
            ],
        ]);

        $this->assertCount(
            1,
            $result
        );

        $this->assertSame(
            'Existing Competitor',
            $result[0]['displayName']['text']
        );

        $this->assertSame(
            82,
            $result[0]['_relevance']['score']
        );

        $this->assertArrayNotHasKey(
            'rating',
            $result[0]
        );
    }
}
