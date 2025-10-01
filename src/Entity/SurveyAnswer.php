<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\SurveyAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an answer to a survey question in the application.
 *
 * This entity is used to store individual answers linked to a specific survey submission.
 * Each survey answer includes:
 * - Question ID (`questionId`): The identifier of the question being answered.
 * - Option Value (`optionValue`): The selected answer or response text.
 * - Submission (`submission`): The related survey submission to which this answer belongs.
 *
 * Used in survey modules where users provide responses to multiple questions.
 */
#[ORM\Entity(repositoryClass: SurveyAnswerRepository::class)]
#[ORM\Table(name: 'survey_answer')]
class SurveyAnswer
{
    use IdTrait;

    #[ORM\Column]
    private int $questionId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $optionValue = null;

    #[ORM\ManyToOne(inversedBy: 'surveyAnswers')]
    #[ORM\JoinColumn(name: 'survey_id', nullable: false)]
    private ?SurveySubmission $submission = null;

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
     * @return string|null
     */
    public function getOptionValue(): ?string
    {
        return $this->optionValue;
    }

    /**
     * @param string|null $optionValue
     *
     * @return void
     */
    public function setOptionValue(?string $optionValue): void
    {
        $this->optionValue = $optionValue;
    }

    /**
     * @return SurveySubmission|null
     */
    public function getSubmission(): ?SurveySubmission
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
