<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PlaylistOutput;
use App\ApiResource\TrackOutput;
use App\Repository\PlaylistRepository;
use App\Entity\User;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class PlaylistItemProvider implements ProviderInterface
{
    /**
     * @param PlaylistRepository $playlistRepository
     * @param Security           $security
     *
     * @psalm-suppress
     */
    public function __construct(
        private PlaylistRepository $playlistRepository,
        private Security $security
    ) {
    }

    /**
     * Provides a PlaylistOutput DTO for a specific playlist owned by the currently authenticated user.
     *
     * @param Operation            $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return PlaylistOutput|null
     *
     * @throws AccessDeniedHttpException
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PlaylistOutput
    {
        $user = $this->security->getUser();
        if (null === $user) {
            return null;
        }

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authenticated user is not a valid application user.');
        }

        $playlistId = (int) ($uriVariables['id'] ?? 0);
        if ($playlistId <= 0) {
            return null;
        }

        $playlist = $this->playlistRepository->findOneForUserWithTracks($playlistId, $user);
        if (!$playlist) {
            return null;
        }

        $tracksDto = [];
        if (method_exists($playlist, 'getTracks')) {
            foreach ($playlist->getTracks() as $t) {
                $artists = method_exists($t, 'getArtist') ? $t->getArtist() : null;
                if (is_string($artists)) {
                    $artists = array_values(array_filter(array_map('trim', preg_split('/,|;|\\|/u', $artists))));
                } elseif (is_array($artists)) {
                } else {
                    $artists = [];
                }

                $durationMs = null;
                if (method_exists($t, 'getDuration')) {
                    $sec = $t->getDuration();
                    $durationMs = $sec > 0 ? $sec * 1000 : null;
                }

                $tracksDto[] = new TrackOutput(
                    id: $t->getId() ?? 0,
                    title: $t->getTitle() ?? '',
                    artists: $t->getArtists() ?? [],
                    album: method_exists($t, 'getAlbum') ? $t->getAlbum() : null,
                    duration: $t->getDuration() ?? 0,
                    spotifyId: method_exists($t, 'getSpotifyId') ? $t->getSpotifyId() : null,
                    coverUrl: method_exists($t, 'getCoverUrl') ? $t->getCoverUrl() : null,
                    previewUrl: method_exists($t, 'getPreviewUrl') ? $t->getPreviewUrl() : null,
                );
            }
        }

        return new PlaylistOutput(
            id: (int)$playlist->getId(),
            title: $playlist->getTitle(),
            description: method_exists($playlist, 'getDescription') ? $playlist->getDescription() : null,
            mood: method_exists($playlist, 'getMood') ? $playlist->getMood()?->value : null,
            activity: method_exists($playlist, 'getActivity') ? $playlist->getActivity()?->value : null,
            trackCount: count($tracksDto),
            tracks: $tracksDto,
            createdAt: method_exists($playlist, 'getCreatedAt') ? $playlist->getCreatedAt() : null
        );
    }
}
