<?php

namespace App\Controller\Api;

use App\Entity\SurveyAnswer;
use App\Entity\SurveySubmission;
use App\Entity\User;
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

final class SurveySubmissionController extends AbstractController
{
    /** Question IDs coming from your questionnaire */
    private const ACTIVITY_QID = 11; // "Quelle activité..." (single)
    private const GENRES_QID   = 12; // "Quels genres..." (multiple)

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

        // ⚠️ IMPORTANT: on lit le surveyId (id du questionnaire)
        // - Soit on l’exige dans le payload
        // - Soit on met un défaut (ex: 1) si tu n’as qu’un seul questionnaire
        $surveyId = $payload['surveyId'] ?? 1; // <-- mets ici la logique que tu veux
        if (!is_numeric($surveyId)) {
            throw new InvalidArgumentException('`surveyId` must be a number.');
        }
        $surveyId = (int) $surveyId;

        $items = $payload['answers'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('`answers` must be a non-empty array.');
        }

        // Pre-extract explicit activity (Q11) and genres (Q12) from user answers
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
        // de-duplicate genres
        if (!empty($genresFromAnswers)) {
            $genresFromAnswers = array_values(array_unique($genresFromAnswers));
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
            // Try to set on entity if setter exists (expects enum or string)
            if (method_exists($submission, 'setDeducedActivity')) {
                try {
                    // If your entity expects an enum, attempt conversion
                    $enumClass = '\\App\\Enum\\ActivityType';
                    if (class_exists($enumClass) && method_exists($enumClass, 'from')) {
                        $submission->setDeducedActivity($enumClass::from($activityFromAnswers));
                    } else {
                        // fallback to string setter if signature allows
                        $submission->setDeducedActivity($activityFromAnswers);
                    }
                } catch (\Throwable $e) {
                    // ignore enum conversion errors silently
                }
            }
            // Also override analysis
            $analysis['activity'] = $activityFromAnswers;
        }

        if (!empty($genresFromAnswers)) {
            if (method_exists($submission, 'setPreferredGenres')) {
                $submission->setPreferredGenres($genresFromAnswers);
            }
            $analysis['genres'] = $genresFromAnswers;
        }

        // If mood present in analysis and entity has setter, try to persist mood
        if (!empty($analysis['mood']) && method_exists($submission, 'setDeducedMood')) {
            try {
                $enumClass = '\\App\\Enum\\MoodType';
                if (class_exists($enumClass) && method_exists($enumClass, 'from')) {
                    $submission->setDeducedMood($enumClass::from((string)$analysis['mood']));
                } else {
                    $submission->setDeducedMood((string)$analysis['mood']);
                }
            } catch (\Throwable $e) {
/* ignore */
            }
        }

        // Flush to persist updated submission fields
        $this->surveySubmissionRepo->save($submission, true);

        return new JsonResponse([
            'status'        => 'ok',
            'submission_id' => $submission->getId(),
            'survey_id'     => $surveyId,
            'analysis'      => $analysis,
        ]);
    }
}
