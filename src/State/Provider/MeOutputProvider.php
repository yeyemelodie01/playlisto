<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use App\ApiResource\MeOutput;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\State\ProviderInterface;

/**
 * Provides a MeOutput resource by transforming the currently authenticated user into a DTO.
 *
 * @implements ProviderInterface<MeOutput>
 */
final readonly class MeOutputProvider implements ProviderInterface
{
    /**
     * Constructor for MeOutputProvider.
     *
     * @param Security $security
     *
     * @psalm-suppress
     */
    public function __construct(private Security $security)
    {
    }

    /**
     * Provides a MeOutput DTO based on the currently authenticated user.
     *
     * @param Operation            $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return MeOutput|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?MeOutput
    {
        $user = $this->security->getUser();
        if (null === $user) {
            return null;
        }

        if (!method_exists($user, 'getEmail')) {
            throw new \LogicException('The UserInterface implementation must have a getEmail() method.');
        }

        return new MeOutput(
            email: $user->getEmail(),
            roles: $user->getRoles(),
            username: method_exists($user, 'getUsername') ? $user->getUsername() : null
        );
    }
}
