<?php

namespace Tests\Feature;

use App\Contracts\AiBusinessClassifier;
use App\Services\AiBusinessClassifierManager;
use App\Services\BusinessClassifier;
use App\Services\BusinessIntelligenceService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BusinessIntelligenceServiceTest extends TestCase
{
    public function test_ai_classifies_wainbee_like_business_as_distributor_and_overrides_search_intent(): void
    {
        $profile = $this->wainbeeLikeProfile();

        $service = $this->serviceWithAiResult(
            $this->industrialDistributorAiResult()
        );

        $classification = $service->classify(
            $profile
        );

        $this->assertSame(
            'ai',
            $classification[
                '_classification_source'
            ]
        );

        $this->assertSame(
            'industrial distributor and systems integrator',
            $classification[
                'business_model'
            ]
        );

        $this->assertSame(
            'Industrial Automation Distributor',
            $classification[
                'business_type'
            ]
        );

        $this->assertSame(
            'broader',
            $classification[
                'market_scope'
            ]
        );

        $this->assertSame(
            'broader_physical',
            $classification[
                'discovery_mode'
            ]
        );

        $this->assertSame(
            'low',
            $classification[
                'geography_weight'
            ]
        );

        $this->assertSame(
            [
                'Motion Control Distributor',
                'Industrial Automation Distributor',
                'Fluid Power Distributor',
                'Industrial Filtration Distributor',
            ],
            $classification[
                'search_queries'
            ]
        );

        $searchProfile = $service->applySearchIntent(
            $this->baseSearchProfile(),
            $classification
        );

        $this->assertSame(
            'Industrial Automation Distributor',
            $searchProfile[
                'business_type'
            ]
        );

        $this->assertSame(
            $classification[
                'search_queries'
            ],
            $searchProfile[
                'search_queries'
            ]
        );

        $this->assertSame(
            'broader',
            $searchProfile[
                'market_scope'
            ]
        );

        $this->assertSame(
            'broader_physical',
            $searchProfile[
                'discovery_mode'
            ]
        );

        $this->assertSame(
            'low',
            $searchProfile[
                'geography_weight'
            ]
        );
    }

    public function test_ai_keeps_local_plumber_local_and_distance_weighted(): void
    {
        $service = $this->serviceWithAiResult([
            'business_model'
                => 'local service provider',

            'business_type'
                => 'Plumber',

            'vertical'
                => 'home_services',

            'industry'
                => 'Residential and commercial plumbing',

            'market_scope'
                => 'local',

            'discovery_mode'
                => 'local_physical',

            'products_services' => [
                'plumbing',
                'drain cleaning',
                'water heater repair',
            ],

            'target_customers' => [
                'homeowners',
                'local businesses',
            ],

            'competitor_types' => [
                'plumber',
                'plumbing contractor',
            ],

            'search_queries' => [
                'Plumber',
                'Emergency Plumber',
                'Drain Cleaning Service',
                'Water Heater Repair',
            ],

            'geography_weight'
                => 'high',

            'confidence'
                => 'high',
        ]);

        $classification = $service->classify(
            $this->plumberLikeProfile()
        );

        $this->assertSame(
            'local',
            $classification[
                'market_scope'
            ]
        );

        $this->assertSame(
            'local_physical',
            $classification[
                'discovery_mode'
            ]
        );

        $this->assertSame(
            'high',
            $classification[
                'geography_weight'
            ]
        );

        $this->assertSame(
            [
                50,
                100,
                300,
            ],
            $classification[
                'radius_strategy_km'
            ]
        );
    }

    public function test_ai_keeps_hubspot_like_saas_broader_and_technology_focused(): void
    {
        $service = $this->serviceWithAiResult([
            'business_model'
                => 'SaaS company',

            'business_type'
                => 'CRM Software Company',

            'vertical'
                => 'technology',

            'industry'
                => 'CRM and customer platform software',

            'market_scope'
                => 'broader',

            'discovery_mode'
                => 'digital_global',

            'products_services' => [
                'crm',
                'customer platform',
                'marketing automation',
                'sales software',
            ],

            'target_customers' => [
                'businesses',
                'sales teams',
                'marketing teams',
            ],

            'competitor_types' => [
                'CRM software company',
                'customer platform software company',
            ],

            'search_queries' => [
                'CRM Software Company',
                'Customer Platform Software Company',
                'Marketing Automation Software Company',
                'Sales CRM Software Company',
            ],

            'geography_weight'
                => 'low',

            'confidence'
                => 'high',
        ]);

        $classification = $service->classify(
            $this->hubspotLikeProfile()
        );

        $this->assertSame(
            'technology',
            $classification[
                'vertical'
            ]
        );

        $this->assertSame(
            'broader',
            $classification[
                'market_scope'
            ]
        );

        $this->assertSame(
            'digital_global',
            $classification[
                'discovery_mode'
            ]
        );

        $this->assertSame(
            'low',
            $classification[
                'geography_weight'
            ]
        );

        $this->assertSame(
            'CRM Software Company',
            $classification[
                'business_type'
            ]
        );
    }

    public function test_broader_financial_service_uses_semantic_global_discovery(): void
    {
        $result = [
            'business_model' => 'national mortgage lender',
            'business_type' => 'Mortgage Lender',
            'vertical' => 'financial_services',
            'industry' => 'Residential mortgage lending',
            'market_scope' => 'broader',
            'discovery_mode' => 'broader_physical',
            'products_services' => [
                'mortgage lending',
                'home loans',
            ],
            'target_customers' => [
                'home buyers',
                'homeowners',
            ],
            'competitor_types' => [
                'mortgage lender',
                'mortgage company',
            ],
            'search_queries' => [
                'Mortgage Lender',
                'Mortgage Company',
            ],
            'geography_weight' => 'low',
            'confidence' => 'high',
        ];

        $service = $this->serviceWithAiResult(
            $result
        );

        $classification = $service->classify([
            'website' => [
                'url' => 'https://example-mortgage.test',
            ],
            'classification_input' => [
                'business_name' => 'Example Mortgage',
                'website_title' => 'National Mortgage Lender',
                'website_description' => 'Home loans across the United States.',
                'homepage_text' => 'Mortgage lending for customers nationwide.',
            ],
        ]);

        $this->assertSame(
            'broader',
            $classification['market_scope']
        );

        $this->assertSame(
            'digital_global',
            $classification['discovery_mode']
        );
    }

    public function test_healthcare_local_cannot_become_national_distance_free_discovery(): void
    {
        $result = [
            'business_model' => 'multi-location dermatology practice',
            'business_type' => 'Dermatology Clinic',
            'vertical' => 'healthcare_local',
            'industry' => 'Dermatology',
            'market_scope' => 'broader',
            'discovery_mode' => 'broader_physical',
            'products_services' => [
                'dermatology',
                'skin care',
            ],
            'target_customers' => [
                'local patients',
            ],
            'competitor_types' => [
                'dermatology clinic',
                'dermatologist',
            ],
            'search_queries' => [
                'Dermatologist',
                'Dermatology Clinic',
            ],
            'geography_weight' => 'low',
            'confidence' => 'high',
        ];

        $service = $this->serviceWithAiResult(
            $result
        );

        $classification = $service->classify([
            'website' => [
                'url' => 'https://example-dermatology.test',
            ],
            'classification_input' => [
                'business_name' => 'Example Dermatology',
                'website_title' => 'Dermatology Clinic',
                'website_description' => 'Local dermatology and cosmetic skin care.',
                'homepage_text' => 'Appointments for local patients.',
            ],
        ]);

        $this->assertSame(
            'local',
            $classification['market_scope']
        );

        $this->assertSame(
            'local_physical',
            $classification['discovery_mode']
        );

        $this->assertSame(
            'high',
            $classification['geography_weight']
        );
    }

    public function test_global_payment_platform_is_stabilized_to_semantic_discovery(): void
    {
        $service = $this->serviceWithAiResult([
            'business_model' => 'payments infrastructure platform',
            'business_type' => 'Online Payment Processor',
            'vertical' => 'financial_services',
            'industry' => 'Online payments and financial infrastructure',
            'market_scope' => 'hybrid',
            'discovery_mode' => 'hybrid',
            'products_services' => [
                'payment processing',
                'payments api',
            ],
            'target_customers' => [
                'online businesses',
            ],
            'competitor_types' => [
                'payment processor',
                'payments platform',
            ],
            'search_queries' => [
                'Payment Processor',
                'Payments Platform',
            ],
            'geography_weight' => 'medium',
            'confidence' => 'high',
        ]);

        $classification = $service->classify([
            'website' => [
                'url' => 'https://example-payments.test',
            ],
            'classification_input' => [
                'business_name' => 'Example Payments',
                'website_title' => 'Payments infrastructure for the internet',
                'website_description' => 'Online payment processing and financial infrastructure for businesses.',
                'homepage_text' => 'APIs for payments.',
            ],
        ]);

        $this->assertSame(
            'broader',
            $classification['market_scope']
        );

        $this->assertSame(
            'digital_global',
            $classification['discovery_mode']
        );
    }

    public function test_specialized_local_healthcare_search_drops_generic_queries(): void
    {
        $service = $this->serviceWithAiResult([
            'business_model' => 'local dermatology practice',
            'business_type' => 'Dermatology Clinic',
            'vertical' => 'healthcare_local',
            'industry' => 'Dermatology',
            'market_scope' => 'local',
            'discovery_mode' => 'local_physical',
            'products_services' => [
                'dermatology',
                'cosmetic dermatology',
            ],
            'target_customers' => [
                'local patients',
            ],
            'competitor_types' => [
                'dermatology clinic',
                'dermatologist',
            ],
            'search_queries' => [
                'Medical Clinic',
                'Doctor',
                'Dermatologist',
                'Cosmetic Dermatology',
            ],
            'geography_weight' => 'high',
            'confidence' => 'high',
        ]);

        $classification = $service->classify([
            'website' => [
                'url' => 'https://example-dermatology.test',
            ],
            'classification_input' => [
                'business_name' => 'Example Dermatology',
                'website_title' => 'Dermatology Clinic',
                'website_description' => 'Dermatology and cosmetic skin care.',
                'homepage_text' => 'Local dermatology appointments.',
            ],
        ]);

        $searchProfile = $service->applySearchIntent(
            $this->baseSearchProfile(),
            $classification
        );

        $this->assertSame(
            [
                'Dermatology Clinic',
                'Dermatologist',
                'Cosmetic Dermatology',
            ],
            $searchProfile['search_queries']
        );
    }

    public function test_broader_market_cannot_remain_local_physical_discovery(): void
    {
        $result = $this->industrialDistributorAiResult();
        $result['discovery_mode'] = 'local_physical';

        $service = $this->serviceWithAiResult(
            $result
        );

        $classification = $service->classify(
            $this->wainbeeLikeProfile()
        );

        $this->assertSame(
            'broader',
            $classification['market_scope']
        );

        $this->assertSame(
            'broader_physical',
            $classification['discovery_mode']
        );
    }

    public function test_unconfigured_ai_returns_exact_heuristic_fallback(): void
    {
        $profile = $this->wainbeeLikeProfile();

        $fallback = app(
            BusinessClassifier::class
        );

        $manager = Mockery::mock(
            AiBusinessClassifierManager::class
        );

        $provider = $this->fakeProvider(
            $this->industrialDistributorAiResult(),
            false
        );

        $manager
            ->shouldReceive('driver')
            ->once()
            ->andReturn(
                $provider
            );

        $service = new BusinessIntelligenceService(
            $fallback,
            $manager
        );

        $this->assertSame(
            $fallback->classify(
                $profile
            ),
            $service->classify(
                $profile
            )
        );
    }

    public function test_api_failure_returns_exact_heuristic_fallback(): void
    {
        $profile = $this->wainbeeLikeProfile();

        $fallback = app(
            BusinessClassifier::class
        );

        $manager = Mockery::mock(
            AiBusinessClassifierManager::class
        );

        $provider = new class implements AiBusinessClassifier {
            public function name(): string
            {
                return 'fake-ai';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function classify(
                array $businessProfile
            ): array {
                throw new RuntimeException(
                    'Simulated quota or timeout error.'
                );
            }
        };

        $manager
            ->shouldReceive('driver')
            ->once()
            ->andReturn(
                $provider
            );

        $service = new BusinessIntelligenceService(
            $fallback,
            $manager
        );

        $this->assertSame(
            $fallback->classify(
                $profile
            ),
            $service->classify(
                $profile
            )
        );
    }

    public function test_invalid_ai_payload_returns_exact_heuristic_fallback(): void
    {
        $profile = $this->wainbeeLikeProfile();

        $fallback = app(
            BusinessClassifier::class
        );

        $service = $this->serviceWithAiResult([
            'business_model'
                => 'distributor',
        ]);

        $this->assertSame(
            $fallback->classify(
                $profile
            ),
            $service->classify(
                $profile
            )
        );
    }

    public function test_ai_search_queries_are_limited_to_four_and_subject_name_is_removed(): void
    {
        $result = $this->industrialDistributorAiResult();

        $result['search_queries'] = [
            'Example Industrial Co competitor',
            'Motion Control Distributor',
            'Industrial Automation Distributor',
            'Fluid Power Distributor',
            'Industrial Filtration Distributor',
            'Hydraulic Equipment Distributor',
        ];

        $service = $this->serviceWithAiResult(
            $result
        );

        $classification = $service->classify(
            $this->wainbeeLikeProfile()
        );

        $this->assertCount(
            4,
            $classification[
                'search_queries'
            ]
        );

        $this->assertNotContains(
            'Example Industrial Co competitor',
            $classification[
                'search_queries'
            ]
        );
    }

    public function test_fallback_search_profile_is_not_modified(): void
    {
        $fallbackClassification = app(
            BusinessClassifier::class
        )->classify(
            $this->wainbeeLikeProfile()
        );

        $manager = Mockery::mock(
            AiBusinessClassifierManager::class
        );

        $manager
            ->shouldReceive('driver')
            ->never();

        $service = new BusinessIntelligenceService(
            app(
                BusinessClassifier::class
            ),
            $manager
        );

        $searchProfile = $this->baseSearchProfile();

        $this->assertSame(
            $searchProfile,
            $service->applySearchIntent(
                $searchProfile,
                $fallbackClassification
            )
        );
    }

    private function serviceWithAiResult(
        array $result
    ): BusinessIntelligenceService {
        $manager = Mockery::mock(
            AiBusinessClassifierManager::class
        );

        $manager
            ->shouldReceive('driver')
            ->once()
            ->andReturn(
                $this->fakeProvider(
                    $result
                )
            );

        return new BusinessIntelligenceService(
            app(
                BusinessClassifier::class
            ),
            $manager
        );
    }

    private function fakeProvider(
        array $result,
        bool $configured = true
    ): AiBusinessClassifier {
        return new class(
            $result,
            $configured
        ) implements AiBusinessClassifier {
            public function __construct(
                private readonly array $result,
                private readonly bool $configured
            ) {
            }

            public function name(): string
            {
                return 'fake-ai';
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function classify(
                array $businessProfile
            ): array {
                return $this->result;
            }
        };
    }

    private function industrialDistributorAiResult(): array
    {
        return [
            'business_model'
                => 'industrial distributor and systems integrator',

            'business_type'
                => 'Industrial Automation Distributor',

            'vertical'
                => 'industrial',

            'industry'
                => 'Industrial automation, motion control and fluid power',

            'market_scope'
                => 'broader',

            'discovery_mode'
                => 'broader_physical',

            'products_services' => [
                'motion control',
                'industrial automation',
                'fluid power',
                'industrial filtration',
                'hydraulics',
                'pneumatics',
                'electromechanical solutions',
            ],

            'target_customers' => [
                'manufacturers',
                'industrial facilities',
                'OEMs',
            ],

            'competitor_types' => [
                'industrial automation distributor',
                'motion control distributor',
                'fluid power distributor',
            ],

            'search_queries' => [
                'Motion Control Distributor',
                'Industrial Automation Distributor',
                'Fluid Power Distributor',
                'Industrial Filtration Distributor',
            ],

            'geography_weight'
                => 'low',

            'confidence'
                => 'high',
        ];
    }

    private function baseSearchProfile(): array
    {
        return [
            'business_type'
                => 'Manufacturer',

            'services' => [
                'motion control',
                'industrial automation',
            ],

            'search_queries' => [
                'Motion Control Supplier',
                'Industrial Automation Supplier',
                'Fluid Power Distributor',
                'Industrial Filtration Supplier',
            ],

            'vertical'
                => 'manufacturing',

            'market_scope'
                => 'broader',

            'geography_weight'
                => 'low',

            'radius_strategy_km' => [
                300,
                1000,
                3000,
            ],

            'location' => [
                'latitude'
                    => 43.6334199,

                'longitude'
                    => -79.6604936,

                'address'
                    => '5789 Coopers Ave, Mississauga, ON L4Z 3S6, Canada',
            ],

            'exclude' => [
                'place_id'
                    => 'example-place',

                'business_name'
                    => 'Example Industrial Co',

                'website_host'
                    => 'example-industrial.test',
            ],

            'classification_confidence'
                => 'high',
        ];
    }

    private function wainbeeLikeProfile(): array
    {
        return [
            'classification_input' => [
                'business_name'
                    => 'Example Industrial Co',

                'website_title'
                    => 'Industrial Solutions for Engineered Systems',

                'website_description'
                    => 'Motion & Control, Industrial Filtration and Automation Solutions',

                'homepage_text'
                    => 'We distribute and integrate hydraulic, pneumatic, electromechanical and drive control systems for industrial customers.',

                'primary_type'
                    => 'manufacturer',

                'primary_type_name'
                    => 'Manufacturer',

                'google_types' => [
                    'manufacturer',
                    'establishment',
                ],

                'headings' => [
                    'Our World Class Brands',
                    'Featured Products',
                ],
            ],

            'location' => [
                'latitude'
                    => 43.6334199,

                'longitude'
                    => -79.6604936,

                'address'
                    => '5789 Coopers Ave, Mississauga, ON L4Z 3S6, Canada',
            ],

            'google_business' => [
                'place_id'
                    => 'example-place',
            ],

            'website' => [
                'url'
                    => 'https://example-industrial.test/',
            ],
        ];
    }

    private function plumberLikeProfile(): array
    {
        return [
            'classification_input' => [
                'business_name'
                    => 'Acme Plumbing',

                'website_title'
                    => 'Acme Plumbing | Emergency Plumbing Services',

                'website_description'
                    => 'Local plumber providing emergency plumbing, drain cleaning and water heater repairs.',

                'homepage_text'
                    => 'Acme Plumbing provides plumbing, emergency plumbing, drain cleaning, pipe repair and water heater services.',

                'primary_type'
                    => 'plumber',

                'primary_type_name'
                    => 'Plumber',

                'google_types' => [
                    'plumber',
                ],
            ],

            'location' => [
                'latitude'
                    => 43.6532,

                'longitude'
                    => -79.3832,

                'address'
                    => '100 Main St, Toronto, ON, Canada',
            ],

            'website' => [
                'url'
                    => 'https://acmeplumbing.test/',
            ],
        ];
    }

    private function hubspotLikeProfile(): array
    {
        return [
            'classification_input' => [
                'business_name'
                    => 'Example CRM',

                'website_title'
                    => 'CRM, Marketing, Sales and Customer Platform',

                'website_description'
                    => 'Customer platform with CRM, marketing automation, sales and customer service software.',

                'homepage_text'
                    => 'Software for marketing, sales, customer service and go-to-market teams.',

                'primary_type'
                    => 'service',

                'primary_type_name'
                    => 'Service',

                'google_types' => [
                    'service',
                    'establishment',
                ],
            ],

            'location' => [
                'latitude'
                    => 42.3701334,

                'longitude'
                    => -71.0763725,

                'address'
                    => '2 Canal Park, Cambridge, MA 02141, USA',
            ],

            'website' => [
                'url'
                    => 'https://example-crm.test/',
            ],
        ];
    }
}
