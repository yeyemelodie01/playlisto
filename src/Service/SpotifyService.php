<?php

namespace App\Service;

use Psr\Cache\InvalidArgumentException;
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
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl = 'https://api.spotify.com/v1',
    ) {
    }

    /**
     * Get a valid Spotify API token, cached until expiration
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function token(): string
    {
        return $this->cache->get('spotify.token', function ($item) {
            $res = $this->http->request('POST', 'https://accounts.spotify.com/api/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => 'grant_type=client_credentials',
                ])->toArray(false);

            if (!isset($res['access_token'])) {
                throw new RuntimeException('Spotify token error: ' . json_encode($res));
            }
                $item->expiresAfter(max(60, (int)($res['expires_in'] ?? 3600) - 60));

                return $res['access_token'];
        });
    }

    /**
     * Search Spotify for tracks, artists, albums, or playlists
     *
     * @param string $q
     * @param string $type
     * @param int    $limit
     *
     * @return array
     *
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function search(string $q, string $type = 'track', int $limit = 10): array
    {
        $token = $this->token();
        $data = $this->http->withOptions([
            'base_uri' => $this->baseUrl,
            'headers'  => ['Authorization' => 'Bearer ' . $token],
            'query'    => ['q' => $q, 'type' => $type, 'limit' => min(max($limit, 1), 50)],
        ])->request('GET', '/search')->toArray(false);

        return $data[$type . 's']['items'] ?? [];
    }

    /**
     * Get tracks from a Spotify playlist by its ID
     *
     * @param string $playlistId
     * @param int    $limit
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getPlaylistTracks(string $playlistId, int $limit = 25): array
    {
        $token = $this->token();
        $data = $this->http->withOptions([
            'base_uri' => $this->baseUrl,
            'headers'  => ['Authorization' => 'Bearer ' . $token],
            'query'    => ['limit' => min(max($limit, 1), 100)],
        ])->request('GET', "/playlists/$playlistId/tracks")->toArray(false);

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
                'preview_url'  => $t['preview_url'] ?? null,
                'external_url' => $t['external_urls']['spotify'] ?? null,
                'image_url'    => $t['album']['images'][0]['url'] ?? null,
                'duration_ms'  => $t['duration_ms'] ?? null,
            ];
        }, $items)));
    }

    /**
     * From mood+activity, build a Spotify search query
     *
     * @param string $mood
     * @param string $activity
     *
     * @return string
     */
    public function queryForMoodActivity(string $mood, string $activity): string
    {
        $mood = strtolower($mood);
        $activity = strtolower($activity);
        $moodKey = match ($mood) {
            'happy' => 'happy',
            'sad' => 'sad',
            'energetic' => 'energetic',
            'stressed' => 'stressed',
            'calm' => 'calm',
            default => 'happy'
        };
        $actKey = match ($activity) {
            'sport' => 'sport',
            'work' => 'work',
            'relax' => 'relax',
            'study' => 'study',
            'cooking' => 'cooking',
            default => ''
        };
        return trim("$moodKey $actKey");
    }

    /**
     * Get tracks for a given mood and activity, optionally biased by genres
     *
     * @param string $mood
     * @param string $activity
     * @param array  $genres
     * @param int    $limit
     *
     * @return array
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function tracksForMoodActivity(string $mood, string $activity, array $genres = [], int $limit = 25): array
    {
        $q = $this->queryForMoodActivity($mood, $activity);
        if ($genres) {
            $q .= ' ' . implode(' ', array_map(fn($g)=>"#" . $g, $genres)); // bias search a bit
        }
        $playlists = $this->search($q, 'playlist', 5);
        if (!$playlists) {
            // fallback: generic mood query
            $playlists = $this->search($mood, 'playlist', 5);
            if (!$playlists) {
                return [];
            }
        }
        $first = $playlists[0];
        $playlistId = $first['id'] ?? null;
        return $playlistId ? $this->getPlaylistTracks($playlistId, $limit) : [];
    }
}
