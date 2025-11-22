<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FavoriteGetOutput;
use App\Entity\Favorite;
use App\Enum\FavoriteType;
use App\Repository\FavoriteRepository;
use App\Repository\PlaylistRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class FavoriteGetProvider implements ProviderInterface
{
    public function __construct(private FavoriteRepository $favoriteRepository, private Security $security, private PlaylistRepository $playlistRepository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (null === $user) {
            throw new AccessDeniedHttpException('User not authenticated.');
        }

        $favorites = $this->favoriteRepository->findBy([
            'user' => $user,
            'type' => FavoriteType::PLAYLIST,
        ]);

        $playlistIds = array_map(
            static fn (Favorite $favorite) => $favorite->getTargetId(),
            $favorites
        );

        if ([] === $playlistIds) {
            return [];
        }

        $playlists = $this->playlistRepository->findBy(['id' => $playlistIds]);

        return array_map(static fn ($playlist) => new FavoriteGetOutput(
            id: $playlist->getId(),
            title: $playlist->getTitle(),
            trackCount: $playlist->getTracks()->count(),
            createdAt: $playlist->getCreatedAt(),
        ), $playlists);
    }
}
