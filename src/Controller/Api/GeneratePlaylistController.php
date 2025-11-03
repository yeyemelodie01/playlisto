<?php

namespace App\Controller\Api;

use App\Entity\SurveySubmission;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Enum\SpotifyGenre;
use App\Service\SpotifyService;
use App\Entity\Playlist;
use App\Entity\Track;
use App\Entity\User;
use App\Repository\PlaylistRepository;
use App\Repository\TrackRepository;
use App\Repository\SurveySubmissionRepository;
use JsonException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_slice;
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
final readonly class GeneratePlaylistController
{
    /**
     * @param SpotifyService             $spotify
     * @param PlaylistRepository         $playlistRepository
     * @param TrackRepository            $trackRepository
     * @param SurveySubmissionRepository $submissionRepository
     * @param Security                   $security
     */
    public function __construct(
        private SpotifyService $spotify,
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
     *
     * @throws \JsonException
     */
    #[Route('/api/me/generate-playlist', name: 'me_generate_playlist', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse(['status' => 'error','message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $submissionId = (int)($payload['submission_id'] ?? 0);
        $limit        = max(1, min((int)($payload['limit'] ?? 20), 50));

        if ($submissionId <= 0) {
            return new JsonResponse(['status' => 'error','message' => 'Missing submission_id'], Response::HTTP_BAD_REQUEST);
        }

        $submission = $this->submissionRepository->find($submissionId);
        if (!$submission) {
            return new JsonResponse(['status' => 'error','message' => 'Submission not found'], Response::HTTP_NOT_FOUND);
        }

        $mood     = $submission->getDeducedMood();
        $activity = $submission->getSelectedActivity();
        $genres   = $submission->getPreferredGenres() ?? [];

        if (empty($genres)) {
            return new JsonResponse([
                'status'  => 'error',
                'message' => 'No preferred genres on submission',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $title = $this->makeTitle($mood, $activity, $genres);
        $desc = $this->makeDescription($submissionId, $mood, $activity, $genres);

        $playlist = new Playlist();
        $playlist->setTitle($title);
        $playlist->setDescription($desc);

        if ($mood instanceof MoodType) {
            $playlist->setMood($mood);
        }
        if ($activity instanceof ActivityType) {
            $playlist->setActivity($activity);
        }

        $user = $this->security->getUser();
        if ($user instanceof User && method_exists($playlist, 'setUser')) {
            $playlist->setUser($user);
        }

        $this->playlistRepository->save($playlist, true);

        try {
            $targets = $this->targetsFromMoodActivity($mood, $activity);
            $items   = $this->spotify->tracksByGenresWithTargets($genres, $targets, $limit);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status'  => 'error',
                'message' => 'Failed to generate playlist: ' . $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $added = [];
        foreach ($items as $it) {
            $spotifyId = $it['id'] ?? null;
            $name      = $it['name'] ?? null;
            if (!is_string($spotifyId) || $spotifyId === '' || !is_string($name) || $name === '') {
                continue;
            }

            $artists = [];
            foreach (($it['artists'] ?? []) as $a) {
                if (isset($a['name']) && is_string($a['name'])) {
                    $artists[] = $a['name'];
                }
            }
            $albumName = $it['album']['name'] ?? '';
            $duration  = (int)($it['duration_ms'] ?? 0);
            $images    = $it['album']['images'] ?? [];
            $coverUrl  = is_array($images) && isset($images[0]['url']) ? (string)$images[0]['url'] : '';
            $preview   = isset($it['preview_url']) && is_string($it['preview_url']) ? $it['preview_url'] : null;

            $genreStr = is_array($genres) && !empty($genres) ? (string)$genres[0] : 'unknown';

            $track = $this->trackRepository->findOneBy(['spotifyId' => $spotifyId]);
            if (!$track) {
                $track = new Track();
                $track->setSpotifyId($spotifyId);
            }
            $track->setTitle($name);
            $track->setArtists($artists);
            $track->setAlbum((string)$albumName);
            $track->setGenre($genreStr);
            $track->setDuration((int)round($duration / 1000));
            if ($coverUrl) {
                $track->setCoverUrl($coverUrl);
            }
            if ($preview) {
                $track->setPreviewUrl($preview);
            }

            $this->trackRepository->save($track, true);

            $playlist->addTrack($track);
            $added[] = [
                'id'           => $spotifyId,
                'name'         => $name,
                'artists'      => $artists,
                'album'        => $albumName,
                'image_url'    => $coverUrl,
                'duration_ms'  => $duration,
                'external_url' => $it['external_urls']['spotify'] ?? null,
                'preview_url'  => $preview,
            ];
        }

        $this->playlistRepository->save($playlist, true);

        return new JsonResponse([
            'status'      => 'ok',
            'submission'  => $submissionId,
            'playlist_id' => $playlist->getId(),
            'count'       => count($added),
            'tracks'      => $added,
        ], Response::HTTP_OK);
    }

    private function makeTitle(?MoodType $mood, ?ActivityType $activity, array $genres): string
    {
        $g = $genres ? (' · ' . implode(', ', array_slice($genres, 0, 2))) : '';
        $a = $activity ? (' · ' . ucfirst($activity->value)) : '';
        $m = $mood ? ucfirst($mood->value) : 'Mix';
        return sprintf('%s%s%s', $m, $a, $g) ?: 'Mix';
    }

    private function makeDescription(int $submissionId, ?MoodType $mood, ?ActivityType $activity, array $genres): string
    {
        $bits = [];
        if ($mood) {
            $bits[] = 'Mood: ' . $mood->value;
        }
        if ($activity) {
            $bits[] = 'Activity: ' . $activity->value;
        }
        if ($genres) {
            $bits[] = 'Genres: ' . implode(', ', array_slice($genres, 0, 5));
        }
        $bits[] = 'Submission #' . $submissionId;
        return implode(' | ', $bits);
    }

    private function targetsFromMoodActivity(?MoodType $mood, ?ActivityType $activity): array
    {
        // Valeurs par défaut "neutres"
        $targets = [
            'min_popularity' => 0,
        ];

        if ($mood) {
            switch ($mood->value) {
                case 'happy':
                    $targets['target_valence'] = 0.85;
                    $targets['target_energy']  = 0.7;
                    $targets['target_danceability'] = 0.7;
                    break;

                case 'sad':
                    $targets['target_valence'] = 0.2;
                    $targets['target_energy']  = 0.3;
                    $targets['target_acousticness'] = 0.5;
                    break;

                case 'calm':
                case 'relaxed':
                    $targets['target_energy']  = 0.35;
                    $targets['target_valence'] = 0.6;
                    $targets['target_acousticness'] = 0.6;
                    break;

                case 'angry':
                    $targets['target_energy']  = 0.9;
                    $targets['target_valence'] = 0.3;
                    break;
            }
        }

        if ($activity) {
            switch ($activity->value) {
                case 'workout':
                    $targets['min_energy'] = 0.75;
                    $targets['min_danceability'] = 0.6;
                    $targets['target_tempo'] = 135;
                    break;

                case 'running':
                    $targets['min_energy'] = 0.8;
                    $targets['target_tempo'] = 165;
                    break;

                case 'study':
                case 'focus':
                    $targets['max_speechiness'] = 0.2;
                    $targets['max_liveness'] = 0.2;
                    $targets['min_instrumentalness'] = 0.6;
                    $targets['target_energy'] = ($targets['target_energy'] ?? 0.4) * 0.9;
                    break;

                case 'reading':
                    $targets['min_instrumentalness'] = 0.7;
                    $targets['max_liveness'] = 0.2;
                    $targets['target_energy'] = 0.3;
                    break;

                case 'party':
                    $targets['min_danceability'] = 0.75;
                    $targets['min_energy'] = 0.75;
                    $targets['target_tempo'] = 125;
                    break;
            }
        }

        return $targets;
    }
}
