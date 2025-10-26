<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Repository\PlaylistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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
#[ORM\Table(
    name: 'playlist',
    indexes: [
        new ORM\Index(name: 'idx_playlist_user', columns: ['user_id']),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_user_title', columns: ['user_id', 'title']),
    ]
)]
class Playlist
{
    use IdTrait;
    use TimestampableEntity;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $description;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: MoodType::class)]
    private MoodType $mood;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: ActivityType::class)]
    private ActivityType $activity;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'playlists')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /**
     * @var Collection<int, Track>
     */
    #[ORM\ManyToMany(targetEntity: Track::class, inversedBy: 'playlists')]
    #[ORM\JoinTable(name: 'playlist_track')]
    #[ORM\OrderBy(['title' => 'ASC'])]
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
     * @return MoodType
     */
    public function getMood(): MoodType
    {
        return $this->mood;
    }

    /**
     * @param MoodType $mood
     *
     * @return void
     */
    public function setMood(MoodType $mood): void
    {
        $this->mood = $mood;
    }

    /**
     * @return ActivityType
     */
    public function getActivity(): ActivityType
    {
        return $this->activity;
    }

    /**
     * @param ActivityType $activity
     *
     * @return void
     */
    public function setActivity(ActivityType $activity): void
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
            if (method_exists($track, 'addPlaylist')) {
                $track->addPlaylist($this);
            }
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
        if ($this->tracks->removeElement($track)) {
            if (method_exists($track, 'removePlaylist')) {
                $track->removePlaylist($this);
            }
        }

        return $this;
    }
}
