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
     * Get tracks for a given mood and activity, optionally filtered by genres.
     *
     * @param string $mood
     * @param string $activity
     * @param array  $genres
     * @param int    $limit
     * @param array  $targets
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function tracksForMoodActivity(string $mood, string $activity, array $genres = [], int $limit = 25, array $targets = []): array
    {
        $mood     = strtolower(trim($mood));
        $activity = strtolower(trim($activity));
        $limit    = max(1, min($limit, 50));

        $userSeeds = $this->normalizeGenres($genres);

        [$_baseSeeds, $baseTargets] = $this->seedsForMoodActivity($mood, $activity);
        $finalTargets = array_replace($baseTargets, $targets);

        $artistSeeds = $this->genreAnchors($userSeeds);
        $seedGenres  = $userSeeds;
        $seedArtists = array_slice($artistSeeds, 0, 5);

        $jittered = $this->jitterTargets($finalTargets);

        if (!empty($reco = $this->recommendations($seedGenres, $seedArtists, [], $jittered, 50, null))) {
            return array_slice($reco, 0, $limit);
        }

        if (!empty($reco = $this->recommendations($seedGenres, $seedArtists, [], $finalTargets, 50, null))) {
            return array_slice($reco, 0, $limit);
        }

        return [];
    }

    /**
     * Get track recommendations from Spotify API based on seed genres and target attributes.
     *
     * @param array       $seedGenres List of seed genres (max 5).
     * @param array       $seedArtists List of seed artist IDs (max 5).
     * @param array       $seedTracks List of seed track IDs (max 5).
     * @param array       $targets Associative array of target audio features (e.g. target_valence, target_energy).
     * @param int         $limit Number of tracks to return (max 100).
     * @param string|null $market Market code (e.g. 'FR').
     *
     * @return array List of recommended tracks.
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function recommendations(array $seedGenres = [], array $seedArtists = [], array $seedTracks = [], array $targets = [], int $limit = 20, ?string $market = null): array
    {
        $seedGenres  = array_slice(array_values(array_unique(array_map('strval', $seedGenres))), 0, 5);
        $seedArtists = array_slice(array_values(array_unique(array_map('strval', $seedArtists))), 0, 5);
        $seedTracks  = array_slice(array_values(array_unique(array_map('strval', $seedTracks))), 0, 5);

        $total = count($seedGenres) + count($seedArtists) + count($seedTracks);
        if ($total > 5) {
            $over = $total - 5;
            while ($over > 0 && !empty($seedTracks)) {
                array_pop($seedTracks);
                $over--;
            }
            while ($over > 0 && !empty($seedGenres)) {
                array_pop($seedGenres);
                $over--;
            }
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
        if ($seedTracks) {
            $query['seed_tracks']  = implode(',', $seedTracks);
        }

        foreach ($targets as $k => $v) {
            if ($v === null) {
                continue;
            }
            $query[$k] = $v;
        }


        $token = $this->token();
        $url = $this->apiBase() . '/recommendations';

        if (
            empty($query['seed_genres'])
            && empty($query['seed_artists'])
            && empty($query['seed_tracks'])
        ) {
            throw new InvalidArgumentException('Spotify recommendations require at least one seed (genres/artists/tracks).');
        }

        $doRequest = function (array $q) use ($url, $token) {
            $response = $this->http->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
                'query' => $q,
            ]);
            return [$response->getStatusCode(), $response->getContent(false)];
        };

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

        $markets = array_values(array_unique([$market, null, 'US', 'GB', 'DE', 'BR', 'JP', 'FR'], SORT_REGULAR));

        $profiles = [
            fn(array $base) => $base,

            function (array $base): array {
                $q = $base;
                unset($q['seed_artists']);
                return $q;
            },

            function (array $base): array {
                $q = [
                    'limit'       => $base['limit'],
                    'seed_genres' => $base['seed_genres'] ?? '',
                    'min_popularity' => 25,
                ];
                if (isset($base['market'])) {
                    $q['market'] = $base['market'];
                }
                return $q;
            },

            function (array $base): array {
                return [
                        'limit'       => $base['limit'],
                        'seed_genres' => $base['seed_genres'] ?? '',
                    ] + (isset($base['market']) ? ['market' => $base['market']] : []);
            },
        ];

        foreach ($markets as $mkt) {
            foreach ($profiles as $make) {
                $q = $make($query);

                if ($mkt === null) {
                    unset($q['market']);
                } else {
                    $q['market'] = $mkt;
                }

                if (empty($q['seed_genres'] ?? '') && empty($q['seed_artists'] ?? '') && empty($q['seed_tracks'] ?? '')) {
                    continue;
                }

                [$status, $raw] = $doRequest($q);

                if ($status === 404 || $raw === '' || $raw === null) {
                    continue;
                }

                $tracks = $parse($status, $raw, $q);
                if (!empty($tracks)) {
                    return $tracks;
                }
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
     * @throws DecodingExceptionInterface
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

        $res = $this->http->request('POST', 'https://accounts.spotify.com/api/token', [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => 'grant_type=client_credentials',
            'timeout' => 10,
        ])->toArray(false);

        if (empty($res['access_token'])) {
            throw new RuntimeException('Spotify token error: ' . json_encode($res, JSON_UNESCAPED_SLASHES));
        }

        return (string) $res['access_token'];
    }

    /**
     * Retourne une liste d'IDs d'artistes “canoniques” pour chaque genre fourni.
     * Utilise l’API Search: q=genre:"<genre>" type=artist
     *
     * @param list<string> $genres
     *
     * @return list<string> artist IDs
     */
    private function genreAnchors(array $genres, ?string $market = null, int $perGenre = 3): array
    {
        $ids = [];

        $markets = array_unique([$market, null, 'US', 'GB', 'DE', 'BR', 'JP', 'FR'], SORT_REGULAR);

        foreach (array_values(array_unique(array_map('strval', $genres))) as $g) {
            $q = 'genre:"' . $g . '"';

            foreach ($markets as $mkt) {
                try {
                    $artists = $this->search($q, 'artist', $perGenre, $mkt);
                    foreach ((array)$artists as $a) {
                        $id = $a['id'] ?? null;
                        if (is_string($id) && $id !== '') {
                            $ids[] = $id;
                        }
                    }

                    if (!empty($ids)) {
                        break;
                    }
                } catch (Throwable $e) {
                }
            }
        }
        return array_values(array_unique($ids));
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
     * @param string $mood
     * @param string $activity
     *
     * @return array
     */
    private function seedsForMoodActivity(string $mood): array
    {
        $mood = strtolower(trim($mood));

        $seedGenres = [];

        $targets = match ($mood) {
            'energetic' => [
                'target_energy'       => 0.90,
                'target_danceability' => 0.70,
                'target_valence'      => 0.65,
                'min_tempo'           => 110,
                'max_tempo'           => 165,
            ],
            'happy' => [
                'target_energy'       => 0.70,
                'target_danceability' => 0.70,
                'target_valence'      => 0.85,
                'min_tempo'           => 100,
                'max_tempo'           => 150,
            ],
            'stressed' => [
                'target_energy'       => 0.45,
                'target_danceability' => 0.35,
                'target_valence'      => 0.40,
                'min_tempo'           => 60,
                'max_tempo'           => 110,
            ],
            'sad' => [
                'target_energy'       => 0.35,
                'target_danceability' => 0.30,
                'target_valence'      => 0.30,
                'min_tempo'           => 60,
                'max_tempo'           => 100,
            ],
            'calm' => [
                'target_energy'       => 0.40,
                'target_danceability' => 0.45,
                'target_valence'      => 0.55,
                'min_tempo'           => 70,
                'max_tempo'           => 120,
            ],
        };

        $clamp = static fn(float $x) => max(0.0, min(1.0, $x));
        foreach (['target_energy','target_danceability','target_valence'] as $k) {
            if (isset($targets[$k])) {
                $targets[$k] = $clamp((float)$targets[$k]);
            }
        }
        if (isset($targets['min_tempo'], $targets['max_tempo']) && $targets['min_tempo'] > $targets['max_tempo']) {
            [$targets['min_tempo'], $targets['max_tempo']] = [$targets['max_tempo'], $targets['min_tempo']];
        }

        return [$seedGenres, $targets];
    }

    /**
     * Apply slight randomness to recommendation target params so repeated calls vary.
     *
     * @param array $t
     *
     * @return array
     */
    private function jitterTargets(array $t): array
    {
        $out = $t;

        $j = static fn(float $v, float $delta) => max(0.0, min(1.0, $v + (mt_rand(-100, 100) / 100.0) * $delta));

        foreach (['target_energy','target_valence','target_danceability'] as $k) {
            if (isset($out[$k])) {
                $out[$k] = $j((float)$out[$k], 0.07);
            }
        }

        if (isset($out['min_tempo'])) {
            $out['min_tempo'] = max(90, (int)$out['min_tempo'] + mt_rand(-6, 6));
        }
        if (isset($out['max_tempo'])) {
            $out['max_tempo'] = min(145, (int)$out['max_tempo'] + mt_rand(-6, 6));
        }

        return $out;
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
