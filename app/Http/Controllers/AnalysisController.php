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
        $normalizedWebsite =
            $this->normalizeWebsiteInput(
                $request->input('website')
            );

        if ($normalizedWebsite !== '') {
            $request->merge([
                'website' => $normalizedWebsite,
            ]);
        }

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

            'edit_mode' => [
                'nullable',
                'in:website,google_business',
            ],
        ]);

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

        $editMode = $validated['edit_mode'] ?? null;

        $websiteScan = null;
        $websiteScanWarning = null;

        $existingWebsite = session('analysis.website');
        $existingWebsiteScan = session('analysis.website_scan');

        $canReuseWebsiteScan =
            $editMode !== null
            && is_string($existingWebsite)
            && $existingWebsite === $validated['website']
            && is_array($existingWebsiteScan);

        if ($canReuseWebsiteScan) {
            $websiteScan = $existingWebsiteScan;

            $existingWarning = session(
                'analysis.website_scan_warning'
            );

            $websiteScanWarning = is_string($existingWarning)
                && trim($existingWarning) !== ''
                    ? $existingWarning
                    : null;
        } else {
            try {
                $websiteScan = $websiteScanner->scan(
                    $validated['website']
                );
            } catch (RuntimeException $exception) {
                $httpStatus = $this->websiteHttpFailureStatus(
                    $exception
                );

                /*
                 * Legitimate websites can block automated homepage
                 * requests with 4xx/5xx responses. If the user already
                 * selected a real Google Business Profile, continue with
                 * Google business signals instead of killing onboarding.
                 *
                 * URL/security failures are still returned as errors.
                 */
                if (
                    $googlePlaceId === ''
                    || $httpStatus === null
                ) {
                    return back()
                        ->withErrors([
                            'website' => $exception->getMessage(),
                        ])
                        ->withInput();
                }

                $websiteScan = $this->unavailableWebsiteScan(
                    $validated['website'],
                    $httpStatus
                );

                $websiteScanWarning =
                    'We couldnвЂ™t read this website directly, so these matches are based mainly on the Google Business Profile.';
            }
        }

        $googlePlace = null;
        $analysisResult = null;

        /*
         * Never guess which Google Business belongs to the user.
         * Full analysis starts only after an explicit Autocomplete
         * selection gives us a stable Place ID.
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

            $existingGooglePlaceId = session(
                'analysis.google_place_id'
            );

            $existingGoogleBusiness = session(
                'analysis.google_business'
            );

            $existingGooglePlace = session(
                'analysis.google_place'
            );

            $canReuseGooglePlace =
                $editMode !== null
                && is_string($existingGooglePlaceId)
                && $existingGooglePlaceId === $googlePlaceId
                && is_string($existingGoogleBusiness)
                && $existingGoogleBusiness
                    === $validated['google_business']
                && is_array($existingGooglePlace);

            try {
                $googlePlace = $canReuseGooglePlace
                    ? $existingGooglePlace
                    : $googlePlaces->getPlaceDetails(
                        $googlePlaceId,
                        $googlePlacesSessionToken,
                        true
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

            'analysis.website_scan_warning'
                => $websiteScanWarning,

            'analysis.google_place'
                => $googlePlace,

            'analysis.result'
                => $analysisResult,

            /*
             * Keep the user's editable competitor set separate from the
             * ranked analysis result. Manual add/remove actions update this
             * list without mutating the original ranking payload.
             */
            'analysis.selected_competitors'
                => is_array($analysisResult)
                    ? array_values(
                        array_filter(
                            data_get(
                                $analysisResult,
                                'top_competitors',
                                []
                            ),
                            'is_array'
                        )
                    )
                    : [],
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

        $websiteScanWarning = session(
            'analysis.website_scan_warning'
        );

        $selectedCompetitors = session(
            'analysis.selected_competitors'
        );

        if (is_array($selectedCompetitors)) {
            $topCompetitors = array_values(
                array_filter(
                    $selectedCompetitors,
                    'is_array'
                )
            );
        } else {
            /*
             * Backwards compatibility for sessions created before competitor
             * customization was introduced.
             */
            $topCompetitors = is_array($analysisResult)
                ? data_get(
                    $analysisResult,
                    'top_competitors',
                    []
                )
                : [];
        }

        return view(
            'competitors',
            [
                'website'
                    => $website,

                'googleBusiness'
                    => $googleBusiness,

                'websiteScan'
                    => $websiteScan,

                'websiteScanWarning'
                    => is_string($websiteScanWarning)
                        && trim($websiteScanWarning) !== ''
                            ? $websiteScanWarning
                            : null,

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

    private function normalizeWebsiteInput(
        mixed $value
    ): string {
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (
            preg_match(
                '#^https?://#i',
                $value
            ) !== 1
        ) {
            $value = 'https://' . $value;
        }

        return $value;
    }

    private function websiteHttpFailureStatus(
        RuntimeException $exception
    ): ?int {
        if (
            preg_match(
                '/^Website returned HTTP status (\d{3})\.$/',
                $exception->getMessage(),
                $matches
            ) !== 1
        ) {
            return null;
        }

        $status = (int) ($matches[1] ?? 0);

        return $status >= 400 && $status <= 599
            ? $status
            : null;
    }

    private function unavailableWebsiteScan(
        string $url,
        int $status
    ): array {
        return [
            'final_url' => $url,
            'status' => $status,
            'title' => null,
            'meta_description' => null,
            'h1' => [],
            'h2' => [],
            'text' => '',
            'available' => false,
        ];
    }
}
