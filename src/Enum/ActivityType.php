<?php

namespace App\Enum;

/**
 * Enum ActivityType.
 *
 * Represents different types of activities.
 */
enum ActivityType: string
{
    case SPORT = 'sport';
    case WORK = 'work';
    case RELAX = 'relax';
    case STUDY = 'study';
    case COOKING = 'cooking';
}
