<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WebsiteScanner
{
    private const MAX_REDIRECTS = 5;
    private const MAX_BODY_BYTES = 2_000_000;
    private const MAX_TEXT_LENGTH = 12_000;

    private readonly AnalysisDeadline $analysisDeadline;

    public function __construct(
        ?AnalysisDeadline $analysisDeadline = null
    ) {
        $this->analysisDeadline = $analysisDeadline
            ?? new AnalysisDeadline();
    }

    public function scan(string $url): array
    {
        $currentUrl = $this->normalizeUrl($url);

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            [$host, $port, $ip] = $this->validateAndResolve($currentUrl);

            try {
                $requestTimeout = $this->analysisDeadline
                    ->timeoutFor(
                        max(
                            0.5,
                            (float) config(
                                'analysis.website_timeout',
                                8
                            )
                        )
                    );

                $connectTimeout = min(
                    $requestTimeout,
                    max(
                        0.25,
                        (float) config(
                            'analysis.website_connect_timeout',
                            2
                        )
                    )
                );

                $response = Http::withHeaders([
                    'User-Agent' => 'AccountIntelligenceBot/1.0',
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
                ])
                    ->connectTimeout($connectTimeout)
                    ->timeout($requestTimeout)
                    ->withOptions([
                        'allow_redirects' => false,
                        'http_errors' => false,
                        'curl' => [
                            CURLOPT_RESOLVE => [
                                sprintf(
                                    '%s:%d:%s',
                                    $host,
                                    $port,
                                    str_contains($ip, ':') ? '['.$ip.']' : $ip
                                ),
                            ],
                            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                            CURLOPT_PROXY => '',
                        ],
                    ])
                    ->get($currentUrl);
            } catch (ConnectionException $exception) {
                throw new RuntimeException(
                    'Could not connect to the website.',
                    0,
                    $exception
                );
            }

            if ($response->redirect()) {
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new RuntimeException('Too many website redirects.');
                }

                $location = trim($response->header('Location'));

                if ($location === '') {
                    throw new RuntimeException(
                        'The website returned an invalid redirect.'
                    );
                }

                $currentUrl = $this->resolveRedirectUrl(
                    $currentUrl,
                    $location
                );

                continue;
            }

            return $this->parseResponse($currentUrl, $response);
        }

        throw new RuntimeException('Unable to scan the website.');
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('The website URL is invalid.');
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new RuntimeException('The website URL is invalid.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(
                'Only HTTP and HTTPS websites can be scanned.'
            );
        }

        if (empty($parts['host'])) {
            throw new RuntimeException('The website host is missing.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException(
                'Website URLs containing credentials are not allowed.'
            );
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;

        if (isset($parts['port']) && (int) $parts['port'] !== $defaultPort) {
            throw new RuntimeException(
                'Non-standard website ports are not allowed.'
            );
        }

        return (string) (new Uri($url))->withFragment('');
    }

    private function validateAndResolve(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            throw new RuntimeException('The website host is invalid.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'], '[]'));
        $port = $scheme === 'https' ? 443 : 80;

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.test')
        ) {
            throw new RuntimeException(
                'Local or internal websites cannot be scanned.'
            );
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! $this->isPublicIp($host)) {
                throw new RuntimeException(
                    'Private or reserved IP addresses are not allowed.'
                );
            }

            return [$host, $port, $host];
        }

        $addresses = [];

        $aRecords = @dns_get_record($host, DNS_A);

        if (is_array($aRecords)) {
            foreach ($aRecords as $record) {
                if (! empty($record['ip'])) {
                    $addresses[] = $record['ip'];
                }
            }
        }

        $aaaaRecords = @dns_get_record($host, DNS_AAAA);

        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                if (! empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if ($addresses === []) {
            $fallback = @gethostbynamel($host);

            if (is_array($fallback)) {
                $addresses = array_merge($addresses, $fallback);
            }
        }

        $addresses = array_values(array_unique($addresses));

        if ($addresses === []) {
            throw new RuntimeException(
                'The website domain could not be resolved.'
            );
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicIp($address)) {
                throw new RuntimeException(
                    'The website resolves to a private or reserved IP address.'
                );
            }
        }

        /*
         * Prefer IPv4 when both IPv4 and IPv6 are available.
         * IPv6 remains supported if the host has no A record.
         */
        usort(
            $addresses,
            fn (string $a, string $b): int =>
                (int) str_contains($a, ':') <=> (int) str_contains($b, ':')
        );

        return [$host, $port, $addresses[0]];
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function resolveRedirectUrl(
        string $currentUrl,
        string $location
    ): string {
        try {
            $resolved = UriResolver::resolve(
                new Uri($currentUrl),
                new Uri($location)
            );
        } catch (\Throwable) {
            throw new RuntimeException(
                'The website returned an invalid redirect URL.'
            );
        }

        return $this->normalizeUrl((string) $resolved);
    }

    private function parseResponse(
        string $finalUrl,
        Response $response
    ): array {
        if (! $response->successful()) {
            throw new RuntimeException(
                'Website returned HTTP status '.$response->status().'.'
            );
        }

        $contentLength = $response->header('Content-Length');

        if (
            $contentLength !== ''
            && ctype_digit($contentLength)
            && (int) $contentLength > self::MAX_BODY_BYTES
        ) {
            throw new RuntimeException(
                'The website homepage is too large to scan.'
            );
        }

        $contentType = strtolower($response->header('Content-Type'));

        if (
            $contentType !== ''
            && ! str_contains($contentType, 'text/html')
            && ! str_contains($contentType, 'application/xhtml+xml')
        ) {
            throw new RuntimeException(
                'The provided URL did not return an HTML webpage.'
            );
        }

        $html = $response->body();

        if ($html === '') {
            throw new RuntimeException('The website returned an empty page.');
        }

        if (strlen($html) > self::MAX_BODY_BYTES) {
            throw new RuntimeException(
                'The website homepage is too large to scan.'
            );
        }

        $previousLibxmlState = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument();

            $loaded = $dom->loadHTML(
                $html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            if (! $loaded) {
                throw new RuntimeException(
                    'The website HTML could not be parsed.'
                );
            }

            $xpath = new DOMXPath($dom);

            $title = $this->firstText($xpath, '//title');

            $metaDescription = $this->firstAttribute(
                $xpath,
                "//meta[
                    translate(
                        @name,
                        'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                        'abcdefghijklmnopqrstuvwxyz'
                    ) = 'description'
                ]",
                'content'
            );

            $h1 = $this->collectText($xpath, '//h1', 5);
            $h2 = $this->collectText($xpath, '//h2', 10);

            foreach (
                [
                    '//script',
                    '//style',
                    '//noscript',
                    '//svg',
                    '//template',
                ] as $query
            ) {
                $nodes = $xpath->query($query);

                if ($nodes === false) {
                    continue;
                }

                foreach (iterator_to_array($nodes) as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }

            $body = $xpath->query('//body')->item(0);

            $visibleText = $body?->textContent ?? '';

            $visibleText = preg_replace(
                '/\s+/u',
                ' ',
                $visibleText
            ) ?? '';

            $visibleText = trim($visibleText);

            $visibleText = Str::limit(
                $visibleText,
                self::MAX_TEXT_LENGTH,
                ''
            );

            return [
                'final_url' => $finalUrl,
                'status' => $response->status(),
                'title' => $title,
                'meta_description' => $metaDescription,
                'h1' => $h1,
                'h2' => $h2,
                'text' => $visibleText,
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlState);
        }
    }

    private function firstText(
        DOMXPath $xpath,
        string $query
    ): ?string {
        $node = $xpath->query($query)?->item(0);

        if (! $node) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', $node->textContent) ?? '';

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function firstAttribute(
        DOMXPath $xpath,
        string $query,
        string $attribute
    ): ?string {
        $node = $xpath->query($query)?->item(0);

        if (! $node || ! $node->attributes?->getNamedItem($attribute)) {
            return null;
        }

        $value = trim(
            $node->attributes->getNamedItem($attribute)->nodeValue ?? ''
        );

        return $value !== '' ? $value : null;
    }

    private function collectText(
        DOMXPath $xpath,
        string $query,
        int $limit
    ): array {
        $nodes = $xpath->query($query);

        if ($nodes === false) {
            return [];
        }

        $values = [];

        foreach ($nodes as $node) {
            $value = preg_replace(
                '/\s+/u',
                ' ',
                $node->textContent
            ) ?? '';

            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $values[] = $value;

            if (count($values) >= $limit) {
                break;
            }
        }

        return array_values(array_unique($values));
    }
}
