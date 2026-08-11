<?php

namespace Tests\Feature;

use App\Services\BusinessClassifier;
use App\Services\SearchProfileBuilder;
use Tests\TestCase;

class ManufacturingCompetitorMatchingTest extends TestCase
{
    public function test_industrial_b2b_profile_uses_specific_manufacturing_queries(): void
    {
        $profile = [
            'classification_input' => [
                'business_name' => 'Example Industrial Co',
                'website_title' => 'Industrial Solutions for Engineered Systems',
                'website_description' =>
                    'Motion & Control, Industrial Filtration and Automation Solutions',
                'homepage_text' =>
                    'We integrate hydraulic, pneumatic, electromechanical and drive control systems. '
                    . 'Our catalog also contains refrigeration and air conditioning components.',
                'primary_type' => 'manufacturer',
                'primary_type_name' => 'Manufacturer',
                'google_types' => [
                    'manufacturer',
                    'establishment',
                ],
                'headings' => [
                    'Our World Class Brands',
                    'Featured Products',
                    'Welcome to Example Industrial Co',
                ],
            ],
            'location' => [
                'latitude' => 43.6334199,
                'longitude' => -79.6604936,
                'address' => '5789 Coopers Ave, Mississauga, ON L4Z 3S6, Canada',
            ],
            'google_business' => [
                'place_id' => 'example-place',
            ],
            'website' => [
                'url' => 'https://example-industrial.test/',
            ],
        ];

        $classification = app(BusinessClassifier::class)
            ->classify($profile);

        $this->assertSame(
            'manufacturing',
            $classification['vertical']
        );

        $this->assertSame(
            'broader',
            $classification['market_scope']
        );

        $this->assertContains(
            'motion control',
            $classification['service_keywords']
        );

        $this->assertContains(
            'industrial filtration',
            $classification['service_keywords']
        );

        $this->assertContains(
            'hydraulic',
            $classification['service_keywords']
        );

        $this->assertNotContains(
            'air conditioning',
            $classification['service_keywords']
        );

        $search = app(SearchProfileBuilder::class)
            ->build($profile, $classification);

        $this->assertSame(
            [
                'Motion Control Supplier',
                'Industrial Automation Supplier',
                'Fluid Power Distributor',
                'Industrial Filtration Supplier',
            ],
            array_slice(
                $search['search_queries'],
                0,
                4
            )
        );

        $this->assertNotContains(
            'Our World Class Brands',
            $search['search_queries']
        );

        $this->assertNotContains(
            'Featured Products',
            $search['search_queries']
        );
    }
}