<?php

namespace App\Factory;

use App\Entity\Favorite;
use App\Enum\FavoriteType;
use App\Strategy\FavoriteStrategyInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class FavoriteFactory
{
    /**
     * @param iterable<FavoriteStrategyInterface> $strategies
     */
    public function __construct(#[TaggedIterator('app.favorite_strategy')] private iterable $strategies)
    {
    }

    public function addFavorite(FavoriteType $type, UserInterface $user, mixed $target): Favorite
    {
        $strategy = $this->getStrategy($type);

        return $strategy->addFavorite($user, $target);
    }

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
