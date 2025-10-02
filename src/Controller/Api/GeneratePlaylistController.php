<?php

namespace App\Controller\Api;

use App\Entity\SurveySubmission;
use App\Enum\SpotifyGenre;
use App\Service\SpotifyService;
use App\Entity\Playlist;
use App\Entity\Track;
use App\Entity\User;
use App\Repository\PlaylistRepository;
use App\Repository\TrackRepository;
use App\Repository\SurveySubmissionRepository;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function ceil;
use function class_exists;
use function count;
use function implode;
use function is_array;
use function method_exists;

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
        private SurveySubmissionRepository $submissionRepository,
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
        $body       = json_decode($request->getContent(), true) ?? [];
        $submissionId = (int)($body['submission_id'] ?? 0);
        $limit      = max(1, min((int)($body['limit'] ?? 20), 50));

        // Auth user
        $owner = $this->security->getUser();
        if (!$owner instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Authenticated user not found or invalid.'], 401);
        }

        if ($submissionId <= 0) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing submission_id'], 400);
        }

        /** @var SurveySubmission|null $submission */
        $submission = $this->submissionRepository->find($submissionId);
        if (!$submission || $submission->getUser()?->getId() !== $owner->getId()) {
            return new JsonResponse(['status' => 'error', 'message' => 'Submission not found'], 404);
        }

        // 🔥 Utiliser STRICTEMENT ce qui est déjà stocké sur la submission
        $moodEnum     = $submission->getDeducedMood();      // MoodType|null
        $activityEnum = $submission->getDeducedActivity();  // ActivityType|null
        $genres       = $submission->getPreferredGenres() ?? [];

        // Valeurs string pour l’appel Spotify (ne jamais caster l'enum en string)
        $mood     = $moodEnum?->value ?? (is_string($moodEnum) ? $moodEnum : '');
        $activity = $activityEnum?->value ?? (is_string($activityEnum) ? $activityEnum : '');

        // Normaliser les genres sur les seeds officiels (et limiter à 5)
        if (class_exists(SpotifyGenre::class) && method_exists(SpotifyGenre::class, 'normalize')) {
            $genres = SpotifyGenre::normalize(is_array($genres) ? $genres : []);
        } else {
            $genres = array_slice(array_values(array_unique(array_map('strval', $genres))), 0, 5);
        }

        // Si vraiment aucun genre valide après normalisation, on laisse vide — SpotifyService gérera (ou tu peux fallback 'pop')
        // $genres = $genres ?: ['pop'];

        try {
            // 1) Récupérer des tracks Spotify
            $tracks = $this->spotify->tracksForMoodActivity($mood, $activity, $genres, $limit);

            // 2) Créer & persister la playlist
            $playlist = new Playlist();
            $playlist->setTitle(sprintf('%s • %s', ucfirst($mood), ucfirst($activity)));
            $playlist->setDescription(sprintf('Auto-generated from submission #%d', $submissionId));
            $playlist->setMood($moodEnum);
            $playlist->setActivity($activityEnum);
            $playlist->setCreatedAt(new DateTime());
            $playlist->setUser($owner);
            $this->playlistRepository->save($playlist, false); // flush plus tard

            // 3) Attacher/Créer les tracks
            foreach ($tracks as $t) {
                $spotifyId = (string)($t['id'] ?? '');
                if ($spotifyId === '') {
                    continue;
                }

                $track = $this->trackRepository->findOneBy(['spotifyId' => $spotifyId]);
                if (!$track) {
                    $track = new Track();
                    $track->setSpotifyId($spotifyId);
                    $track->setTitle((string)($t['name'] ?? ''));
                    $track->setArtist(is_array($t['artists'] ?? null) ? implode(', ', $t['artists']) : (string)($t['artists'] ?? ''));
                    $track->setAlbum((string)($t['album'] ?? ''));
                    $track->setGenre(!empty($genres) ? implode(', ', $genres) : '');
                    $track->setCoverUrl((string)($t['image_url'] ?? ''));
                    $track->setDuration((int)ceil(((int)($t['duration_ms'] ?? 0)) / 1000)); // sec
                    $this->trackRepository->save($track, false);
                }

                $track->addPlaylist($playlist);
                $this->trackRepository->save($track, true);
            }

            // 4) Flush final
            $this->playlistRepository->save($playlist, true);

            return new JsonResponse([
                'status'      => 'ok',
                'submission'  => $submissionId,
                'query'       => compact('mood', 'activity', 'genres', 'limit'),
                'playlist_id' => $playlist->getId(),
                'count'       => count($tracks),
                'tracks'      => $tracks,
            ]);
        } catch (\Throwable $e) {
            $status = $e instanceof \InvalidArgumentException ? 400 : 500;
            return new JsonResponse([
                'status'  => 'error',
                'message' => 'Failed to generate playlist: ' . $e->getMessage(),
            ], $status);
        }
    }
}
