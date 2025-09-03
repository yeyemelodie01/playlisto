<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\QuestionOutput;
use App\ApiResource\SurveyOptionOutput;
use App\ApiResource\SurveyQuestionOutput;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class QuestionProvider implements ProviderInterface
{
    /**
     * Constructor for QuestionProvider.
     *
     * @param Security $security the security component used to fetch the current authenticated user
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(private Security $security)
    {
    }

    /**
     * Provides a QuestionOutput DTO based on the currently authenticated user.
     *
     * @param Operation            $operation    The operation being performed (GET, etc.).
     * @param array<string, mixed> $uriVariables an array of URI variables (unused here)
     * @param array<string, mixed> $context      additional context passed by API Platform
     *
     * @return QuestionOutput|null returns a QuestionOutput object if a user is authenticated, null otherwise
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?QuestionOutput
    {
        if (null === $this->security->getUser()) {
            return null;
        }

        $questions = [
            new SurveyQuestionOutput(
                id: 101,
                type: 'single_choice',
                label: 'How do you feel right now?',
                options: [
                    new SurveyOptionOutput(1, 'Happy'),
                    new SurveyOptionOutput(2, 'Calm'),
                    new SurveyOptionOutput(3, 'Energetic'),
                    new SurveyOptionOutput(4, 'Stressed'),
                ]
            ),
            new SurveyQuestionOutput(
                id: 102,
                type: 'single_choice',
                label: 'What are you doing?',
                options: [
                    new SurveyOptionOutput(10, 'Work'),
                    new SurveyOptionOutput(11, 'Study'),
                    new SurveyOptionOutput(12, 'Relax'),
                    new SurveyOptionOutput(13, 'Sport'),
                ]
            ),
            new SurveyQuestionOutput(
                id: 103,
                type: 'multiple_choice',
                label: 'Preferred genres',
                options: [
                    new SurveyOptionOutput(20, 'Lofi'),
                    new SurveyOptionOutput(21, 'Pop'),
                    new SurveyOptionOutput(22, 'Hip-Hop'),
                    new SurveyOptionOutput(23, 'Jazz'),
                ]
            ),
        ];

        return new QuestionOutput(
            id: 1,
            title: 'Mood & Activity',
            questions: $questions
        );
    }
}
