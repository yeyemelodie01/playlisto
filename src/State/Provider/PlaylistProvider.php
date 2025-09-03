<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PlaylistOutput;
use App\Entity\Playlist as PlaylistEntity;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Provides a collection of PlaylistOutput DTOs for the currently authenticated user.
 *
 * @implements ProviderInterface<array<PlaylistOutput>>
 */
final readonly class PlaylistProvider implements ProviderInterface
{
    /**
     * Constructor for PlaylistProvider.
     *
     * @param LoggerInterface $logger   the logger service
     * @param Security        $security the security component used to fetch the current authenticated user
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(
        private LoggerInterface $logger,
        private Security $security
    ) {
    }

    /**
     * Provides a collection of PlaylistOutput DTOs for the currently authenticated user.
     *
     * @param Operation            $operation    The operation being performed (GET, etc.).
     * @param array<string, mixed> $uriVariables an array of URI variables (unused here)
     * @param array<string, mixed> $context      additional context passed by API Platform
     *
     * @return PlaylistOutput[]|null an array of PlaylistOutput DTOs or null if no user is authenticated
     *
     * @throws \Throwable on unexpected errors
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null
    {
        unset($operation, $uriVariables, $context);

        try {
            $user = $this->security->getUser();
            if (null === $user) {
                // No authenticated user: return empty list (or null if your operation expects null)
                return [];
            }

            if (!method_exists($user, 'getPlaylists')) {
                throw new \LogicException('The User implementation must have a getPlaylists() method returning a Collection<Playlist>.');
            }

            /** @var Collection<int, PlaylistEntity>|iterable $collection */
            $collection = $user->getPlaylists();

            // Normalize to iterable
            if ($collection instanceof Collection) {
                $iterable = $collection->toArray();
            } elseif (is_iterable($collection)) {
                $iterable = $collection;
            } else {
                throw new \LogicException('getPlaylists() must return a Doctrine Collection or iterable.');
            }

            $result = [];
            foreach ($iterable as $playlist) {
                if (!$playlist instanceof PlaylistEntity) {
                    // Skip unexpected items but log for visibility
                    $this->logger->warning('PlaylistProvider: encountered non-Playlist entity in user playlists.');
                    continue;
                }
                $result[] = $this->mapEntityToDto($playlist);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Error providing playlist data: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Maps a Playlist entity to a PlaylistOutput DTO.
     *
     * @param PlaylistEntity $playlist the Playlist entity to map
     *
     * @return PlaylistOutput the mapped PlaylistOutput DTO
     */
    private function mapEntityToDto(PlaylistEntity $playlist): PlaylistOutput
    {
        // Adapt this mapping to the fields of your PlaylistOutput DTO
        // and to your Playlist entity getters
        $mood = method_exists($playlist, 'getMood') ? $playlist->getMood()?->value : null;
        $activity = method_exists($playlist, 'getActivity') ? $playlist->getActivity()?->value : null;
        $tracks = method_exists($playlist, 'getTracks') ? $playlist->getTracks() : null;
        $trackCount = $tracks instanceof Collection ? $tracks->count() : (is_countable($tracks) ? count($tracks) : 0);

        return new PlaylistOutput(
            id: (int) $playlist->getId(),
            title: (string) $playlist->getTitle(),
            description: method_exists($playlist, 'getDescription') ? ($playlist->getDescription() ?? null) : null,
            mood: $mood,
            activity: $activity,
            trackCount: $trackCount,
            createdAt: method_exists($playlist, 'getCreatedAt') ? $playlist->getCreatedAt() : null,
        );
    }
}
