<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\SurveyAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurveyAnswerRepository::class)]
#[ORM\Table(name: 'survey_answer')]
class SurveyAnswer
{
    use IdTrait;

    #[ORM\Column]
    private int $questionId;

    #[ORM\Column]
    private int $optionId;

    #[ORM\ManyToOne(inversedBy: 'surveyAnswers')]
    private ?SurveySubmission $submission;

    /**
     * @return int
     */
    public function getQuestionId(): int
    {
        return $this->questionId;
    }

    /**
     * @param int $questionId
     *
     * @return void
     */
    public function setQuestionId(int $questionId): void
    {
        $this->questionId = $questionId;
    }

    /**
     * @return int
     */
    public function getOptionId(): int
    {
        return $this->optionId;
    }

    /**
     * @param int $optionId
     *
     * @return void
     */
    public function setOptionId(int $optionId): void
    {
        $this->optionId = $optionId;
    }

    /**
     * @return SurveySubmission
     */
    public function getSubmission(): SurveySubmission
    {
        return $this->submission;
    }

    /**
     * @param SurveySubmission $submission
     *
     * @return void
     */
    public function setSubmission(SurveySubmission $submission): void
    {
        $this->submission = $submission;
    }
}
