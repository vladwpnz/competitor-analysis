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
                    'We could not read this website directly, so these matches are based mainly on the Google Business Profile.';
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

                if (
                    $this->googlePlaceIsAddressOnly(
                        $googlePlace
                    )
                ) {
                    return back()
                        ->withErrors([
                            'google_business'
                                => 'Please select your actual Google Business Profile, not a street address or map location.',
                        ])
                        ->withInput();
                }

                if (
                    ! $this->googleBusinessMatchesWebsite(
                        $validated['website'],
                        $websiteScan,
                        $googlePlace
                    )
                ) {
                    return back()
                        ->withErrors([
                            'google_business'
                                => 'The selected Google Business Profile appears to belong to a different website. Please choose the profile that matches your business.',
                        ])
                        ->withInput();
                }

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

            'analysis.selected_competitors'
                => is_array($analysisResult)
                    && is_array(
                        data_get(
                            $analysisResult,
                            'top_competitors',
                            []
                        )
                    )
                        ? array_values(
                            data_get(
                                $analysisResult,
                                'top_competitors',
                                []
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

    private function googlePlaceIsAddressOnly(
        array $googlePlace
    ): bool {
        if (
            data_get(
                $googlePlace,
                'pureServiceAreaBusiness'
            ) === true
        ) {
            return false;
        }

        $types = data_get(
            $googlePlace,
            'types',
            []
        );

        if (! is_array($types)) {
            $types = [];
        }

        $primaryType = data_get(
            $googlePlace,
            'primaryType'
        );

        if (
            is_string($primaryType)
            && trim($primaryType) !== ''
        ) {
            $types[] = $primaryType;
        }

        if ($types === []) {
            /*
             * Missing type information must not block a potentially valid
             * business. We reject only places Google explicitly identifies
             * purely as geographic/address entities.
             */
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

    private function googleBusinessMatchesWebsite(
        string $website,
        array $websiteScan,
        array $googlePlace
    ): bool {
        $googleWebsite = data_get(
            $googlePlace,
            'websiteUri'
        );

        if (
            ! is_string($googleWebsite)
            || trim($googleWebsite) === ''
        ) {
            return true;
        }

        $websiteHost = $this->normalizedHost(
            $website
        );

        $googleHost = $this->normalizedHost(
            $googleWebsite
        );

        if (
            $websiteHost === null
            || $googleHost === null
        ) {
            return true;
        }

        if (
            $websiteHost === $googleHost
            || str_ends_with(
                $websiteHost,
                '.' . $googleHost
            )
            || str_ends_with(
                $googleHost,
                '.' . $websiteHost
            )
        ) {
            return true;
        }

        return $this->looksLikeRelatedBrandDomain(
            $websiteHost,
            $googleHost,
            $websiteScan,
            $googlePlace
        );
    }

    private function looksLikeRelatedBrandDomain(
        string $websiteHost,
        string $googleHost,
        array $websiteScan,
        array $googlePlace
    ): bool {
        $websiteBrand = $this->domainBrandLabel(
            $websiteHost
        );

        $googleBrand = $this->domainBrandLabel(
            $googleHost
        );

        if (
            $websiteBrand === null
            || $googleBrand === null
        ) {
            return false;
        }

        $websiteBrandKey = $this->brandKey(
            $websiteBrand
        );

        $googleBrandKey = $this->brandKey(
            $googleBrand
        );

        if (
            $websiteBrandKey === ''
            || $googleBrandKey === ''
        ) {
            return false;
        }

        $googleName = trim(
            (string) data_get(
                $googlePlace,
                'displayName.text',
                ''
            )
        );

        if ($googleName === '') {
            return false;
        }

        $googleNameCompact = $this->brandKey(
            $googleName
        );

        if (
            ! str_contains(
                $googleNameCompact,
                $websiteBrandKey
            )
        ) {
            return false;
        }

        /*
         * Some large multi-location companies use a separate branded
         * locator domain for branch Google profiles. A corporate site such
         * as the short parent brand can therefore legitimately differ from
         * the branch website host. Keep this fallback narrow: the domain
         * brands must still be clearly related and the branch name must
         * agree with the identity visible on the corporate website.
         */
        if ($websiteBrandKey === $googleBrandKey) {
            return true;
        }

        if (
            ! str_starts_with(
                $googleBrandKey,
                $websiteBrandKey
            )
            && ! str_starts_with(
                $websiteBrandKey,
                $googleBrandKey
            )
        ) {
            return false;
        }

        /*
         * Large brands can expose branch Google profiles through a separate
         * branded locator host even when the corporate homepage is blocked
         * or cannot provide enough identity text. Example shape:
         * corporate-brand.com -> maps.corporatebrandservice.com.
         *
         * Keep this exception narrow: it only applies to known locator
         * subdomains and only when the locator domain brand exactly matches
         * the compact Google Business display name. This avoids turning a
         * simple shared prefix into a blanket match.
         */
        if (
            $this->isBranchLocatorHost($googleHost)
            && $googleNameCompact === $googleBrandKey
        ) {
            return true;
        }

        $websiteIdentityTokens =
            $this->websiteIdentityTokens(
                $websiteScan
            );

        $googleNameTokens =
            $this->identityTokens(
                $googleName
            );

        $brandTokens =
            $this->identityTokens(
                $websiteBrand
            );

        $googleDescriptorTokens = array_values(
            array_diff(
                $googleNameTokens,
                $brandTokens
            )
        );

        if ($googleDescriptorTokens === []) {
            return false;
        }

        return array_intersect(
            $googleDescriptorTokens,
            $websiteIdentityTokens
        ) !== [];
    }

    private function isBranchLocatorHost(
        string $host
    ): bool {
        $labels = explode('.', $host);

        $firstLabel = mb_strtolower(
            trim((string) ($labels[0] ?? ''))
        );

        return in_array(
            $firstLabel,
            [
                'branch',
                'branches',
                'locations',
                'locator',
                'maps',
            ],
            true
        );
    }

    private function domainBrandLabel(
        string $host
    ): ?string {
        $labels = array_values(
            array_filter(
                explode('.', $host),
                static fn (string $label): bool =>
                    trim($label) !== ''
            )
        );

        if ($labels === []) {
            return null;
        }

        $count = count($labels);

        if ($count === 1) {
            return $labels[0];
        }

        $index = $count - 2;

        if (
            $count >= 3
            && strlen($labels[$count - 1]) === 2
            && in_array(
                $labels[$count - 2],
                [
                    'ac',
                    'co',
                    'com',
                    'gov',
                    'net',
                    'org',
                ],
                true
            )
        ) {
            $index = $count - 3;
        }

        $label = trim(
            $labels[$index] ?? ''
        );

        return $label === ''
            ? null
            : $label;
    }

    private function websiteIdentityTokens(
        array $websiteScan
    ): array {
        $parts = [];

        foreach (
            [
                'title',
                'meta_description',
                'text',
            ] as $key
        ) {
            $value = data_get(
                $websiteScan,
                $key
            );

            if (
                is_string($value)
                && trim($value) !== ''
            ) {
                $parts[] = mb_substr(
                    trim($value),
                    0,
                    $key === 'text'
                        ? 3000
                        : 500
                );
            }
        }

        foreach (['h1', 'h2'] as $key) {
            $headings = data_get(
                $websiteScan,
                $key,
                []
            );

            if (! is_array($headings)) {
                continue;
            }

            foreach ($headings as $heading) {
                if (
                    is_string($heading)
                    && trim($heading) !== ''
                ) {
                    $parts[] = trim($heading);
                }
            }
        }

        return $this->identityTokens(
            implode(' ', $parts)
        );
    }

    private function identityTokens(
        string $value
    ): array {
        $value = mb_strtolower(
            $value
        );

        $value = preg_replace(
            '/[^a-z0-9]+/u',
            ' ',
            $value
        ) ?? $value;

        $stopWords = [
            'about',
            'and',
            'canada',
            'company',
            'corp',
            'corporation',
            'for',
            'group',
            'inc',
            'limited',
            'location',
            'locations',
            'ltd',
            'of',
            'the',
            'with',
        ];

        $tokens = preg_split(
            '/\s+/',
            trim($value)
        ) ?: [];

        $normalized = [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if (
                strlen($token) < 3
                || in_array(
                    $token,
                    $stopWords,
                    true
                )
            ) {
                continue;
            }

            if (
                strlen($token) > 4
                && str_ends_with($token, 's')
            ) {
                $token = substr(
                    $token,
                    0,
                    -1
                );
            }

            $normalized[$token] = true;
        }

        return array_keys(
            $normalized
        );
    }

    private function brandKey(
        string $value
    ): string {
        return preg_replace(
            '/[^a-z0-9]+/',
            '',
            mb_strtolower($value)
        ) ?? '';
    }

    private function normalizedHost(
        string $url
    ): ?string {
        $host = parse_url(
            $url,
            PHP_URL_HOST
        );

        if (
            ! is_string($host)
            || trim($host) === ''
        ) {
            return null;
        }

        $host = mb_strtolower(
            trim($host)
        );

        if (str_starts_with($host, 'www.')) {
            $host = mb_substr(
                $host,
                4
            );
        }

        return $host === ''
            ? null
            : $host;
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
