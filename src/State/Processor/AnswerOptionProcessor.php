<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SurveyAnswer;
use App\Entity\SurveySubmission;
use App\Entity\User;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\ApiResource\AnswerOptionInput;
use App\ApiResource\AnswerOptionResultOutput;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Processor for handling submitted answers and deducing mood, activity, and recommendation seeds.
 *
 * This class processes the input from the 'me/answers' endpoint, validates it, and applies simple rules to deduce
 * the user's mood, activity, and recommendation seeds based on their answers.
 *
 * @implements ProcessorInterface<AnswerOptionInput, AnswerOptionResultOutput>
 */
final readonly class AnswerOptionProcessor implements ProcessorInterface
{
    /**
     * @param Security $security
     *
     * @psalm-suppress
     */
    public function __construct(private Security $security, private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Processes the submitted answers and deduces mood, activity, and recommendation seeds.
     *
     * @param mixed                $data
     * @param Operation            $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return AnswerOptionResultOutput
     *
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($this->security->getUser() === null) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if (!$data instanceof AnswerOptionInput) {
            throw new BadRequestHttpException('Invalid payload.');
        }

        if (!isset($data->surveyId) || $data->surveyId <= 0) {
            throw new BadRequestHttpException('Invalid surveyId.');
        }
        if ($data->answers === []) {
            throw new BadRequestHttpException('answers must be a non-empty array.');
        }

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
        $selectedActivity = 'relax';
        $seeds = [];

        foreach ($data->answers as $item) {
            $questionId = (int) ($item['questionId'] ?? 0);
            $optionIds = $item['optionIds'] ?? [];
            if (!\is_array($optionIds)) {
                continue;
            }

            if (isset($optionIds[0], $moodMap[(int) $optionIds[0]]) && $questionId === 101) {
                $deducedMood = $moodMap[(int) $optionIds[0]];
            }

            if (isset($optionIds[0], $activityMap[(int) $optionIds[0]]) && $questionId === 102) {
                $selectedActivity = $activityMap[(int) $optionIds[0]];
            }

            if ($questionId === 103) {
                foreach ($optionIds as $answeroptionid) {
                    $answeroptionid = (int) $answeroptionid;
                    if (isset($genreSeeds[$answeroptionid])) {
                        $seeds[] = $genreSeeds[$answeroptionid];
                    }
                }
            }
        }

        $seeds = array_values(array_unique($seeds));

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            $userEntity = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null,
            ]);
            if (!$userEntity) {
                throw new AccessDeniedHttpException('Authenticated user entity not found.');
            }
            $user = $userEntity;
        }


        $submission = new SurveySubmission();
        $submission->setSurveyId((int) $data->surveyId);
        $submission->setUser($user);
        $submission->setDeducedMood(MoodType::from($deducedMood));
        $submission->setSelectedActivity(ActivityType::from($selectedActivity));
        $this->entityManager->persist($submission);

        foreach ($data->answers as $item) {
            $question = ($item['questionId'] ?? 0);
            $optionIds = $item['optionIds'] ?? [];
            if (!\is_array($optionIds)) {
                continue;
            }
            foreach ($optionIds as $answeroptionid) {
                $answer = new SurveyAnswer();
                $answer->setSubmission($submission);
                $answer->setQuestion($question);
                $answer->setAnswerOption($answeroptionid);
                $this->entityManager->persist($answer);
            }
        }

        $this->entityManager->flush();

        return new AnswerOptionResultOutput(
            surveyId: $data->surveyId,
            deducedMood: $deducedMood,
            selectedActivity: $selectedActivity,
            recommendationSeeds: $seeds ?: null
        );
    }
}
