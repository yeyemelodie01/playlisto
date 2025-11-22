<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use App\State\Processor\FavoriteDeleteProcessor;

/**
 * API resource output for favorite deletion.
 * Returned after successfully processing a favorite deletion request.
 */
#[ApiResource(
    operations: [
        new Delete(
            uriTemplate: '/{favoriteType}/{targetId}/delete-to-favorite',
            read: false,
            deserialize: false,
            name: 'deleteFavorite',
            processor: FavoriteDeleteProcessor::class
        ),
    ]
)]
final class FavoriteDeleteOutput
{
    /**
     * Favorite deletion response output.
     *
     * @param string|null $message a message describing the result of the favorite deletion
     */
    public function __construct(public ?string $message = null)
    {
    }
}
