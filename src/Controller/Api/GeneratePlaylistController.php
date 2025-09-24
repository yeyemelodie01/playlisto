<?php

namespace App\Controller\Api;

use App\Service\SpotifyService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for generating playlists based on mood, activity, and genres.
 *
 * This controller provides an endpoint to generate a playlist tailored to the user's specified mood, activity, and preferred genres.
 * It utilizes the SpotifyService to fetch tracks that match the criteria.
 *
 * @psalm-suppress PossiblyUnusedMethod
 */
final class GeneratePlaylistController
{
    /**
     * @param SpotifyService $spotify The Spotify service for interacting with the Spotify API.
     */
    public function __construct(private readonly SpotifyService $spotify)
    {
    }

    /**
     * Generate a playlist based on mood, activity, and genres.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    #[Route('/api/me/generate-playlist', name: 'me_generate_playlist', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        $analysis = $body['analysis'] ?? null;

        $mood     = (string) ($body['mood']     ?? ($analysis['mood']     ?? 'happy'));
        $activity = (string) ($body['activity'] ?? ($analysis['activity'] ?? 'relax'));
        $genres   = (array)  ($body['genres']   ?? ($analysis['genres']   ?? []));
        $limit    = (int)    ($body['limit']    ?? 20);

        $mood     = strtolower(trim($mood));
        $activity = strtolower(trim($activity));
        $genres   = array_values(array_filter(array_map(fn($g) => strtolower(trim((string)$g)), $genres)));
        $limit    = max(1, min($limit, 50));

        try {
            $tracks = $this->spotify->tracksForMoodActivity($mood, $activity, $genres, $limit);

            return new JsonResponse([
                'status' => 'ok',
                'query'  => compact('mood', 'activity', 'genres', 'limit'),
                'count'  => count($tracks),
                'tracks' => $tracks,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status'  => 'error',
                'message' => 'Failed to generate playlist: ' . $e->getMessage(),
            ], 500);
        }
    }
}
