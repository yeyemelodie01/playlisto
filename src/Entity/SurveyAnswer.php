<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\SurveyAnswerRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * Represents an answer to a survey question in the application.
 *
 * This entity is used to store individual answers linked to a specific survey submission.
 * Each survey answer includes:
 * - Option (`option`): The selected answer option chosen by the user.
 * - Question (`question`): The survey question to which this answer corresponds.
 * - Submission (`submission`): The related survey submission to which this answer belongs.
 *
 * Used in survey modules where users provide responses to multiple questions.
 */
#[ORM\Entity(repositoryClass: SurveyAnswerRepository::class)]
#[ORM\Table(
    name: 'survey_answer',
    indexes: [
        new ORM\Index(name: 'idx_sa_submission', columns: ['submission_id']),
        new ORM\Index(name: 'idx_sa_question', columns: ['question_id']),
        new ORM\Index(name: 'idx_sa_option', columns: ['answer_option_id']),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_submission_question_option', columns: ['submission_id', 'question_id', 'answer_option_id']),
    ]
)]
class SurveyAnswer
{
    use IdTrait;
    use TimestampableEntity;

    #[ORM\ManyToOne(targetEntity: AnswerOption::class, inversedBy: 'surveyAnswer')]
    #[ORM\JoinColumn(name: 'answer_option_id', nullable: true, onDelete: 'SET NULL')]
    private ?AnswerOption $answerOption = null;

    #[ORM\ManyToOne(targetEntity: SurveySubmission::class, inversedBy: 'surveyAnswer')]
    #[ORM\JoinColumn(name: 'submission_id', nullable: false, onDelete: 'CASCADE')]
    private ?SurveySubmission $submission = null;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'surveyAnswers')]
    #[ORM\JoinColumn(name: 'question_id', nullable: false, onDelete: 'CASCADE')]
    private ?Question $question = null;

    /**
     * @return AnswerOption|null
     */
    public function getAnswerOption(): ?AnswerOption
    {
        return $this->answerOption;
    }

    /**
     * @param AnswerOption|null $answerOption
     *
     * @return void
     */
    public function setAnswerOption(?AnswerOption $answerOption): void
    {
        $this->answerOption = $answerOption;
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
