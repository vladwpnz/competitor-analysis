<?php

namespace App\Services;

use RuntimeException;

class CompetitorEnrichmentService
{
    private const MAX_COMPETITORS = 5;

    public function __construct(
        private readonly GooglePlacesService $googlePlaces
    ) {
    }

    public function enrich(array $competitors): array
    {
        $competitors = array_slice(
            array_values($competitors),
            0,
            self::MAX_COMPETITORS
        );

        if (
            $competitors === []
            || ! $this->googlePlaces->isConfigured()
        ) {
            return $competitors;
        }

        $enriched = [];

        foreach ($competitors as $competitor) {
            if (! is_array($competitor)) {
                continue;
            }

            $placeId = data_get(
                $competitor,
                'id'
            );

            if (
                ! is_string($placeId)
                || trim($placeId) === ''
            ) {
                $enriched[] = $competitor;
                continue;
            }

            try {
                $details = $this->googlePlaces
                    ->getPlaceDetails(
                        trim($placeId)
                    );
            } catch (RuntimeException) {
                $enriched[] = $competitor;
                continue;
            }

            if (! is_array($details)) {
                $enriched[] = $competitor;
                continue;
            }

            $enriched[] = $this->mergeDetails(
                $competitor,
                $details
            );
        }

        return $enriched;
    }

    private function mergeDetails(
        array $competitor,
        array $details
    ): array {
        $fields = [
            'displayName',
            'formattedAddress',
            'primaryType',
            'primaryTypeDisplayName',
            'types',
            'location',
            'businessStatus',
            'pureServiceAreaBusiness',
            'googleMapsUri',
            'websiteUri',
            'rating',
            'userRatingCount',
        ];

        foreach ($fields as $field) {
            if (
                ! array_key_exists($field, $details)
                || $details[$field] === null
            ) {
                continue;
            }

            $competitor[$field] = $details[$field];
        }

        return $competitor;
    }
}
