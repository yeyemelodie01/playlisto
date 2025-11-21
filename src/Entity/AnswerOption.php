<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\AnswerOptionRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** * Represents an answer to a question in the application.
 *
 * This entity is used to store individual answers linked to a specific question.
 * Each answer includes:
 * - Label (`label`): The text content of the answer.
 * - Question (`question`): The related question to which this answer belongs.
 *
 * Used in modules where users respond to questions, such as surveys or quizzes.
 */
#[ORM\Entity(repositoryClass: AnswerOptionRepository::class)]
#[ORM\Table(
    name: 'answer_option',
    indexes: [
        new ORM\Index(name: 'idx_answeroption_question', columns: ['question_id']),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_question_label',
            columns: ['question_id', 'label']
        ),
    ]
)]
class AnswerOption
{
    use IdTrait;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'answerOption')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Question $question = null;

    #[ORM\OneToMany(targetEntity: SurveyAnswer::class, mappedBy: 'answerOption')]
    private Collection $surveyAnswers;

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     *
     * @return void
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return Question|null
     */
    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    /**
     * @param Question|null $question
     *
     * @return void
     */
    public function setQuestion(?Question $question): void
    {
        $this->question = $question;
    }
}
