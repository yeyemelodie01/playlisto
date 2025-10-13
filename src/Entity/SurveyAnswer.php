<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\SurveyAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an answer-option to a survey question in the application.
 *
 * This entity is used to store individual answers linked to a specific survey submission.
 * Each survey answer-option includes:
 * - Option (`option`): The selected answer option chosen by the user.
 * - Question (`question`): The survey question to which this answer-option corresponds.
 * - Submission (`submission`): The related survey submission to which this answer-option belongs.
 *
 * Used in survey modules where users provide responses to multiple questions.
 */
#[ORM\Entity(repositoryClass: SurveyAnswerRepository::class)]
#[ORM\Table(name: 'survey_answer')]
#[ORM\UniqueConstraint(
    name: 'uniq_submission_question_option',
    columns: ['submission_id', 'question_id', 'option_id']
)]
class SurveyAnswer
{
    use IdTrait;

    #[ORM\ManyToOne(targetEntity: AnswerOption::class)]
    #[ORM\JoinColumn(name: 'option_id', nullable: true, onDelete: 'SET NULL')]
    private ?AnswerOption $option = null;

    #[ORM\ManyToOne(inversedBy: 'surveyAnswers')]
    #[ORM\JoinColumn(name: 'submission_id', nullable: false, onDelete: 'CASCADE')]
    private ?SurveySubmission $submission = null;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'surveyAnswers')]
    #[ORM\JoinColumn(name: 'question_id', nullable: false, onDelete: 'CASCADE')]
    private ?Question $question = null;

    public function getOption(): ?AnswerOption
    {
        return $this->option;
    }

    public function setOption(?AnswerOption $option): void
    {
        $this->option = $option;
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

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): void
    {
        $this->question = $question;
    }
}
