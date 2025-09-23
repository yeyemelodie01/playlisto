<?php

namespace App\Controller\Api;

use App\Service\OpenAIService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AnalyzeAnswersController
{
    public function __construct(private readonly OpenAIService $openAI)
    {
    }

    #[Route('api/me/answers/analyze', name: 'api_me_answers_analyze')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
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

        // 2) Normaliser les réponses attendues par OpenAIService (questionId + value)
        $normalized = [];
        foreach ($answers as $a) {
            if (!is_array($a)) {
                continue;
            }
            $qid = $a['questionId'] ?? $a['question_id'] ?? null;
            $val = $a['value'] ?? $a['answer'] ?? null;
            if ($qid === null || $val === null) {
                continue;
            }
            $normalized[] = [
                'questionId' => (int) $qid,
                'value'      => (string) $val,
            ];
        }

        if ($normalized === []) {
            return new JsonResponse([
                'error' => 'invalid_answers',
                'message' => 'Each item in "answers" must include "questionId" and "value".',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 3) Appel OpenAI pour classer mood + activity (+ genres)
        try {
            $result = $this->openAI->analyzeAnswers($normalized);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'openai_analyze_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 4) Réponse normalisée : mood + activity (+ genres si dispo)
        return new JsonResponse([
            'mood'     => $result['mood']     ?? null,
            'activity' => $result['activity'] ?? null,
            'genres'   => $result['genres']   ?? [],
        ], Response::HTTP_OK);
    }
}
