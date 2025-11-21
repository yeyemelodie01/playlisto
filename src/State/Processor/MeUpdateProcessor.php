<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MeInput;
use App\ApiResource\MeOutput;
use App\Entity\User;
use App\Repository\UserRepository;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function assert;

final readonly class MeUpdateProcessor implements ProcessorInterface
{
    /**
     * @param Security                    $security
     * @param UserRepository              $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     */
    public function __construct(private Security $security, private UserRepository $userRepository, private UserPasswordHasherInterface $passwordHasher,)
    {
    }

    /**
     * @param mixed     $data
     * @param Operation $operation
     * @param array     $uriVariables
     * @param array     $context
     *
     * @return mixed
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new RuntimeException('User not authenticated');
        }
            assert($data instanceof MeInput);

            if (null === $data->currentPassword || '' === trim($data->currentPassword)) {
                throw new RuntimeException('Le mot de passe actuel est requis pour modifier votre profil.');
            }

            if (!$this->passwordHasher->isPasswordValid($user, $data->currentPassword)) {
                throw new RuntimeException('Le mot de passe actuel est incorrect.');
            }

            if ($data->email && $data->email !== $user->getEmail()) {
                $user->setEmail($data->email);
            }

            if ($data->username && method_exists($user, 'setUsername')) {
                $user->setUsername($data->username);
            }

            if ($data->password) {
                $hashed = $this->passwordHasher->hashPassword($user, $data->password);
                $user->setPassword($hashed);
            }

            $this->userRepository->save($user, true);

            return new MeOutput(
                email: $user->getEmail(),
                roles: $user->getRoles(),
                username: method_exists($user, 'getUsername') ? $user->getUsername() : null
            );

    }
}
