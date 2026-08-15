<?php

namespace Tests\Feature;

use App\Services\BusinessClassifier;
use App\Services\BusinessIntelligenceService;
use App\Services\BusinessProfileBuilder;
use App\Services\CompetitorAnalysisService;
use App\Services\CompetitorEnrichmentService;
use App\Services\CompetitorRelevanceScorer;
use App\Services\CompetitorSearchService;
use App\Services\GeminiCompetitorDiscoveryService;
use App\Services\SearchProfileBuilder;
use Mockery;
use Tests\TestCase;

class BlockedWebsiteCompetitorDiscoveryTest extends TestCase
{
    public function test_blocked_corporate_website_still_uses_ai_website_discovery_before_google_places(): void
    {
        $businessIntelligence = Mockery::mock(
            BusinessIntelligenceService::class
        );

        $classification = [
            'business_model' => 'Commercial bank',
            'business_type' => 'Bank',
            'vertical' => 'financial_services',
            'industry' => 'Banking',
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'radius_strategy_km' => [300, 1000, 3000],
            'service_keywords' => [
                'personal banking',
                'business banking',
                'credit cards',
                'mortgages',
                'investments',
            ],
            'target_customers' => [
                'consumers',
                'small businesses',
                'commercial clients',
            ],
            'competitor_types' => [
                'commercial banks',
                'retail banks',
            ],
            'search_queries' => [
                'Canadian commercial bank',
            ],
            'discovery_mode' => 'broader_physical',
            'confidence' => 'high',
            '_classification_source' => 'ai',
        ];

        $businessIntelligence
            ->shouldReceive('classify')
            ->once()
            ->andReturn($classification);

        $businessIntelligence
            ->shouldReceive('applySearchIntent')
            ->once()
            ->andReturnUsing(
                static function (
                    array $searchProfile,
                    array $resolvedClassification
                ): array {
                    $searchProfile['business_type']
                        = $resolvedClassification['business_type'];
                    $searchProfile['business_model']
                        = $resolvedClassification['business_model'];
                    $searchProfile['vertical']
                        = $resolvedClassification['vertical'];
                    $searchProfile['market_scope']
                        = $resolvedClassification['market_scope'];
                    $searchProfile['geography_weight']
                        = $resolvedClassification['geography_weight'];
                    $searchProfile['discovery_mode']
                        = $resolvedClassification['discovery_mode'];
                    $searchProfile['services']
                        = $resolvedClassification['service_keywords'];

                    return $searchProfile;
                }
            );

        $this->app->instance(
            BusinessIntelligenceService::class,
            $businessIntelligence
        );

        $discovery = Mockery::mock(
            GeminiCompetitorDiscoveryService::class
        );

        $discovery
            ->shouldReceive('isConfigured')
            ->once()
            ->andReturnTrue();

        $discovery
            ->shouldReceive('discover')
            ->once()
            ->with(
                Mockery::on(
                    static fn (array $businessProfile): bool =>
                        data_get(
                            $businessProfile,
                            'website.url'
                        ) === 'https://www.rbc.com'
                        && data_get(
                            $businessProfile,
                            'classification_input.business_name'
                        ) === 'RBC Royal Bank'
                        && data_get(
                            $businessProfile,
                            'classification_input.homepage_text'
                        ) === ''
                ),
                Mockery::on(
                    static fn (array $resolvedClassification): bool =>
                        ($resolvedClassification['business_type'] ?? null)
                            === 'Bank'
                        && ($resolvedClassification['market_scope'] ?? null)
                            === 'broader'
                )
            )
            ->andReturn([
                [
                    'name' => 'TD Bank',
                    'domain' => 'td.com',
                    'reason' => 'Direct Canadian retail and commercial banking competitor.',
                ],
                [
                    'name' => 'Scotiabank',
                    'domain' => 'scotiabank.com',
                    'reason' => 'Direct Canadian banking competitor across consumer and business services.',
                ],
                [
                    'name' => 'BMO',
                    'domain' => 'bmo.com',
                    'reason' => 'Direct Canadian banking competitor with overlapping financial products.',
                ],
                [
                    'name' => 'CIBC',
                    'domain' => 'cibc.com',
                    'reason' => 'Direct Canadian retail and commercial banking competitor.',
                ],
                [
                    'name' => 'National Bank of Canada',
                    'domain' => 'nbc.ca',
                    'reason' => 'Direct Canadian banking competitor for personal and business customers.',
                ],
            ]);

        $this->app->instance(
            GeminiCompetitorDiscoveryService::class,
            $discovery
        );

        $competitorSearch = Mockery::mock(
            CompetitorSearchService::class
        );

        $competitorSearch->shouldNotReceive(
            'findCandidates'
        );

        $competitorEnrichment = Mockery::mock(
            CompetitorEnrichmentService::class
        );

        $competitorEnrichment->shouldNotReceive(
            'enrich'
        );

        $service = new CompetitorAnalysisService(
            app(BusinessProfileBuilder::class),
            app(BusinessClassifier::class),
            app(SearchProfileBuilder::class),
            $competitorSearch,
            app(CompetitorRelevanceScorer::class),
            $competitorEnrichment
        );

        $result = $service->analyze(
            [
                'final_url' => 'https://www.rbc.com',
                'status' => 403,
                'title' => null,
                'meta_description' => null,
                'h1' => [],
                'h2' => [],
                'text' => '',
                'available' => false,
            ],
            [
                'id' => 'rbc-wellington',
                'displayName' => [
                    'text' => 'RBC Royal Bank',
                    'languageCode' => 'en',
                ],
                'formattedAddress'
                    => '200 Bay St, Toronto, ON, Canada',
                'primaryType' => 'bank',
                'primaryTypeDisplayName' => [
                    'text' => 'Bank',
                    'languageCode' => 'en',
                ],
                'types' => [
                    'bank',
                    'finance',
                ],
                'location' => [
                    'latitude' => 43.6469,
                    'longitude' => -79.3797,
                ],
                'businessStatus' => 'OPERATIONAL',
                'pureServiceAreaBusiness' => false,
                'websiteUri'
                    => 'https://maps.rbcroyalbank.com/',
            ]
        );

        $this->assertSame(
            'ai_direct',
            $result['discovery_source']
        );

        $this->assertSame(
            ['ai_direct'],
            $result['search_stages']
        );

        $this->assertCount(
            5,
            $result['top_competitors']
        );

        $this->assertSame(
            'TD Bank',
            data_get(
                $result,
                'top_competitors.0.displayName.text'
            )
        );

        $this->assertSame(
            'https://td.com',
            data_get(
                $result,
                'top_competitors.0.websiteUri'
            )
        );
    }
}
