<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PlaylistInput;
use App\ApiResource\PlaylistOutput;
use App\Entity\Playlist;
use App\Entity\User as AppUser;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Repository\PlaylistRepository;
use App\Repository\TrackRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function assert;
use function count;

/**
 * Processor for handling playlist-related operations.
 *
 * This class implements the ProcessorInterface to process data related to playlists.
 */
final readonly class PlaylistProcessor implements ProcessorInterface
{
    /**
     * @param PlaylistRepository $playlistRepository
     * @param Security           $security
     * @param TrackRepository    $trackRepository
     *
     * @psalm-suppress
     */
    public function __construct(private PlaylistRepository $playlistRepository, private Security $security, private TrackRepository $trackRepository)
    {
    }

    /**
     * Processes the given data for a playlist operation.
     *
     * @param mixed                $data
     * @param Operation            $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return mixed The processed data.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if (!$user instanceof AppUser) {
            $userEntity = $this->playlistRepository->findOneBy([
                'email' => method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null,
            ]);
            if (!$userEntity) {
                throw new AccessDeniedHttpException('Authenticated user entity not found.');
            }
            $user = $userEntity;
        }

        if ($operation instanceof Post) {
            assert($data instanceof PlaylistInput);

            if (trim($data->title ?? '') === '') {
                throw new BadRequestHttpException('Title is required.');
            }

            $playlist = new Playlist();
            $playlist->setTitle($data->title);
            if (method_exists($playlist, 'setDescription')) {
                $playlist->setDescription($data->description ?? null);
            }

            if ($data->mood !== null && method_exists($playlist, 'setMood')) {
                $playlist->setMood(MoodType::from($data->mood));
            }

            if ($data->activity !== null && method_exists($playlist, 'setActivity')) {
                $playlist->setActivity(ActivityType::from($data->activity));
            }

            if (method_exists($playlist, 'setUser')) {
                $playlist->setUser($user);
            }

            if ($data->trackIds && method_exists($playlist, 'addTrack')) {
                foreach ($data->trackIds as $trackId) {
                    $track = $this->trackRepository->find((int)$trackId);
                    if ($track) {
                        $playlist->addTrack($track);
                    }
                }
            }

            $this->playlistRepository->save($playlist, true);

            return $this->toOutput($playlist);
        }

        if ($operation instanceof Put || $operation instanceof Patch) {
            assert($data instanceof PlaylistInput);
            $playlistId = (int)($uriVariables['playlistId'] ?? 0);

            $playlist = $this->playlistRepository->find($playlistId);
            if (!$playlist) {
                throw new NotFoundHttpException('Playlist not found');
            }
            if (method_exists($playlist, 'getUser') && $playlist->getUser() !== $user) {
                throw new AccessDeniedHttpException('Not your playlist.');
            }

            if (isset($data->title)) {
                $playlist->setTitle($data->title);
            }

            if (property_exists($data, 'description')) {
                $playlist->setDescription($data->description ?? null);
            }

            if (property_exists($data, 'mood') && method_exists($playlist, 'setMood')) {
                $playlist->setMood($data->mood !== null ? MoodType::from($data->mood) : $playlist->getMood());
            }

            if (property_exists($data, 'activity') && method_exists($playlist, 'setActivity')) {
                $playlist->setActivity($data->activity !== null ? ActivityType::from($data->activity) : $playlist->getActivity());
            }

            if ($data->trackIds !== null && method_exists($playlist, 'getTracks') && method_exists($playlist, 'addTrack') && method_exists($playlist, 'removeTrack')) {
                $tracks = $playlist->getTracks();
                if (is_iterable($tracks)) {
                    foreach ($tracks as $track) {
                        $playlist->removeTrack($track);
                    }
                }

                foreach ($data->trackIds as $trackId) {
                    $track = $this->trackRepository->find((int)$trackId);
                    if ($track) {
                        $playlist->addTrack($track);
                    }
                }
            }

            $this->playlistRepository->save($playlist, true);

            return $this->toOutput($playlist);
        }

        if ($operation instanceof Delete) {
            $playlistId = (int)($uriVariables['playlistId'] ?? 0);

            $playlist = $this->playlistRepository->find($playlistId);

            if (!$playlist) {
                throw new NotFoundHttpException('Playlist not found');
            }
            if (method_exists($playlist, 'getUser') && $playlist->getUser() !== $user) {
                throw new AccessDeniedHttpException('Not your playlist.');
            }

            $this->playlistRepository->remove($playlist, true);

            return null;
        }

        return $data;
    }

    private function toOutput(Playlist $playlist): PlaylistOutput
    {
        $tracks = method_exists($playlist, 'getTracks') ? $playlist->getTracks() : null;
        $trackCount = is_object($tracks) && method_exists($tracks, 'count') ? $tracks->count() : (is_countable($tracks) ? count($tracks) : 0);

        return new PlaylistOutput(
            id: (int) $playlist->getId(),
            title: $playlist->getTitle(),
            description: method_exists($playlist, 'getDescription') ? $playlist->getDescription() : null,
            mood: method_exists($playlist, 'getMood') ? $playlist->getMood()?->value : null,
            activity: method_exists($playlist, 'getActivity') ? $playlist->getActivity()?->value : null,
            trackCount: $trackCount,
            createdAt: method_exists($playlist, 'getCreatedAt') ? $playlist->getCreatedAt() : null,
        );
    }
}
