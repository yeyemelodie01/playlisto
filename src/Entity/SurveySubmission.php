<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Repository\SurveySubmissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: SurveySubmissionRepository::class)]
#[ORM\Table(name: 'survey_submission')]
#[ORM\UniqueConstraint(name: 'uniq_survey_user', columns: ['survey_id', 'user_id'])]
class SurveySubmission
{
    use IdTrait;
    use TimestampableEntity;

    #[ORM\Column(name: 'survey_id')]
    private int $surveyId;
    #[ORM\Column(nullable: true, enumType: MoodType::class)]
    private ?MoodType $deducedMood = null;

    #[ORM\Column(nullable: true, enumType: ActivityType::class)]
    private ?ActivityType $deducedActivity = null;

    #[ORM\ManyToOne(inversedBy: 'surveySubmissions')]
    private ?User $user;

    #[ORM\OneToMany(targetEntity: SurveyAnswer::class, mappedBy: 'submission')]
    private Collection $surveyAnswers;

    /**
     * Constructor to initialize the SurveySubmission entity.
     *
     * Initializes the createdAt property to the current date and time,
     * and sets up an empty collection for answers.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->answers = new ArrayCollection();
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
     * @return MoodType|null
     */
    public function getDeducedMood(): ?MoodType
    {
        return $this->deducedMood;
    }

    /**
     * @param MoodType|null $deducedMood
     *
     * @return void
     */
    public function setDeducedMood(?MoodType $deducedMood): void
    {
        $this->deducedMood = $deducedMood;
    }

    /**
     * @return ActivityType|null
     */
    public function getDeducedActivity(): ?ActivityType
    {
        return $this->deducedActivity;
    }

    /**
     * @param ActivityType|null $deducedActivity
     *
     * @return void
     */
    public function setDeducedActivity(?ActivityType $deducedActivity): void
    {
        $this->deducedActivity = $deducedActivity;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     *
     * @return void
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return Collection<int, SurveyAnswer>
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    /**
     * @param Collection $answers
     *
     * @return void
     */
    public function setAnswers(Collection $answers): void
    {
        $this->answers = $answers;
    }

    /**
     * @param SurveyAnswer $answer
     *
     * @return void
     */
    public function addAnswer(SurveyAnswer $answer): void
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setSubmission($this);
        }
    }

    /**
     * @param SurveyAnswer $answer
     *
     * @return void
     */
    public function removeAnswer(SurveyAnswer $answer): void
    {
        if ($this->answers->removeElement($answer)) {
            if ($answer->getSubmission() === $this) {
                $answer->setSubmission($this);
            }
        }
    }
}
