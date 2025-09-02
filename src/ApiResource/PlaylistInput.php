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
    #[Assert\Length(min: 1, max: 140)]
    public string $title;

    #[Assert\Length(max: 500)]
    public ?string $description = null;

    #[Assert\Choice(choices: ['happy', 'sad', 'energetic', 'stressed', 'calm'], message: 'Choose a valid mood.')]
    public ?string $mood = null;

    #[Assert\Choice(choices: ['sport', 'work', 'relax', 'study', 'cooking'], message: 'Choose a valid activity.')]
    public ?string $activity = null;

    #[Assert\All(constraints: [new Assert\Type('integer'), new Assert\Positive()])]
    public ?array $trackIds = null;
}
