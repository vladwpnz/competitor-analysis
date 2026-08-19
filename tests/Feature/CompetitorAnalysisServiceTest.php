<?php

namespace Tests\Feature;

use App\Exceptions\AnalysisDeadlineExceeded;
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

    public function test_local_analysis_keeps_only_one_result_per_competitor_company(): void
    {
        $competitorSearch = Mockery::mock(
            CompetitorSearchService::class
        );

        $competitorSearch
            ->shouldReceive('findCandidates')
            ->once()
            ->with(
                Mockery::on(
                    static fn (array $searchProfile): bool =>
                        $searchProfile['market_scope']
                            === 'local'
                        && $searchProfile[
                            'business_type'
                        ] === 'Plumber'
                ),
                30,
                CompetitorSearchService::STAGE_INITIAL
            )
            ->andReturn([
                $this->strongCandidate(
                    'rooterman-one',
                    'Rooterman Plumbing of Austin',
                    2.0,
                    'local_50km'
                ),

                $this->strongCandidate(
                    'rooterman-two',
                    'Rooter-Man Plumbing Austin TX',
                    3.0,
                    'local_50km'
                ),

                $this->strongCandidate(
                    'garrett',
                    'Garrett Plumbing',
                    4.0,
                    'local_50km'
                ),

                $this->strongCandidate(
                    'ez-flow',
                    'EZ Flow Plumbing',
                    5.0,
                    'local_50km'
                ),

                $this->strongCandidate(
                    'crow',
                    'Crow Plumbing Service',
                    6.0,
                    'local_50km'
                ),

                $this->strongCandidate(
                    'o-and-m',
                    'O M Plumbing',
                    7.0,
                    'local_50km'
                ),
            ]);

        $service = $this->service(
            $competitorSearch
        );

        $googlePlace =
            $this->plumberGooglePlace();

        $googlePlace['formattedAddress']
            = '12205 Antoinette Pl, Austin, TX 78727, USA';

        $googlePlace['location'] = [
            'latitude' => 30.414,
            'longitude' => -97.694,
        ];

        $result = $service->analyze(
            $this->plumberWebsiteScan(),
            $googlePlace
        );

        $ids = array_column(
            $result['top_competitors'],
            'id'
        );

        $rooterManIds = array_intersect(
            $ids,
            [
                'rooterman-one',
                'rooterman-two',
            ]
        );

        $this->assertCount(
            1,
            $rooterManIds
        );

        $this->assertCount(
            5,
            $result['top_competitors']
        );

        $this->assertContains(
            'o-and-m',
            $ids
        );

        $this->assertSame(
            5,
            $result['strong_match_count']
        );

        $this->assertSame(
            [
                CompetitorSearchService::STAGE_INITIAL,
            ],
            $result['search_stages']
        );
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

    public function test_digital_global_analysis_uses_direct_ai_competitors_without_google_places(): void
    {
        $businessIntelligence = Mockery::mock(
            BusinessIntelligenceService::class
        );

        $businessIntelligence
            ->shouldReceive('classify')
            ->once()
            ->andReturn([
                'business_model' => 'SaaS company',
                'business_type' => 'CRM Software Provider',
                'vertical' => 'technology',
                'market_scope' => 'broader',
                'geography_weight' => 'low',
                'radius_strategy_km' => [300, 1000, 3000],
                'service_keywords' => [
                    'crm',
                    'marketing automation',
                ],
                'search_queries' => [
                    'CRM software provider',
                ],
                'discovery_mode' => 'digital_global',
                'confidence' => 'high',
                '_classification_source' => 'ai',
            ]);

        $businessIntelligence
            ->shouldReceive('applySearchIntent')
            ->once()
            ->andReturnUsing(
                static function (
                    array $searchProfile,
                    array $classification
                ): array {
                    $searchProfile['business_type']
                        = $classification['business_type'];
                    $searchProfile['vertical']
                        = 'technology';
                    $searchProfile['market_scope']
                        = 'broader';
                    $searchProfile['geography_weight']
                        = 'low';
                    $searchProfile['discovery_mode']
                        = 'digital_global';

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
            ->andReturn([
                [
                    'name' => 'Salesforce',
                    'domain' => 'salesforce.com',
                    'reason' => 'Direct CRM platform overlap.',
                ],
                [
                    'name' => 'Zoho',
                    'domain' => 'zoho.com',
                    'reason' => 'CRM and marketing suite overlap.',
                ],
                [
                    'name' => 'Freshworks',
                    'domain' => 'freshworks.com',
                    'reason' => 'CRM and service platform overlap.',
                ],
                [
                    'name' => 'ActiveCampaign',
                    'domain' => 'activecampaign.com',
                    'reason' => 'Marketing automation and CRM overlap.',
                ],
                [
                    'name' => 'Pipedrive',
                    'domain' => 'pipedrive.com',
                    'reason' => 'Sales CRM overlap.',
                ],
                [
                    'name' => 'Zendesk',
                    'domain' => 'zendesk.com',
                    'reason' => 'Customer service platform overlap.',
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

        $result = $service->analyze([
            'final_url' => 'https://hubspot.com',
            'status' => 200,
            'title' => 'HubSpot Customer Platform',
            'meta_description'
                => 'CRM, marketing, sales and service software.',
            'h1' => [
                'Customer Platform',
            ],
            'h2' => [
                'CRM',
                'Marketing',
                'Sales',
            ],
            'text'
                => 'Global SaaS customer platform for CRM, marketing, sales and service.',
        ]);

        $this->assertSame(
            'ai_direct',
            $result['discovery_source']
        );

        $this->assertSame(
            ['ai_direct'],
            $result['search_stages']
        );

        $this->assertSame(
            6,
            $result['candidate_count']
        );

        $this->assertCount(
            5,
            $result['top_competitors']
        );

        $this->assertCount(
            6,
            $result['digital_candidate_pool']
        );

        $this->assertSame(
            'Zendesk',
            data_get(
                $result,
                'digital_candidate_pool.5.displayName.text'
            )
        );

        $this->assertSame(
            'Salesforce',
            data_get(
                $result,
                'top_competitors.0.displayName.text'
            )
        );

        $this->assertSame(
            'https://salesforce.com',
            data_get(
                $result,
                'top_competitors.0.websiteUri'
            )
        );

        $this->assertSame(
            'Direct Competitor',
            data_get(
                $result,
                'top_competitors.0.primaryTypeDisplayName.text'
            )
        );

        $this->assertSame(
            'ai_direct',
            data_get(
                $result,
                'top_competitors.0._match.search_modes.0'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $result,
                'top_competitors.0._relevance.strong_match'
            )
        );

        $this->assertStringStartsWith(
            'ai-',
            data_get(
                $result,
                'top_competitors.0.id'
            )
        );
    }

    public function test_broader_physical_analysis_uses_website_first_ai_discovery_without_google_places(): void
    {
        $businessIntelligence = Mockery::mock(
            BusinessIntelligenceService::class
        );

        $businessIntelligence
            ->shouldReceive('classify')
            ->once()
            ->andReturn([
                'business_model'
                    => 'Industrial automation and fluid power distributor',
                'business_type'
                    => 'Industrial Distributor',
                'vertical'
                    => 'industrial',
                'industry'
                    => 'Industrial Automation and Fluid Power Distribution',
                'market_scope'
                    => 'broader',
                'geography_weight'
                    => 'low',
                'radius_strategy_km'
                    => [300, 1000, 3000],
                'service_keywords' => [
                    'industrial automation',
                    'fluid power',
                    'motion control',
                    'filtration',
                ],
                'target_customers' => [
                    'industrial manufacturers',
                    'OEMs',
                    'engineering teams',
                ],
                'competitor_types' => [
                    'industrial distributors',
                    'automation distributors',
                    'fluid power distributors',
                ],
                'search_queries' => [
                    'industrial automation distributor',
                    'fluid power distributor',
                ],
                'discovery_mode'
                    => 'broader_physical',
                'confidence'
                    => 'high',
                '_classification_source'
                    => 'ai',
            ]);

        $businessIntelligence
            ->shouldReceive('applySearchIntent')
            ->once()
            ->andReturnUsing(
                static function (
                    array $searchProfile,
                    array $classification
                ): array {
                    $searchProfile['business_type']
                        = $classification['business_type'];
                    $searchProfile['business_model']
                        = $classification['business_model'];
                    $searchProfile['vertical']
                        = $classification['vertical'];
                    $searchProfile['market_scope']
                        = $classification['market_scope'];
                    $searchProfile['geography_weight']
                        = $classification['geography_weight'];
                    $searchProfile['discovery_mode']
                        = $classification['discovery_mode'];
                    $searchProfile['services']
                        = $classification['service_keywords'];

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
                    static function (
                        array $businessProfile
                    ): bool {
                        return
                            data_get(
                                $businessProfile,
                                'website.url'
                            ) === 'https://northline-industrial.test/'
                            && str_contains(
                                (string) data_get(
                                    $businessProfile,
                                    'website.text',
                                    ''
                                ),
                                'industrial automation'
                            );
                    }
                ),
                Mockery::on(
                    static fn (
                        array $classification
                    ): bool =>
                        data_get(
                            $classification,
                            'discovery_mode'
                        ) === 'broader_physical'
                )
            )
            ->andReturn([
                [
                    'name' => 'Apex Motion Supply',
                    'domain' => 'apex-motion.test',
                    'reason'
                        => 'Competes in industrial products, automation and engineered solutions.',
                ],
                [
                    'name' => 'Vector Fluid Systems',
                    'domain' => 'vector-fluid.test',
                    'reason'
                        => 'Competes as a broad industrial distributor serving similar customers.',
                ],
                [
                    'name' => 'Precision Motion Supply',
                    'domain' => 'precision-motion.test',
                    'reason'
                        => 'Competes in industrial distribution, motion and automation products.',
                ],
                [
                    'name' => 'Orion Automation',
                    'domain' => 'orion-automation.test',
                    'reason'
                        => 'Competes in motion, control and industrial automation solutions.',
                ],
                [
                    'name' => 'Keystone Pneumatics',
                    'domain' => 'keystone-pneumatics.test',
                    'reason'
                        => 'Competes in industrial automation and pneumatic control solutions.',
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

        $googlePlace = [
            'id' => 'northline-mississauga',
            'displayName' => [
                'text' => 'Northline Industrial',
                'languageCode' => 'en',
            ],
            'formattedAddress'
                => '5789 Coopers Ave, Mississauga, ON, Canada',
            'primaryType'
                => 'industrial_equipment_supplier',
            'primaryTypeDisplayName' => [
                'text' => 'Industrial Equipment Supplier',
                'languageCode' => 'en',
            ],
            'types' => [
                'industrial_equipment_supplier',
            ],
            'location' => [
                'latitude' => 43.6500,
                'longitude' => -79.6500,
            ],
            'businessStatus'
                => 'OPERATIONAL',
            'pureServiceAreaBusiness'
                => false,
            'websiteUri'
                => 'https://northline-industrial.test/',
        ];

        $result = $service->analyze(
            [
                'final_url'
                    => 'https://northline-industrial.test/',
                'status'
                    => 200,
                'title'
                    => 'Northline Industrial: Engineered Systems in Canada',
                'meta_description'
                    => 'Industrial automation, motion, control and filtration solutions.',
                'h1' => [
                    'Industrial Solutions for Engineered Systems',
                ],
                'h2' => [
                    'Industrial Automation',
                    'Motion and Control',
                    'Filtration',
                ],
                'text'
                    => 'Northline Industrial provides industrial automation, fluid power, motion control and filtration solutions across Canada.',
            ],
            $googlePlace
        );

        $this->assertSame(
            'broader_physical',
            data_get(
                $result,
                'classification.discovery_mode'
            )
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
            'Apex Motion Supply',
            data_get(
                $result,
                'top_competitors.0.displayName.text'
            )
        );

        $this->assertSame(
            'https://apex-motion.test',
            data_get(
                $result,
                'top_competitors.0.websiteUri'
            )
        );

        $this->assertNull(
            data_get(
                $result,
                'top_competitors.0._match.distance_km'
            )
        );
    }

    public function test_slow_gemini_competitor_discovery_falls_back_to_existing_google_places_pipeline(): void
    {
        $businessIntelligence = Mockery::mock(
            BusinessIntelligenceService::class
        );

        $businessIntelligence
            ->shouldReceive('classify')
            ->once()
            ->andReturn([
                'business_model' => 'SaaS company',
                'business_type' => 'Software Platform',
                'vertical' => 'technology',
                'market_scope' => 'broader',
                'geography_weight' => 'low',
                'radius_strategy_km' => [300, 1000, 3000],
                'service_keywords' => ['software'],
                'search_queries' => ['software platform'],
                'discovery_mode' => 'digital_global',
                'confidence' => 'high',
                '_classification_source' => 'ai',
            ]);

        $businessIntelligence
            ->shouldReceive('applySearchIntent')
            ->once()
            ->andReturnUsing(
                static function (
                    array $searchProfile
                ): array {
                    $searchProfile['business_type']
                        = 'Software Platform';
                    $searchProfile['vertical']
                        = 'technology';
                    $searchProfile['market_scope']
                        = 'broader';
                    $searchProfile['geography_weight']
                        = 'low';
                    $searchProfile['discovery_mode']
                        = 'digital_global';
                    $searchProfile['search_queries']
                        = ['software platform'];

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
            ->andThrow(
                new AnalysisDeadlineExceeded(
                    'Gemini competitor discovery timed out.'
                )
            );

        $this->app->instance(
            GeminiCompetitorDiscoveryService::class,
            $discovery
        );

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
            'final_url' => 'https://example.com',
            'status' => 200,
            'title' => 'Example Platform',
            'meta_description'
                => 'Global software platform.',
            'h1' => [
                'Software Platform',
            ],
            'h2' => [],
            'text'
                => 'Global software platform.',
        ]);

        $this->assertSame(
            'google_places_fallback',
            $result['discovery_source']
        );

        $this->assertSame(
            [
                CompetitorSearchService::STAGE_INITIAL,
            ],
            $result['search_stages']
        );

        $this->assertSame(
            [],
            $result['top_competitors']
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
        $competitorEnrichment = Mockery::mock(
            CompetitorEnrichmentService::class
        );

        $competitorEnrichment
            ->shouldReceive('enrich')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturnUsing(
                static fn (array $competitors): array => $competitors
            );

        return new CompetitorAnalysisService(
            app(BusinessProfileBuilder::class),
            app(BusinessClassifier::class),
            app(SearchProfileBuilder::class),
            $competitorSearch,
            app(CompetitorRelevanceScorer::class),
            $competitorEnrichment
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
