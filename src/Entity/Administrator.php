<?php

namespace App\Entity;

use App\Repository\AdministratorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents an administrator user with access to the admin panel.
 */
#[ORM\Entity(repositoryClass: AdministratorRepository::class)]
#[ORM\Table(name: 'administrator')]
class Administrator extends BaseUser
{
    /**
     * @var string
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank]
    private string $firstName = '';

    /**
     * @var string
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank]
    private string $lastName = '';

    /**
     * @var bool
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
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
     * @return Administrator
     */
    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
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
}
