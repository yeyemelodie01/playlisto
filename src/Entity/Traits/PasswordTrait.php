<?php

namespace App\Entity\Traits;

/**
 * Trait PasswordTrait.
 */
trait PasswordTrait
{
    /**
     * @var string|null
     */
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
     * @return $this
     */
    public function setPassword(string $password): static
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
