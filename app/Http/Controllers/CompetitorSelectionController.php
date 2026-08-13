<?php

namespace App\Http\Controllers;

use App\Services\GeminiCompetitorDiscoveryService;
use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CompetitorSelectionController extends Controller
{
    private const MAX_SELECTED_COMPETITORS = 20;

    public function search(
        Request $request,
        GooglePlacesService $googlePlaces,
        GeminiCompetitorDiscoveryService $digitalDiscovery
    ): JsonResponse {
        if (! $this->hasAnalysisSession()) {
            return response()->json([
                'message'
                    => 'Start an analysis before customizing competitors.',
                'suggestions'
                    => [],
            ], 409);
        }

        $validated = $request->validate([
            'q' => [
                'required',
                'string',
                'min:3',
                'max:120',
            ],
        ]);

        $query = trim(
            $validated['q']
        );

        if ($this->usesAiWebsiteDiscovery()) {
            return $this->searchDigitalCompetitors(
                $query,
                $digitalDiscovery
            );
        }

        session()->forget(
            'analysis.manual_competitor_candidates'
        );

        if (! $googlePlaces->isConfigured()) {
            return response()->json([
                'message'
                    => 'Google Business search is not configured yet.',
                'suggestions'
                    => [],
            ], 503);
        }

        try {
            /*
             * Text Search is intentional here instead of Autocomplete.
             * The user may search by a business name OR a website/domain,
             * and this manual competitor selection is not part of the
             * subject-business Autocomplete billing/session flow.
             */
            $places = $googlePlaces->searchBusinesses(
                $query,
                6
            );
        } catch (RuntimeException) {
            return response()->json([
                'message'
                    => 'Competitor search is temporarily unavailable.',
                'suggestions'
                    => [],
            ], 502);
        }

        $ownPlaceId = trim(
            (string) session(
                'analysis.google_place_id',
                ''
            )
        );

        $existingPlaceIds = [];

        foreach ($this->selectedCompetitors() as $competitor) {
            $placeId = data_get(
                $competitor,
                'id'
            );

            if (
                is_string($placeId)
                && trim($placeId) !== ''
            ) {
                $existingPlaceIds[
                    trim($placeId)
                ] = true;
            }
        }

        $suggestions = [];

        foreach ($places as $place) {
            if (! is_array($place)) {
                continue;
            }

            $placeId = data_get(
                $place,
                'id'
            );

            if (
                ! is_string($placeId)
                || trim($placeId) === ''
            ) {
                continue;
            }

            $placeId = trim($placeId);

            if (
                $placeId === $ownPlaceId
                || isset(
                    $existingPlaceIds[$placeId]
                )
            ) {
                continue;
            }

            $name = data_get(
                $place,
                'displayName.text'
            );

            if (
                ! is_string($name)
                || trim($name) === ''
            ) {
                continue;
            }

            $category = data_get(
                $place,
                'primaryTypeDisplayName.text'
            );

            if (
                ! is_string($category)
                || trim($category) === ''
            ) {
                $primaryType = data_get(
                    $place,
                    'primaryType'
                );

                $category = is_string($primaryType)
                    && trim($primaryType) !== ''
                        ? Str::headline(
                            trim($primaryType)
                        )
                        : 'Business';
            }

            $suggestions[] = [
                'place_id' => $placeId,
                'name' => trim($name),
                'secondary_text' => trim(
                    (string) data_get(
                        $place,
                        'formattedAddress',
                        ''
                    )
                ),
                'category' => trim($category),
            ];
        }

        return response()->json([
            'suggestions' => array_values(
                $suggestions
            ),
        ]);
    }

    public function add(
        Request $request,
        GooglePlacesService $googlePlaces
    ): JsonResponse {
        if (! $this->hasAnalysisSession()) {
            return response()->json([
                'message'
                    => 'Start an analysis before customizing competitors.',
            ], 409);
        }

        $validated = $request->validate([
            'place_id' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $placeId = trim(
            $validated['place_id']
        );

        $ownPlaceId = trim(
            (string) session(
                'analysis.google_place_id',
                ''
            )
        );

        if (
            $ownPlaceId !== ''
            && hash_equals(
                $ownPlaceId,
                $placeId
            )
        ) {
            return response()->json([
                'message'
                    => 'Your own business cannot be added as a competitor.',
            ], 422);
        }

        $selected = $this->selectedCompetitors();

        foreach ($selected as $competitor) {
            if (
                data_get(
                    $competitor,
                    'id'
                ) === $placeId
            ) {
                return response()->json([
                    'message'
                        => 'This competitor is already in your list.',
                ], 409);
            }
        }

        if (
            count($selected)
            >= self::MAX_SELECTED_COMPETITORS
        ) {
            return response()->json([
                'message'
                    => 'You can select up to 20 competitors.',
            ], 422);
        }

        if (
            $this->usesAiWebsiteDiscovery()
            && str_starts_with(
                $placeId,
                'ai-'
            )
        ) {
            return $this->addDigitalCompetitor(
                $placeId,
                $selected
            );
        }

        if (! $googlePlaces->isConfigured()) {
            return response()->json([
                'message'
                    => 'Google Business lookup is not configured yet.',
            ], 503);
        }

        try {
            /*
             * Deliberately use the normal details mask here.
             * editorialSummary is only requested for the subject business,
             * never for manually added competitors.
             */
            $competitor = $googlePlaces
                ->getPlaceDetails(
                    $placeId
                );
        } catch (RuntimeException) {
            return response()->json([
                'message'
                    => 'Could not load that competitor right now.',
            ], 502);
        }

        if (
            ! is_array($competitor)
            || data_get(
                $competitor,
                'id'
            ) !== $placeId
        ) {
            return response()->json([
                'message'
                    => 'Google returned an invalid competitor record.',
            ], 502);
        }

        $competitor['_manual_selection']
            = true;

        $competitor['_match'] = [
            'queries' => [],
            'executed_queries' => [],
            'query_hits' => 0,
            'search_modes' => [
                'manual',
            ],
            'distance_km'
                => $this->distanceFromSubject(
                    $competitor
                ),
        ];

        $selected[] = $competitor;

        session([
            'analysis.selected_competitors'
                => array_values(
                    $selected
                ),
        ]);

        return response()->json([
            'message'
                => 'Competitor added.',
            'competitor'
                => $this->frontendCompetitor(
                    $competitor
                ),
            'count'
                => count($selected),
        ]);
    }

    private function searchDigitalCompetitors(
        string $query,
        GeminiCompetitorDiscoveryService $digitalDiscovery
    ): JsonResponse {
        $analysisResult = session(
            'analysis.result'
        );

        $businessProfile = is_array($analysisResult)
            ? data_get(
                $analysisResult,
                'business_profile'
            )
            : null;

        $classification = is_array($analysisResult)
            ? data_get(
                $analysisResult,
                'classification'
            )
            : null;

        if (
            ! is_array($businessProfile)
            || ! is_array($classification)
        ) {
            return response()->json([
                'message'
                    => 'AI competitor search context is unavailable. Start the analysis again.',
                'suggestions'
                    => [],
            ], 409);
        }

        /*
         * AI website discovery already asks Gemini for up to eight direct
         * competitors but shows only five. Search that cached pool first
         * so common manual additions do not require another API call or
         * fail because a project-level Gemini rate limit was reached.
         */
        $cachedMatches = $this->cachedDigitalCompetitorMatches(
            is_array($analysisResult)
                ? data_get(
                    $analysisResult,
                    'digital_candidate_pool',
                    []
                )
                : [],
            $query
        );

        if ($cachedMatches !== []) {
            return $this->digitalSearchResponse(
                $cachedMatches
            );
        }

        if (! $digitalDiscovery->isConfigured()) {
            return response()->json([
                'message'
                    => 'No matching competitor was found in the current shortlist.',
                'suggestions'
                    => [],
            ]);
        }

        try {
            $matches = $digitalDiscovery->search(
                $businessProfile,
                $classification,
                $query
            );
        } catch (RuntimeException $exception) {
            $status = str_contains(
                $exception->getMessage(),
                'HTTP 429'
            )
                ? 429
                : 502;

            return response()->json([
                'message'
                    => $status === 429
                        ? 'AI search limit reached. Try again shortly.'
                        : 'AI competitor search is temporarily unavailable.',
                'suggestions'
                    => [],
            ], $status);
        } catch (Throwable) {
            return response()->json([
                'message'
                    => 'AI competitor search is temporarily unavailable.',
                'suggestions'
                    => [],
            ], 502);
        }

        return $this->digitalSearchResponse(
            $matches
        );
    }

    private function cachedDigitalCompetitorMatches(
        mixed $candidatePool,
        string $query
    ): array {
        if (! is_array($candidatePool)) {
            return [];
        }

        $needle = $this->normalizeDigitalSearchText(
            $query
        );

        if ($needle === '') {
            return [];
        }

        $matches = [];

        foreach ($candidatePool as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $name = $this->normalizeDigitalSearchText(
                (string) data_get(
                    $candidate,
                    'displayName.text',
                    ''
                )
            );

            $domain = $this->normalizeDigitalSearchText(
                (string) data_get(
                    $candidate,
                    '_discovery.domain',
                    ''
                )
            );

            if (
                ($name !== '' && str_contains($name, $needle))
                || ($domain !== '' && str_contains($domain, $needle))
                || ($name !== '' && str_contains($needle, $name))
                || ($domain !== '' && str_contains($needle, $domain))
            ) {
                $candidate['_manual_selection'] = true;
                data_set(
                    $candidate,
                    '_discovery.source',
                    'ai_manual'
                );
                data_set(
                    $candidate,
                    '_match.search_modes',
                    [
                        'ai_direct_cached_manual',
                    ]
                );

                $matches[] = $candidate;
            }
        }

        return $matches;
    }

    private function normalizeDigitalSearchText(
        string $value
    ): string {
        $value = mb_strtolower(
            trim($value)
        );

        $value = preg_replace(
            '#^https?://#',
            '',
            $value
        ) ?? $value;

        $value = preg_replace(
            '#^www\\.#',
            '',
            $value
        ) ?? $value;

        return trim(
            $value,
            " /\\t\\n\\r\\0\\x0B"
        );
    }

    private function digitalSearchResponse(
        array $matches
    ): JsonResponse {
        $existingIds = [];

        foreach ($this->selectedCompetitors() as $competitor) {
            $id = data_get(
                $competitor,
                'id'
            );

            if (
                is_string($id)
                && trim($id) !== ''
            ) {
                $existingIds[
                    trim($id)
                ] = true;
            }
        }

        $suggestions = [];
        $candidateCache = [];

        foreach ($matches as $match) {
            if (! is_array($match)) {
                continue;
            }

            if (
                isset($match['displayName'])
                && isset($match['id'])
            ) {
                $competitor = $match;
                $competitor['_manual_selection'] = true;
            } else {
                $competitor = $this->digitalCompetitorRecord(
                    $match
                );
            }

            if ($competitor === null) {
                continue;
            }

            $id = (string) data_get(
                $competitor,
                'id',
                ''
            );

            if (
                $id === ''
                || isset($existingIds[$id])
            ) {
                continue;
            }

            $candidateCache[$id] = $competitor;

            $suggestions[] = [
                'place_id' => $id,
                'name' => (string) data_get(
                    $competitor,
                    'displayName.text',
                    'Competitor'
                ),
                'secondary_text' => (string) data_get(
                    $competitor,
                    '_discovery.domain',
                    ''
                ),
                'category'
                    => 'Direct Competitor',
            ];
        }

        session([
            'analysis.manual_competitor_candidates'
                => $candidateCache,
        ]);

        return response()->json([
            'suggestions' => array_values(
                $suggestions
            ),
        ]);
    }

    private function addDigitalCompetitor(
        string $competitorId,
        array $selected
    ): JsonResponse {
        $candidateCache = session(
            'analysis.manual_competitor_candidates',
            []
        );

        $competitor = is_array($candidateCache)
            ? ($candidateCache[$competitorId] ?? null)
            : null;

        if (
            ! is_array($competitor)
            || data_get(
                $competitor,
                'id'
            ) !== $competitorId
        ) {
            return response()->json([
                'message'
                    => 'Search for that competitor again before adding it.',
            ], 422);
        }

        $selected[] = $competitor;

        session([
            'analysis.selected_competitors'
                => array_values(
                    $selected
                ),
        ]);

        unset(
            $candidateCache[$competitorId]
        );

        session([
            'analysis.manual_competitor_candidates'
                => $candidateCache,
        ]);

        return response()->json([
            'message'
                => 'Competitor added.',
            'competitor'
                => $this->frontendCompetitor(
                    $competitor
                ),
            'count'
                => count($selected),
        ]);
    }

    private function digitalCompetitorRecord(
        array $candidate
    ): ?array {
        $name = trim(
            (string) data_get(
                $candidate,
                'name',
                ''
            )
        );

        $domain = mb_strtolower(
            trim(
                (string) data_get(
                    $candidate,
                    'domain',
                    ''
                )
            )
        );

        $reason = trim(
            (string) data_get(
                $candidate,
                'reason',
                ''
            )
        );

        if (
            $name === ''
            || $domain === ''
            || $reason === ''
            || filter_var(
                $domain,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) === false
        ) {
            return null;
        }

        $id =
            'ai-'
            . substr(
                hash(
                    'sha256',
                    $domain
                ),
                0,
                24
            );

        return [
            'id' => $id,

            'displayName' => [
                'text' => $name,
            ],

            'primaryType'
                => 'digital_platform',

            'primaryTypeDisplayName' => [
                'text'
                    => 'Direct Competitor',
            ],

            'types' => [
                'digital_platform',
            ],

            'websiteUri'
                => 'https://' . $domain,

            '_manual_selection' => true,

            '_match' => [
                'queries' => [],
                'executed_queries' => [],
                'query_hits' => 0,
                'search_modes' => [
                    'ai_direct_manual',
                ],
                'distance_km' => null,
            ],

            '_relevance' => [
                'quality' => 'strong',
                'strong_match' => true,
                'type_compatible' => true,
                'evidence' => [
                    'discovery_source'
                        => 'ai_direct_manual',
                ],
            ],

            '_discovery' => [
                'source' => 'ai_manual',
                'domain' => $domain,
                'reason' => $reason,
            ],
        ];
    }

    private function usesAiWebsiteDiscovery(): bool
    {
        return data_get(
            session(
                'analysis.result'
            ),
            'discovery_source'
        ) === 'ai_direct';
    }

    public function remove(
        Request $request
    ): JsonResponse {
        if (! $this->hasAnalysisSession()) {
            return response()->json([
                'message'
                    => 'Start an analysis before customizing competitors.',
            ], 409);
        }

        $validated = $request->validate([
            'place_id' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $placeId = trim(
            $validated['place_id']
        );

        $selected = array_values(
            array_filter(
                $this->selectedCompetitors(),
                static function (
                    array $competitor
                ) use ($placeId): bool {
                    return data_get(
                        $competitor,
                        'id'
                    ) !== $placeId;
                }
            )
        );

        session([
            'analysis.selected_competitors'
                => $selected,
        ]);

        return response()->json([
            'message'
                => 'Competitor removed.',
            'count'
                => count($selected),
        ]);
    }

    public function email(): View|RedirectResponse
    {
        if (! $this->hasAnalysisSession()) {
            return redirect()
                ->route('home');
        }

        $selected = $this->selectedCompetitors();

        if ($selected === []) {
            return redirect()
                ->route('competitors')
                ->withErrors([
                    'competitors'
                        => 'Add at least one competitor before continuing.',
                ]);
        }

        return view(
            'analysis-email',
            [
                'website'
                    => (string) session(
                        'analysis.website',
                        ''
                    ),
                'googleBusiness'
                    => (string) session(
                        'analysis.google_business',
                        ''
                    ),
                'competitorCount'
                    => count($selected),
                'email'
                    => (string) session(
                        'analysis.contact_email',
                        ''
                    ),
            ]
        );
    }

    public function storeEmail(
        Request $request
    ): RedirectResponse {
        if (! $this->hasAnalysisSession()) {
            return redirect()
                ->route('home');
        }

        if ($this->selectedCompetitors() === []) {
            return redirect()
                ->route('competitors')
                ->withErrors([
                    'competitors'
                        => 'Add at least one competitor before continuing.',
                ]);
        }

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
            ],
        ]);

        session([
            'analysis.contact_email'
                => trim($validated['email']),
        ]);

        return redirect()
            ->route('analysis.email')
            ->with(
                'analysis_email_saved',
                true
            );
    }

    private function hasAnalysisSession(): bool
    {
        return
            is_array(
                session(
                    'analysis.result'
                )
            )
            && is_string(
                session(
                    'analysis.website'
                )
            )
            && trim(
                (string) session(
                    'analysis.website'
                )
            ) !== '';
    }

    private function selectedCompetitors(): array
    {
        $selected = session(
            'analysis.selected_competitors'
        );

        if (is_array($selected)) {
            return array_values(
                array_filter(
                    $selected,
                    'is_array'
                )
            );
        }

        $analysisResult = session(
            'analysis.result'
        );

        $fallback = is_array($analysisResult)
            ? data_get(
                $analysisResult,
                'top_competitors',
                []
            )
            : [];

        return is_array($fallback)
            ? array_values(
                array_filter(
                    $fallback,
                    'is_array'
                )
            )
            : [];
    }

    private function frontendCompetitor(
        array $competitor
    ): array {
        $name = trim(
            (string) data_get(
                $competitor,
                'displayName.text',
                'Competitor'
            )
        );

        if ($name === '') {
            $name = 'Competitor';
        }

        $category = data_get(
            $competitor,
            'primaryTypeDisplayName.text'
        );

        if (
            ! is_string($category)
            || trim($category) === ''
        ) {
            $primaryType = data_get(
                $competitor,
                'primaryType'
            );

            $category = is_string($primaryType)
                && trim($primaryType) !== ''
                    ? Str::headline(
                        trim($primaryType)
                    )
                    : 'Relevant business';
        }

        $websiteUri = data_get(
            $competitor,
            'websiteUri'
        );

        $faviconUrl = null;

        if (
            is_string($websiteUri)
            && filter_var(
                $websiteUri,
                FILTER_VALIDATE_URL
            )
        ) {
            $parts = parse_url(
                $websiteUri
            );

            if (
                is_array($parts)
                && isset(
                    $parts['scheme'],
                    $parts['host']
                )
            ) {
                $faviconUrl =
                    $parts['scheme']
                    . '://'
                    . $parts['host']
                    . '/favicon.ico';
            }
        }

        $initials = collect(
            preg_split(
                '/\s+/',
                $name
            ) ?: []
        )
            ->filter()
            ->take(2)
            ->map(
                static fn (string $word): string =>
                    mb_strtoupper(
                        mb_substr(
                            $word,
                            0,
                            1
                        )
                    )
            )
            ->implode('');

        if ($initials === '') {
            $initials = 'C';
        }

        $marketScope = data_get(
            session(
                'analysis.result'
            ),
            'search_profile.market_scope',
            'hybrid'
        );

        $distanceKm = data_get(
            $competitor,
            '_match.distance_km'
        );

        return [
            'place_id'
                => (string) data_get(
                    $competitor,
                    'id',
                    ''
                ),
            'name' => $name,
            'category'
                => trim($category),
            'rating'
                => is_numeric(
                    data_get(
                        $competitor,
                        'rating'
                    )
                )
                    ? (float) data_get(
                        $competitor,
                        'rating'
                    )
                    : null,
            'review_count'
                => is_numeric(
                    data_get(
                        $competitor,
                        'userRatingCount'
                    )
                )
                    ? (int) data_get(
                        $competitor,
                        'userRatingCount'
                    )
                    : null,
            'distance_miles'
                => $marketScope !== 'broader'
                    && is_numeric($distanceKm)
                        ? round(
                            (float) $distanceKm
                            * 0.621371,
                            1
                        )
                        : null,
            'show_distance'
                => $marketScope !== 'broader',
            'favicon_url'
                => $faviconUrl,
            'initials'
                => $initials,
        ];
    }

    private function distanceFromSubject(
        array $competitor
    ): ?float {
        $subject = session(
            'analysis.google_place'
        );

        if (! is_array($subject)) {
            return null;
        }

        $lat1 = data_get(
            $subject,
            'location.latitude'
        );

        $lon1 = data_get(
            $subject,
            'location.longitude'
        );

        $lat2 = data_get(
            $competitor,
            'location.latitude'
        );

        $lon2 = data_get(
            $competitor,
            'location.longitude'
        );

        if (
            ! is_numeric($lat1)
            || ! is_numeric($lon1)
            || ! is_numeric($lat2)
            || ! is_numeric($lon2)
        ) {
            return null;
        }

        $earthRadiusKm = 6371.0088;

        $lat1Rad = deg2rad(
            (float) $lat1
        );

        $lat2Rad = deg2rad(
            (float) $lat2
        );

        $deltaLat = deg2rad(
            (float) $lat2
            - (float) $lat1
        );

        $deltaLon = deg2rad(
            (float) $lon2
            - (float) $lon1
        );

        $a =
            sin($deltaLat / 2) ** 2
            + cos($lat1Rad)
            * cos($lat2Rad)
            * sin($deltaLon / 2) ** 2;

        $a = min(
            1.0,
            max(
                0.0,
                $a
            )
        );

        return
            $earthRadiusKm
            * 2
            * atan2(
                sqrt($a),
                sqrt(1 - $a)
            );
    }
}