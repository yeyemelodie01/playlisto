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
     * @param Security $security the security component used to fetch the current authenticated user
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(private Security $security)
    {
    }

    /**
     * Provides a MeOutput DTO based on the currently authenticated user.
     *
     * @param Operation            $operation    The operation being performed (GET, etc.).
     * @param array<string, mixed> $uriVariables an array of URI variables (unused here)
     * @param array<string, mixed> $context      additional context passed by API Platform
     *
     * @return MeOutput|null returns a MeOutput object if a user is authenticated, null otherwise
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?MeOutput
    {
        unset($operation, $uriVariables, $context);
        $user = $this->security->getUser();

        if (null === $user) {
            return null;
        }

        if (!method_exists($user, 'getEmail')) {
            throw new \LogicException('The UserInterface implementation must have a getEmail() method.');
        }

        return new MeOutput($user->getEmail(), $user->getRoles());
    }
}
