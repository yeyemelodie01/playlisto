<?php

namespace App\Controller\Api;

use App\Entity\AnswerOption;
use App\Entity\User;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use App\Enum\SpotifyGenre;
use App\Repository\AnswerOptionRepository;
use App\Repository\QuestionRepository;
use App\Repository\SurveyAnswerRepository;
use App\Repository\SurveySubmissionRepository;
use App\Service\OpenAIService;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\SurveySubmission;
use App\Entity\SurveyAnswer;
use Symfony\Bundle\SecurityBundle\Security;
use Throwable;

final class AnalyzeAnswersController
{
    private const ACTIVITY_QID = 14;
    private const GENRES_QID   = 15;

    /**
     * @param OpenAIService              $openAI
     * @param SurveySubmissionRepository $submissionRepo
     * @param SurveyAnswerRepository     $answerRepo
     * @param QuestionRepository         $questionRepo
     * @param AnswerOptionRepository     $optionRepo
     * @param Security                   $security
     */
    public function __construct(
        private readonly OpenAIService $openAI,
        private readonly SurveySubmissionRepository $submissionRepo,
        private readonly SurveyAnswerRepository $answerRepo,
        private readonly QuestionRepository $questionRepo,
        private readonly AnswerOptionRepository $optionRepo,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/me/answers/analyze', name: 'api_me_answers_analyze', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse([
                'error' => 'invalid_json',
                'message' => 'Body must be valid JSON.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $surveyId = (int)($data['surveyId'] ?? 0);
        $answers  = $data['answers'] ?? null;

        if ($surveyId <= 0) {
            return new JsonResponse([
                'error' => 'invalid_payload',
                'message' => 'Field "surveyId" is required and must be a positive integer.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_array($answers) || $answers === []) {
            return new JsonResponse([
                'error' => 'invalid_payload',
                'message' => 'Field "answers" is required and must be a non-empty array.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requiredQids = range(1, 13);

        $receivedQids = [];
        foreach ($answers as $a) {
            if (isset($a['questionId'])) {
                $receivedQids[] = (int) $a['questionId'];
            }
        }

        $missing = array_values(array_diff($requiredQids, $receivedQids));
        if ($missing) {
            return new JsonResponse([
                'error'   => 'missing_required_questions',
                'message' => 'Some required questions are missing.',
                'missing' => $missing,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'unauthenticated',
                'message' => 'Authenticated User entity not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $submission = new SurveySubmission();
        $submission->setUser($user);
        $submission->setSurveyId($surveyId);

        $this->submissionRepo->save($submission, true);

        $payloadForAI = [];

        foreach ($answers as $a) {
            $qid = (int)($a['questionId'] ?? 0);
            if ($qid <= 0) {
                return new JsonResponse(['error' => 'invalid_question_id'], 422);
            }

            $question = $this->questionRepo->find($qid);
            if (!$question) {
                return new JsonResponse(['error' => "unknown_question_$qid"], 422);
            }
            if ($question->getSurveyId() !== $submission->getSurveyId()) {
                return new JsonResponse(['error' => "survey_mismatch_q$qid"], 422);
            }

            $isActivity = ($qid === self::ACTIVITY_QID);
            $isGenres   = ($qid === self::GENRES_QID);

            if (isset($a['optionIds']) && is_array($a['optionIds'])) {
                $labels = [];

                foreach ($a['optionIds'] as $oid) {
                    if (!is_numeric($oid)) {
                        continue;
                    }

                    $opt = $this->optionRepo->find((int)$oid);
                    if (!$opt instanceof AnswerOption) {
                        return new JsonResponse(['error' => "unknown_option_$oid"], 422);
                    }
                    if ($opt->getQuestion()?->getId() !== $qid) {
                        return new JsonResponse(['error' => "option_not_of_question_q{$qid}"], 422);
                    }

                    $ans = new SurveyAnswer();
                    $ans->setSubmission($submission);
                    $ans->setQuestion($question);
                    $ans->setAnswerOption($opt);
                    $this->answerRepo->save($ans, false);

                    $labels[] = trim($opt->getLabel());
                }

                if ($labels) {
                    $payloadForAI[] = [
                        'questionId'   => $qid,
                        'optionValues' => array_values(array_unique($labels)),
                        'isActivity'   => $isActivity ?: null,
                        'isGenres'     => $isGenres   ?: null,
                    ];
                }
                continue;
            }

            if (isset($a['optionId']) && is_numeric($a['optionId'])) {
                $opt = $this->optionRepo->find((int)$a['optionId']);
                if (!$opt instanceof AnswerOption) {
                    return new JsonResponse(['error' => "unknown_option_{$a['optionId']}"], 422);
                }
                if ($opt->getQuestion()?->getId() !== $qid) {
                    return new JsonResponse(['error' => "option_not_of_question_q{$qid}"], 422);
                }

                $ans = new SurveyAnswer();
                $ans->setSubmission($submission);
                $ans->setQuestion($question);
                $ans->setAnswerOption($opt);
                $this->answerRepo->save($ans, false);

                $payloadForAI[] = [
                    'questionId'  => $qid,
                    'optionValue' => trim($opt->getLabel()),
                    'isActivity'  => $isActivity ?: null,
                    'isGenres'    => $isGenres   ?: null,
                ];
                continue;
            }

            if (isset($a['optionValue']) && is_string($a['optionValue']) && trim($a['optionValue']) !== '') {
                $label = trim($a['optionValue']);

                $opt = $this->optionRepo->findOneBy(['question' => $question, 'label' => $label])
                    ?? $this->optionRepo->findOneBy(['question' => $question, 'label' => mb_strtolower($label)]);

                if (!$opt instanceof AnswerOption) {
                    return new JsonResponse([
                        'error' => "unknown_option_label_for_q{$qid}",
                        'label' => $label,
                    ], 422);
                }

                $ans = new SurveyAnswer();
                $ans->setSubmission($submission);
                $ans->setQuestion($question);
                $ans->setAnswerOption($opt);
                $this->answerRepo->save($ans, false);

                $payloadForAI[] = [
                    'questionId'  => $qid,
                    'optionValue' => $label,
                    'isActivity'  => $isActivity ?: null,
                    'isGenres'    => $isGenres   ?: null,
                ];
                continue;
            }

            if (isset($a['optionValues']) && is_array($a['optionValues']) && $a['optionValues'] !== []) {
                $labels = array_values(array_unique(array_filter(
                    array_map(static fn($v) => trim((string)$v), $a['optionValues']),
                    static fn($v) => $v !== ''
                )));

                $found   = [];
                $missing = [];

                foreach ($labels as $label) {
                    $opt = $this->optionRepo->findOneBy(['question' => $question, 'label' => $label])
                        ?? $this->optionRepo->findOneBy(['question' => $question, 'label' => mb_strtolower($label)]);

                    if (!$opt instanceof AnswerOption) {
                        $missing[] = $label;
                        continue;
                    }
                    if ($opt->getQuestion()?->getId() !== $qid) {
                        return new JsonResponse(['error' => "option_not_of_question_q{$qid}"], 422);
                    }
                    $found[] = $opt;
                }

                if ($missing) {
                    return new JsonResponse([
                        'error'  => "unknown_option_labels_for_q{$qid}",
                        'labels' => $missing,
                    ], 422);
                }

                foreach ($found as $opt) {
                    $ans = new SurveyAnswer();
                    $ans->setSubmission($submission);
                    $ans->setQuestion($question);
                    $ans->setAnswerOption($opt);
                    $this->answerRepo->save($ans, false);
                }

                $payloadForAI[] = [
                    'questionId'   => $qid,
                    'optionValues' => $labels,
                    'isActivity'   => $isActivity ?: null,
                    'isGenres'     => $isGenres   ?: null,
                ];
                continue;
            }

            return new JsonResponse([
                'error' => "question_{$qid}_requires_optionId(s)_or_optionValue(s)",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->answerRepo->flush();

        try {
            $result = $this->openAI->analyzeAnswers(['answers' => $payloadForAI]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'error' => 'openai_analyze_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $moodStr = strtolower($result['mood'] ?? 'calm');
        $moodMap = [
            'happy'     => MoodType::HAPPY,
            'sad'       => MoodType::SAD,
            'energetic' => MoodType::ENERGETIC,
            'stressed'  => MoodType::STRESSED,
            'calm'      => MoodType::CALM,
        ];
        $submission->setDeducedMood($moodMap[$moodStr] ?? MoodType::CALM);

        $activity = null;
        $genres   = [];

        foreach ($payloadForAI as $entry) {
            if (!empty($entry['isActivity'])) {
                $val = isset($entry['optionValue']) ? mb_strtolower(trim((string)$entry['optionValue'])) : null;
                if ($val !== null && $val !== '') {
                    $activity = $val;
                }
                break;
            }
        }
        foreach ($payloadForAI as $entry) {
            if (!empty($entry['isGenres']) && !empty($entry['optionValues']) && is_array($entry['optionValues'])) {
                $genres = array_values(array_unique(array_map(
                    static fn($g) => mb_strtolower(trim((string)$g)),
                    $entry['optionValues']
                )));
                break;
            }
        }

        if ($activity !== null) {
            $norm = strtr($activity, ['é' => 'e','è' => 'e','ê' => 'e','à' => 'a','â' => 'a','î' => 'i','ï' => 'i','ô' => 'o','û' => 'u','ü' => 'u']);
            $norm = preg_replace('/\s+/', ' ', $norm);
            $norm = mb_strtolower($norm);


            $actMap = [
                'sport'   => ActivityType::SPORT,
                'travail' => ActivityType::WORK,
                'detente' => ActivityType::RELAX,
                'etude'   => ActivityType::STUDY,
                'cuisine' => ActivityType::COOKING,
                'aucune'  => ActivityType::NONE,
            ];
            if (isset($actMap[$norm])) {
                $submission->setSelectedActivity($actMap[$norm]);
            }
        }

        if (!empty($genres)) {
            $lower = array_map(static fn ($g) => mb_strtolower(trim((string)$g)), $genres);

            $normalized = SpotifyGenre::normalize($lower);

            if (!empty($normalized)) {
                $submission->setPreferredGenres($normalized);
            }
        }

        $this->submissionRepo->save($submission, true);

        return new JsonResponse([
            'submissionId'    => $submission->getId(),
            'deducedMood'     => $submission->getDeducedMood()->value,
            'selectedActivity' => $submission->getSelectedActivity()?->value,
            'preferredGenres' => $submission->getPreferredGenres() ?? [],
        ], Response::HTTP_OK);
    }
}
