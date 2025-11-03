<?php

namespace App\Service;

use App\Enum\SpotifyGenre;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SpotifyService
{
    /**
     * @param HttpClientInterface $http
     * @param CacheInterface      $cache
     * @param string              $clientId
     * @param string              $clientSecret
     * @param string              $baseUrl
     */
    public function __construct(
        private HttpClientInterface $http,
        private CacheInterface $cache,
        private string $clientId,
        private string $clientSecret,
        private string $baseUrl,
    ) {
    }

    /**
     * @param string      $q
     * @param string      $type
     * @param int         $limit
     * @param string|null $market
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function search(string $q, string $type = 'track', int $limit = 10, ?string $market = null): array
    {
        $token = $this->token();
        $url   = $this->apiBase() . '/search';

        $query = [
            'q'      => $q,
            'type'   => $type,
            'limit'  => min(max($limit, 1), 50),
        ];
        if ($market !== null && $market !== '') {
            $query['market'] = $market;
        }

        $data = $this->http->request('GET', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'query'   => $query,
        ])->toArray(false);

        return $data[$type . 's']['items'] ?? [];
    }


    /**
     * @param array       $seedGenres
     * @param array       $seedArtists
     * @param array       $targets
     * @param int         $limit
     * @param string|null $market
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function recommendations(array $seedGenres = [], array $seedArtists = [], array $targets = [], int $limit = 20, ?string $market = null): array
    {
        $seedGenres  = array_values(array_unique(array_map('strval', $seedGenres)));
        $seedArtists = array_values(array_unique(array_map('strval', $seedArtists)));

        $keepGenres  = min(max(count($seedGenres), 1), 3);
        $seedGenres  = array_slice($seedGenres, 0, $keepGenres);

        $slotsLeft   = 5 - count($seedGenres);
        $seedArtists = array_slice($seedArtists, 0, max(0, $slotsLeft));

        if (count($seedGenres) === 0) {
            throw new InvalidArgumentException('At least one valid seed genre is required.');
        }

        $query = [
            'limit' => max(1, min($limit, 50)),
        ];
        if ($market !== null && $market !== '') {
            $query['market'] = $market;
        }
        if ($seedGenres) {
            $query['seed_genres']  = implode(',', $seedGenres);
        }
        if ($seedArtists) {
            $query['seed_artists'] = implode(',', $seedArtists);
        }

        $allowed = [
            'target_acousticness','target_danceability','target_energy','target_instrumentalness',
            'target_liveness','target_speechiness','target_valence',
            'min_acousticness','max_acousticness','min_danceability','max_danceability',
            'min_energy','max_energy','min_instrumentalness','max_instrumentalness',
            'min_liveness','max_liveness','min_speechiness','max_speechiness',
            'min_valence','max_valence',
            'min_tempo','max_tempo','target_tempo',
            'min_popularity','max_popularity',
        ];

        foreach ($targets as $k => $v) {
            if ($v === null) {
                continue;
            }
            if (!in_array($k, $allowed, true)) {
                continue;
            }
            $query[$k] = $v;
        }

        $token = $this->token();
        $url   = $this->apiBase() . '/recommendations';

        if (
            empty($query['seed_genres'])
            && empty($query['seed_artists'])
            && empty($query['seed_tracks'])
        ) {
            throw new InvalidArgumentException('Spotify recommendations require at least one seed (genres/artists/tracks).');
        }

        $parse = function (int $status, ?string $raw, array $q) use ($url): array {

            if ($status >= 400) {
                return [];
            }
            if ($raw === '' || $raw === null) {
                return [];
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return [];
            }
            if (isset($data['error'])) {
                return [];
            }
            $tracks = $data['tracks'] ?? [];
            return is_array($tracks) ? $tracks : [];
        };

        $markets = [$market, 'US', 'GB', 'DE', 'FR', 'BR', 'JP', 'CA', 'AU', 'NL', 'SE', 'ES', 'IT', 'MX'];
        $markets = array_values(array_unique(array_filter($markets, fn($m) => $m === null || is_string($m))));

        foreach ($markets as $mkt) {
            $q = $query;
            if ($mkt !== null) {
                $q['market'] = $mkt;
            } else {
                unset($q['market']);
            }

            try {
                $resp = $this->http->request('GET', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept'        => 'application/json',
                    ],
                    'query'   => $q,
                    'timeout' => 15,
                ]);

                $status = $resp->getStatusCode();
                $raw    = $resp->getContent(false);
                $tracks = $parse($status, $raw, $q);

                if (!empty($tracks)) {
                    return $tracks;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    /**
     * @return string
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function token(): string
    {
        $clientId = trim($this->clientId ?? '');
        $clientSecret = trim($this->clientSecret ?? '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Spotify credentials missing (SPOTIFY_CLIENT_ID / SPOTIFY_CLIENT_SECRET).');
        }

        $auth = base64_encode($clientId . ':' . $clientSecret);

        $resp = $this->http->request('POST', 'https://accounts.spotify.com/api/token', [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Accept'        => 'application/json',
            ],
            'body'    => ['grant_type' => 'client_credentials'],
            'timeout' => 15,
        ]);

        $status = $resp->getStatusCode();
        $raw = $resp->getContent(false);
        $data = json_decode($raw, true);

        if ($status >= 400) {
            $snippet = is_string($raw) ? mb_substr($raw, 0, 300) : '';
            throw new RuntimeException(sprintf(
                'Spotify token HTTP %d. Body: %s',
                $status,
                $snippet !== '' ? $snippet : '<empty>'
            ));
        }

        if (!is_array($data)) {
            $snippet = is_string($raw) ? mb_substr($raw, 0, 300) : '';
            throw new RuntimeException('Spotify token: invalid JSON response. Body: ' . $snippet);
        }

        if (isset($data['error'])) {
            $desc = $data['error_description'] ?? (is_string($data['error']) ? $data['error'] : 'unknown error');
            throw new RuntimeException('Spotify token error: ' . $desc);
        }

        $token = $data['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Spotify token missing in response: ' . json_encode($data, JSON_UNESCAPED_SLASHES));
        }

        return $token;
    }

    /**
     * @param array $genres
     *
     * @return string[]
     */
    private function normalizeGenres(array $genres): array
    {
        return SpotifyGenre::normalize($genres);
    }

    /**
     * @return string
     */
    private function apiBase(): string
    {
        $base = trim(($this->baseUrl ?? ''));
        if ($base === '') {
            $base = 'https://api.spotify.com';
        }
        $base = rtrim($base, "/ \t\n\r\0\x0B");

        if (!str_ends_with($base, '/v1')) {
            $base .= '/v1';
        }

        return $base;
    }

    /**
     * @param string[] $artistIds
     *
     * @return array<string, string[]>
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function fetchArtistsGenres(array $artistIds): array
    {
        $artistIds = array_values(array_unique(array_filter(array_map('strval', $artistIds))));
        if (!$artistIds) {
            return [];
        }

        $chunks = array_chunk($artistIds, 50);
        $out = [];

        foreach ($chunks as $chunk) {
            $cacheKey = 'spotify_artists_' . md5(implode(',', $chunk));
            $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($chunk) {
                $item->expiresAfter(1800);
                $token = $this->token();
                $url = $this->apiBase() . '/artists';
                $resp = $this->http->request('GET', $url, [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                    'query'   => ['ids' => implode(',', $chunk)],
                    'timeout' => 15,
                ])->toArray(false);

                return is_array($resp) ? $resp : [];
            });

            $artists = $data['artists'] ?? [];
            if (!is_array($artists)) {
                continue;
            }
            foreach ($artists as $a) {
                $id = (string)($a['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $genres = array_values(array_unique(array_map('strval', $a['genres'] ?? [])));
                $out[$id] = $genres;
            }
        }

        return $out;
    }

    /**
     * @param array    $tracks
     * @param string[] $allowedGenres
     *
     * @return array
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function filterTracksByArtistGenres(array $tracks, array $allowedGenres): array
    {
        if (!$tracks || !$allowedGenres) {
            return [];
        }

        $allowedSet = array_fill_keys(array_map('strtolower', $allowedGenres), true);

        $artistIds = [];
        foreach ($tracks as $t) {
            foreach (($t['artists'] ?? []) as $a) {
                $id = (string)($a['id'] ?? '');
                if ($id !== '') {
                    $artistIds[] = $id;
                }
            }
        }
        $artistIds = array_values(array_unique($artistIds));

        $artistGenres = $this->fetchArtistsGenres($artistIds);

        if (!$artistGenres) {
            return [];
        }

        $out = [];
        foreach ($tracks as $t) {
            $ok = false;
            foreach (($t['artists'] ?? []) as $a) {
                $id = (string)($a['id'] ?? '');
                if ($id === '' || empty($artistGenres[$id])) {
                    continue;
                }

                foreach ($artistGenres[$id] as $g) {
                    $gLower = strtolower((string)$g);
                    $gClean = preg_replace('/[^a-z0-9]+/i', '', str_replace('-', ' ', $gLower) ?? '');

                    foreach ($allowedSet as $seed => $_true) {
                        $seedClean = preg_replace('/[^a-z0-9]+/i', '', str_replace('-', ' ', (string)$seed) ?? '');
                        if ($seedClean === '') {
                            continue;
                        }

                        if ($gLower === $seed) {
                            $ok = true;
                            break 2;
                        }

                        if (preg_match('/\b' . preg_quote($seed, '/') . '\b/i', $gLower) === 1) {
                            $ok = true;
                            break 2;
                        }

                        if ($gClean !== '' && str_contains($gClean, $seedClean)) {
                            $ok = true;
                            break 2;
                        }
                    }
                }
            }

            if ($ok) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * @param array       $genres
     * @param array       $targets
     * @param int         $limit
     * @param string|null $market
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws DecodingExceptionInterface
     */
    public function tracksByGenresWithTargets(array $genres, array $targets = [], int $limit = 25, ?string $market = null): array
    {
        $limit = max(1, min($limit, 50));

        $seedGenres = $this->normalizeGenres($genres);
        if (empty($seedGenres)) {
            throw new InvalidArgumentException('No valid genres provided.');
        }

        $seed3 = array_slice($seedGenres, 0, 3);

        $try = function (array $seeds) use ($targets, $limit, $market): array {
            $reco = $this->recommendations($seeds, [], $targets, 100, $market);
            if (!$reco) {
                return [];
            }

            return $this->extracted($reco, $seeds, $targets, $limit);
        };

        if ($res = $try($seed3)) {
            return $res;
        }

        $pairs = [];
        for ($i = 0; $i < count($seed3); $i++) {
            for ($j = $i + 1; $j < count($seed3); $j++) {
                $pairs[] = [$seed3[$i], $seed3[$j]];
            }
        }
        foreach ($pairs as $p) {
            if ($res = $try($p)) {
                return $res;
            }
        }

        foreach ($seed3 as $g) {
            if ($res = $try([$g])) {
                return $res;
            }
        }

        $wideTargets = $targets;
        unset($wideTargets['target_tempo']);
        if (isset($wideTargets['min_energy'])) {
            $wideTargets['min_energy'] = max(0.0, (float)$wideTargets['min_energy'] - 0.1);
        }
        if (isset($wideTargets['min_danceability'])) {
            $wideTargets['min_danceability'] = max(0.0, (float)$wideTargets['min_danceability'] - 0.1);
        }

        $tryWide = function (array $seeds) use ($wideTargets, $limit, $market): array {
            $reco = $this->recommendations($seeds, [], $wideTargets, 100, $market);
            if (!$reco) {
                return [];
            }
            return $this->extracted($reco, $seeds, $wideTargets, $limit);
        };

        if ($res = $tryWide($seed3)) {
            return $res;
        }
        foreach ($pairs as $p) {
            if ($res = $tryWide($p)) {
                return $res;
            }
        }
        foreach ($seed3 as $g) {
            if ($res = $tryWide([$g])) {
                return $res;
            }
        }

        $tryNoTargets = function (array $seeds) use ($limit, $market, $targets): array {
            $reco = $this->recommendations($seeds, [], [], 100, $market);
            if (!$reco) {
                return [];
            }
            return $this->extracted($reco, $seeds, $targets, $limit);
        };

        if ($res = $tryNoTargets($seed3)) {
            return $res;
        }
        foreach ($pairs as $p) {
            if ($res = $tryNoTargets($p)) {
                return $res;
            }
        }
        foreach ($seed3 as $g) {
            if ($res = $tryNoTargets([$g])) {
                return $res;
            }
        }

        $accum = [];
        foreach ($seed3 as $g) {
            $items = $this->search('genre:"' . $g . '"', 'track', 50, $market);
            if ($items) {
                $accum = array_merge($accum, $items);
            }
        }
        if ($accum) {
            $seen = [];
            $unique = [];
            foreach ($accum as $it) {
                $id = $it['id'] ?? null;
                if (!is_string($id) || $id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $unique[] = $it;
            }
            return $this->extracted($unique, $seed3, $targets, $limit);
        }

        return [];
    }

    /**
     * @param array $targets
     *
     * @return array{min_energy?:float,min_danceability?:float,min_tempo?:float,max_tempo?:float}
     */
    private function buildAudioRules(array $targets): array
    {
        $rules = [];

        if (isset($targets['min_energy'])) {
            $rules['min_energy'] = max(0.0, min(1.0, (float)$targets['min_energy']));
        } elseif (isset($targets['target_energy'])) {
            $rules['min_energy'] = max(0.0, min(1.0, (float)$targets['target_energy'] - 0.1));
        }

        if (isset($targets['min_danceability'])) {
            $rules['min_danceability'] = max(0.0, min(1.0, (float)$targets['min_danceability']));
        } elseif (isset($targets['target_danceability'])) {
            $rules['min_danceability'] = max(0.0, min(1.0, (float)$targets['target_danceability'] - 0.1));
        }

        if (isset($targets['min_tempo'])) {
            $rules['min_tempo'] = max(0.0, (float)$targets['min_tempo']);
        }
        if (isset($targets['max_tempo'])) {
            $rules['max_tempo'] = max(0.0, (float)$targets['max_tempo']);
        }
        if (isset($targets['target_tempo'])) {
            $t = (float)$targets['target_tempo'];
            if (!isset($rules['min_tempo'])) {
                $rules['min_tempo'] = max(0.0, $t - 10.0);
            }
            if (!isset($rules['max_tempo'])) {
                $rules['max_tempo'] = max(0.0, $t + 20.0);
            }
        }

        return $rules;
    }

    /**
     * @param string[] $trackIds
     *
     * @return array<string, array>
     */
    private function fetchAudioFeatures(array $trackIds): array
    {
        $trackIds = array_values(array_unique(array_filter(array_map('strval', $trackIds))));
        if (!$trackIds) {
            return [];
        }

        try {
            $token = $this->token();
        } catch (\Throwable $e) {
            return [];
        }
        $url   = $this->apiBase() . '/audio-features';
        $out   = [];

        foreach (array_chunk($trackIds, 100) as $chunk) {
            try {
                $resp = $this->http->request('GET', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept'        => 'application/json',
                    ],
                    'query'   => ['ids' => implode(',', $chunk)],
                    'timeout' => 15,
                ]);
                $data = $resp->toArray(false);
                $afs  = $data['audio_features'] ?? [];
                if (!is_array($afs)) {
                    continue;
                }
                foreach ($afs as $af) {
                    if (!is_array($af)) {
                        continue;
                    }
                    $id = (string)($af['id'] ?? '');
                    if ($id !== '') {
                        $out[$id] = $af;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $out;
    }

    /**
     * @param array $tracks
     * @param array $features
     * @param array $rules
     *
     * @return array
     */
    private function filterTracksByAudio(array $tracks, array $features, array $rules): array
    {
        if (!$tracks || !$rules) {
            return $tracks;
        }

        if (!$features) {
            return $tracks;
        }

        $kept = [];
        foreach ($tracks as $t) {
            $id = (string)($t['id'] ?? '');
            if ($id === '' || !isset($features[$id]) || !is_array($features[$id])) {
                $kept[] = $t;
                continue;
            }
            $af = $features[$id];

            if (isset($rules['min_energy']) && isset($af['energy']) && (float)$af['energy'] < (float)$rules['min_energy']) {
                continue;
            }
            if (isset($rules['min_danceability']) && isset($af['danceability']) && (float)$af['danceability'] < (float)$rules['min_danceability']) {
                continue;
            }
            if (isset($rules['min_tempo']) && isset($af['tempo']) && (float)$af['tempo'] < (float)$rules['min_tempo']) {
                continue;
            }
            if (isset($rules['max_tempo']) && isset($af['tempo']) && (float)$af['tempo'] > (float)$rules['max_tempo']) {
                continue;
            }

            $kept[] = $t;
        }

        return $kept;
    }

    /**
     * @param array $unique
     * @param array $seed3
     * @param array $targets
     * @param mixed $limit
     *
     * @return array
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function extracted(array $unique, array $seed3, array $targets, mixed $limit): array
    {
        $filtered = $this->filterTracksByArtistGenres($unique, $seed3);
        $rules = $this->buildAudioRules($targets);
        if (!empty($rules)) {
            $features = $this->fetchAudioFeatures(array_map(fn($t) => (string)($t['id'] ?? ''), $filtered));
            $filtered = $this->filterTracksByAudio($filtered, $features, $rules);
        }
        return array_slice($filtered, 0, $limit);
    }
}
