<?php

namespace App\Entity\Traits;

use App\Entity\BaseUser;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Trait PasswordTrait.
 */
trait PasswordTrait
{
    /**
     * @var string|null
     */
    #[Ignore]
    #[ORM\Column(type: Types::STRING, length: 255, unique: false)]
    #[Assert\NotBlank(message: 'The password is required')]
    #[Assert\Length(min: 8, minMessage: 'The password must be at least 8 characters long')]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
        message: 'the password must contain at least one letter, one number and one special character'
    )]
    protected ?string $password = null;

    /**
     * @return string|null
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return BaseUser|PasswordTrait
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
