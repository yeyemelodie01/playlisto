<?php

namespace App\Strategy;

use App\Entity\Favorite;
use App\Entity\Playlist;
use App\Enum\FavoriteType;
use App\Repository\FavoriteRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Strategy responsible for handling playlist favorite creation.
 * Supports the FavoriteType::PLAYLIST type and persists a new Favorite entity.
 */
#[AutoconfigureTag('app.favorite_strategy')]
final readonly class PlaylistFavoriteStrategy implements FavoriteStrategyInterface
{
    public function __construct(private FavoriteRepository $favoriteRepository)
    {
    }

    public function supports(string $type): bool
    {
        return $type === FavoriteType::PLAYLIST->value;
    }

    public function addFavorite(UserInterface $user, mixed $target): Favorite
    {
        if (!$target instanceof Playlist) {
            throw new \InvalidArgumentException('Expected a Playlist as target for playlist favorite.');
        }

        $existing = $this->favoriteRepository->findOneBy([
            'user' => $user,
            'type' => FavoriteType::PLAYLIST,
            'targetId' => $target->getId(),
        ]);

        if ($existing) {
            return $existing;
        }

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setType(FavoriteType::PLAYLIST);
        $favorite->setTargetId($target->getId());

        $this->favoriteRepository->save($favorite, true);

        return $favorite;
    }

    public function removeFavorite(UserInterface $user, mixed $target): bool
    {
        if (!$target instanceof Playlist) {
            throw new \InvalidArgumentException('Expected a Playlist as target for playlist favorite.');
        }

        $favorite = $this->favoriteRepository->findOneBy([
            'user' => $user,
            'type' => FavoriteType::PLAYLIST,
            'targetId' => $target->getId(),
        ]);

        if (!$favorite) {
            return false;
        }

        $this->favoriteRepository->remove($favorite, true);

        return true;
    }
}
