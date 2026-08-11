<?php

namespace Tests\Feature;

use App\Services\BusinessProfileBuilder;
use Tests\TestCase;

class BusinessProfileBuilderTest extends TestCase
{
    public function test_it_builds_profile_from_website_only(): void
    {
        $builder = app(BusinessProfileBuilder::class);

        $profile = $builder->build([
            'final_url' => 'https://example.com',
            'status' => 200,
            'title' => 'Example Plumbing',
            'meta_description'
                => 'Residential and commercial plumbing services.',
            'h1' => [
                'Professional Plumbing Services',
            ],
            'h2' => [
                'Emergency Plumbing',
                'Water Heater Repair',
            ],
            'text'
                => 'Example Plumbing provides emergency plumbing...',
        ]);

        $this->assertSame(
            'Example Plumbing',
            $profile['website']['title']
        );

        $this->assertNull(
            $profile['google_business']['place_id']
        );

        $this->assertNull(
            $profile[
                'classification_input'
            ]['google_editorial_summary']
        );

        $this->assertSame(
            'Example Plumbing',
            $profile[
                'classification_input'
            ]['website_title']
        );

        $this->assertContains(
            'Emergency Plumbing',
            $profile[
                'classification_input'
            ]['headings']
        );
    }

    public function test_it_combines_website_and_google_place_data(): void
    {
        $builder = app(BusinessProfileBuilder::class);

        $websiteScan = [
            'final_url'
                => 'https://example-plumbing.com',
            'status' => 200,
            'title' => 'Example Plumbing',
            'meta_description'
                => 'Local plumbing and emergency repairs.',
            'h1' => [
                'Plumbing Services',
            ],
            'h2' => [
                'Emergency Repairs',
            ],
            'text'
                => 'Plumbing, repairs, water heaters and drains.',
        ];

        $googlePlace = [
            'id' => 'place-123',
            'displayName' => [
                'text' => 'Example Plumbing LLC',
                'languageCode' => 'en',
            ],
            'formattedAddress'
                => '100 Main St, Example City',
            'primaryType' => 'plumber',
            'primaryTypeDisplayName' => [
                'text' => 'Plumber',
                'languageCode' => 'en',
            ],
            'types' => [
                'plumber',
                'home_goods_store',
            ],
            'location' => [
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ],
            'businessStatus' => 'OPERATIONAL',
            'pureServiceAreaBusiness' => false,
            'websiteUri'
                => 'https://example-plumbing.com',
            'googleMapsUri'
                => 'https://maps.google.com/example',
            'rating' => 4.8,
            'userRatingCount' => 127,
            'editorialSummary' => [
                'text'
                    => 'Local plumbing company providing emergency repairs, drain cleaning and water heater service.',
                'languageCode' => 'en',
            ],
        ];

        $profile = $builder->build(
            $websiteScan,
            $googlePlace
        );

        $this->assertSame(
            'Example Plumbing LLC',
            $profile['google_business']['name']
        );

        $this->assertSame(
            'plumber',
            $profile[
                'classification_input'
            ]['primary_type']
        );

        $this->assertContains(
            'plumber',
            $profile[
                'classification_input'
            ]['google_types']
        );

        $this->assertSame(
            40.7128,
            $profile['location']['latitude']
        );

        $this->assertSame(
            127,
            $profile[
                'google_business'
            ]['review_count']
        );

        $this->assertSame(
            'Local plumbing company providing emergency repairs, drain cleaning and water heater service.',
            $profile[
                'google_business'
            ]['editorial_summary']
        );

        $this->assertSame(
            'Local plumbing company providing emergency repairs, drain cleaning and water heater service.',
            $profile[
                'classification_input'
            ]['google_editorial_summary']
        );
    }
}
