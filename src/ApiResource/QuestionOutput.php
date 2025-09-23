<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Provider\QuestionProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Data Transfer Object (DTO) for returning the active question for the current user.
 *
 * This class encapsulates the question ID, title, and a list of associated survey questions.
 * * @psalm-suppress PossiblyUnusedProperty
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: 'me/questions',
            description: 'Get active question for the current user',
            security: "is_granted('ROLE_USER')",
            name: 'GetActiveQuestion',
            provider: QuestionProvider::class
        ),
    ],
    normalizationContext: ['groups' => ['question:read']],
)]
final class QuestionOutput
{
    #[Groups(['question:read'])]
    public int $id;

    #[Groups(['question:read'])]
    public string $label;

    /** @var SurveyQuestionOutput[] */
    #[Groups(['question:read'])]
    public array $questions = [];

    /**
     * Constructor to initialize all properties.
     *
     * @param int                    $id        the question ID
     * @param string                 $label     the question title
     * @param SurveyQuestionOutput[] $questions the list of survey questions
     */
    public function __construct(int $id, string $label, array $questions)
    {
        $this->id = $id;
        $this->label = $label;
        $this->questions = $questions;
    }
}
