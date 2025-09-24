<?php

namespace App\Service;

use App\Enum\SpotifyGenre;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SpotifyService
{
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
     * @param string $q
     * @param string $type
     * @param int    $limit
     * @param string $market
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function search(string $q, string $type = 'track', int $limit = 10, string $market = 'FR'): array
    {
        $token = $this->token();
        $url   = rtrim($this->baseUrl, '/') . '/search';

        $data = $this->http->request('GET', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'query'   => [
                'q'      => $q,
                'type'   => $type,
                'limit'  => min(max($limit, 1), 50),
                'market' => $market,
            ],
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
     *
     * @return array
     */
    public function tracksForMoodActivity(string $mood, string $activity, array $genres = [], int $limit = 25): array
    {
        $mood     = strtolower(trim($mood));
        $activity = strtolower(trim($activity));
        $limit    = max(1, min($limit, 50));

        $userSeeds = $this->normalizeGenres($genres);
        [$baseSeeds, $targets] = $this->seedsForMoodActivity($mood, $activity);

        $seedPool = !empty($userSeeds) ? $userSeeds : $baseSeeds;
        $seedPool = array_values(array_unique(array_map('strval', $seedPool)));
        if (!empty($seedPool)) {
            shuffle($seedPool);
        }
        $seedPick = array_slice($seedPool, 0, min(5, count($seedPool)));

        if (!empty($seedPick)) {
            try {
                $jittered = $this->jitterTargets($targets);
                $reco = $this->recommendations($seedPick, $jittered, $limit, 'FR');
                if (!empty($reco)) {
                    return array_slice($reco, 0, $limit);
                }
            } catch (\Throwable $e) {
                error_log('[Spotify] recommendations (seedPick+jitter) failed: ' . $e->getMessage());
            }

            try {
                $reco = $this->recommendations($seedPick, [], $limit, 'FR');
                if (!empty($reco)) {
                    return array_slice($reco, 0, $limit);
                }
            } catch (\Throwable $e) {
                error_log('[Spotify] recommendations (seedPick, no targets) failed: ' . $e->getMessage());
            }
        }

        if (!empty($userSeeds)) {
            $seedGenres = array_slice(array_values(array_unique($userSeeds)), 0, 5);
            $bag = [];
            foreach ($seedGenres as $g) {
                try {
                    $pls = $this->search(trim($g . ' playlist'), 'playlist', 5, 'FR');
                    foreach (($pls ?? []) as $pl) {
                        if (empty($pl['id'])) {
                            continue;
                        }
                        $tracks = $this->getPlaylistTracks((string)$pl['id'], min(20, $limit));
                        if (!empty($tracks)) {
                            $bag = array_merge($bag, $tracks);
                        }
                        if (count($bag) >= $limit * 2) {
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[Spotify] genre playlist fallback failed for "' . $g . '": ' . $e->getMessage());
                }
            }
            if (!empty($bag)) {
                $bag = $this->dedupeTracks($bag);
                shuffle($bag);
                return array_slice($bag, 0, $limit);
            }
        }

        try {
            $seedGenres = $this->normalizeGenres($baseSeeds);
            if (!empty($seedGenres)) {
                shuffle($seedGenres);
                $seedGenres = array_slice($seedGenres, 0, min(5, count($seedGenres)));
            }
            if (empty($seedGenres)) {
                $seedGenres = ['pop'];
            }

            $reco = $this->recommendations($seedGenres, $targets, $limit, 'FR');
            if (!empty($reco)) {
                return array_slice($reco, 0, $limit);
            }
        } catch (\Throwable $e) {
            error_log('[Spotify] recommendations (base seeds) failed: ' . $e->getMessage());
        }

        $qBase = $this->queryForMoodActivity($mood, $activity);
        $variants = array_values(array_unique(array_filter([
            $qBase,
            trim("$mood $activity playlist"),
            trim("$mood playlist"),
            trim("$activity playlist"),
            $mood,
            $activity,
            trim("$mood $activity mix"),
            trim("$mood mix"),
            trim("$activity mix"),
        ])));

        $bag = [];
        foreach ($variants as $q) {
            try {
                $playlists = $this->search($q, 'playlist', 5, 'FR');
                foreach (($playlists ?? []) as $pl) {
                    if (empty($pl['id'])) {
                        continue;
                    }
                    $tracks = $this->getPlaylistTracks((string)$pl['id'], min(20, $limit));
                    if (!empty($tracks)) {
                        $bag = array_merge($bag, $tracks);
                    }
                    if (count($bag) >= $limit * 2) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                error_log('[Spotify] playlist search error for "' . $q . '": ' . $e->getMessage());
            }
        }

        foreach (($seedGenres ?? []) as $seed) {
            try {
                $pls = $this->search(trim($seed . ' playlist'), 'playlist', 5, 'FR');
                foreach (($pls ?? []) as $pl) {
                    if (empty($pl['id'])) {
                        continue;
                    }
                    $tracks = $this->getPlaylistTracks((string)$pl['id'], min(20, $limit));
                    if (!empty($tracks)) {
                        $bag = array_merge($bag, $tracks);
                    }
                    if (count($bag) >= $limit * 3) {
                        break;
                    }
                }

                $tracks = $this->search($seed, 'track', 25, 'FR');
                if (!empty($tracks)) {
                    $mapped = array_values(array_filter(array_map(function ($t) {
                        if (!$t) {
                            return null;
                        }
                        return [
                            'id'           => $t['id'] ?? null,
                            'name'         => $t['name'] ?? null,
                            'artists'      => array_map(fn($a) => $a['name'] ?? '', $t['artists'] ?? []),
                            'album'        => $t['album']['name'] ?? null,
                            'image_url'    => $t['album']['images'][0]['url'] ?? null,
                            'duration_ms'  => $t['duration_ms'] ?? null,
                            'external_url' => $item['external_urls']['spotify'] ?? null,
                        ];
                    }, $tracks)));
                    $bag = array_merge($bag, $mapped);
                }
            } catch (\Throwable $e) {
                error_log('[Spotify] track/playlist search fallback failed for seed "' . $seed . '": ' . $e->getMessage());
            }
        }

        if (!empty($bag)) {
            $bag = $this->dedupeTracks($bag);
            shuffle($bag);
            return array_slice($bag, 0, $limit);
        }

        return [];
    }

    /**
     * Get track recommendations from Spotify API based on seed genres and target attributes.
     *
     * @param array  $seedGenres List of seed genres (max 5).
     * @param array  $targets    Associative array of target audio features (e.g. target_valence, target_energy).
     * @param int    $limit      Number of tracks to return (max 100).
     * @param string $market     Market code (e.g. 'FR').
     *
     * @return array List of recommended tracks.
     *
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    private function recommendations(array $seedGenres, array $targets = [], int $limit = 25, string $market = 'FR'): array
    {
        $token = $this->token();
        $url   = rtrim($this->baseUrl, '/') . '/recommendations';

        $query = [
                'limit'       => min(max($limit, 1), 100),
                'market'      => $market,
                'seed_genres' => implode(',', $seedGenres),
            ] + $targets;

        $response = $this->http->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'query' => $query,
        ]);

        $data = $response->toArray(false);


        if (!is_array($data) || $data === []) {
            return [];
        }

        if (isset($data['error'])) {
            throw new RuntimeException('Spotify error: ' . json_encode($data, JSON_UNESCAPED_SLASHES));
        }

        return $data['tracks'] ?? [];
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
            throw new \RuntimeException('Spotify credentials missing (SPOTIFY_CLIENT_ID / SPOTIFY_CLIENT_SECRET).');
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
            throw new \RuntimeException('Spotify token error: ' . json_encode($res, JSON_UNESCAPED_SLASHES));
        }

        return (string) $res['access_token'];
    }

    /**
     * Construct a search query string based on mood and activity.
     *
     * @param string $mood
     * @param string $activity
     *
     * @return string
     */
    private function queryForMoodActivity(string $mood, string $activity): string
    {
        $mood = strtolower(trim($mood));
        $activity = strtolower(trim($activity));

        $moodKey = match ($mood) {
            'happy'     => 'happy',
            'sad'       => 'sad',
            'energetic' => 'energetic',
            'stressed'  => 'stressed',
            'calm'      => 'calm',
            default     => $mood,
        };

        $actKey = match (strtolower($activity)) {
            'sport'   => 'sport',
            'work'    => 'work',
            'relax'   => 'relax',
            'study'   => 'study',
            'cooking' => 'cooking',
            default   => $activity,
        };

        return trim($moodKey . ' ' . $actKey);
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
     * Return the list of available seed genres from Spotify API, with caching.
     *
     * @param string $mood
     * @param string $activity
     *
     * @return array
     */
    private function seedsForMoodActivity(string $mood, string $activity): array
    {
        $mood = strtolower(trim($mood));
        $activity = strtolower(trim($activity));

        $seedGenres = [];

        $targets = match ($mood) {
            'happy'     => ['target_valence' => 0.8, 'target_energy' => 0.7, 'min_danceability' => 0.5],
            'energetic' => ['target_energy' => 0.9, 'min_tempo' => 120, 'min_danceability' => 0.6],
            'stressed'  => ['target_valence' => 0.4, 'target_acousticness' => 0.5, 'max_energy' => 0.5],
            'calm'      => ['target_energy' => 0.3, 'target_acousticness' => 0.6, 'max_tempo' => 110],
            'sad'       => ['target_valence' => 0.2, 'target_acousticness' => 0.5, 'max_energy' => 0.5],
            default     => [],
        };

        if ($activity === 'sport') {
            $targets = array_replace($targets, ['min_energy' => 0.6, 'min_tempo' => 100]);
        } elseif ($activity === 'study' || $activity === 'work') {
            $targets = array_replace($targets, ['max_energy' => 0.6]);
        }

        return [$seedGenres, $targets];
    }

    /**
     * Return the list of available seed genres from Spotify API, with caching.
     *
     * @param string $param
     * @param mixed $limit
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function getPlaylistTracks(string $param, mixed $limit): array
    {
        $playlistId = (string) $param;
        $limit = (int) $limit;
        $limit = min(max($limit, 1), 100);

        $token = $this->token();

        $data = $this->http->request('GET', rtrim($this->baseUrl, '/') . "/playlists/{$playlistId}/tracks", [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'query'   => ['limit' => $limit],
        ])->toArray(false);

        $items = $data['items'] ?? [];

        return array_values(array_filter(array_map(function ($i) {
            $t = $i['track'] ?? null;
            if (!$t) {
                return null;
            }
            return [
                'id'           => $t['id'] ?? null,
                'name'         => $t['name'] ?? null,
                'artists'      => array_map(fn($a) => $a['name'] ?? '', $t['artists'] ?? []),
                'album'        => $t['album']['name'] ?? null,
                'external_url' => $t['external_urls']['spotify'] ?? null,
                'image_url'    => $t['album']['images'][0]['url'] ?? null,
                'duration_ms'  => $t['duration_ms'] ?? null,
            ];
        }, $items)));
    }
    /**
     * Apply slight randomness to recommendation target params so repeated calls vary.
     *
     * @param array $targets
     *
     * @return array
     */
    private function jitterTargets(array $targets): array
    {
        if (empty($targets)) {
            return $targets;
        }

        $out = $targets;

        $j = function (float $val, float $delta = 0.07): float {
            $shift = (mt_rand(-100, 100) / 100.0) * $delta;
            $v = $val + $shift;
            return max(0.0, min(1.0, $v));
        };

        foreach (['target_valence','target_energy','target_acousticness','min_danceability'] as $k) {
            if (isset($out[$k])) {
                $out[$k] = $j((float)$out[$k]);
            }
        }

        foreach (['min_tempo','max_tempo'] as $k) {
            if (isset($out[$k])) {
                $shift = mt_rand(-8, 8); // +/- 8 BPM
                $v = (int)$out[$k] + $shift;
                $out[$k] = max(60, min(200, $v));
            }
        }

        if (!isset($out['min_popularity']) && mt_rand(0, 1) === 1) {
            $out['min_popularity'] = mt_rand(30, 85);
        }

        return $out;
    }

    /**
     * Deduplicate tracks by id while preserving first occurrence.
     *
     * @param array $tracks
     *
     * @return array
     */
    private function dedupeTracks(array $tracks): array
    {
        $seen = [];
        $out  = [];
        foreach ($tracks as $t) {
            $id = $t['id'] ?? null;
            if (!$id || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $t;
        }
        return $out;
    }
}
