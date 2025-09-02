<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PlaylistOutput;
use App\Entity\Playlist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class PlaylistItemProvider implements ProviderInterface
{
    /**
     * Constructor for PlaylistItemProvider.
     *
     * @param EntityManagerInterface $entityManager the Doctrine entity manager
     * @param Security               $security      the security component used to fetch the current authenticated user
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    /**
     * Provides a PlaylistOutput DTO for a specific playlist owned by the currently authenticated user.
     *
     * @param Operation            $operation    The operation being performed (GET, etc.).
     * @param array<string, mixed> $uriVariables an array of URI variables, expecting 'id' for the playlist ID
     * @param array<string, mixed> $context      additional context passed by API Platform
     *
     * @return PlaylistOutput|null returns a PlaylistOutput object if found and owned by the user, null otherwise
     *
     * @throws AccessDeniedHttpException if the playlist does not belong to the authenticated user
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PlaylistOutput
    {
        $user = $this->security->getUser();
        if (null === $user) {
            return null; // No authenticated user
        }

        $playlistId = $uriVariables['id'] ?? 0;
        $playlist = $this->entityManager->getRepository(Playlist::class)->findOneBy([
            'id' => $playlistId,
            'user' => $user,
        ]);
        if (!$playlist) {
            return null; // Playlist not found or does not belong to the user
        }

        if (method_exists($playlist, 'getUser') && $playlist->getUser() !== $user) {
            throw new AccessDeniedHttpException('You do not have access to this playlist.');
        }

        $tracks = method_exists($playlist, 'getTracks') ? $playlist->getTracks() : null;
        $trackCount = is_object($tracks) && method_exists($tracks, 'count') ? $tracks->count() : (is_countable($tracks) ? \count($tracks) : 0);

        return new PlaylistOutput(
            id: $playlist->getId(),
            title: $playlist->getTitle(),
            description: method_exists($playlist, 'getDescription') ? $playlist->getDescription() : null,
            mood: method_exists($playlist, 'getMood') ? $playlist->getMood() : null,
            activity: method_exists($playlist, 'getActivity') ? $playlist->getActivity() : null,
            trackCount: $trackCount,
            createdAt: method_exists($playlist, 'getCreatedAt') ? $playlist->getCreatedAt() : null
        );
    }
}
