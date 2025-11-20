<?php

namespace App\Controller\Api;

use App\Entity\SurveyAnswer;
use App\Entity\SurveySubmission;
use App\Entity\User;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Enum\SpotifyGenre;
use App\Repository\AnswerOptionRepository;
use App\Repository\QuestionRepository;
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
    private const ACTIVITY_QID = 14;
    private const GENRES_QID   = 15;

    /**
     * @param Security                   $security
     * @param SurveySubmissionRepository $surveySubmissionRepo
     * @param SurveyAnswerRepository     $surveyAnswerRepo
     * @param OpenAIService              $openAIService
     * @param QuestionRepository         $questionRepo
     * @param AnswerOptionRepository     $answerOptionRepo
     */
    public function __construct(private readonly Security $security, private readonly SurveySubmissionRepository $surveySubmissionRepo, private readonly SurveyAnswerRepository $surveyAnswerRepo, private readonly OpenAIService $openAIService, private readonly QuestionRepository $questionRepo, private readonly AnswerOptionRepository $answerOptionRepo,)
    {
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

        $payload = json_decode($request->getContent() ?: '{}', true) ?? [];

        $items = $payload['answers'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('`answers` must be a non-empty array.');
        }

        $surveyId = null;
        $surveyIdRaw = $payload['surveyId'] ?? null;

        if (is_numeric($surveyIdRaw)) {
            $surveyId = (int) $surveyIdRaw;
        } else {
            $firstQid = (int)($items[0]['questionId'] ?? 0);
            if ($firstQid > 0) {
                $firstQ = $this->questionRepo->find($firstQid);
                if ($firstQ) {
                    $surveyId = $firstQ->getSurveyId();
                }
            }
        }

        if (!$surveyId || $surveyId <= 0) {
            return new JsonResponse([
                'error'   => 'invalid_payload',
                'message' => 'surveyId manquant : envoyez-le ou fournissez au moins une question valide pour l’inférer.',
            ], 422);
        }

        $activityFromAnswers = null;
        $genresFromAnswers = [];

        foreach ($items as $row) {
            $qid = (int) (($row['questionId'] ?? 0));
            if ($qid === self::ACTIVITY_QID) {
                if (array_key_exists('optionValue', $row) && is_string($row['optionValue'])) {
                    $activityFromAnswers = mb_strtolower(trim($row['optionValue']));
                } elseif (array_key_exists('optionId', $row) && is_numeric($row['optionId'])) {
                    $opt = $this->answerOptionRepo->find((int)$row['optionId']);
                    if ($opt && $opt->getQuestion()?->getId() === self::ACTIVITY_QID) {
                        $activityFromAnswers = mb_strtolower(trim($opt->getLabel()));
                    }
                }
            }

            if ($qid === self::GENRES_QID) {
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

        if (!empty($genresFromAnswers)) {
            $genresFromAnswers = array_values(array_unique($genresFromAnswers));

            $invalidGenres   = [];
            $validatedGenres = [];

            foreach ($genresFromAnswers as $g) {
                $enum = SpotifyGenre::tryFrom($g);
                if ($enum !== null) {
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

        $submission = new SurveySubmission();
        $submission->setSurveyId($surveyId);
        $submission->setUser($user);
        $submission->setCreatedAt(new DateTime());

        $this->surveySubmissionRepo->save($submission, false);

        foreach ($items as $row) {
            $qId = $row['questionId'] ?? null;
            if ($qId === null || $qId === '') {
                throw new InvalidArgumentException('Each answer-option must contain `questionId`.');
            }

            $persistAnswer = function (?int $optionId, ?string $optionValue) use ($surveyId, $submission, $qId) {
                $question = $this->questionRepo->find($qId);
                if (!$question) {
                    throw new InvalidArgumentException(sprintf('Unknown question id %d.', $qId));
                }
                if ($question->getSurveyId() !== $surveyId) {
                    throw new InvalidArgumentException(sprintf(
                        'Question %d n’appartient pas au survey %d.',
                        $qId,
                        $surveyId
                    ));
                }

                if ($optionId !== null) {
                    $opt = $this->answerOptionRepo->find($optionId);
                    if (!$opt) {
                        throw new InvalidArgumentException(sprintf('Unknown option id %d for question %d.', $optionId, $qId));
                    }

                    if ($opt->getQuestion()?->getId() !== $qId) {
                        throw new InvalidArgumentException(sprintf('Option %d does not belong to question %d.', $optionId, $qId));
                    }
                    $ans = new SurveyAnswer();
                    $ans->setSubmission($submission);
                    $ans->setQuestion($question);
                    $ans->setAnswerOption($opt);
                    $this->surveyAnswerRepo->save($ans, false);

                    return;
                }

                if ($optionValue !== null && trim($optionValue) !== '') {
                    $label = trim($optionValue);
                    $opt = $this->answerOptionRepo->findOneBy(['question' => $question, 'label' => $label]) ?? $this->answerOptionRepo->findOneBy(['question' => $question, 'label' => mb_strtolower($label)]);

                    if (!$opt) {
                        throw new InvalidArgumentException(sprintf('Unknown option label "%s" for question %d.', $label, $qId));
                    }

                    $ans = new SurveyAnswer();
                    $ans->setSubmission($submission);
                    $ans->setQuestion($question);
                    $ans->setAnswerOption($opt);
                    $this->surveyAnswerRepo->save($ans, false);

                    return;
                }

                throw new InvalidArgumentException(sprintf('Missing optionId/optionValue for question %d.', $qId));
            };

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

        $this->surveySubmissionRepo->save($submission, true);

        $answersForAI = [];
        foreach ($items as $row) {
            $qId = (int) ($row['questionId'] ?? 0);

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

        if (!empty($activityFromAnswers)) {
            // normalisation accents + mapping FR -> codes enum
            $norm = mb_strtolower(trim($activityFromAnswers));
            $norm = strtr($norm, [
                'é' => 'e','è' => 'e','ê' => 'e','ë' => 'e',
                'à' => 'a','â' => 'a',
                'î' => 'i','ï' => 'i',
                'ô' => 'o',
                'û' => 'u','ü' => 'u',
                'ç' => 'c',
            ]);
            $map = [
                'sport'   => 'sport',
                'travail' => 'work',
                'detente' => 'relax',
                'etude'   => 'study',
                'cuisine' => 'cooking',
                'aucune'  => 'none',
            ];
            $canonical = $map[$norm] ?? $norm;

            try {
                $activityEnum = ActivityType::from($canonical);
            } catch (ValueError $e) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid activity value "%s". Allowed: %s',
                    $activityFromAnswers,
                    implode(', ', array_map(fn($c) => $c->value, ActivityType::cases()))
                ));
            }

            if (method_exists($submission, 'setSelectedActivity')) {
                $submission->setSelectedActivity($activityEnum);
            }
            $analysis['activity'] = $activityEnum->value;
        }

        if (!empty($genresFromAnswers)) {
            if (method_exists($submission, 'setPreferredGenres')) {
                $submission->setPreferredGenres($genresFromAnswers);
            }
            $analysis['genres'] = $genresFromAnswers;
        }

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
