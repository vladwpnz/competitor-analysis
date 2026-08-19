<?php

namespace Tests\Feature;

use App\Services\CompetitorRelevanceScorer;
use App\Services\CompetitorSearchService;
use App\Services\GooglePlacesService;
use App\Services\SearchProfileBuilder;
use Mockery;
use Tests\TestCase;

class TechnologyCompetitorMatchingTest extends TestCase
{
    public function test_focused_technology_queries_are_prioritized_before_generic_software_company(): void
    {
        $builder = new SearchProfileBuilder();

        $profile = [
            'classification_input' => [
                'business_name' => 'HubSpot',
                'primary_type' => null,
                'primary_type_name' => null,
                'website_title' => 'HubSpot | Software & Tools for your Business Homepage',
                'headings' => [
                    'The Customer Platform',
                    'Marketing',
                    'Sales',
                    'Where go to market teams go to grow scale close retain grow',
                ],
            ],
            'location' => [
                'latitude' => 42.3701334,
                'longitude' => -71.0763725,
                'address' => '2 Canal Park, Cambridge, MA 02141, USA',
            ],
            'google_business' => [
                'place_id' => 'hubspot-place',
            ],
            'website' => [
                'url' => 'https://www.hubspot.com/',
            ],
        ];

        $classification = [
            'vertical' => 'technology',
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'radius_strategy_km' => [300, 1000, 3000],
            'service_keywords' => [
                'software',
                'crm',
                'customer platform',
                'marketing automation',
                'marketing software',
                'sales software',
                'customer service software',
                'go to market',
            ],
            'confidence' => 'high',
        ];

        $result = $builder->build(
            $profile,
            $classification
        );

        $this->assertSame(
            [
                'CRM Software Company',
                'Customer Platform Software Company',
                'Marketing Automation Software Company',
                'Sales CRM Software Company',
            ],
            array_slice(
                $result['search_queries'],
                0,
                4
            )
        );

        $this->assertSame(
            'Software Company',
            $result['business_type']
        );

        $genericIndex = array_search(
            'Software Company',
            $result['search_queries'],
            true
        );

        $this->assertIsInt($genericIndex);
        $this->assertGreaterThanOrEqual(
            4,
            $genericIndex
        );
    }

    public function test_focused_technology_ranking_prefers_similar_product_account_over_generic_dev_shop(): void
    {
        $scorer = new CompetitorRelevanceScorer();

        $searchProfile = [
            'business_type' => 'Software Company',
            'vertical' => 'technology',
            'market_scope' => 'broader',
            'services' => [
                'software',
                'crm',
                'customer platform',
                'marketing automation',
                'marketing software',
                'sales software',
                'customer service software',
                'go to market',
            ],
            'search_queries' => [
                'CRM Software Company',
                'Customer Platform Software Company',
                'Marketing Automation Software Company',
                'Sales CRM Software Company',
            ],
        ];

        $crmProduct = [
            'id' => 'crm-product',
            'displayName' => [
                'text' => 'MAssist CRM Software',
            ],
            'primaryType' => 'service',
            'primaryTypeDisplayName' => [
                'text' => 'Service',
            ],
            'types' => [
                'service',
                'establishment',
            ],
            '_match' => [
                'query_hits' => 2,
                'queries' => [
                    'CRM Software Company',
                    'Marketing Automation Software Company',
                ],
                'distance_km' => 7000.0,
            ],
        ];

        $devShop = [
            'id' => 'dev-shop',
            'displayName' => [
                'text' => 'Join.To.IT - Software Development Company',
            ],
            'primaryType' => 'service',
            'primaryTypeDisplayName' => [
                'text' => 'Service',
            ],
            'types' => [
                'service',
                'establishment',
            ],
            '_match' => [
                'query_hits' => 1,
                'queries' => [
                    'Software Company',
                ],
                'distance_km' => 4400.0,
            ],
        ];

        $ranked = $scorer->rank(
            [
                $devShop,
                $crmProduct,
            ],
            $searchProfile,
            2
        );

        $this->assertSame(
            'crm-product',
            $ranked[0]['id']
        );

        $this->assertTrue(
            $ranked[0]['_relevance']['type_compatible']
        );

        $this->assertFalse(
            $ranked[1]['_relevance']['type_compatible']
        );

        $this->assertGreaterThan(
            $ranked[1]['_relevance']['score'],
            $ranked[0]['_relevance']['score']
        );
    }

    public function test_broader_technology_search_uses_country_context_without_distance_ranking(): void
    {
        $google = Mockery::mock(
            GooglePlacesService::class
        );

        $google
            ->shouldReceive('searchBusinessesBatch')
            ->once()
            ->with([[
                'query' => 'CRM Software Company in USA',
                'max_results' => 15,
            ]])
            ->andReturn([
                0 => [[
                    'id' => 'crm-us-1',
                    'displayName' => [
                        'text' => 'Example CRM Platform',
                    ],
                    'primaryType' => 'software_company',
                ]],
            ]);

        $service = new CompetitorSearchService(
            $google
        );

        $candidates = $service->findCandidates([
            'search_queries' => [
                'CRM Software Company',
            ],
            'vertical' => 'technology',
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'location' => [
                'latitude' => 42.3701334,
                'longitude' => -71.0763725,
                'address' => '2 Canal Park, Cambridge, MA 02141, USA',
            ],
            'exclude' => [
                'place_id' => 'hubspot-place',
                'business_name' => 'HubSpot',
            ],
        ]);

        $this->assertCount(
            1,
            $candidates
        );

        $this->assertSame(
            ['broader_country_context'],
            $candidates[0]['_match']['search_modes']
        );

        $this->assertSame(
            ['CRM Software Company in USA'],
            $candidates[0]['_match']['executed_queries']
        );
    }

}
