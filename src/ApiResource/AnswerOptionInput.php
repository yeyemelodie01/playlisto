<?php

namespace App\ApiResource;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object (DTO) for handling answer-option input.
 *
 * This class is used to encapsulate the input data required for answer-option operations.
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
final class AnswerOptionInput
{
    /**
     * The ID of the survey this submission relates to.
     */
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public int $surveyId;

    /**
     * A non-empty list of per-question answers.
     * Each item must be an object with:
     *  - questionId: int (required)
     *  - optionIds: int[] (required, at least 1)
     *
     * @var array<int, array{questionId:int, optionIds: int[]}>
     */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Assert\All([
        new Assert\Collection([
            'fields' => [
                'questionId' => [new Assert\NotNull(), new Assert\Type('integer')],
                'optionIds'  => [new Assert\Type('array'), new Assert\Count(min: 1)],
            ],
            'allowExtraFields' => false,
            'allowMissingFields' => false,
        ])
    ])]
    public array $answers = [];
}
