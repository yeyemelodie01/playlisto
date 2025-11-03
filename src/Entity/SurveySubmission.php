<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Repository\SurveySubmissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a survey submission in the application.
 *
 * This entity is used to store user responses to surveys.
 * Each survey submission includes:
 * - Survey ID (`surveyId`): Identifier for the specific survey.
 * - Deduced Mood (`deducedMood`): The mood inferred from the user's answers.
 * - Selected Activity ()
 * - Preferred Genres (`preferredGenres`): A list of music genres preferred by the user.
 * - User (`user`): The user who submitted the survey.
 * - Survey Answers (`surveyAnswers`): A collection of answers provided in the survey.
 *
 * Survey submissions are linked to users and contain multiple answers,
 * enabling analysis of user preferences and behaviors.
 */
#[ORM\Entity(repositoryClass: SurveySubmissionRepository::class)]
#[ORM\Table(
    name: 'survey_submission',
    indexes: [
        new ORM\Index(name: 'idx_survey_submission_survey', columns: ['survey_id']),
        new ORM\Index(name: 'idx_survey_submission_user', columns: ['user_id'])
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_user_survey', columns: ['user_id', 'survey_id']),
    ]
)]
class SurveySubmission
{
    use IdTrait;
    use TimestampableEntity;

    #[ORM\Column(name: 'survey_id', type: Types::INTEGER, nullable: false)]
    #[Assert\NotNull]
    #[Assert\Positive(message: 'surveyId must be greater than zero')]
    private int $surveyId;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: MoodType::class)]
    private ?MoodType $deducedMood = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: ActivityType::class)]
    private ?ActivityType $selectedActivity = null;

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
    public function getSelectedActivity(): ?ActivityType
    {
        return $this->selectedActivity;
    }

    /**
     * @param ActivityType|null $selectedActivity
     *
     * @return void
     */
    public function setSelectedActivity(?ActivityType $selectedActivity): void
    {
        $this->selectedActivity = $selectedActivity;
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
    public function getSurveyAnswers(): Collection
    {
        return $this->surveyAnswers;
    }

    /**
     * @param Collection $answers
     *
     * @return void
     */
    public function setSurveyAnswers(Collection $answers): void
    {
        $this->surveyAnswers = $answers;
    }

    /**
     * @param SurveyAnswer $answer
     *
     * @return void
     */
    public function addSurveyAnswer(SurveyAnswer $answer): void
    {
        $q = $answer->getQuestion();
        if ($q && $q->getSurveyId() !== $this->surveyId) {
            throw new \DomainException('Answer question does not belong to submission survey.');
        }

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
    public function removeSurveyAnswer(SurveyAnswer $answer): void
    {
        if ($this->surveyAnswers->removeElement($answer) && $answer->getSubmission() === $this) {
            $answer->setSubmission($this);
        }
    }
}
