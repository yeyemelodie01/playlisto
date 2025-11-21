<?php

namespace App\Strategy;

use App\Entity\Favorite;
use Symfony\Component\Security\Core\User\UserInterface;

interface FavoriteStrategyInterface
{
    public function supports(string $type): bool;

    public function addFavorite(UserInterface $user, mixed $target): Favorite;
}