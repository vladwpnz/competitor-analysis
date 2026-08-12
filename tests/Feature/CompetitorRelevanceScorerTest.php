<?php

namespace Tests\Feature;

use App\Services\CompetitorRelevanceScorer;
use Tests\TestCase;

class CompetitorRelevanceScorerTest extends TestCase
{
    public function test_relevant_local_plumber_receives_high_score(): void
    {
        $scorer = app(
            CompetitorRelevanceScorer::class
        );

        $searchProfile = [
            'business_type' => 'Plumber',

            'services' => [
                'plumbing',
                'emergency plumbing',
                'drain cleaning',
            ],

            'search_queries' => [
                'Plumber',
                'Emergency Plumbing',
                'Drain Cleaning',
            ],

            'market_scope' => 'local',
        ];

        $candidate = [
            'id' => 'plumber-1',

            'displayName' => [
                'text' => 'Alpha Plumbing',
            ],

            'primaryType' => 'plumber',

            'primaryTypeDisplayName' => [
                'text' => 'Plumber',
            ],

            'types' => [
                'plumber',
            ],

            '_match' => [
                'query_hits' => 2,

                'queries' => [
                    'Plumber',
                    'Emergency Plumbing',
                ],

                'distance_km' => 8.5,
            ],
        ];

        $result = $scorer->score(
            $candidate,
            $searchProfile
        );

        $this->assertGreaterThanOrEqual(
            70,
            $result['score']
        );

        $this->assertSame(
            'high',
            $result['quality']
        );

        $this->assertTrue(
            $result['strong_match']
        );
    }

    public function test_relevance_beats_distance_for_local_business(): void
    {
        $scorer = app(
            CompetitorRelevanceScorer::class
        );

        $searchProfile = [
            'business_type' => 'Plumber',

            'services' => [
                'plumbing',
                'emergency plumbing',
                'drain cleaning',
            ],

            'search_queries' => [
                'Plumber',
                'Emergency Plumbing',
                'Drain Cleaning',
            ],

            'market_scope' => 'local',
        ];

        $relevantButFarther = [
            'id' => 'real-competitor',

            'displayName' => [
                'text' => 'Metro Plumbing',
            ],

            'primaryType' => 'plumber',

            'types' => [
                'plumber',
            ],

            '_match' => [
                'query_hits' => 3,

                'queries' => [
                    'Plumber',
                    'Emergency Plumbing',
                    'Drain Cleaning',
                ],

                'distance_km' => 35,
            ],
        ];

        $closeButWrong = [
            'id' => 'wrong-business',

            'displayName' => [
                'text' => 'City Electric',
            ],

            'primaryType' => 'electrician',

            'types' => [
                'electrician',
            ],

            '_match' => [
                'query_hits' => 1,

                'queries' => [
                    'Plumber',
                ],

                'distance_km' => 2,
            ],
        ];

        $ranked = $scorer->rank(
            [
                $closeButWrong,
                $relevantButFarther,
            ],
            $searchProfile,
            5
        );

        $this->assertSame(
            'real-competitor',
            $ranked[0]['id']
        );

        $this->assertGreaterThan(
            $ranked[1]['_relevance']['score'],
            $ranked[0]['_relevance']['score']
        );
    }

    public function test_broader_business_does_not_penalize_distance(): void
    {
        $scorer = app(
            CompetitorRelevanceScorer::class
        );

        $searchProfile = [
            'business_type' => 'Insurance Agency',

            'services' => [
                'insurance',
                'commercial insurance',
            ],

            'search_queries' => [
                'Insurance Agency',
                'Commercial Insurance',
            ],

            'market_scope' => 'broader',
        ];

        $near = [
            'id' => 'near-broker',

            'displayName' => [
                'text' => 'Near Insurance',
            ],

            'primaryType'
                => 'insurance_agency',

            'primaryTypeDisplayName' => [
                'text' => 'Insurance Agency',
            ],

            'types' => [
                'insurance_agency',
            ],

            '_match' => [
                'query_hits' => 2,

                'queries' => [
                    'Insurance Agency',
                    'Commercial Insurance',
                ],

                'distance_km' => 25,
            ],
        ];

        $far = $near;
        $far['id'] = 'far-broker';
        $far['_match']['distance_km'] = 2500;

        $nearResult = $scorer->score(
            $near,
            $searchProfile
        );

        $farResult = $scorer->score(
            $far,
            $searchProfile
        );

        $this->assertSame(
            $nearResult['score'],
            $farResult['score']
        );

        $this->assertSame(
            0.0,
            $nearResult['breakdown']['distance']
        );

        $this->assertSame(
            0.0,
            $farResult['breakdown']['distance']
        );
    }

    public function test_rank_returns_only_requested_top_results(): void
    {
        $scorer = app(
            CompetitorRelevanceScorer::class
        );

        $searchProfile = [
            'business_type' => 'Plumber',

            'services' => [
                'plumbing',
            ],

            'search_queries' => [
                'Plumber',
            ],

            'market_scope' => 'local',
        ];

        $candidates = [];

        for ($i = 1; $i <= 8; $i++) {
            $candidates[] = [
                'id' => 'candidate-' . $i,

                'displayName' => [
                    'text'
                        => 'Plumbing Company ' . $i,
                ],

                'primaryType' => 'plumber',

                'types' => [
                    'plumber',
                ],

                '_match' => [
                    'query_hits' => 1,

                    'queries' => [
                        'Plumber',
                    ],

                    'distance_km' => $i * 5,
                ],
            ];
        }

        $ranked = $scorer->rank(
            $candidates,
            $searchProfile,
            5
        );

        $this->assertCount(
            5,
            $ranked
        );

        $this->assertSame(
            'candidate-1',
            $ranked[0]['id']
        );

        $this->assertGreaterThanOrEqual(
            $ranked[4]['_relevance']['score'],
            $ranked[0]['_relevance']['score']
        );
    }
    public function test_healthcare_specialty_matching_does_not_confuse_physical_with_physician(): void
    {
        $scorer = app(
            CompetitorRelevanceScorer::class
        );

        $searchProfile = [
            'business_type'
                => 'Physical Therapy Clinic',

            'services' => [
                'physical therapy',
                'rehabilitation',
            ],

            'search_queries' => [
                'Physical Therapy Clinic',
                'Physical Therapist',
            ],

            'vertical'
                => 'healthcare_local',

            'industry'
                => 'Physical Therapy',

            'market_scope'
                => 'local',
        ];

        $candidate = [
            'id' => 'physician-office',

            'displayName' => [
                'text' => 'Physician Associates',
            ],

            'primaryType'
                => 'doctor',

            'primaryTypeDisplayName' => [
                'text' => 'Doctor',
            ],

            'types' => [
                'doctor',
                'health',
            ],

            '_match' => [
                'query_hits' => 1,

                'queries' => [
                    'Physical Therapy Clinic',
                ],

                'distance_km' => 2.0,
            ],
        ];

        $result = $scorer->score(
            $candidate,
            $searchProfile
        );

        $this->assertSame(
            0.0,
            $result['evidence'][
                'healthcare_specialty_ratio'
            ]
        );
    }

    public function test_healthcare_specialty_matching_keeps_real_word_variants(): void
    {
        $scorer = app(
            CompetitorRelevanceScorer::class
        );

        $searchProfile = [
            'business_type'
                => 'Dermatology Clinic',

            'services' => [
                'dermatology',
            ],

            'search_queries' => [
                'Dermatology Clinic',
            ],

            'vertical'
                => 'healthcare_local',

            'industry'
                => 'Dermatology',

            'market_scope'
                => 'local',
        ];

        $candidate = [
            'id' => 'dermatologist',

            'displayName' => [
                'text' => 'Austin Dermatologist',
            ],

            'primaryType'
                => 'doctor',

            'primaryTypeDisplayName' => [
                'text' => 'Doctor',
            ],

            'types' => [
                'doctor',
                'health',
            ],

            '_match' => [
                'query_hits' => 1,

                'queries' => [
                    'Dermatology Clinic',
                ],

                'distance_km' => 5.0,
            ],
        ];

        $result = $scorer->score(
            $candidate,
            $searchProfile
        );

        $this->assertSame(
            1.0,
            $result['evidence'][
                'healthcare_specialty_ratio'
            ]
        );
    }

    public function test_specialized_local_healthcare_uses_name_evidence_when_google_type_is_generic(): void
    {
        $scorer = app(
            CompetitorRelevanceScorer::class
        );

        $searchProfile = [
            'business_type'
                => 'Dermatology & Plastic Surgery Clinic',
            'services' => [
                'general medical dermatology',
                'mohs micrographic surgery',
                'cosmetic plastic surgery',
            ],
            'search_queries' => [
                'Dermatology & Plastic Surgery Clinic',
                'dermatologist near me',
                'plastic surgeon near me',
                'medical spa near me',
            ],
            'vertical' => 'healthcare_local',
            'industry'
                => 'Dermatology, Plastic Surgery, and Medical Spa Services',
            'market_scope' => 'local',
        ];

        $dermatology = [
            'id' => 'dermatology',
            'displayName' => [
                'text' => 'Central Texas Dermatology',
            ],
            'primaryType' => 'doctor',
            'primaryTypeDisplayName' => [
                'text' => 'Doctor',
            ],
            'types' => [
                'doctor',
                'health',
            ],
            '_match' => [
                'query_hits' => 3,
                'queries' => [
                    'Dermatology & Plastic Surgery Clinic',
                    'dermatologist near me',
                    'plastic surgeon near me',
                ],
                'distance_km' => 6.34,
            ],
        ];

        $pediatrics = [
            'id' => 'pediatrics',
            'displayName' => [
                'text' => 'Austin Pediatrics',
            ],
            'primaryType' => 'doctor',
            'primaryTypeDisplayName' => [
                'text' => 'Doctor',
            ],
            'types' => [
                'doctor',
                'health',
            ],
            '_match' => [
                'query_hits' => 3,
                'queries' => [
                    'Dermatology & Plastic Surgery Clinic',
                    'dermatologist near me',
                    'plastic surgeon near me',
                ],
                'distance_km' => 2.0,
            ],
        ];

        $ranked = $scorer->rank(
            [
                $pediatrics,
                $dermatology,
            ],
            $searchProfile,
            2
        );

        $this->assertSame(
            'dermatology',
            $ranked[0]['id']
        );

        $this->assertTrue(
            $ranked[0]['_relevance']['type_compatible']
        );

        $this->assertTrue(
            $ranked[0]['_relevance']['strong_match']
        );

        $this->assertSame(
            1.0,
            $ranked[0]['_relevance']['evidence'][
                'healthcare_specialty_ratio'
            ]
        );

        $this->assertFalse(
            $ranked[1]['_relevance']['type_compatible']
        );

        $this->assertGreaterThan(
            $ranked[1]['_relevance']['score'],
            $ranked[0]['_relevance']['score']
        );
    }

}