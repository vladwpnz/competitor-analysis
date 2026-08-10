<?php

namespace Tests\Feature;

use App\Services\BusinessClassifier;
use Tests\TestCase;

class BusinessClassifierTest extends TestCase
{
    public function test_it_classifies_plumber_as_local_business(): void
    {
        $classifier = app(BusinessClassifier::class);

        $profile = [
            'classification_input' => [
                'business_name' => 'Acme Plumbing',
                'website_title'
                    => 'Emergency Plumbing Services',
                'website_description'
                    => 'Local plumber for drain cleaning, pipe repair and water heaters.',
                'headings' => [
                    'Professional Plumbing Services',
                    'Emergency Plumbing',
                ],
                'homepage_text'
                    => 'Drain cleaning, pipe repair and water heater services.',
                'primary_type' => 'plumber',
                'primary_type_name' => 'Plumber',
                'google_types' => [
                    'plumber',
                ],
            ],
        ];

        $result = $classifier->classify($profile);

        $this->assertSame(
            'home_services',
            $result['vertical']
        );

        $this->assertSame(
            'local',
            $result['market_scope']
        );

        $this->assertSame(
            'high',
            $result['geography_weight']
        );

        $this->assertSame(
            [50, 100, 300],
            $result['radius_strategy_km']
        );

        $this->assertContains(
            'plumbing',
            $result['service_keywords']
        );
    }

    public function test_it_classifies_insurance_as_broader_business(): void
    {
        $classifier = app(BusinessClassifier::class);

        $profile = [
            'classification_input' => [
                'business_name'
                    => 'North Star Insurance Brokers',
                'website_title'
                    => 'Commercial Insurance Broker',
                'website_description'
                    => 'Business insurance and risk management solutions.',
                'headings' => [
                    'Commercial Insurance',
                ],
                'homepage_text'
                    => 'Insurance brokerage serving businesses across multiple regions.',
                'primary_type'
                    => 'insurance_agency',
                'primary_type_name'
                    => 'Insurance Agency',
                'google_types' => [
                    'insurance_agency',
                ],
            ],
        ];

        $result = $classifier->classify($profile);

        $this->assertSame(
            'financial_services',
            $result['vertical']
        );

        $this->assertSame(
            'broader',
            $result['market_scope']
        );

        $this->assertSame(
            'low',
            $result['geography_weight']
        );

        $this->assertSame(
            [300, 1000, 3000],
            $result['radius_strategy_km']
        );

        $this->assertContains(
            'insurance',
            $result['service_keywords']
        );
    }

    public function test_it_can_classify_from_website_signals_without_google(): void
    {
        $classifier = app(BusinessClassifier::class);

        $profile = [
            'classification_input' => [
                'business_name' => null,
                'website_title'
                    => 'Bright Dental Clinic',
                'website_description'
                    => 'Dentistry, dental implants and teeth whitening.',
                'headings' => [
                    'Family Dentist',
                    'Dental Implants',
                ],
                'homepage_text'
                    => 'Local dental clinic offering modern dentistry.',
                'primary_type' => null,
                'primary_type_name' => null,
                'google_types' => [],
            ],
        ];

        $result = $classifier->classify($profile);

        $this->assertSame(
            'healthcare_local',
            $result['vertical']
        );

        $this->assertSame(
            'local',
            $result['market_scope']
        );

        $this->assertContains(
            'dentistry',
            $result['service_keywords']
        );

        $this->assertContains(
            'dental implants',
            $result['service_keywords']
        );
    }

    public function test_unknown_business_uses_safe_hybrid_fallback(): void
    {
        $classifier = app(BusinessClassifier::class);

        $profile = [
            'classification_input' => [
                'business_name' => 'Example Company',
                'website_title' => 'Welcome',
                'website_description'
                    => 'We provide professional solutions.',
                'headings' => [
                    'Our Services',
                ],
                'homepage_text'
                    => 'Contact our team to learn more.',
                'primary_type' => null,
                'primary_type_name' => null,
                'google_types' => [],
            ],
        ];

        $result = $classifier->classify($profile);

        $this->assertNull(
            $result['vertical']
        );

        $this->assertSame(
            'hybrid',
            $result['market_scope']
        );

        $this->assertSame(
            'medium',
            $result['geography_weight']
        );

        $this->assertSame(
            'unknown',
            $result['confidence']
        );
    }
}