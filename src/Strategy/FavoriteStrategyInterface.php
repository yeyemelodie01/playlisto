<?php

namespace App\Strategy;

use App\Entity\Favorite;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Interface for strategies that handle favorite creation for specific favorite types.
 * Each implementation determines if it supports a given type and performs the favorite creation.
 */
interface FavoriteStrategyInterfacez
{
    /**
     * Checks whether this strategy supports the given favorite type.
     *
     * @param string $type The favorite type value from the FavoriteType enum.
     *
     * @return bool True if this strategy handles the provided type, false otherwise.
     */
    public function supports(string $type): bool;

    /**
     * Creates and persists a favorite for the given user and target.
     *
     * @param UserInterface $user The user adding the favorite.
     * @param mixed $target The entity or identifier being favorited.
     *
     * @return Favorite The created Favorite entity.
     *
     * @throws \InvalidArgumentException If the target is invalid or unsupported.
     */
    public function addFavorite(UserInterface $user, mixed $target): Favorite;
}