<?php

namespace App\Services;

class BusinessProfileBuilder
{
    public function build(
        array $websiteScan,
        ?array $googlePlace = null
    ): array {
        $googlePlace ??= [];

        $displayName = data_get(
            $googlePlace,
            'displayName.text'
        );

        $primaryTypeDisplayName = data_get(
            $googlePlace,
            'primaryTypeDisplayName.text'
        );

        $latitude = data_get(
            $googlePlace,
            'location.latitude'
        );

        $longitude = data_get(
            $googlePlace,
            'location.longitude'
        );

        $websiteSignals = [
            'url' => $websiteScan['final_url'] ?? null,
            'title' => $websiteScan['title'] ?? null,
            'meta_description'
                => $websiteScan['meta_description'] ?? null,
            'h1' => $websiteScan['h1'] ?? [],
            'h2' => $websiteScan['h2'] ?? [],
            'text' => $websiteScan['text'] ?? '',
        ];

        $googleSignals = [
            'place_id' => $googlePlace['id'] ?? null,
            'name' => $displayName,
            'address'
                => $googlePlace['formattedAddress'] ?? null,
            'primary_type'
                => $googlePlace['primaryType'] ?? null,
            'primary_type_name'
                => $primaryTypeDisplayName,
            'types' => $googlePlace['types'] ?? [],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'business_status'
                => $googlePlace['businessStatus'] ?? null,
            'service_area_business'
                => $googlePlace['pureServiceAreaBusiness']
                    ?? false,
            'website'
                => $googlePlace['websiteUri'] ?? null,
            'google_maps_url'
                => $googlePlace['googleMapsUri'] ?? null,
            'rating' => $googlePlace['rating'] ?? null,
            'review_count'
                => $googlePlace['userRatingCount'] ?? null,
        ];

        return [
            'website' => $websiteSignals,
            'google_business' => $googleSignals,

            /*
             * These fields will become the input for the
             * classification and competitor matching layer.
             */
            'classification_input' => [
                'business_name' => $displayName,
                'website_title'
                    => $websiteSignals['title'],
                'website_description'
                    => $websiteSignals['meta_description'],
                'headings' => array_values(
                    array_filter(
                        array_merge(
                            $websiteSignals['h1'],
                            $websiteSignals['h2']
                        )
                    )
                ),
                'homepage_text'
                    => $websiteSignals['text'],
                'primary_type'
                    => $googleSignals['primary_type'],
                'primary_type_name'
                    => $googleSignals['primary_type_name'],
                'google_types'
                    => $googleSignals['types'],
            ],

            'location' => [
                'address' => $googleSignals['address'],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'service_area_business'
                    => $googleSignals[
                        'service_area_business'
                    ],
            ],
        ];
    }
}