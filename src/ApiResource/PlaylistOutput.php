<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Processor\PlaylistProcessor;
use App\State\Provider\PlaylistItemProvider;
use App\State\Provider\PlaylistProvider;
use DateTimeInterface;
use Symfony\Component\Serializer\Annotation\Groups;

use function count;

/** * Data Transfer Object (DTO) representing a playlist output for API responses.
 *
 * This resource provides:
 * - the playlist ID,
 * - the title,
 * - an optional description,
 * - associated mood and activity (as enum values),
 * - the number of tracks contained,
 * - an array of TrackOutput DTOs representing the tracks in the playlist,
 * - the creation date.
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/me/playlists',
            description: 'List playlists of the currently authenticated user',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: 'GetMyPlaylists',
            provider: PlaylistProvider::class
        ),
        new Get(
            uriTemplate: '/me/playlists/{id}',
            description: 'Get a specific playlist of the currently authenticated user',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: 'GetMyPlaylist',
            provider: PlaylistItemProvider::class
        ),
        new Post(
            uriTemplate: '/me/playlists',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: PlaylistInput::class,
            output: PlaylistOutput::class,
            name: 'CreateMyPlaylist',
            processor: PlaylistProcessor::class
        ),
        new Patch(
            uriTemplate: '/me/playlists/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: PlaylistInput::class,
            output: PlaylistOutput::class,
            name: 'UpdateMyPlaylist',
            provider: PlaylistItemProvider::class,
            processor: PlaylistProcessor::class
        ),
        new Delete(
            uriTemplate: '/me/playlists/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: false,
            output: false,
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
    public ?string $description = null;

    #[Groups(['playlist:read'])]
    public ?string $mood = null;

    #[Groups(['playlist:read'])]
    public ?string $activity = null;
    #[Groups(['playlist:read'])]
    public int $trackCount = 0;
    /**
     * @var TrackOutput[]
     */
    #[Groups(['playlist:read'])]
    public array $tracks = [];

    #[Groups(['playlist:read'])]
    public ?DateTimeInterface $createdAt = null;

    public function __construct(
        int $id,
        string $title,
        ?string $description = null,
        ?string $mood = null,
        ?string $activity = null,
        int $trackCount = 0,
        array $tracks = [],
        ?DateTimeInterface $createdAt = null,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->mood = $mood;
        $this->activity = $activity;
        $this->tracks = $tracks;
        $this->trackCount = $trackCount > 0 ? $trackCount : count($tracks);
        $this->createdAt = $createdAt;
    }
}
