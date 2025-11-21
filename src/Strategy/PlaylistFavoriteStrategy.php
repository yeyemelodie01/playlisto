<?php

namespace App\Strategy;

use App\Entity\Favorite;
use App\Entity\Playlist;
use App\Enum\FavoriteType;
use App\Repository\FavoriteRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;

#[AutoconfigureTag('app.favorite_strategy')]
class PlaylistFavoriteStrategy implements FavoriteStrategyInterface
{
    public function __construct(private readonly FavoriteRepository $favoriteRepository)
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

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setType(FavoriteType::PLAYLIST);
        $favorite->setTargetId($target->getId());

        $this->favoriteRepository->save($favorite, true);

        return $favorite;
    }
}
