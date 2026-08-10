<?php

namespace App\Http\Controllers;

use App\Services\CompetitorAnalysisService;
use App\Services\GooglePlacesService;
use App\Services\WebsiteScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AnalysisController extends Controller
{
    public function home(): View
    {
        return view('home');
    }

    public function start(
        Request $request,
        WebsiteScanner $websiteScanner,
        GooglePlacesService $googlePlaces,
        CompetitorAnalysisService $competitorAnalysis
    ): RedirectResponse {
        $validated = $request->validate([
            'website' => [
                'required',
                'url',
                'max:2048',
            ],

            'google_business' => [
                'required',
                'string',
                'max:255',
            ],

            'google_place_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'google_places_session_token' => [
                'required_with:google_place_id',
                'nullable',
                'uuid',
            ],
        ]);

        try {
            $websiteScan = $websiteScanner->scan(
                $validated['website']
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withErrors([
                    'website' => $exception->getMessage(),
                ])
                ->withInput();
        }

        $googlePlace = null;
        $analysisResult = null;

        $googlePlaceId = trim(
            (string) (
                $validated['google_place_id']
                ?? ''
            )
        );

        $googlePlacesSessionToken = trim(
            (string) (
                $validated['google_places_session_token']
                ?? ''
            )
        );

        /*
         * Never guess which Google Business belongs
         * to the user.
         *
         * The full analysis starts only after the user
         * explicitly selects an Autocomplete prediction
         * and we receive its stable Place ID.
         */
        if ($googlePlaceId !== '') {
            if (! $googlePlaces->isConfigured()) {
                return back()
                    ->withErrors([
                        'google_business'
                            => 'Google Business lookup is not configured yet.',
                    ])
                    ->withInput();
            }

            try {
                $googlePlace = $googlePlaces->getPlaceDetails(
                    $googlePlaceId,
                    $googlePlacesSessionToken
                );

                $analysisResult = $competitorAnalysis->analyze(
                    $websiteScan,
                    $googlePlace
                );
            } catch (RuntimeException $exception) {
                return back()
                    ->withErrors([
                        'google_business'
                            => $exception->getMessage(),
                    ])
                    ->withInput();
            }
        }

        session([
            'analysis.website'
                => $validated['website'],

            'analysis.google_business'
                => $validated['google_business'],

            'analysis.google_place_id'
                => $googlePlaceId !== ''
                    ? $googlePlaceId
                    : null,

            'analysis.google_places_session_token'
                => (
                    $googlePlaceId !== ''
                    && $googlePlacesSessionToken !== ''
                )
                    ? $googlePlacesSessionToken
                    : null,

            'analysis.website_scan'
                => $websiteScan,

            'analysis.google_place'
                => $googlePlace,

            'analysis.result'
                => $analysisResult,
        ]);

        return redirect()
            ->route('competitors');
    }

    public function competitors(): View|RedirectResponse
    {
        $website = session(
            'analysis.website'
        );

        $googleBusiness = session(
            'analysis.google_business'
        );

        $websiteScan = session(
            'analysis.website_scan'
        );

        if (
            ! is_string($website)
            || $website === ''
            || ! is_string($googleBusiness)
            || $googleBusiness === ''
            || ! is_array($websiteScan)
        ) {
            return redirect()
                ->route('home');
        }

        $googlePlace = session(
            'analysis.google_place'
        );

        $analysisResult = session(
            'analysis.result'
        );

        $topCompetitors = is_array($analysisResult)
            ? data_get(
                $analysisResult,
                'top_competitors',
                []
            )
            : [];

        return view(
            'competitors',
            [
                'website'
                    => $website,

                'googleBusiness'
                    => $googleBusiness,

                'websiteScan'
                    => $websiteScan,

                'googlePlace'
                    => is_array($googlePlace)
                        ? $googlePlace
                        : null,

                'analysisResult'
                    => is_array($analysisResult)
                        ? $analysisResult
                        : null,

                'topCompetitors'
                    => is_array($topCompetitors)
                        ? $topCompetitors
                        : [],
            ]
        );
    }
}