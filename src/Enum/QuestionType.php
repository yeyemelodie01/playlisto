<?php

namespace App\Enum;

/**
 * Enum QuestionType.
 *
 * Represents different types of questions.
 */
enum QuestionType: string
{
    case SINGLE = 'single';
    case MULTIPLE = 'multiple';
}
