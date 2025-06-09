<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Repository\PlaylistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * Represents a music playlist in the application.
 *
 * This entity stores user-generated playlists and their associated data.
 * Each playlist includes:
 * - Title (`title`): The name of the playlist.
 * - Description (`description`): A summary or explanation of the playlist content.
 * - Mood (`mood`): The emotional tone or vibe associated with the playlist.
 * - Activity (`activity`): The activity context for which the playlist is intended.
 * - Created At (`createdAt`): The timestamp of playlist creation.
 * - Updated At (`updatedAt`): The timestamp of the last modification.
 * - User (`user`): The creator of the playlist.
 * - Tracks (`tracks`): A collection of tracks included in the playlist.
 *
 * Playlists are linked to users and can contain multiple tracks,
 * enabling mood- or activity-based music organization.
 */
#[ORM\Entity(repositoryClass:  PlaylistRepository::class)]
#[ORM\Table(name: 'playlist')]
class Playlist
{
    use IdTrait;
    use TimestampableEntity;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(type:'string', length: 50, enumType: MoodType::class)]
    private MoodType $mood;

    #[ORM\Column(type:'string', length: 50, enumType: ActivityType::class)]
    private ActivityType $activity;

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
