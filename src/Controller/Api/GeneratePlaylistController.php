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
     * @param SpotifyService             $spotify
     * @param PlaylistRepository         $playlistRepository
     * @param TrackRepository            $trackRepository
     * @param SurveySubmissionRepository $submissionRepository
     * @param Security                   $security
     */
    public function __construct(
        private readonly SpotifyService $spotify,
        private readonly PlaylistRepository $playlistRepository,
        private readonly TrackRepository $trackRepository,
        private readonly SurveySubmissionRepository $submissionRepository,
        private readonly Security $security,
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
        $body         = json_decode($request->getContent(), true) ?? [];
        $submissionId = (int)($body['submission_id'] ?? 0);
        $limit        = max(1, min((int)($body['limit'] ?? 20), 50));

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

        $moodEnum     = $submission->getDeducedMood();
        $activityEnum = $submission->getDeducedActivity();
        $rawGenres    = $submission->getPreferredGenres() ?? [];

        $mood     = $moodEnum?->value ?? (is_string($moodEnum) ? $moodEnum : '');
        $activity = $activityEnum?->value ?? (is_string($activityEnum) ? $activityEnum : '');

        $userGenres = class_exists(SpotifyGenre::class) && method_exists(SpotifyGenre::class, 'normalize')
            ? SpotifyGenre::normalize(is_array($rawGenres) ? $rawGenres : [])
            : array_slice(array_values(array_unique(array_map('strval', $rawGenres))), 0, 5);

        $seedGenres = $userGenres;

        $targets = $this->targetsFromContext($moodEnum, $activityEnum);

        if (empty($seedGenres)) {
            return new JsonResponse([
                'status'  => 'error',
                'message' => 'No preferred genres found for this submission. Please answer the genres question.',
            ], 422);
        }
        unset($targets['target_energy'], $targets['target_valence'], $targets['target_danceability'], $targets['max_speechiness'], $targets['min_instrumentalness'], $targets['max_acousticness'], $targets['min_tempo'], $targets['max_tempo']);
        //dd($mood, $activity, $seedGenres, $limit, $targets);
        try {
            $tracks = $this->spotify->tracksForMoodActivity($mood, $activity, $seedGenres, $limit, $targets);

            $playlist = new Playlist();
            $playlist->setTitle($this->makeTitle($moodEnum, $activityEnum, $userGenres));
            $playlist->setDescription($this->makeDescription($submissionId, $moodEnum, $activityEnum, $userGenres));
            $playlist->setMood($moodEnum);
            $playlist->setActivity($activityEnum);
            $playlist->setUser($owner);
            $this->playlistRepository->save($playlist, false);

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

                    $artists = $t['artists'] ?? [];
                    if (!is_array($artists)) {
                        $artists = $artists ? [ (string)$artists ] : [];
                    }
                    $track->setArtists(array_values(array_map('strval', $artists)));

                    $track->setAlbum((string)($t['album'] ?? ''));
                    $track->setGenre(!empty($seedGenres) ? implode(', ', $seedGenres) : '');
                    $track->setCoverUrl((string)($t['image_url'] ?? ''));
                    if (isset($t['preview_url'])) {
                        $track->setPreviewUrl((string)$t['preview_url']);
                    }
                    $durationMs = (int)($t['duration_ms'] ?? 0);
                    $track->setDuration((int)ceil($durationMs / 1000));
                    $this->trackRepository->save($track, false);
                }

                $track->addPlaylist($playlist);
            }

            // 5) Flush final
            $this->playlistRepository->save($playlist, true);

            return new JsonResponse([
                'status'      => 'ok',
                'submission'  => $submissionId,
                'query'       => [
                    'mood'     => $mood,
                    'activity' => $activity,
                    'genres'   => $userGenres,
                    'seedGenres' => $seedGenres,
                    'limit'    => $limit,
                ],
                'playlist_id' => $playlist->getId(),
                'count'       => count($tracks),
                'tracks'      => $tracks,
            ]);
        } catch (Throwable $e) {
            $status = $e instanceof InvalidArgumentException ? 400 : 502;
            return new JsonResponse([
                'status'  => 'error',
                'message' => 'Failed to generate playlist: ' . $e->getMessage(),
                'debug'   => [
                    'seeds'   => $seedGenres,
                    'targets' => $targets,
                ],
            ], $status);
        }
    }

    /**
     * @param MoodType|null     $mood
     * @param ActivityType|null $activity
     *
     * @return array<string, int|float>
     */
    private function targetsFromContext(?MoodType $mood, ?ActivityType $activity): array
    {
        $energy = 0.5;
        $valence = 0.5;
        $dance = 0.5;
        $minTempo = 80;
        $maxTempo = 130;

        if ($mood) {
            switch ($mood->value) {
                case 'energetic':
                    $energy = 0.85;
                    $valence = 0.70;
                    $dance = 0.70;
                    $minTempo = 110;
                    $maxTempo = 165;
                    break;
                case 'happy':
                    $energy = 0.70;
                    $valence = 0.85;
                    $dance = 0.70;
                    $minTempo = 100;
                    $maxTempo = 150;
                    break;
                case 'stressed':
                    $energy = 0.45;
                    $valence = 0.40;
                    $dance = 0.35;
                    $minTempo = 60;
                    $maxTempo = 110;
                    break;
                case 'sad':
                    $energy = 0.35;
                    $valence = 0.30;
                    $dance = 0.30;
                    $minTempo = 60;
                    $maxTempo = 100;
                    break;
                case 'calm':
                default:
                    $energy = 0.40;
                    $valence = 0.55;
                    $dance = 0.45;
                    $minTempo = 70;
                    $maxTempo = 120;
                    break;
            }
        }

        $extra = [
            'max_speechiness'      => 0.55,
            'min_instrumentalness' => 0.10,
            'max_acousticness'     => 0.70,
        ];

        if ($activity) {
            switch ($activity->value) {
                case 'sport':
                    $energy   = max($energy, 0.58);
                    $dance    = max($dance, 0.55);
                    $valence  = max($valence, 0.52);
                    $minTempo = max($minTempo, 95);
                    $maxTempo = max($maxTempo, 138);

                    $extra['max_speechiness']       = 0.60;
                    $extra['min_instrumentalness']  = 0.08;
                    $extra['max_acousticness']      = 0.75;

                    $extra['min_popularity']        = 30;
                    break;

                case 'work':
                    $energy = max(0.0, $energy - 0.05);
                    $dance  = max(0.0, $dance - 0.10);
                    $valence = 0.50;
                    $minTempo = 70;
                    $maxTempo = 115;
                    $extra['max_speechiness']      = 0.50;
                    $extra['min_instrumentalness'] = 0.10;
                    break;

                case 'study':
                    $energy = 0.35;
                    $dance = 0.25;
                    $valence = 0.50;
                    $minTempo = 60;
                    $maxTempo = 90;
                    $extra['max_speechiness']      = 0.40;
                    $extra['min_instrumentalness'] = 0.30;
                    break;

                case 'relax':
                    $energy = 0.35;
                    $dance = 0.35;
                    $valence = 0.60;
                    $minTempo = 60;
                    $maxTempo = 100;
                    $extra['max_speechiness']      = 0.50;
                    $extra['min_instrumentalness'] = 0.10;
                    break;

                case 'cooking':
                    $energy = 0.55;
                    $dance = 0.60;
                    $valence = 0.65;
                    $minTempo = 85;
                    $maxTempo = 125;
                    $extra['max_speechiness']      = 0.55;
                    $extra['min_instrumentalness'] = 0.10;
                    break;
            }
        }

        $clamp = static fn(float $x): float => max(0.0, min(1.0, $x));

        $extras = array_filter([
            'max_speechiness'      => $extra['max_speechiness'],
            'min_instrumentalness' => $extra['min_instrumentalness'],
            'max_acousticness'     => $extra['max_acousticness'],
        ], static fn($v) => $v !== null);

        return array_merge([
            'target_energy'       => $clamp($energy),
            'target_valence'      => $clamp($valence),
            'target_danceability' => $clamp($dance),
            'min_tempo'           => (int) $minTempo,
            'max_tempo'           => (int) $maxTempo,
        ], $extras);
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
}
