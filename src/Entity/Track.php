<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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
class Track
{
    use IdTrait;

    private string $title;
    private string $artist;
    private string $album;
    private string $genre;
    private int $duration;
    private int $spotifyId;
    private string $coverUrl;

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
     * @return string
     */
    public function getArtist(): string
    {
        return $this->artist;
    }

    /**
     * @param string $artist
     *
     * @return void
     */
    public function setArtist(string $artist): void
    {
        $this->artist = $artist;
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
     * @return int
     */
    public function getSpotifyId(): int
    {
        return $this->spotifyId;
    }

    /**
     * @param int $spotifyId
     *
     * @return void
     */
    public function setSpotifyId(int $spotifyId): void
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
