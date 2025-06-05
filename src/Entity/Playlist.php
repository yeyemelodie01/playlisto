<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Class Playlist
 * Represents a playlist in the system.
 */
class Playlist
{
    use IdTrait;

    private string $title;
    private string $description;
    private string $mood;
    private string $activity;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'playlists')]
    private ?User $user = null;

    /**
     * @var Collection<int, Track>
     */
    #[ORM\ManyToMany(targetEntity: Track::class, inversedBy: 'playlists')]
    private Collection $tracks;

    public function __construct()
    {
        $this->tracks = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getMood(): string
    {
        return $this->mood;
    }

    /**
     * @param string $mood
     *
     * @return void
     */
    public function setMood(string $mood): void
    {
        $this->mood = $mood;
    }

    /**
     * @return string
     */
    public function getActivity(): string
    {
        return $this->activity;
    }

    /**
     * @param string $activity
     *
     * @return void
     */
    public function setActivity(string $activity): void
    {
        $this->activity = $activity;
    }

    /**
     * @return DateTime|null
     */
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime|null $createdAt
     *
     * @return void
     */
    public function setCreatedAt(?DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return DateTime|null
     */
    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTime|null $updatedAt
     */
    public function setUpdatedAt(?DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     *
     * @return $this
     */
    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Track>
     */
    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    /**
     * @param Track $track
     *
     * @return $this
     */
    public function addTrack(Track $track): static
    {
        if (!$this->tracks->contains($track)) {
            $this->tracks->add($track);
        }

        return $this;
    }

    /**
     * @param Track $track
     *
     * @return $this
     */
    public function removeTrack(Track $track): static
    {
        $this->tracks->removeElement($track);

        return $this;
    }
}
