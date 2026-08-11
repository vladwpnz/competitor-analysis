<?php

namespace App\Services;

use Illuminate\Support\Str;

class SearchProfileBuilder
{
    private const GENERIC_HEADINGS = [
        'home',
        'homepage',
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
        'platform',
        'products',
        'solutions',
        'resources',
        'marketing',
        'sales',
        'customer service',
        'content',
        'the customer platform',
        'startups & small businesses',
    ];

    private const GENERIC_GOOGLE_TYPES = [
        'point of interest',
        'service',
        'establishment',
        'corporate office',
        'organization',
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

        $vertical = $this->stringOrNull(
            data_get(
                $classification,
                'vertical'
            )
        );

        $marketScope = (string) data_get(
            $classification,
            'market_scope',
            'hybrid'
        );

        $businessType = $this->determineBusinessType(
            $primaryTypeName,
            $primaryType,
            $websiteTitle,
            $businessName,
            $vertical
        );

        $queryCandidates = [];

        if ($vertical === 'technology') {
            /*
             * For broad technology companies, specific product/category
             * intent is more valuable than a generic "Software Company"
             * query. CompetitorSearchService executes only the first four
             * queries, so put CRM/platform/automation intent first and keep
             * the generic business type as a fallback at the end.
             */
            foreach (
                $this->technologyQueries($services)
                as $query
            ) {
                $queryCandidates[] = $query;
            }

            if ($businessType !== null) {
                $queryCandidates[] = $businessType;
            }
        } else {
            if ($businessType !== null) {
                $queryCandidates[] = $businessType;
            }

            foreach ($services as $service) {
                $queryCandidates[] = $service;

                if (
                    $businessType !== null
                    && $marketScope !== 'broader'
                ) {
                    $queryCandidates[] =
                        $service . ' ' . $businessType;
                }
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

        if (
            $queryCandidates === []
            && $websiteTitle !== null
        ) {
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

            'vertical' => $vertical,

            'market_scope' => $marketScope,

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
        ?string $businessName,
        ?string $vertical
    ): ?string {
        if ($primaryTypeName !== null) {
            $normalized = $this->normalizePhrase(
                $primaryTypeName
            );

            if (! $this->isGenericGoogleType($normalized)) {
                return $normalized;
            }
        }

        if ($primaryType !== null) {
            $normalized = $this->normalizePhrase(
                str_replace('_', ' ', $primaryType)
            );

            if (! $this->isGenericGoogleType($normalized)) {
                return $normalized;
            }
        }

        /*
         * Google often exposes broad B2B companies only as
         * "service", "establishment" or "point_of_interest".
         * When the website signals clearly identify technology,
         * use a useful canonical Maps query instead of those
         * generic Google types or the full marketing page title.
         */
        if ($vertical === 'technology') {
            return 'Software Company';
        }

        if ($websiteTitle !== null) {
            $title = $this->removeBusinessName(
                $websiteTitle,
                $businessName
            );

            if ($title === null) {
                return null;
            }

            $title = preg_replace(
                '/\b(homepage|home\s+page)\b/ui',
                ' ',
                $title
            ) ?? $title;

            $title = $this->normalizePhrase(
                $title
            );

            return $title === ''
                ? null
                : $title;
        }

        return null;
    }

    private function technologyQueries(
        array $services
    ): array {
        $queries = [];
        $normalizedServices = array_map(
            fn (string $service) =>
                mb_strtolower($service),
            $services
        );

        $hasAny = static function (
            array $needles
        ) use ($normalizedServices): bool {
            foreach ($needles as $needle) {
                if (
                    in_array(
                        mb_strtolower($needle),
                        $normalizedServices,
                        true
                    )
                ) {
                    return true;
                }
            }

            return false;
        };

        if (
            $hasAny([
                'crm',
                'customer relationship management',
            ])
        ) {
            $queries[] = 'CRM Software Company';
        }

        if ($hasAny(['customer platform'])) {
            $queries[] = 'Customer Platform Software Company';
        }

        if (
            $hasAny([
                'marketing automation',
                'marketing software',
            ])
        ) {
            $queries[] = 'Marketing Automation Software Company';
        }

        if (
            $hasAny([
                'sales software',
                'sales platform',
            ])
        ) {
            $queries[] = 'Sales CRM Software Company';
        }

        if (
            $hasAny([
                'customer service software',
                'customer support software',
            ])
        ) {
            $queries[] = 'Customer Service Software Company';
        }

        if (
            $hasAny([
                'revenue platform',
                'go to market',
            ])
        ) {
            $queries[] = 'Go To Market Software Company';
        }

        if ($hasAny(['saas'])) {
            $queries[] = 'SaaS Company';
        }

        if ($hasAny(['cybersecurity'])) {
            $queries[] = 'Cybersecurity Software Company';
        }

        if ($hasAny(['cloud services'])) {
            $queries[] = 'Cloud Software Company';
        }

        if ($hasAny(['software development'])) {
            $queries[] = 'Software Development Company';
        }

        if ($hasAny(['web development'])) {
            $queries[] = 'Web Development Company';
        }

        if ($hasAny(['app development'])) {
            $queries[] = 'App Development Company';
        }

        return $queries;
    }

    private function isGenericGoogleType(
        string $value
    ): bool {
        return in_array(
            mb_strtolower($value),
            self::GENERIC_GOOGLE_TYPES,
            true
        );
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

        /*
         * Single broad words are usually navigation labels rather
         * than competitor-defining queries.
         */
        if (
            ! str_contains($heading, ' ')
            && mb_strlen($heading) < 12
        ) {
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