<?php

namespace App\Entity;

use App\Entity\Traits\ActiveTrait;
use App\Entity\Traits\IdTrait;
use App\Entity\Traits\PasswordTrait;
use App\Repository\AdministratorRepository;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents an administrator user with access to the admin panel.
 */
#[ORM\Entity(repositoryClass: AdministratorRepository::class)]
#[ORM\Table(name: 'administrator')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class Administrator implements UserInterface, PasswordAuthenticatedUserInterface
{
    use IdTrait;
    use ActiveTrait;
    use PasswordTrait;
    use TimestampableEntity;

    /**
     * @var string
     */
    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank]
    private string $firstName = '';

    /**
     * @var string
     */
    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank]
    private string $lastName = '';

    /**
     * @var string|null
     */
    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email cannot be empty.')]
    #[Assert\Email(message: 'Invalid email format.')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot exceed 180 characters.')]
    #[Assert\Regex(pattern: "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/", message: 'Invalid email format.')]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var bool
     */
    #[ORM\Column(type: 'boolean')]
    private bool $superAdministrator = false;

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     *
     * @return void
     */
    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     *
     * @return void
     */
    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     *
     * @return void
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return bool
     */
    public function isSuperAdministrator(): bool
    {
        return $this->superAdministrator;
    }

    /**
     * @param bool $superAdministrator
     *
     * @return void
     */
    public function setSuperAdministrator(bool $superAdministrator): void
    {
        $this->superAdministrator = $superAdministrator;
    }

    /**
     * @return array|string[]
     */
    #[\Override]
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
    #[\Override]
    public function getUserIdentifier(): string
    {
        if (null === $this->email || '' === $this->email) {
            throw new \LogicException('L\'email ne peut pas être vide.');
        }

        return $this->email;
    }
}
