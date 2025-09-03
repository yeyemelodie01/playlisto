<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a user of the application.
 *
 * This entity contains information about the application's users, including:
 * - Email (`email`): The unique email address used for authentication.
 * - Roles (`roles`): An array of roles granted to the user (e.g., ROLE_USER, ROLE_ADMIN).
 * - Password (`password`): The hashed password for secure authentication.
 * - Username (`username`): The display name or pseudonym of the user.
 * - Created at (`createdAt`): The timestamp of account creation.
 * - Updated at (`updatedAt`): The timestamp of the last update.
 *
 * Relationships:
 * - One-to-many with `Playlist`: a user can create multiple playlists.
 *
 * This entity implements `UserInterface` and `PasswordAuthenticatedUserInterface`
 * for compatibility with Symfony's security system.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    /**
     * @var string The username or display name of the user
     */
    #[ORM\Column(length: 255)]
    private string $username;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $spotifyId = null;

    /**
     * @var Collection<int, Playlist>
     */
    #[ORM\OneToMany(targetEntity: Playlist::class, mappedBy: 'user')]
    private Collection $playlists;

    /**
     * @var Collection<int, Answer>
     */
    #[ORM\OneToMany(targetEntity: Answer::class, mappedBy: 'user')]
    private Collection $answers;

    #[ORM\OneToMany(targetEntity: Recommendation::class, mappedBy: 'user')]
    private Collection $recommendations;

    public function __construct()
    {
        $this->playlists = new ArrayCollection();
        $this->answers = new ArrayCollection();
        $this->recommendations = new ArrayCollection();
    }


    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     *
     * @return $this
     */
    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getSpotifyId(): ?string
    {
        return $this->spotifyId;
    }

    public function setSpotifyId(?string $spotifyId): static
    {
        $this->spotifyId = $spotifyId;
        return $this;
    }

    /**
     * @return Collection<int, Playlist>
     */
    public function getPlaylists(): Collection
    {
        return $this->playlists;
    }

    /**
     * @param Playlist $playlist
     *
     * @return $this
     */
    public function addPlaylist(Playlist $playlist): static
    {
        if (!$this->playlists->contains($playlist)) {
            $this->playlists->add($playlist);
            $playlist->setUser($this);
        }

        return $this;
    }

    /**
     * @param Playlist $playlist
     *
     * @return $this
     */
    public function removePlaylist(Playlist $playlist): static
    {
        if ($this->playlists->removeElement($playlist)) {
            // set the owning side to null (unless already changed)
            if ($playlist->getUser() === $this) {
                $playlist->setUser(null);
            }
        }

        return $this;
    }

    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function setAnswers(Collection $answers): void
    {
        $this->answers = $answers;
    }

    public function addAnswer(Answer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setUser($this);
        }

        return $this;
    }

    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            // set the owning side to null (unless already changed)
            if ($answer->getUser() === $this) {
                $answer->setUser(null);
            }
        }

        return $this;
    }

    public function getRecommendations(): Collection
    {
        return $this->recommendations;
    }

    public function setRecommendations(Collection $recommendations): void
    {
        $this->recommendations = $recommendations;
    }
}
