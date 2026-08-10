<?php

namespace App\Services;

class BusinessClassifier
{
    private const LOCAL_RULES = [
        'home_services' => [
            'plumber',
            'plumbing',
            'electrician',
            'electrical',
            'roofer',
            'roofing',
            'hvac',
            'heating',
            'air conditioning',
            'waterproofing',
            'locksmith',
            'pest control',
            'landscaping',
            'cleaning service',
            'garage door',
        ],

        'healthcare_local' => [
            'dentist',
            'dental',
            'orthodontist',
            'chiropractor',
            'physiotherapist',
            'physical therapy',
            'medical clinic',
            'urgent care',
            'dermatologist',
            'optometrist',
        ],

        'beauty_wellness' => [
            'beauty salon',
            'hair salon',
            'spa',
            'massage',
            'nail salon',
            'barber',
            'esthetician',
            'pilates',
            'yoga studio',
            'fitness center',
            'gym',
        ],
    ];

    private const BROADER_RULES = [
        'financial_services' => [
            'insurance broker',
            'insurance agency',
            'insurance company',
            'mortgage broker',
            'mortgage lender',
            'financial advisor',
            'financial consultant',
            'investment',
            'wealth management',
            'banking',
            'bank',
        ],

        'manufacturing' => [
            'manufacturer',
            'manufacturing',
            'industrial',
            'factory',
            'wholesale',
            'fabrication',
            'machinery',
        ],

        'technology' => [
            'software company',
            'software development',
            'saas',
            'technology company',
            'cybersecurity',
            'cloud services',
            'it consulting',
            'web development',
            'app development',
        ],
    ];

    private const SERVICE_KEYWORDS = [
        'plumbing',
        'emergency plumbing',
        'drain cleaning',
        'water heater',
        'pipe repair',
        'electrical',
        'electrical repair',
        'roofing',
        'roof repair',
        'waterproofing',
        'hvac',
        'air conditioning',
        'heating',
        'dentistry',
        'dental implants',
        'orthodontics',
        'teeth whitening',
        'insurance',
        'commercial insurance',
        'business insurance',
        'life insurance',
        'mortgage',
        'mortgage lending',
        'financial planning',
        'wealth management',
        'manufacturing',
        'fabrication',
        'software development',
        'saas',
        'cybersecurity',
        'cloud services',
        'web development',
        'app development',
        'massage',
        'pilates',
        'yoga',
        'skin care',
        'facial',
        'fitness',
    ];

    public function classify(array $profile): array
    {
        $haystack = $this->buildHaystack($profile);

        $localScores = $this->scoreRules(
            $haystack,
            self::LOCAL_RULES
        );

        $broaderScores = $this->scoreRules(
            $haystack,
            self::BROADER_RULES
        );

        $bestLocal = $this->bestMatch($localScores);
        $bestBroader = $this->bestMatch($broaderScores);

        $localScore = $bestLocal['score'];
        $broaderScore = $bestBroader['score'];

        $marketScope = $this->determineMarketScope(
            $localScore,
            $broaderScore
        );

        $vertical = $this->determineVertical(
            $bestLocal,
            $bestBroader
        );

        return [
            'vertical' => $vertical,
            'market_scope' => $marketScope,
            'geography_weight' => $this->geographyWeight(
                $marketScope
            ),
            'radius_strategy_km' => $this->radiusStrategy(
                $marketScope
            ),
            'service_keywords' => $this->extractServiceKeywords(
                $haystack
            ),
            'confidence' => $this->confidence(
                max($localScore, $broaderScore)
            ),
            'signals' => [
                'local_score' => $localScore,
                'broader_score' => $broaderScore,
                'matched_local_vertical'
                    => $bestLocal['vertical'],
                'matched_broader_vertical'
                    => $bestBroader['vertical'],
            ],
        ];
    }

    private function buildHaystack(array $profile): string
    {
        $values = [
            data_get(
                $profile,
                'classification_input.business_name'
            ),
            data_get(
                $profile,
                'classification_input.website_title'
            ),
            data_get(
                $profile,
                'classification_input.website_description'
            ),
            data_get(
                $profile,
                'classification_input.homepage_text'
            ),
            data_get(
                $profile,
                'classification_input.primary_type'
            ),
            data_get(
                $profile,
                'classification_input.primary_type_name'
            ),
        ];

        $headings = data_get(
            $profile,
            'classification_input.headings',
            []
        );

        $googleTypes = data_get(
            $profile,
            'classification_input.google_types',
            []
        );

        $values = array_merge(
            $values,
            is_array($headings) ? $headings : [],
            is_array($googleTypes) ? $googleTypes : []
        );

        $values = array_filter(
            $values,
            fn ($value) => is_string($value)
                && trim($value) !== ''
        );

        $text = implode(' ', $values);

        $text = str_replace(
            ['_', '-', '/', '\\'],
            ' ',
            $text
        );

        $text = preg_replace(
            '/\s+/u',
            ' ',
            $text
        ) ?? $text;

        return mb_strtolower(trim($text));
    }

    private function scoreRules(
        string $haystack,
        array $rules
    ): array {
        $scores = [];

        foreach ($rules as $vertical => $keywords) {
            $score = 0;

            foreach ($keywords as $keyword) {
                if (
                    str_contains(
                        $haystack,
                        mb_strtolower($keyword)
                    )
                ) {
                    $score++;
                }
            }

            $scores[$vertical] = $score;
        }

        return $scores;
    }

    private function bestMatch(array $scores): array
    {
        if ($scores === []) {
            return [
                'vertical' => null,
                'score' => 0,
            ];
        }

        arsort($scores);

        $vertical = array_key_first($scores);
        $score = $scores[$vertical];

        if ($score === 0) {
            $vertical = null;
        }

        return [
            'vertical' => $vertical,
            'score' => $score,
        ];
    }

    private function determineMarketScope(
        int $localScore,
        int $broaderScore
    ): string {
        if ($localScore === 0 && $broaderScore === 0) {
            return 'hybrid';
        }

        if ($localScore > $broaderScore) {
            return 'local';
        }

        if ($broaderScore > $localScore) {
            return 'broader';
        }

        return 'hybrid';
    }

    private function determineVertical(
        array $bestLocal,
        array $bestBroader
    ): ?string {
        if ($bestLocal['score'] > $bestBroader['score']) {
            return $bestLocal['vertical'];
        }

        if ($bestBroader['score'] > $bestLocal['score']) {
            return $bestBroader['vertical'];
        }

        if (
            $bestLocal['score'] > 0
            && $bestLocal['vertical'] !== null
        ) {
            return $bestLocal['vertical'];
        }

        return null;
    }

    private function geographyWeight(
        string $marketScope
    ): string {
        return match ($marketScope) {
            'local' => 'high',
            'broader' => 'low',
            default => 'medium',
        };
    }

    private function radiusStrategy(
        string $marketScope
    ): array {
        return match ($marketScope) {
            'local' => [
                50,
                100,
                300,
            ],

            'broader' => [
                300,
                1000,
                3000,
            ],

            default => [
                100,
                300,
                1000,
                3000,
            ],
        };
    }

    private function extractServiceKeywords(
        string $haystack
    ): array {
        $matches = [];

        foreach (self::SERVICE_KEYWORDS as $keyword) {
            if (
                str_contains(
                    $haystack,
                    mb_strtolower($keyword)
                )
            ) {
                $matches[] = $keyword;
            }
        }

        return array_values(
            array_unique(
                array_slice(
                    $matches,
                    0,
                    12
                )
            )
        );
    }

    private function confidence(int $score): string
    {
        return match (true) {
            $score >= 4 => 'high',
            $score >= 2 => 'medium',
            $score === 1 => 'low',
            default => 'unknown',
        };
    }
}