<?php

namespace App\Factory;

use App\Entity\Favorite;
use App\Entity\Playlist;
use App\Enum\FavoriteType;
use App\Strategy\FavoriteStrategyInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Factory responsible for creating favorites using the appropriate strategy
 * based on the given favorite type.
 *
 * It follows the Strategy design pattern to keep the creation logic clean
 * and scalable to multiple favorite types.
 */
final readonly class FavoriteFactory
{
    /**
     * Constructor.
     *
     * @param iterable<FavoriteStrategyInterface> $strategies a collection of strategies used to handle different favorite types
     */
    public function __construct(#[TaggedIterator('app.favorite_strategy')] private iterable $strategies)
    {
    }

    /**
     * Adds a favorite for the given user and target using the appropriate strategy
     * determined by the provided favorite type.
     *
     * @param FavoriteType  $type   the type of favorite to create
     * @param UserInterface $user   the user adding the favorite
     * @param mixed         $target The resource to be marked as favorite (e.g., playlist ID, track ID).
     *
     * @return Favorite the created favorite entity
     *
     * @throws \InvalidArgumentException if no matching strategy is found or the target is invalid
     */
    public function addFavorite(FavoriteType $type, UserInterface $user, mixed $target): Favorite
    {
        $strategy = $this->getStrategy($type);

        return $strategy->addFavorite($user, $target);
    }

    /**
     * Removes a favorite for the given user and target using the appropriate strategy.
     *
     * @param FavoriteType  $type   the type of favorite to remove (playlist, track, ...)
     * @param UserInterface $user   the user removing the favorite
     * @param mixed         $target the resource whose favorite must be removed (e.g. playlist id, entity, ...)
     *
     * @return bool true if a favorite was deleted, false if none existed
     *
     * @throws \InvalidArgumentException if no matching strategy is found or the target is invalid
     */
    public function removeFavorite(FavoriteType $type, UserInterface $user, mixed $target): bool
    {
        $strategy = $this->getStrategy($type);

        return $strategy->removeFavorite($user, $target);
    }

    /**
     * Retrieves the correct strategy that supports the given favorite type.
     *
     * @param FavoriteType $type the favorite type to resolve the strategy for
     *
     * @return FavoriteStrategyInterface the strategy able to handle the given type
     *
     * @throws \InvalidArgumentException if no strategy supports the provided type
     */
    private function getStrategy(FavoriteType $type): FavoriteStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($type->value)) {
                return $strategy;
            }
        }

        throw new \InvalidArgumentException(sprintf('No strategy found for favorite type "%s".', $type->value));
    }
}
