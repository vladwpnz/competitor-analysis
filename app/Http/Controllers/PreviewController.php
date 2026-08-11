<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PreviewController extends Controller
{
    public function competitors(): View
    {
        $topCompetitors = [
            [
                'displayName' => [
                    'text' => 'Northline Plumbing',
                ],
                'primaryType' => 'plumber',
                'primaryTypeDisplayName' => [
                    'text' => 'Plumber',
                ],
                'rating' => 4.8,
                'userRatingCount' => 286,
                'formattedAddress' => 'Austin, TX',
                'websiteUri' => null,
                'googleMapsUri' => null,
                '_relevance' => [
                    'score' => 94,
                    'strong_match' => true,
                ],
                '_match' => [
                    'distance_km' => 4.7,
                    'query_hits' => 4,
                ],
            ],
            [
                'displayName' => [
                    'text' => 'ClearFlow Plumbing',
                ],
                'primaryType' => 'plumber',
                'primaryTypeDisplayName' => [
                    'text' => 'Plumbing Service',
                ],
                'rating' => 4.7,
                'userRatingCount' => 194,
                'formattedAddress' => 'Austin, TX',
                'websiteUri' => null,
                'googleMapsUri' => null,
                '_relevance' => [
                    'score' => 91,
                    'strong_match' => true,
                ],
                '_match' => [
                    'distance_km' => 7.2,
                    'query_hits' => 4,
                ],
            ],
            [
                'displayName' => [
                    'text' => 'BluePeak Plumbing & Drain',
                ],
                'primaryType' => 'plumber',
                'primaryTypeDisplayName' => [
                    'text' => 'Plumber',
                ],
                'rating' => 4.6,
                'userRatingCount' => 157,
                'formattedAddress' => 'Round Rock, TX',
                'websiteUri' => null,
                'googleMapsUri' => null,
                '_relevance' => [
                    'score' => 87,
                    'strong_match' => true,
                ],
                '_match' => [
                    'distance_km' => 18.5,
                    'query_hits' => 3,
                ],
            ],
            [
                'displayName' => [
                    'text' => 'Summit Plumbing Solutions',
                ],
                'primaryType' => 'plumber',
                'primaryTypeDisplayName' => [
                    'text' => 'Plumbing Service',
                ],
                'rating' => 4.5,
                'userRatingCount' => 121,
                'formattedAddress' => 'Cedar Park, TX',
                'websiteUri' => null,
                'googleMapsUri' => null,
                '_relevance' => [
                    'score' => 82,
                    'strong_match' => true,
                ],
                '_match' => [
                    'distance_km' => 24.1,
                    'query_hits' => 3,
                ],
            ],
            [
                'displayName' => [
                    'text' => 'PrimeFlow Home Services',
                ],
                'primaryType' => 'plumber',
                'primaryTypeDisplayName' => [
                    'text' => 'Plumbing Service',
                ],
                'rating' => 4.4,
                'userRatingCount' => 98,
                'formattedAddress' => 'Pflugerville, TX',
                'websiteUri' => null,
                'googleMapsUri' => null,
                '_relevance' => [
                    'score' => 76,
                    'strong_match' => true,
                ],
                '_match' => [
                    'distance_km' => 28.9,
                    'query_hits' => 2,
                ],
            ],
        ];

        $analysisResult = [
            'search_stage_count' => 3,
            'top_competitors' => $topCompetitors,
        ];

        return view('competitors', [
            'website' => 'https://example-plumbing.com',
            'googleBusiness' => 'Example Plumbing & Heating — Austin, TX',

            'websiteScan' => [
                'final_url' => 'https://example-plumbing.com',
                'status' => 200,
                'title' => 'Example Plumbing & Heating | Austin TX',
                'meta_description'
                    => 'Residential and commercial plumbing services in Austin, Texas.',
                'h1' => [
                    'Trusted Plumbing Services in Austin',
                ],
                'h2' => [
                    'Emergency Plumbing',
                    'Drain Cleaning',
                    'Water Heater Repair',
                ],
                'text'
                    => 'Residential and commercial plumbing, emergency repairs, drain cleaning and water heater services.',
            ],

            'googlePlace' => null,
            'analysisResult' => $analysisResult,
            'topCompetitors' => $topCompetitors,

            'isPreview' => true,
        ]);
    }
}