<?php

namespace App\Enum;

/**
 * Enum MoodType.
 *
 * Represents different types of moods.
 */
enum MoodType: string
{
    case HAPPY = 'happy';
    case SAD = 'sad';
    case ENERGETIC = 'energetic';
    case STRESSED = 'stressed';
    case CALM = 'calm';
}
