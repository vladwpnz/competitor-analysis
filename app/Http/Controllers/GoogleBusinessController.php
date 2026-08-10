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
}