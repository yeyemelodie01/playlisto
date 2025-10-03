<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\TrackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a music track in the application.
 *
 * This entity contains metadata related to an audio track.
 * Each track includes:
 * - Title (`title`): The name of the track.
 * - Artist (`artist`): The performer or group.
 * - Album (`album`): The album from which the track originates.
 * - Genre (`genre`): The musical genre classification.
 * - Duration (`duration`): Track length in seconds.
 * - Spotify ID (`spotifyId`): Identifier for integration with Spotify.
 * - Cover URL (`coverUrl`): Link to the album or track's cover image.
 *
 * Tracks can belong to multiple playlists through a many-to-many relationship.
 */
#[ORM\Entity(repositoryClass: TrackRepository::class)]
#[ORM\Table(name: 'track')]
class Track
{
    use IdTrait;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private array $artists;

    #[ORM\Column(length: 255)]
    private string $album;

    #[ORM\Column(length: 255)]
    private string $genre;

    #[ORM\Column(type: 'integer')]
    private int $duration;

    #[ORM\Column(length: 64)]
    private string $spotifyId;

    #[ORM\Column(length: 255)]
    private string $coverUrl;

    #[ORM\Column(length: 255, nullable: true)]
    private string $previewUrl;

    /**
     * @var Collection<int, Playlist>
     */
    #[ORM\ManyToMany(targetEntity: Playlist::class, mappedBy: 'tracks')]
    private Collection $playlists;

    public function __construct()
    {
        $this->playlists = new ArrayCollection();
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
     * @return array
     */
    public function getArtists(): array
    {
        return $this->artists;
    }

    /**
     * @param array $artists
     *
     * @return void
     */
    public function setArtists(array $artists): void
    {
        $this->artists = $artists;
    }

    /**
     * @return string
     */
    public function getAlbum(): string
    {
        return $this->album;
    }

    /**
     * @param string $album
     *
     * @return void
     */
    public function setAlbum(string $album): void
    {
        $this->album = $album;
    }

    /**
     * @return string
     */
    public function getGenre(): string
    {
        return $this->genre;
    }

    /**
     * @param string $genre
     *
     * @return void
     */
    public function setGenre(string $genre): void
    {
        $this->genre = $genre;
    }

    /**
     * @return int
     */
    public function getDuration(): int
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     *
     * @return void
     */
    public function setDuration(int $duration): void
    {
        $this->duration = $duration;
    }

    /**
     * @return string
     */
    public function getSpotifyId(): string
    {
        return $this->spotifyId;
    }

    /**
     * @param string $spotifyId
     *
     * @return void
     */
    public function setSpotifyId(string $spotifyId): void
    {
        $this->spotifyId = $spotifyId;
    }

    /**
     * @return string
     */
    public function getCoverUrl(): string
    {
        return $this->coverUrl;
    }

    /**
     * @param string $coverUrl
     *
     * @return void
     */
    public function setCoverUrl(string $coverUrl): void
    {
        $this->coverUrl = $coverUrl;
    }

    public function getPreviewUrl(): string
    {
        return $this->previewUrl;
    }

    public function setPreviewUrl(string $previewUrl): void
    {
        $this->previewUrl = $previewUrl;
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
            $playlist->addTrack($this);
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
            $playlist->removeTrack($this);
        }

        return $this;
    }
}
