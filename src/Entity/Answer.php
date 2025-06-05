<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;

class Answer
{
    use IdTrait;

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
