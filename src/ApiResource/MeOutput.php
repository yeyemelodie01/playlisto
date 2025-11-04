<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\State\Processor\MeProcessor;
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
        new Put(
            uriTemplate: '/me',
            description: 'Update the currently authenticated user',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: MeInput::class,
            output: self::class,
            provider: MeOutputProvider::class,
            processor: MeProcessor::class
        ),
        new Delete(
            uriTemplate: '/me',
            description: 'Delete the currently authenticated user',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: MeProcessor::class
        ),
    ],
    paginationEnabled: false
)]
final class MeOutput
{
    /**
     * @param string             $email
     * @param array<int, string> $roles
     * @param string|null        $username
     */
    public function __construct(public string $email, public array $roles, public ?string $username = null)
    {
    }
}
