<?php

namespace App\Controller\Api;

use App\Service\SpotifyService;
use App\Enum\MoodType;
use App\Enum\ActivityType;
use App\Entity\Playlist;
use App\Entity\Track;
use App\Entity\User;
use DateTime;
use App\Repository\PlaylistRepository;
use App\Repository\TrackRepository;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use function is_array;

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
    public function __construct(
        private readonly SpotifyService $spotify,
        private PlaylistRepository $playlistRepository,
        private TrackRepository $trackRepository,
        private Security $security,
    ) {
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

        // Convert to enums expected by Playlist entity
        $moodEnum = MoodType::tryFrom($mood);
        if (!$moodEnum) {
            throw new InvalidArgumentException(sprintf('Invalid mood "%s"', $mood));
        }
        $activityEnum = ActivityType::tryFrom($activity);
        if (!$activityEnum) {
            throw new InvalidArgumentException(sprintf('Invalid activity "%s"', $activity));
        }

        try {
            $tracks = $this->spotify->tracksForMoodActivity($mood, $activity, $genres, $limit);

            // Persist the generated playlist and its tracks
            $owner = $this->security->getUser();
            if (!$owner instanceof User) {
                throw new RuntimeException('Authenticated user not found or invalid.');
            }

            $playlist = new Playlist();
            $playlist->setTitle(sprintf('%s • %s', ucfirst($mood), ucfirst($activity)));
            $playlist->setDescription(sprintf('Auto-generated from mood=%s, activity=%s, genres=%s', $mood, $activity, implode(', ', $genres)));
            $playlist->setMood($moodEnum);
            $playlist->setActivity($activityEnum);
            $playlist->setCreatedAt(new DateTime());
            $playlist->setUser($owner);

            $this->playlistRepository->save($playlist, true);

            foreach ($tracks as $t) {
                $spotifyId = (string)($t['id'] ?? '');
                if ($spotifyId === '') {
                    continue;
                }

                // Reuse existing track if we already saved it before
                $track = $this->trackRepository->findOneBy(['spotifyId' => $spotifyId]);
                if (!$track) {
                    $track = new Track();
                    $track->setSpotifyId($spotifyId);
                    $track->setTitle((string)($t['name'] ?? ''));
                    $track->setArtist(is_array($t['artists'] ?? null) ? implode(', ', $t['artists']) : (string)($t['artists'] ?? ''));
                    $track->setAlbum((string)($t['album'] ?? ''));
                    $track->setGenre(!empty($genres) ? implode(', ', $genres) : '');
                    $track->setCoverUrl((string)($t['image_url'] ?? ''));
                    $track->setDuration((int) (((int)($t['duration_ms'] ?? 0)) / 1000)); // seconds
                }

                // Link to the newly created playlist
                $track->addPlaylist($playlist);
                $this->trackRepository->save($track, true);
            }

            return new JsonResponse([
                'status' => 'ok',
                'query'  => compact('mood', 'activity', 'genres', 'limit'),
                'playlist_id' => $playlist->getId(),
                'count'  => count($tracks),
                'tracks' => $tracks,
            ]);
        } catch (Throwable $e) {
            $status = $e instanceof InvalidArgumentException ? 400 : 500;
            return new JsonResponse([
                'status'  => 'error',
                'message' => 'Failed to generate playlist: ' . $e->getMessage(),
            ], $status);
        }
    }
}
