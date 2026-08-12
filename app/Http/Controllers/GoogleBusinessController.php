<?php

namespace App\Http\Controllers;

use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GoogleBusinessController extends Controller
{
    public function search(
        Request $request,
        GooglePlacesService $googlePlaces
    ): JsonResponse {
        $validated = $request->validate([
            'q' => [
                'required',
                'string',
                'min:3',
                'max:120',
            ],

            'session_token' => [
                'required',
                'uuid',
            ],
        ]);

        if (! $googlePlaces->isConfigured()) {
            return response()->json([
                'message'
                    => 'Google Business search is not configured yet.',
                'suggestions' => [],
            ], 503);
        }

        try {
            $predictions = $googlePlaces
                ->autocompleteBusinesses(
                    $validated['q'],
                    $validated['session_token']
                );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message'
                    => 'Google Business search is temporarily unavailable.',
                'suggestions' => [],
            ], 502);
        }

        $suggestions = [];

        foreach ($predictions as $prediction) {
            if (! is_array($prediction)) {
                continue;
            }

            $placeId = data_get(
                $prediction,
                'placePrediction.placeId'
            );

            if (
                ! is_string($placeId)
                || trim($placeId) === ''
            ) {
                continue;
            }

            $fullText = data_get(
                $prediction,
                'placePrediction.text.text'
            );

            $name = data_get(
                $prediction,
                'placePrediction.structuredFormat.mainText.text'
            );

            $secondaryText = data_get(
                $prediction,
                'placePrediction.structuredFormat.secondaryText.text'
            );

            $types = data_get(
                $prediction,
                'placePrediction.types',
                []
            );

            if (
                $this->typesAreAddressOnly(
                    $types
                )
            ) {
                continue;
            }

            $suggestions[] = [
                'place_id' => $placeId,

                'full_text' => is_string($fullText)
                    ? $fullText
                    : '',

                'name' => is_string($name)
                    ? $name
                    : (
                        is_string($fullText)
                            ? $fullText
                            : 'Google Business'
                    ),

                'secondary_text'
                    => is_string($secondaryText)
                        ? $secondaryText
                        : '',

                'types' => is_array($types)
                    ? $types
                    : [],
            ];
        }

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    private function typesAreAddressOnly(
        mixed $types
    ): bool {
        if (! is_array($types) || $types === []) {
            return false;
        }

        $normalizedTypes = [];

        foreach ($types as $type) {
            if (
                ! is_string($type)
                || trim($type) === ''
            ) {
                continue;
            }

            $normalizedTypes[] = mb_strtolower(
                trim($type)
            );
        }

        if ($normalizedTypes === []) {
            return false;
        }

        $addressOnlyTypes = [
            'street_address',
            'route',
            'intersection',
            'premise',
            'subpremise',
            'street_number',
            'floor',
            'room',
            'postal_code',
            'postal_code_prefix',
            'postal_code_suffix',
            'postal_town',
            'locality',
            'sublocality',
            'sublocality_level_1',
            'sublocality_level_2',
            'sublocality_level_3',
            'sublocality_level_4',
            'sublocality_level_5',
            'neighborhood',
            'administrative_area_level_1',
            'administrative_area_level_2',
            'administrative_area_level_3',
            'administrative_area_level_4',
            'administrative_area_level_5',
            'administrative_area_level_6',
            'administrative_area_level_7',
            'country',
            'geocode',
            'plus_code',
        ];

        foreach ($normalizedTypes as $type) {
            if (
                ! in_array(
                    $type,
                    $addressOnlyTypes,
                    true
                )
            ) {
                return false;
            }
        }

        return true;
    }
}