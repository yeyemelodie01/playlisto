<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\QuestionOutput;
use App\ApiResource\SurveyOptionOutput;
use App\ApiResource\SurveyQuestionOutput;
use App\Entity\Question;
use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use UnitEnum;

final readonly class QuestionProvider implements ProviderInterface
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
     * Provides a QuestionOutput DTO based on the currently authenticated user.
     *
     * @param Operation            $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return QuestionOutput|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?QuestionOutput
    {
        if (null === $this->security->getUser()) {
            return null;
        }
        $qb = $this->entityManager->createQueryBuilder()
            ->select('q')
            ->addSelect('a')
            ->from(Question::class, 'q')
            ->leftJoin('q.answers', 'a')
            ->orderBy('q.id', 'ASC');

        $questions = $qb->getQuery()->getResult();

        $dto = new QuestionOutput(1, 'Mood & Activity', []);

        foreach ($questions as $q) {
            $typeEnum = method_exists($q, 'getType') ? $q->getType() : null;
            if ($typeEnum instanceof UnitEnum) {
                $type = ($typeEnum instanceof BackedEnum) ? $typeEnum->value : $typeEnum->name;
            } else {
                $type = is_string($typeEnum) ? $typeEnum : 'single_choice';
            }

            $labelVal = method_exists($q, 'getLabel') ? $q->getLabel() : null;
            $label = is_string($labelVal) ? $labelVal : ('Question #'.$q->getId());

            $qDto = new SurveyQuestionOutput($q->getId(), $type, $label, []);

            foreach ($q->getAnswers() as $answer) {
                $raw = method_exists($answer, 'getLabel') ? $answer->getLabel() : null;
                if ($raw instanceof UnitEnum) {
                    $optLabel = ($raw instanceof BackedEnum) ? $raw->value : $raw->name;
                } else {
                    $optLabel = is_string($raw) ? $raw : ('Option #'.$answer->getId());
                }

                $oDto = new SurveyOptionOutput($answer->getId(), $optLabel);
                $qDto->options[] = $oDto;
            }

            $dto->questions[] = $qDto;
        }

        return $dto;
    }
}
