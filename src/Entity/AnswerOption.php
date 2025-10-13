<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\AnswerOptionRepository;
use Doctrine\ORM\Mapping as ORM;

/** * Represents an answer-option to a question in the application.
 *
 * This entity is used to store individual answers linked to a specific question.
 * Each answer-option includes:
 * - Label (`label`): The text content of the answer-option.
 * - Question (`question`): The related question to which this answer-option belongs.
 *
 * Used in modules where users respond to questions, such as surveys or quizzes.
 */
#[ORM\Entity(repositoryClass: AnswerOptionRepository::class)]
#[ORM\Table(name: 'answer_option')]
class AnswerOption
{
    use IdTrait;

    /**
     * @var string | null The text content of the answer-option.
     */
    #[ORM\Column(length: 255)]
    private ?string $label;
    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Question $question = null;

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

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): void
    {
        $this->question = $question;
    }
}
