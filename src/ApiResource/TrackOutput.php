<?php

namespace App\ApiResource;

use Symfony\Component\Serializer\Annotation\Groups;

final class TrackOutput
{
    #[Groups(['playlist:read'])]
    public int|string $id;

    #[Groups(['playlist:read'])]
    public string $title;

    #[Groups(['playlist:read'])]
    public array $artists;

    #[Groups(['playlist:read'])]
    public ?string $album = null;

    #[Groups(['playlist:read'])]
    public ?int $duration = null;

    #[Groups(['playlist:read'])]
    public ?string $spotifyId = null;

    #[Groups(['playlist:read'])]
    public ?string $coverUrl = null;

    #[Groups(['playlist:read'])]
    public ?string $previewUrl = null;

    public function __construct(int|string $id, string $title, array $artists = [], ?string $album = null, ?int $duration = null, ?string $spotifyId = null, ?string $coverUrl = null, ?string $previewUrl = null,)
    {
        $this->id = $id;
        $this->title = $title;
        $this->artists = $artists;
        $this->album = $album;
        $this->duration = $duration;
        $this->spotifyId = $spotifyId;
        $this->coverUrl = $coverUrl;
        $this->previewUrl = $previewUrl;
    }
}
