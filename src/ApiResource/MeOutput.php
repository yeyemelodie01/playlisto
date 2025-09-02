<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Provider\MeOutputProvider;

/**
 * Data Transfer Object (DTO) for returning the current authenticated user's information.
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me',
            description: 'Get the currently authenticated user',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: 'GetCurrentUser',
            provider: MeOutputProvider::class
        ),
    ],
    paginationEnabled: false
)]
final class MeOutput
{
    /**
     * Constructor to initialize all properties.
     *
     * @param string  $email the user's email
     * @param array[] $roles the user's roles
     */
    public function __construct(public string $email, public array $roles)
    {
    }
}
