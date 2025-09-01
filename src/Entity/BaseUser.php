<?php

namespace App\Entity;

use App\Entity\Traits\ActiveTrait;
use App\Entity\Traits\IdTrait;
use App\Entity\Traits\PasswordTrait;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Abstract class BaseUser.
 *
 * This class serves as a base for user-related entities, providing common properties
 * and methods for managing user information such as email, roles, password, and timestamps.
 *
 * It includes traits for ID management, timestamping, active status, and password handling.
 */
#[ORM\Entity]
#[ORM\Table(name: 'base_user')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'dtype', type: 'string')]
#[ORM\DiscriminatorMap(['user' => \App\Entity\User::class, 'admin' => \App\Entity\Administrator::class])]
abstract class BaseUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    use IdTrait;
    use TimestampableEntity;
    use ActiveTrait;
    use PasswordTrait;

    /**
     * @var string|null The email of the user
     */
    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email cannot be empty.')]
    #[Assert\Email(message: 'Invalid email format.')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot exceed 180 characters.')]
    #[Assert\Regex(pattern: "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", message: 'Invalid email format.')]
    protected ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column(type: 'json')]
    protected array $roles = [];

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return array|string[]
     */
    #[Override]
    public function getRoles(): array
    {
        $roles = $this->roles;
        // garantir que chaque utilisateur possède au moins ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     *
     * @return $this
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @return string
     */
    #[Override]
    public function getUserIdentifier(): string
    {
        if (null === $this->email || '' === $this->email) {
            throw new \LogicException('L\'email ne peut pas être vide.');
        }

        return $this->email;
    }
}
