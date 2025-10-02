<?php

namespace App\Controller\Api;

use App\Entity\SurveyAnswer;
use App\Entity\SurveySubmission;
use App\Entity\User;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Enum\SpotifyGenre;
use App\Repository\SurveyAnswerRepository;
use App\Repository\SurveySubmissionRepository;
use App\Service\OpenAIService;
use DateTime;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use ValueError;

final class SurveySubmissionController extends AbstractController
{
    private const ACTIVITY_QID = 11;
    private const GENRES_QID   = 12;

    public function __construct(
        private readonly Security $security,
        private readonly SurveySubmissionRepository $surveySubmissionRepo,
        private readonly SurveyAnswerRepository $surveyAnswerRepo,
        private readonly OpenAIService $openAIService,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/api/me/surveys/submit', name: 'api_me_surveys_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new RuntimeException('Authenticated user not found.');
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $surveyId = $payload['surveyId'] ?? 1;
        if (!is_numeric($surveyId)) {
            throw new InvalidArgumentException('`surveyId` must be a number.');
        }
        $surveyId = (int) $surveyId;

        $items = $payload['answers'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('`answers` must be a non-empty array.');
        }

        // Pre-extract explicit activity (Q11) and genres (Q12) from user answers (values must be valid enum strings).
        $activityFromAnswers = null;   // string|null (enum value lowercase)
        $genresFromAnswers   = [];     // array<string>

        foreach ($items as $row) {
            $qid = (int) (($row['questionId'] ?? 0));
            if ($qid === self::ACTIVITY_QID) {
                // activity is single choice: optionId or optionValue
                if (array_key_exists('optionValue', $row) && is_string($row['optionValue'])) {
                    $activityFromAnswers = strtolower(trim($row['optionValue']));
                } elseif (array_key_exists('optionId', $row) && is_string($row['optionId'])) {
                    $activityFromAnswers = strtolower(trim($row['optionId']));
                }
            }
            if ($qid === self::GENRES_QID) {
                // genres is multiple choice: optionValues or optionIds
                if (array_key_exists('optionValues', $row) && is_array($row['optionValues'])) {
                    foreach ($row['optionValues'] as $g) {
                        $g = strtolower(trim((string)$g));
                        if ($g !== '') {
                            $genresFromAnswers[] = $g;
                        }
                    }
                } elseif (array_key_exists('optionIds', $row) && is_array($row['optionIds'])) {
                    foreach ($row['optionIds'] as $g) {
                        $g = strtolower(trim((string)$g));
                        if ($g !== '') {
                            $genresFromAnswers[] = $g;
                        }
                    }
                } elseif (array_key_exists('optionValue', $row) && is_string($row['optionValue'])) {
                    $g = strtolower(trim($row['optionValue']));
                    if ($g !== '') {
                        $genresFromAnswers[] = $g;
                    }
                }
            }
        }
        // de-duplicate and validate genres against SpotifyGenre enum
        if (!empty($genresFromAnswers)) {
            $genresFromAnswers = array_values(array_unique($genresFromAnswers));

            $invalidGenres   = [];
            $validatedGenres = [];

            foreach ($genresFromAnswers as $g) {
                $enum = SpotifyGenre::tryFrom($g);
                if ($enum !== null) {
                    // normalize to enum value (already lowercase)
                    $validatedGenres[] = $enum->value;
                } else {
                    $invalidGenres[] = $g;
                }
            }

            if (!empty($invalidGenres)) {
                $allowed = implode(', ', array_map(static fn($c) => $c->value, SpotifyGenre::cases()));
                throw new InvalidArgumentException(sprintf(
                    'Invalid genre(s): %s. Allowed values are: %s',
                    implode(', ', $invalidGenres),
                    $allowed
                ));
            }

            $genresFromAnswers = $validatedGenres;
        }

        // 1) Création de la soumission (avec survey_id !)
        $submission = new SurveySubmission();
        // si ton entité possède setSurveyId(int $id)
        $submission->setSurveyId($surveyId);
        // si au contraire tu as une relation Survey, fais:
        // $submission->setSurvey($surveyEntity);

        $submission->setUser($user);
        $submission->setCreatedAt(new DateTime());

        // on ne flush pas encore -> on flush à la fin
        $this->surveySubmissionRepo->save($submission, false);

        // 2) Sauvegarde des réponses
        foreach ($items as $row) {
            $qId = $row['questionId'] ?? null;
            if ($qId === null || $qId === '') {
                throw new InvalidArgumentException('Each answer must contain `questionId`.');
            }
            $qId = (int) $qId;

            // Helper to persist one SurveyAnswer safely (never NULL option_value)
            $persistAnswer = function (?int $optionId, ?string $optionValue) use ($submission, $qId) {
                $ans = new SurveyAnswer();
                $ans->setSubmission($submission);
                $ans->setQuestionId($qId);
                if (method_exists($ans, 'setOptionId') && $optionId !== null) {
                    $ans->setOptionId($optionId);
                }
                // Ensure non-null value for NOT NULL column if your schema requires it
                if (method_exists($ans, 'setOptionValue')) {
                    $val = $optionValue;
                    if (is_string($val)) {
                        $val = trim($val);
                    }
                    $ans->setOptionValue($val ?? '');
                }
                $this->surveyAnswerRepo->save($ans, false);
            };

            // Accept either optionId/optionIds (ids) or optionValue/optionValues (labels)
            if (array_key_exists('optionId', $row)) {
                $id = is_numeric($row['optionId']) ? (int)$row['optionId'] : null;
                $persistAnswer($id, null);
                continue;
            }

            if (array_key_exists('optionIds', $row)) {
                $list = is_array($row['optionIds']) ? $row['optionIds'] : [];
                foreach ($list as $opt) {
                    $id = is_numeric($opt) ? (int)$opt : null;
                    $persistAnswer($id, null);
                }
                continue;
            }

            if (array_key_exists('optionValue', $row)) {
                $val = $row['optionValue'];
                $persistAnswer(null, $val !== null ? (string)$val : null);
                continue;
            }

            if (array_key_exists('optionValues', $row)) {
                $list = is_array($row['optionValues']) ? $row['optionValues'] : [];
                foreach ($list as $opt) {
                    $persistAnswer(null, $opt !== null ? (string)$opt : null);
                }
                continue;
            }

            throw new InvalidArgumentException(
                sprintf('Answer for question %s must contain `optionId`/`optionIds` or `optionValue`/`optionValues`.', $qId)
            );
        }

        // flush global
        $this->surveySubmissionRepo->save($submission, true);

        // 3) Prépare les données pour OpenAI
        $answersForAI = [];
        foreach ($items as $row) {
            $qId = (int) ($row['questionId'] ?? 0);
            // Do NOT send activity (Q11) or genres (Q12) to OpenAI: we only want mood inference
            if ($qId === self::ACTIVITY_QID || $qId === self::GENRES_QID) {
                continue;
            }
            if ($qId === 0) {
                continue;
            }

            if (isset($row['optionId'])) {
                $answersForAI[$qId] = [ (string) $row['optionId'] ];
                continue;
            }
            if (!empty($row['optionIds']) && is_array($row['optionIds'])) {
                $answersForAI[$qId] = array_map(static fn($v) => (string) $v, $row['optionIds']);
                continue;
            }
            if (isset($row['optionValue'])) {
                $answersForAI[$qId] = [ (string) $row['optionValue'] ];
                continue;
            }
            if (!empty($row['optionValues']) && is_array($row['optionValues'])) {
                $answersForAI[$qId] = array_map(static fn($v) => (string) $v, $row['optionValues']);
                continue;
            }

            $answersForAI[$qId] = [];
        }

        // 4) Appel OpenAI (cherche la bonne méthode dispo)
        if (method_exists($this->openAIService, 'analyzeSurvey')) {
            $analysis = $this->openAIService->analyzeSurvey($answersForAI);
        } elseif (method_exists($this->openAIService, 'analyzeAnswers')) {
            $analysis = $this->openAIService->analyzeAnswers($answersForAI);
        } elseif (method_exists($this->openAIService, 'analyze')) {
            $analysis = $this->openAIService->analyze($answersForAI);
        } elseif (method_exists($this->openAIService, 'inferMoodActivityGenres')) {
            $analysis = $this->openAIService->inferMoodActivityGenres($answersForAI);
        } else {
            throw new RuntimeException('openAIService has no suitable analyze*() method.');
        }

        // Enforce explicit activity (Q11) and genres (Q12) from user answers
        if (!empty($activityFromAnswers)) {
            try {
                $activityEnum = ActivityType::from($activityFromAnswers);
            } catch (ValueError $e) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid activity value "%s". Must be one of: %s',
                    $activityFromAnswers,
                    implode(', ', array_map(fn($c) => $c->value, ActivityType::cases()))
                ));
            }
            if (method_exists($submission, 'setDeducedActivity')) {
                $submission->setDeducedActivity($activityEnum);
            }
            $analysis['activity'] = $activityEnum->value;
        }

        if (!empty($genresFromAnswers)) {
            if (method_exists($submission, 'setPreferredGenres')) {
                $submission->setPreferredGenres($genresFromAnswers);
            }
            $analysis['genres'] = $genresFromAnswers;
        }

        // If mood present in analysis and entity has setter, try to persist mood
        if (!empty($analysis['mood']) && method_exists($submission, 'setDeducedMood')) {
            $moodRaw = (string)$analysis['mood'];
            try {
                $moodEnum = MoodType::from($moodRaw);
            } catch (ValueError $e) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid mood value "%s". Must be one of: %s',
                    $moodRaw,
                    implode(', ', array_map(fn($c) => $c->value, MoodType::cases()))
                ));
            }
            $submission->setDeducedMood($moodEnum);
            $analysis['mood'] = $moodEnum->value;
        }

        $this->surveySubmissionRepo->save($submission, true);

        return new JsonResponse([
            'status'        => 'ok',
            'submission_id' => $submission->getId(),
            'survey_id'     => $surveyId,
            'analysis'      => $analysis,
        ]);
    }
}
