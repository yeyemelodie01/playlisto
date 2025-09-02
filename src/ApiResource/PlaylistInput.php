<?php

namespace App\ApiResource;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object (DTO) for creating or updating a playlist.
 *
 * This class is used to encapsulate the input data required for playlist operations.
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
final class PlaylistInput
{
    #[Assert\NotBlank]
    public string $title;

    public ?string $description = null;

    public ?string $mood = null;

    public ?string $activity = null;
    public ?array $trackIds = null;
}
