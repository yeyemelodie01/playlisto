<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Processor\PlaylistProcessor;
use App\State\Provider\PlaylistItemProvider;
use App\State\Provider\PlaylistProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/** * Data Transfer Object (DTO) representing a playlist output for API responses.
 *
 * This resource provides:
 * - the playlist ID,
 * - the title,
 * - an optional description,
 * - associated mood and activity (as enum values),
 * - the number of tracks contained,
 * - the creation date.
 *
 * @psalm-suppress PossiblyUnusedProperty
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
        new Get(
            uriTemplate: '/me/playlists/{id}',
            description: 'Get a specific playlist of the currently authenticated user',
            security: "is_granted('ROLE_USER')",
            name: 'GetMyPlaylist',
            provider: PlaylistItemProvider::class
        ),
        new Post(
            uriTemplate: '/me/playlists',
            security: "is_granted('ROLE_USER')",
            input: PlaylistInput::class,
            output: PlaylistOutput::class,
            name: 'CreateMyPlaylist',
            processor: PlaylistProcessor::class
        ),
        new Patch(
            uriTemplate: '/me/playlists/{id}',
            security: "is_granted('ROLE_USER')",
            input: PlaylistInput::class,
            output: PlaylistOutput::class,
            name: 'UpdateMyPlaylist',
            provider: PlaylistItemProvider::class,
            processor: PlaylistProcessor::class
        ),
        new Delete(
            uriTemplate: '/me/playlists/{id}',
            security: "is_granted('ROLE_USER')",
            name: 'DeleteMyPlaylist',
            provider: PlaylistItemProvider::class,
            processor: PlaylistProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['playlist:read']]
)]
final class PlaylistOutput
{
    #[Groups(['playlist:read'])]
    public int $id;

    #[Groups(['playlist:read'])]
    public string $title;

    #[Groups(['playlist:read'])]
    public ?string $description;

    #[ApiProperty(openapiContext: ['enum' => ['happy', 'sad', 'energetic', 'stressed', 'calm']])]
    #[Groups(['playlist:read'])]
    public ?string $mood;

    #[ApiProperty(openapiContext: ['enum' => ['sport', 'work', 'relax', 'study', 'cooking']])]
    #[Groups(['playlist:read'])]
    public ?string $activity;
    #[Groups(['playlist:read'])]
    public int $trackCount;

    #[ApiProperty(example: '2025-09-01T10:00:00+00:00')]
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
