<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Processor\FavoriteCreateProcessor;

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
     * Constructor.
     *
     * @param string|null $message
     */
    public function __construct(public ?string $message = null)
    {
    }
}