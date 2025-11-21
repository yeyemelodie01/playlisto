<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
 * - Spotify ID (`spotifyId`): An optional identifier linking to the user's Spotify account.
 * - Last login at (`lastLoginAt`): The timestamp of the user's last login.
 *
 * Relationships:
 * - One-to-many with `Playlist`: a user can create multiple playlists.
 * - One-to-many with `Recommendation`: a user can receive multiple recommendations.
 *
 * This entity implements `UserInterface` and `PasswordAuthenticatedUserInterface`
 * for compatibility with Symfony's security system.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $username = '';

    #[ORM\Column(type: Types::STRING, length: 64, unique: true, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $spotifyId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastLoginAt = null;

    #[ORM\OneToMany(targetEntity: Playlist::class, mappedBy: 'user')]
    private Collection $playlists;

    #[ORM\OneToMany(targetEntity: SurveySubmission::class, mappedBy: 'user')]
    private Collection $surveySubmissions;

    public function __construct()
    {
        $this->playlists = new ArrayCollection();
        $this->surveySubmissions = new ArrayCollection();
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

    /**
     * @return string|null
     */
    public function getSpotifyId(): ?string
    {
        return $this->spotifyId;
    }

    /**
     * @param string|null $spotifyId
     *
     * @return $this
     */
    public function setSpotifyId(?string $spotifyId): static
    {
        $this->spotifyId = $spotifyId;

        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    /**
     * @param DateTimeImmutable|null $lastLoginAt
     *
     * @return void
     */
    public function setLastLoginAt(?DateTimeImmutable $lastLoginAt): void
    {
        $this->lastLoginAt = $lastLoginAt;
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
            if ($playlist->getUser() === $this) {
                $playlist->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SurveySubmission>
     */
    public function getSurveySubmissions(): Collection
    {
        return $this->surveySubmissions;
    }

    /**
     * @param Collection $surveySubmissions
     */
    public function setSurveySubmissions(Collection $surveySubmissions): void
    {
        $this->surveySubmissions = $surveySubmissions;
    }

    /**
     * @param SurveySubmission $surveySubmission
     *
     * @return $this
     */
    public function addSurveySubmission(SurveySubmission $surveySubmission): static
    {
        if (!$this->surveySubmissions->contains($surveySubmission)) {
            $this->surveySubmissions->add($surveySubmission);
            $surveySubmission->setUser($this);
        }

        return $this;
    }

    /**
     * @param SurveySubmission $surveySubmission
     *
     * @return $this
     */
    public function removeSurveySubmission(SurveySubmission $surveySubmission): static
    {
        if ($this->surveySubmissions->removeElement($surveySubmission)) {
            if ($surveySubmission->getUser() === $this) {
                $surveySubmission->setUser(null);
            }
        }

        return $this;
    }
}
