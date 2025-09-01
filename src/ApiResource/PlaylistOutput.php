<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\Provider\PlaylistProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO exposé pour la lecture des playlists côté front.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/me/playlists',
            description: 'List playlists of the currently authenticated user',
            security: "is_granted('ROLE_USER')",
            name: 'GetMyPlaylists',
            provider: PlaylistProvider::class
        ),
    ],
    normalizationContext: ['groups' => ['playlist:read']]
)]
final class PlaylistOutput
{
    /** Identifiant de la playlist */
    #[Groups(['playlist:read'])]
    public int $id;

    /** Titre de la playlist */
    #[Groups(['playlist:read'])]
    public string $title;

    /** Description éventuelle */
    #[Groups(['playlist:read'])]
    public ?string $description;

    /** Humeur associée (enum value) */
    #[Groups(['playlist:read'])]
    public ?string $mood;

    /** Activité associée (enum value) */
    #[Groups(['playlist:read'])]
    public ?string $activity;

    /** Nombre de pistes contenues */
    #[Groups(['playlist:read'])]
    public int $trackCount;

    /** Date de création */
    #[Groups(['playlist:read'])]
    public ?\DateTimeInterface $createdAt;

    public function __construct(
        int $id,
        string $title,
        ?string $description = null,
        ?string $mood = null,
        ?string $activity = null,
        int $trackCount = 0,
        ?\DateTimeInterface $createdAt = null,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->mood = $mood;
        $this->activity = $activity;
        $this->trackCount = $trackCount;
        $this->createdAt = $createdAt;
    }
}
