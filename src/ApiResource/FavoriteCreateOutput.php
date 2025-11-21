<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Processor\FavoriteCreateProcessor;

/**
 * API resource output for favorite creation.
 * Returned after successfully processing a favorite creation request.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/{favoriteType}/add-to-favorite',
            deserialize: false,
            name: 'createFavorite',
            processor: FavoriteCreateProcessor::class
        ),
    ]
)]
final class FavoriteCreateOutput
{
    /**
     * Favorite creation response output.
     *
     * @param string|null $message A message describing the result of the favorite creation.
     */
    public function __construct(public ?string $message = null)
    {
    }
}