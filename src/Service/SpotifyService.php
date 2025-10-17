<?php

namespace App\Service;

use App\Enum\SpotifyGenre;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class SpotifyService
{
    private array $lastQuery;

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
        $this->lastQuery = [];
    }



    public function lastQuery(): array
    {
        return $this->lastQuery;
    }

    /**
     * Get track recommendations based on mood, activity, and optional genres.
     *
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
     * Get track recommendations from Spotify API based on seed genres and target attributes.
     *
     * @param array       $seedGenres List of seed genres (max 5).
     * @param array       $seedArtists List of seed artist IDs (max 5).
     * @param array       $targets Associative array of target audio features (e.g. target_valence, target_energy).
     * @param int         $limit Number of tracks to return (max 50).
     * @param string|null $market Market code (e.g. 'FR').
     *
     * @return array List of recommended tracks.
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
            throw new \InvalidArgumentException('At least one valid seed genre is required.');
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
            if ($raw === '' || $raw === null) {
                throw new RuntimeException(
                    'Spotify recommendations HTTP ' . $status . ': response body is empty.'
                    . ' url=' . $url . ' query=' . http_build_query($q)
                );
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                throw new RuntimeException('Spotify recommendations invalid JSON (HTTP ' . $status . '): ' . $raw);
            }
            if (isset($data['error'])) {
                $msg  = is_array($data['error']) ? ($data['error']['message'] ?? 'unknown error') : (string)$data['error'];
                $code = is_array($data['error']) ? ($data['error']['status'] ?? $status)       : $status;
                throw new RuntimeException('Spotify error ' . $code . ': ' . $msg);
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

            $this->lastQuery = $q;

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
     * Get a Spotify API token using client credentials flow.
     *
     * @return string The Spotify API access token.
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
     * Normalize user-provided genres to Spotify seed genres.
     *
     * @param array $genres
     *
     * @return string[]
     */
    private function normalizeGenres(array $genres): array
    {
        $valid = array_map(fn($g) => $g->value, SpotifyGenre::cases());

        $map = [
            'lofi'        => SpotifyGenre::LOFI->value,
            'lo-fi'       => SpotifyGenre::LOFI->value,
            'rap'         => SpotifyGenre::HIP_HOP->value,
            'rap-fr'      => SpotifyGenre::HIP_HOP->value,
            'rap-francais' => SpotifyGenre::HIP_HOP->value,
            'rap-us'      => SpotifyGenre::HIP_HOP->value,
            'hiphop'      => SpotifyGenre::HIP_HOP->value,
            'hip-hop'     => SpotifyGenre::HIP_HOP->value,
            'dancehall'   => SpotifyGenre::DANCEHALL->value,
            'zouk'        => SpotifyGenre::WORLD_MUSIC->value,
        ];

        $out = [];
        foreach ($genres as $g) {
            $g = strtolower(trim((string)$g));
            if ($g === '') {
                continue;
            }
            $g = $map[$g] ?? $g;
            if (in_array($g, $valid, true)) {
                $out[] = $g;
            }
        }

        return array_slice(array_unique($out), 0, 5);
    }

    /**
     * @param array $genres
     * @param int   $limit
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function tracksByGenres(array $genres, int $limit = 25): array
    {
        $limit = max(1, min($limit, 50));

        // 1) Normalisation + borne Spotify (max 5), puis on combinera ensuite
        $seedGenres = $this->normalizeGenres($genres);
        if (empty($seedGenres)) {
            throw new \InvalidArgumentException('No valid genres provided.');
        }
        // On garde 3 genres max pour les essais "multi"
        $seed3 = array_slice($seedGenres, 0, 3);

        // Helper local pour factoriser les appels (toujours limit 100 côté API, puis slice local)
        $try = function (array $seeds, array $targets = []) use ($limit): array {
            if (!empty($reco = $this->recommendations($seeds, [], $targets, 100, null))) {
                return array_slice($reco, 0, $limit);
            }
            return [];
        };

        // --- 2) Essais à 3 seeds (strict genres) ---
        if ($res = $try($seed3, [])) {
            return $res;
        }
        if ($res = $try($seed3, ['min_popularity' => 0])) {
            return $res;
        }

        // --- 3) Essais à 2 seeds (toutes les combinaisons) ---
        // Génère toutes les paires uniques issues de $seed3
        $pairs = [];
        for ($i = 0; $i < count($seed3); $i++) {
            for ($j = $i + 1; $j < count($seed3); $j++) {
                $pairs[] = [$seed3[$i], $seed3[$j]];
            }
        }
        foreach ($pairs as $p) {
            if ($res = $try($p, [])) {
                return $res;
            }
            if ($res = $try($p, ['min_popularity' => 0])) {
                return $res;
            }
        }

        // --- 4) Essais à 1 seed (chacun des genres) ---
        foreach ($seed3 as $g) {
            if ($res = $try([$g], [])) {
                return $res;
            }
            if ($res = $try([$g], ['min_popularity' => 0])) {
                return $res;
            }
        }

        return [];
    }

    /**
     * @return string
     */
    private function apiBase(): string
    {
        $base = rtrim(trim($this->baseUrl), "/ \t\n\r\0\x0B");

        if (!str_ends_with($base, '/v1')) {
            $base .= '/v1';
        }

        return $base;
    }
}
