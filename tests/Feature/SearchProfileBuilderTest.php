<?php

namespace Tests\Feature;

use App\Services\SearchProfileBuilder;
use Tests\TestCase;

class SearchProfileBuilderTest extends TestCase
{
    public function test_it_builds_local_plumber_search_profile(): void
    {
        $builder = app(SearchProfileBuilder::class);

        $businessProfile = [
            'website' => [
                'url' => 'https://acmeplumbing.com',
            ],

            'google_business' => [
                'place_id' => 'customer-place-123',
            ],

            'classification_input' => [
                'business_name' => 'Acme Plumbing',
                'website_title'
                    => 'Acme Plumbing | Emergency Plumbing Services',
                'website_description'
                    => 'Local plumbing and repair services.',
                'headings' => [
                    'Emergency Plumbing',
                    'Drain Cleaning',
                    'Our Services',
                ],
                'homepage_text'
                    => 'Emergency plumbing, drains and water heaters.',
                'primary_type' => 'plumber',
                'primary_type_name' => 'Plumber',
                'google_types' => [
                    'plumber',
                ],
            ],

            'location' => [
                'address' => '100 Main St, Toronto, ON',
                'latitude' => 43.6532,
                'longitude' => -79.3832,
            ],
        ];

        $classification = [
            'vertical' => 'home_services',
            'market_scope' => 'local',
            'geography_weight' => 'high',
            'radius_strategy_km' => [
                50,
                100,
                300,
            ],
            'service_keywords' => [
                'plumbing',
                'emergency plumbing',
                'drain cleaning',
                'water heater',
            ],
            'confidence' => 'high',
        ];

        $profile = $builder->build(
            $businessProfile,
            $classification
        );

        $this->assertSame(
            'Plumber',
            $profile['business_type']
        );

        $this->assertSame(
            'local',
            $profile['market_scope']
        );

        $this->assertSame(
            'high',
            $profile['geography_weight']
        );

        $this->assertSame(
            [50, 100, 300],
            $profile['radius_strategy_km']
        );

        $this->assertContains(
            'plumbing',
            $profile['services']
        );

        $this->assertContains(
            'Plumber',
            $profile['search_queries']
        );

        $this->assertContains(
            'emergency plumbing',
            $profile['search_queries']
        );

        $this->assertSame(
            'customer-place-123',
            $profile['exclude']['place_id']
        );

        $this->assertSame(
            'Acme Plumbing',
            $profile['exclude']['business_name']
        );

        $this->assertSame(
            'acmeplumbing.com',
            $profile['exclude']['website_host']
        );
    }

    public function test_it_builds_broader_insurance_search_profile(): void
    {
        $builder = app(SearchProfileBuilder::class);

        $businessProfile = [
            'website' => [
                'url' => 'https://northstarinsurance.com',
            ],

            'google_business' => [
                'place_id' => 'insurance-place-456',
            ],

            'classification_input' => [
                'business_name'
                    => 'North Star Insurance Brokers',
                'website_title'
                    => 'Commercial Insurance Broker',
                'website_description'
                    => 'Business insurance and risk management.',
                'headings' => [
                    'Commercial Insurance',
                    'Business Insurance',
                    'Risk Management',
                ],
                'homepage_text'
                    => 'Insurance brokerage for businesses.',
                'primary_type'
                    => 'insurance_agency',
                'primary_type_name'
                    => 'Insurance Agency',
                'google_types' => [
                    'insurance_agency',
                ],
            ],

            'location' => [
                'address' => 'Toronto, ON',
                'latitude' => 43.6532,
                'longitude' => -79.3832,
            ],
        ];

        $classification = [
            'vertical' => 'financial_services',
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'radius_strategy_km' => [
                300,
                1000,
                3000,
            ],
            'service_keywords' => [
                'insurance',
                'commercial insurance',
                'business insurance',
            ],
            'confidence' => 'high',
        ];

        $profile = $builder->build(
            $businessProfile,
            $classification
        );

        $this->assertSame(
            'Insurance Agency',
            $profile['business_type']
        );

        $this->assertSame(
            'broader',
            $profile['market_scope']
        );

        $this->assertSame(
            'low',
            $profile['geography_weight']
        );

        $this->assertSame(
            [300, 1000, 3000],
            $profile['radius_strategy_km']
        );

        $this->assertContains(
            'commercial insurance',
            $profile['services']
        );

        $this->assertContains(
            'Insurance Agency',
            $profile['search_queries']
        );
    }

    public function test_it_can_build_search_profile_without_google_data(): void
    {
        $builder = app(SearchProfileBuilder::class);

        $businessProfile = [
            'website' => [
                'url'
                    => 'https://acmewatertreatment.com',
            ],

            'google_business' => [
                'place_id' => null,
            ],

            'classification_input' => [
                'business_name' => 'Acme',
                'website_title'
                    => 'Acme | Industrial Water Treatment',
                'website_description'
                    => 'Industrial water treatment solutions.',
                'headings' => [
                    'Industrial Water Treatment',
                    'Filtration Systems',
                ],
                'homepage_text'
                    => 'Water treatment systems for industrial customers.',
                'primary_type' => null,
                'primary_type_name' => null,
                'google_types' => [],
            ],

            'location' => [
                'address' => null,
                'latitude' => null,
                'longitude' => null,
            ],
        ];

        $classification = [
            'vertical' => null,
            'market_scope' => 'hybrid',
            'geography_weight' => 'medium',
            'radius_strategy_km' => [
                100,
                300,
                1000,
                3000,
            ],
            'service_keywords' => [],
            'confidence' => 'unknown',
        ];

        $profile = $builder->build(
            $businessProfile,
            $classification
        );

        $this->assertSame(
            'Industrial Water Treatment',
            $profile['business_type']
        );

        $this->assertSame(
            'hybrid',
            $profile['market_scope']
        );

        $this->assertContains(
            'Industrial Water Treatment',
            $profile['search_queries']
        );

        $this->assertNull(
            $profile['exclude']['place_id']
        );

        $this->assertSame(
            'acmewatertreatment.com',
            $profile['exclude']['website_host']
        );
    }

    public function test_it_removes_generic_headings_and_limits_queries(): void
    {
        $builder = app(SearchProfileBuilder::class);

        $businessProfile = [
            'website' => [
                'url' => 'https://example.com',
            ],

            'google_business' => [
                'place_id' => 'example-place',
            ],

            'classification_input' => [
                'business_name' => 'Example Company',
                'website_title'
                    => 'Example Company | Software Development',
                'website_description'
                    => 'Software services.',
                'headings' => [
                    'Home',
                    'About Us',
                    'Our Services',
                    'Web Development',
                    'App Development',
                    'Cloud Services',
                    'Cybersecurity',
                    'Custom Software',
                    'Technology Consulting',
                ],
                'homepage_text'
                    => 'Software and technology services.',
                'primary_type' => null,
                'primary_type_name' => null,
                'google_types' => [],
            ],

            'location' => [
                'address' => null,
                'latitude' => null,
                'longitude' => null,
            ],
        ];

        $classification = [
            'market_scope' => 'broader',
            'geography_weight' => 'low',
            'radius_strategy_km' => [
                300,
                1000,
                3000,
            ],
            'service_keywords' => [
                'software development',
                'web development',
                'app development',
                'cloud services',
            ],
            'confidence' => 'medium',
        ];

        $profile = $builder->build(
            $businessProfile,
            $classification
        );

        $this->assertNotContains(
            'Home',
            $profile['search_queries']
        );

        $this->assertNotContains(
            'About Us',
            $profile['search_queries']
        );

        $this->assertLessThanOrEqual(
            8,
            count($profile['search_queries'])
        );
    }
}