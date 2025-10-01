<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Enum\QuestionType;
use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a question in the application.
 *
 * This entity defines a quiz or form question that can have multiple answers.
 * Each question includes:
 * - Label (`label`): The textual content of the question.
 * - Type (`type`): The format of the question, either 'single' or 'multiple' choice.
 * - Answers (`answers`): A collection of possible answers linked to the question.
 *
 * The `answers` are managed through a one-to-many relationship,
 * allowing each question to be associated with several answer choices.
 */
#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'question')]
class Question
{
    use IdTrait;

    /**
     * @var string The textual content of the question.
     */
    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(type: Types::STRING, length: 16, options: ['default' => 'single'])]
    #[Assert\Choice(choices: ['single','multiple'])]
    private string $type = 'single';

    /**
     * @var Collection<int, Answer>
     */
    #[ORM\OneToMany(targetEntity: Answer::class, mappedBy: 'question', cascade: ['persist'], orphanRemoval: true)]
    private Collection $answers;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
    }

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
     * @return Collection<int, Answer>
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    /**
     * @param Answer $answer
     *
     * @return $this
     */
    public function addAnswer(Answer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setQuestion($this);
        }

        return $this;
    }

    /**
     * @param Answer $answer
     *
     * @return $this
     */
    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            // set the owning side to null (unless already changed)
            if ($answer->getQuestion() === $this) {
                $answer->setQuestion(null);
            }
        }

        return $this;
    }

    /**
     * @return QuestionType
     */
    public function getType(): QuestionType
    {
        return QuestionType::from($this->type);
    }

    /**
     * @param QuestionType|string $type
     *
     * @return void
     */
    public function setType(QuestionType|string $type): void
    {
        $this->type = $type instanceof QuestionType ? $type->value : (string)$type;
    }
}
