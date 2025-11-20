<?php

namespace App\Service;

use App\Enum\SpotifyGenre;
use InvalidArgumentException;
use RuntimeException;
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
     * @param string              $clientId
     * @param string              $clientSecret
     * @param string              $baseUrl
     */
    public function __construct(
        private HttpClientInterface $http,
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
     */
    public function tracksByGenresWithTargets(array $genres, array $targets = [], int $limit = 25, ?string $market = null): array
    {
        $limit = max(1, min($limit, 50));

        $seedGenres = $this->normalizeGenres($genres);
        if (empty($seedGenres)) {
            throw new InvalidArgumentException('No valid genres provided.');
        }
        $seed3 = array_slice($seedGenres, 0, 3);

        $tryReco = function (array $seeds) use ($limit, $market): array {
            $reco = $this->recommendations($seeds, [], [], 100, $market);
            if (!$reco) {
                return [];
            }
            return $this->extracted($reco, $seeds, [], $limit);
        };

        if ($res = $tryReco($seed3)) {
            return $res;
        }

        $pairs = [];
        for ($i = 0; $i < count($seed3); $i++) {
            for ($j = $i + 1; $j < count($seed3); $j++) {
                $pairs[] = [$seed3[$i], $seed3[$j]];
            }
        }
        foreach ($pairs as $p) {
            if ($res = $tryReco($p)) {
                return $res;
            }
        }

        foreach ($seed3 as $g) {
            if ($res = $tryReco([$g])) {
                return $res;
            }
        }

        $markets = [$market, 'US', 'GB', 'DE', 'FR', 'BR', 'JP', 'CA', 'AU', 'NL', 'SE', 'ES', 'IT', 'MX'];
        $markets = array_values(array_unique(array_filter($markets, fn($m) => $m === null || is_string($m))));

        $accum = [];
        $seen  = [];
        foreach ($markets as $mkt) {
            foreach ($seed3 as $g) {
                try {
                    $items = $this->search('genre:"' . $g . '"', 'track', 50, $mkt);
                } catch (\Throwable) {
                    $items = [];
                }
                if (!$items) {
                    continue;
                }
                foreach ($items as $it) {
                    $id = (string)($it['id'] ?? '');
                    if ($id === '' || isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    $accum[] = $it;
                    if (count($accum) >= $limit) {
                        return $this->extracted($accum, $seed3, [], $limit);
                    }
                }
            }
        }

        foreach ($markets as $mkt) {
            foreach ($seed3 as $g) {
                try {
                    $reco = $this->recommendations([$g], [], ['min_popularity' => 0], 100, $mkt);
                } catch (\Throwable) {
                    $reco = [];
                }
                if ($reco) {
                    return $this->extracted($reco, [$g], [], $limit);
                }
            }
        }

        return [];
    }


    /**
     * @param array $unique
     * @param array $seed3
     * @param array $targets
     * @param mixed $limit
     *
     * @return array
     */
    public function extracted(array $unique, array $seed3, array $targets, mixed $limit): array
    {
        return array_slice($unique, 0, $limit);
    }
}
