<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\ApiResource\AnswerInput;
use App\ApiResource\AnswerResultOutput;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Processor for handling submitted answers and deducing mood, activity, and recommendation seeds.
 *
 * This class processes the input from the 'me/answers' endpoint, validates it, and applies simple rules to deduce
 * the user's mood, activity, and recommendation seeds based on their answers.
 *
 * @implements ProcessorInterface<AnswerInput, AnswerResultOutput>
 */
final readonly class AnswerProcessor implements ProcessorInterface
{
    /**
     * Constructor for AnswerProcessor.
     *
     * @param Security $security the security component used to fetch the current authenticated user
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(private Security $security)
    {
    }

    /**
     * Processes the submitted answers and deduces mood, activity, and recommendation seeds.
     *
     * @param mixed                $data         The input data, expected to be an instance of AnswerInput.
     * @param Operation            $operation    The operation being performed (POST, etc.).
     * @param array<string, mixed> $uriVariables an array of URI variables (unused here)
     * @param array<string, mixed> $context      additional context passed by API Platform
     *
     * @return AnswerResultOutput the result containing deduced mood, activity, and recommendation seeds
     *
     * @throws AccessDeniedHttpException if the user is not authenticated
     * @throws BadRequestHttpException   if the input data is invalid
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Ensure the user is authenticated
        if (null === $this->security->getUser()) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        // Validate payload type
        if (!$data instanceof AnswerInput) {
            throw new BadRequestHttpException('Invalid payload.');
        }

        // Basic validation
        if (!isset($data->surveyId) || $data->surveyId <= 0) {
            throw new BadRequestHttpException('Invalid surveyId.');
        }
        if (!\is_array($data->answers) || $data->answers === []) {
            throw new BadRequestHttpException('answers must be a non-empty array.');
        }

        // Simple deduction rules (adapt to your enums / database as needed)
        $moodMap = [
            1 => 'happy',
            2 => 'calm',
            3 => 'energetic',
            4 => 'stressed',
        ];
        $activityMap = [
            10 => 'work',
            11 => 'study',
            12 => 'relax',
            13 => 'sport',
        ];
        $genreSeeds = [
            20 => 'lofi',
            21 => 'pop',
            22 => 'hip-hop',
            23 => 'jazz',
        ];

        $deducedMood = 'calm';
        $deducedActivity = 'relax';
        $seeds = [];

        foreach ($data->answers as $item) {
            $questionId = (int)($item['questionId'] ?? 0);
            $optionIds  = $item['optionIds'] ?? [];
            if (!\is_array($optionIds)) {
                continue;
            }

            if ($questionId === 101 && isset($optionIds[0]) && isset($moodMap[(int)$optionIds[0]])) {
                $deducedMood = $moodMap[(int)$optionIds[0]];
            }

            if ($questionId === 102 && isset($optionIds[0]) && isset($activityMap[(int)$optionIds[0]])) {
                $deducedActivity = $activityMap[(int)$optionIds[0]];
            }

            if ($questionId === 103) {
                foreach ($optionIds as $oid) {
                    $oid = (int)$oid;
                    if (isset($genreSeeds[$oid])) {
                        $seeds[] = $genreSeeds[$oid];
                    }
                }
            }
        }

        $seeds = array_values(array_unique($seeds));

        // TODO: persist a SurveySubmission + SurveyAnswer entities if needed

        return new AnswerResultOutput(
            surveyId: (int)$data->surveyId,
            deducedMood: $deducedMood,
            deducedActivity: $deducedActivity,
            recommendationSeeds: $seeds ?: null
        );
    }
}
