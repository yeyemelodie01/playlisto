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

/**
 * Represents a survey submission in the application.
 *
 * This entity is used to store user responses to surveys.
 * Each survey submission includes:
 * - Survey ID (`surveyId`): Identifier for the specific survey.
 * - Deduced Mood (`deducedMood`): The mood inferred from the user's answers.
 * - Deduced Activity (`deducedActivity`): The activity inferred from the user's answers.
 * - Preferred Genres (`preferredGenres`): A list of music genres preferred by the user.
 * - User (`user`): The user who submitted the survey.
 * - Survey Answers (`surveyAnswers`): A collection of answers provided in the survey.
 *
 * Survey submissions are linked to users and contain multiple answers,
 * enabling analysis of user preferences and behaviors.
 */
#[ORM\Entity(repositoryClass: SurveySubmissionRepository::class)]
#[ORM\Table(name: 'survey_submission')]
#[ORM\Index(name: 'idx_survey_submission_survey', columns: ['survey_id'])]
#[ORM\Index(name: 'idx_survey_submission_user', columns: ['user_id'])]
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

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferredGenres = null;

    #[ORM\ManyToOne(inversedBy: 'surveySubmissions')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: SurveyAnswer::class, mappedBy: 'submission', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $surveyAnswers;

    /**
     * Constructor to initialize the SurveySubmission entity.
     *
     * Initializes the 'answers' collection.
     */
    public function __construct()
    {
        $this->surveyAnswers = new ArrayCollection();
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
     * @return array|null
     */
    public function getPreferredGenres(): ?array
    {
        return $this->preferredGenres;
    }

    /**
     * @param array|null $preferredGenres
     */
    public function setPreferredGenres(?array $preferredGenres): void
    {
        $this->preferredGenres = $preferredGenres;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
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
        return $this->surveyAnswers;
    }

    /**
     * @param Collection $answers
     *
     * @return void
     */
    public function setAnswers(Collection $answers): void
    {
        $this->surveyAnswers = $answers;
    }

    /**
     * @param SurveyAnswer $answer
     *
     * @return void
     */
    public function addAnswer(SurveyAnswer $answer): void
    {
        if (!$this->surveyAnswers->contains($answer)) {
            $this->surveyAnswers->add($answer);
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
        if ($this->surveyAnswers->removeElement($answer)) {
            // set the owning side to null (unless already changed)
            if ($answer->getSubmission() === $this) {
                $answer->setSubmission(null);
            }
        }
    }
}
