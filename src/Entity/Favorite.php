<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Enum\FavoriteType;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: FavoriteRepository::class)]
class Favorite
{
    use IdTrait;
    use TimestampableEntity;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserInterface $user = null;

    #[ORM\Column(enumType: FavoriteType::class)]
    private FavoriteType $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $targetId;

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): FavoriteType
    {
        return $this->type;
    }

    public function setType(FavoriteType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function setTargetId(string $targetId): self
    {
        $this->targetId = $targetId;
        return $this;
    }
}