<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\AnswerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an answer to a question in the application.
 *
 * This entity is used to store possible responses linked to a specific question.
 * Each answer includes:
 * - Label (`label`): The text content of the answer.
 * - Question (`question`): The related question to which this answer belongs.
 *
 * Used in quiz or form-based modules where questions can have multiple answers.
 */
#[ORM\Entity(repositoryClass: AnswerRepository::class)]
#[ORM\Table(name: 'answer')]
class Answer
{
    use IdTrait;

    /**
     * @var string | null The text content of the answer.
     */
    #[ORM\Column(length: 255)]
    private string $label;
    #[ORM\ManyToOne(inversedBy: 'answers')]
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
     * @return $this
     */
    public function setQuestion(?Question $question): static
    {
        $this->question = $question;

        return $this;
    }
}
