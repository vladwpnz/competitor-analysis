<?php

namespace App\Services;

use Illuminate\Support\Str;

class SearchProfileBuilder
{
    private const GENERIC_HEADINGS = [
        'home',
        'welcome',
        'about',
        'about us',
        'our services',
        'services',
        'contact',
        'contact us',
        'learn more',
        'get started',
        'why choose us',
    ];

    public function build(
        array $businessProfile,
        array $classification
    ): array {
        $businessName = $this->stringOrNull(
            data_get(
                $businessProfile,
                'classification_input.business_name'
            )
        );

        $primaryType = $this->stringOrNull(
            data_get(
                $businessProfile,
                'classification_input.primary_type'
            )
        );

        $primaryTypeName = $this->stringOrNull(
            data_get(
                $businessProfile,
                'classification_input.primary_type_name'
            )
        );

        $websiteTitle = $this->stringOrNull(
            data_get(
                $businessProfile,
                'classification_input.website_title'
            )
        );

        $headings = data_get(
            $businessProfile,
            'classification_input.headings',
            []
        );

        $services = data_get(
            $classification,
            'service_keywords',
            []
        );

        $services = is_array($services)
            ? $this->uniqueStrings($services)
            : [];

        $businessType = $this->determineBusinessType(
            $primaryTypeName,
            $primaryType,
            $websiteTitle,
            $businessName
        );

        $queryCandidates = [];

        if ($businessType !== null) {
            $queryCandidates[] = $businessType;
        }

        foreach ($services as $service) {
            $queryCandidates[] = $service;

            if ($businessType !== null) {
                $queryCandidates[] = $service . ' ' . $businessType;
            }
        }

        if (is_array($headings)) {
            foreach ($headings as $heading) {
                $heading = $this->cleanHeading(
                    $heading,
                    $businessName
                );

                if ($heading !== null) {
                    $queryCandidates[] = $heading;
                }
            }
        }

        if ($queryCandidates === [] && $websiteTitle !== null) {
            $fallback = $this->removeBusinessName(
                $websiteTitle,
                $businessName
            );

            if ($fallback !== null) {
                $queryCandidates[] = $fallback;
            }
        }

        $searchQueries = $this->prepareQueries(
            $queryCandidates
        );

        return [
            'business_type' => $businessType,

            'services' => $services,

            'search_queries' => $searchQueries,

            'market_scope' => data_get(
                $classification,
                'market_scope',
                'hybrid'
            ),

            'geography_weight' => data_get(
                $classification,
                'geography_weight',
                'medium'
            ),

            'radius_strategy_km' => data_get(
                $classification,
                'radius_strategy_km',
                [100, 300, 1000, 3000]
            ),

            'location' => [
                'latitude' => data_get(
                    $businessProfile,
                    'location.latitude'
                ),
                'longitude' => data_get(
                    $businessProfile,
                    'location.longitude'
                ),
                'address' => data_get(
                    $businessProfile,
                    'location.address'
                ),
            ],

            /*
             * Used later to make sure the user's own business
             * never appears in the competitor result set.
             */
            'exclude' => [
                'place_id' => data_get(
                    $businessProfile,
                    'google_business.place_id'
                ),
                'business_name' => $businessName,
                'website_host' => $this->extractHost(
                    data_get(
                        $businessProfile,
                        'website.url'
                    )
                ),
            ],

            'classification_confidence' => data_get(
                $classification,
                'confidence',
                'unknown'
            ),
        ];
    }

    private function determineBusinessType(
        ?string $primaryTypeName,
        ?string $primaryType,
        ?string $websiteTitle,
        ?string $businessName
    ): ?string {
        if ($primaryTypeName !== null) {
            return $this->normalizePhrase(
                $primaryTypeName
            );
        }

        if ($primaryType !== null) {
            return $this->normalizePhrase(
                str_replace('_', ' ', $primaryType)
            );
        }

        if ($websiteTitle !== null) {
            return $this->removeBusinessName(
                $websiteTitle,
                $businessName
            );
        }

        return null;
    }

    private function cleanHeading(
        mixed $heading,
        ?string $businessName
    ): ?string {
        if (! is_string($heading)) {
            return null;
        }

        $heading = $this->removeBusinessName(
            $heading,
            $businessName
        );

        if ($heading === null) {
            return null;
        }

        $normalized = mb_strtolower($heading);

        if (in_array(
            $normalized,
            self::GENERIC_HEADINGS,
            true
        )) {
            return null;
        }

        /*
         * Very long headings usually make poor Maps queries.
         */
        if (mb_strlen($heading) > 80) {
            return null;
        }

        return $heading;
    }

    private function removeBusinessName(
        string $value,
        ?string $businessName
    ): ?string {
        $value = trim($value);

        if ($businessName !== null) {
            $value = str_ireplace(
                $businessName,
                ' ',
                $value
            );
        }

        $value = preg_replace(
            '/[\|\-–—:]+/u',
            ' ',
            $value
        ) ?? $value;

        $value = $this->normalizePhrase($value);

        return $value === '' ? null : $value;
    }

    private function prepareQueries(array $queries): array
    {
        $prepared = [];

        foreach ($queries as $query) {
            if (! is_string($query)) {
                continue;
            }

            $query = $this->normalizePhrase(
                $query
            );

            if ($query === '') {
                continue;
            }

            $key = mb_strtolower($query);

            if (isset($prepared[$key])) {
                continue;
            }

            $prepared[$key] = $query;

            /*
             * Enough variety without exploding Google API usage.
             */
            if (count($prepared) >= 8) {
                break;
            }
        }

        return array_values($prepared);
    }

    private function uniqueStrings(array $values): array
    {
        $result = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = $this->normalizePhrase(
                $value
            );

            if ($value === '') {
                continue;
            }

            $result[mb_strtolower($value)] = $value;
        }

        return array_values($result);
    }

    private function normalizePhrase(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        return trim($value);
    }

    private function extractHost(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = mb_strtolower($host);

        return Str::startsWith($host, 'www.')
            ? mb_substr($host, 4)
            : $host;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}