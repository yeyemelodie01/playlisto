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
 * allowing each question to be associated with several answer-option choices.
 */
#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'question')]
class Question
{
    use IdTrait;

    #[ORM\Column(type: Types::INTEGER)]
    private int $surveyId;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(enumType: QuestionType::class)]
    private QuestionType $type = QuestionType::SINGLE;

    /**
     * @var ArrayCollection|Collection
     */
    #[ORM\OneToMany(targetEntity: AnswerOption::class, mappedBy: 'question', cascade: ['persist'], orphanRemoval: true)]
    private Collection|ArrayCollection $answers;

    /**
     * @var ArrayCollection|Collection
     */
    #[ORM\OneToMany(targetEntity: SurveyAnswer::class, mappedBy: 'question')]
    private Collection|ArrayCollection $surveyAnswers;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
        $this->surveyAnswers = new ArrayCollection();
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
     * @return ArrayCollection|Collection
     */
    public function getAnswers(): ArrayCollection|Collection
    {
        return $this->answers;
    }

    /**
     * @param ArrayCollection|Collection $answers
     *
     * @return void
     */
    public function setAnswers(ArrayCollection|Collection $answers): void
    {
        $this->answers = $answers;
    }

    /**
     * @param AnswerOption $answer
     *
     * @return void
     */
    public function addAnswer(AnswerOption $answer): void
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setQuestion($this);
        }
    }

    /**
     * @param AnswerOption $answer
     *
     * @return void
     */
    public function removeAnswer(AnswerOption $answer): void
    {
        if ($this->answers->removeElement($answer)) {
            // set the owning side to null (unless already changed)
            if ($answer->getQuestion() === $this) {
                $answer->setQuestion(null);
            }
        }
    }

    /**
     * @return int
     */
    public function getSurveyId(): int
    {
        return $this->surveyId;
    }

    /**
     * @param int $surveyId
     *
     * @return void
     */
    public function setSurveyId(int $surveyId): void
    {
        $this->surveyId = $surveyId;
    }

    /**
     * @return QuestionType
     */
    public function getType(): QuestionType
    {
        return $this->type;
    }

    /**
     * @param QuestionType $type
     *
     * @return void
     */
    public function setType(QuestionType $type): void
    {
        $this->type = $type;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getSurveyAnswers(): ArrayCollection|Collection
    {
        return $this->surveyAnswers;
    }

    /**
     * @param ArrayCollection|Collection $surveyAnswers
     *
     * @return void
     */
    public function setSurveyAnswers(ArrayCollection|Collection $surveyAnswers): void
    {
        $this->surveyAnswers = $surveyAnswers;
    }

    /**
     * @param SurveyAnswer $surveyAnswer
     *
     * @return void
     */
    public function addSurveyAnswer(SurveyAnswer $surveyAnswer): void
    {
        if (!$this->surveyAnswers->contains($surveyAnswer)) {
            $this->surveyAnswers->add($surveyAnswer);
            $surveyAnswer->setQuestion($this);
        }
    }

    /**
     * @param SurveyAnswer $surveyAnswer
     *
     * @return void
     */
    public function removeSurveyAnswer(SurveyAnswer $surveyAnswer): void
    {
        if ($this->surveyAnswers->removeElement($surveyAnswer)) {
            // set the owning side to null (unless already changed)
            if ($surveyAnswer->getQuestion() === $this) {
                $surveyAnswer->setQuestion(null);
            }
        }
    }
}
