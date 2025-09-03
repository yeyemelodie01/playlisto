<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Processor\AnswerProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * API Resource for submitting question answers and receiving a deduced mood and activity profile.
 *
 * This class serves as the output structure for the 'me/answers' endpoint, processed by AnswerProcessor.
 *
 * @psalm-suppress PossiblyUnusedProperty
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/me/answers',
            description: 'Submit question answers and get a deduced mood and activity profile.',
            security: "is_granted('ROLE_USER')",
            input: AnswerInput::class,
            output: AnswerResultOutput::class,
            name: 'SubmitAnswers',
            processor: AnswerProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['answer:result:read']],
)]
final class AnswerResultOutput
{
    #[Groups(['answer:result:read'])]
    public int $surveyId;

    #[Groups(['answer:result:read'])]
    public string $deducedMood;

    #[Groups(['answer:result:read'])]
    public string $deducedActivity;

    #[Groups(['answer:result:read'])]
    public ?array $recommendationSeeds = null;

    /**
     * Constructor to initialize all properties.
     *
     * @param int         $surveyId            the survey ID
     * @param string      $deducedMood         the deduced mood
     * @param string      $deducedActivity     the deduced activity
     * @param string[]|null $recommendationSeeds optional array of recommendation seeds
     */
    public function __construct(int $surveyId, string $deducedMood, string $deducedActivity, ?array $recommendationSeeds = null)
    {
        $this->surveyId = $surveyId;
        $this->deducedMood = $deducedMood;
        $this->deducedActivity = $deducedActivity;
        $this->recommendationSeeds = $recommendationSeeds;
    }
}
