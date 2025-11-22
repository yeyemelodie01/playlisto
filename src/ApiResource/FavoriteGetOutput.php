<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\Provider\FavoriteGetProvider;

/**
 * API resource for retrieving the authenticated user's favorites.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/favorites',
            description: 'Get the favorites',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: 'Getfavorites',
            provider: FavoriteGetProvider::class
        ),
    ],
    paginationEnabled: false
)]
final class FavoriteGetOutput
{
    public function __construct(public int $id, public string $title, public ?int $trackCount, public ?\DateTimeInterface $createdAt)
    {
    }
}
