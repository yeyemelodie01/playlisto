<?php

namespace App\ApiResource;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object (DTO) for handling answer input.
 *
 * This class is used to encapsulate the input data required for answer operations.
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
final class AnswerOptionInput
{
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public int $surveyId;

    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Assert\All([
        new Assert\Collection([
            'fields' => ['questionId' => [new Assert\NotNull(), new Assert\Type('integer')], 'optionIds'  => [new Assert\Type('array'), new Assert\Count(min: 1)],
            ],
            'allowExtraFields' => false,
            'allowMissingFields' => false,
        ])
    ])]
    public array $answers = [];
}
