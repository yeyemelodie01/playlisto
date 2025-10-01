<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\SurveyAnswerRepository;
use App\Repository\SurveySubmissionRepository;
use App\Service\OpenAIService;
use DateTime;
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
    private const ACTIVITY_QID = 161;
    private const GENRES_QID   = 162;
    // If you want to enforce required mood-deduction questions, list them here:
    private const REQUIRED_QIDS = [155,156,157,158,159,160, self::ACTIVITY_QID, self::GENRES_QID];

    public function __construct(
        private readonly OpenAIService $openAI,
        private readonly SurveySubmissionRepository $surveySubmissionRepository,
        private readonly SurveyAnswerRepository $surveyAnswerRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/me/answers/analyze', name: 'api_me_answers_analyze', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse([
                'error' => 'invalid_json',
                'message' => 'Body must be valid JSON.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $answers = $data['answers'] ?? null;
        if (!is_array($answers) || $answers === []) {
            return new JsonResponse([
                'error' => 'invalid_payload',
                'message' => 'Field "answers" is required and must be a non-empty array.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Ensure all required question IDs are present
        $receivedQids = [];
        foreach ($answers as $a) {
            if (isset($a['questionId'])) {
                $receivedQids[] = (int) $a['questionId'];
            }
        }
        $missing = array_values(array_diff(self::REQUIRED_QIDS, $receivedQids));
        if ($missing) {
            return new JsonResponse([
                'error'   => 'missing_required_questions',
                'message' => 'Some required questions are missing.',
                'missing' => $missing,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 🔹 1) Créer une nouvelle soumission
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            // ça ne devrait pas arriver avec #[IsGranted('ROLE_USER')],
            // mais on gère proprement le cas
            return new JsonResponse([
                'error' => 'unauthenticated',
                'message' => 'Authenticated User entity not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $submission = new SurveySubmission();
        $submission->setUser($user);
        $submission->setCreatedAt(new DateTime());
        $this->surveySubmissionRepository->save($submission);

        // 🔹 2) Enregistrer chaque réponse
        $normalized = [];
        foreach ($answers as $a) {
            $qid = $a['questionId'] ?? null;
            $val = $a['value'] ?? null;
            if ($qid === null || $val === null) {
                continue;
            }

            // Support single and multiple answers. Value MUST contain numeric option id(s).
            $optionIds = is_array($val) ? $val : [$val];

            // Validate that all optionIds are numeric
            foreach ($optionIds as $opt) {
                if (!is_numeric($opt)) {
                    return new JsonResponse([
                        'error' => 'invalid_option_value',
                        'message' => sprintf('Question %d expects numeric option id(s). Got: %s', (int)$qid, json_encode($val)),
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            foreach ($optionIds as $opt) {
                $surveyAnswer = new SurveyAnswer();
                $surveyAnswer->setSubmission($submission);
                $surveyAnswer->setQuestionId((int)$qid);
                $surveyAnswer->setOptionId((int)$opt);
                $this->surveyAnswerRepository->save($surveyAnswer);
            }

            // Keep the raw value for OpenAI (ids or array of ids)
            $normalized[] = [
                'questionId' => (int)$qid,
                'value' => $val,
            ];
        }

        // 🔹 3) Analyse via OpenAI
        try {
            $result = $this->openAI->analyzeAnswers($normalized);
        } catch (Throwable $e) {
            return new JsonResponse([
                'error' => 'openai_analyze_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 🔹 4) Réponse finale
        return new JsonResponse([
            'submissionId' => $submission->getId(),
            'mood' => $result['mood'] ?? null,
            'activity' => $result['activity'] ?? null,
            'genres' => $result['genres'] ?? [],
        ], Response::HTTP_OK);
    }
}
