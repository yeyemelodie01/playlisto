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
     * Constructor for QuestionProvider.
     *
     * @param Security $security the security component used to fetch the current authenticated user
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(private Security $security, private EntityManagerInterface $entityManager)
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
        $qb = $this->entityManager->createQueryBuilder()
            ->select('q')           // point de départ
            ->addSelect('a')        // <- on ajoute l’alias 'a' qu’on va joindre
            ->from(Question::class, 'q')
            ->leftJoin('q.answers', 'a')   // <- la bonne association d’après tes entités
            ->orderBy('q.id', 'ASC');

        $questions = $qb->getQuery()->getResult();

        $dto = new QuestionOutput(1, 'Mood & Activity', []);

        foreach ($questions as $q) {
            $typeEnum = method_exists($q, 'getType') ? $q->getType() : null;
            if ($typeEnum instanceof UnitEnum) {
                // Backed enum -> value, sinon -> name
                $type = ($typeEnum instanceof BackedEnum) ? $typeEnum->value : $typeEnum->name;
            } else {
                // si déjà une string ou null
                $type = is_string($typeEnum) ? $typeEnum : 'single_choice';
            }

            $labelVal = method_exists($q, 'getLabel') ? $q->getLabel() : null;
            $label = is_string($labelVal) ? $labelVal : ('Question #' . $q->getId());

            $qDto = new SurveyQuestionOutput($q->getId(), $type, $label, []);

            foreach ($q->getAnswers() as $answer) {
                $raw = method_exists($answer, 'getLabel') ? $answer->getLabel() : null;
                if ($raw instanceof UnitEnum) {
                    $optLabel = ($raw instanceof BackedEnum) ? $raw->value : $raw->name;
                } else {
                    $optLabel = is_string($raw) ? $raw : ('Option #' . $answer->getId());
                }

                $oDto = new SurveyOptionOutput($answer->getId(), $optLabel);
                $qDto->options[] = $oDto;
            }

            $dto->questions[] = $qDto;
        }


        return $dto;
    }
}
