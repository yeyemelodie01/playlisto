<?php

namespace App\ApiResource;

use App\Enum\ActivityType;
use App\Enum\MoodType;
use Symfony\Component\Serializer\Annotation\Groups;
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
    #[Assert\NotBlank(message: 'The title field is required.')]
    #[Assert\Length(min: 1, max: 140)]
    #[Groups(['playlist:write'])]
    public string $title;

    #[Assert\Length(max: 500)]
    #[Groups(['playlist:write'])]
    public ?string $description = null;

    #[Assert\Choice(callback: [PlaylistInput::class, 'allowedMoods'], message: 'Choose a valid mood.')]
    public ?string $mood = null;

    #[Assert\Choice(callback: [PlaylistInput::class, 'allowedActivities'], message: 'Choose a valid activity.')]
    public ?string $activity = null;

    #[Groups(['playlist:write'])]
    public ?array $trackIds = null;

    /**
     * @return array<string>
     */
    public static function allowedMoods(): array
    {
        return array_map(static fn(MoodType $c) => $c->value, MoodType::cases());
    }

    /**
     * @return array<string>
     */
    public static function allowedActivities(): array
    {
        return array_map(static fn(ActivityType $c) => $c->value, ActivityType::cases());
    }
}
