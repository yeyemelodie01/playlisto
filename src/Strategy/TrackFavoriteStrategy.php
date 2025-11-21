<?php

namespace App\Strategy;

use App\Entity\Favorite;
use App\Entity\Track;
use App\Enum\FavoriteType;
use App\Repository\FavoriteRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;

#[AutoconfigureTag('app.favorite_strategy')]
class TrackFavoriteStrategy implements FavoriteStrategyInterface
{
    public function __construct(private readonly FavoriteRepository $favoriteRepository)
    {
    }

    public function supports(string $type): bool
    {
        return $type === FavoriteType::TRACK->value;
    }

    public function addFavorite(UserInterface $user, mixed $target): Favorite
    {
        if (!$target instanceof Track) {
            throw new \InvalidArgumentException('Expected a Track as target for track favorite.');
        }

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setType(FavoriteType::TRACK);
        $favorite->setTargetId($target->getId());

        $this->favoriteRepository->save($favorite, true);

        return $favorite;
    }
}