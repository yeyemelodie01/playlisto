<?php

namespace App\Controller\Api;

use App\Repository\QuestionRepository;
use App\Entity\Answer;
use App\Service\OpenAIService;
use App\Entity\Question;
use App\Enum\QuestionType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Class AdminQuestionGenerateController.
 *
 * This controller handles the generation of questions using the OpenAI service.
 *
 * @psalm-suppress UnusedClass
 */
final class AdminQuestionGenerateController
{
    public function __construct(
        private readonly OpenAIService $openAIService,
        private readonly QuestionRepository $questionRepository
    ) {
    }

    /**
     * Generate questions using OpenAI and store them in the database.
     *
     * Expects a JSON payload with an optional 'count' parameter to specify the number of questions to generate (default is 6).
     *
     * @param Request $request The HTTP request object
     *
     * @return JsonResponse A JSON response containing the created questions or an error message
     * @throws \JsonException
     */
    #[Route('/api/admin/questions/generate', name: 'api_admin_questions_generate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $count = (int)($payload['count'] ?? 6);

        try {
            $items = $this->openAIService->generateQuestions($count);

            $created = [];
            foreach ($items as $i) {
                $rawTitle = isset($i['title']) ? trim((string) $i['title']) : '';
                $rawOpts  = isset($i['options']) && is_array($i['options']) ? array_values($i['options']) : [];

                $typeStr = isset($i['type']) ? strtolower((string) $i['type']) : 'single';
                $typeStr = in_array($typeStr, ['single','multiple'], true) ? $typeStr : 'single';
                if ($rawTitle === '') {
                    continue;
                }


                $q = new Question();
                $q->setLabel($rawTitle);
                $q->setType(QuestionType::from($typeStr));


                foreach ($rawOpts as $opt) {
                    $text = trim((string) $opt);
                    if ($text === '') {
                        continue;
                    }

                    $a = new Answer();
                    if (method_exists($a, 'setLabel')) {
                        $a->setLabel($text);
                    } elseif (method_exists($a, 'setContent')) {
                        $a->setContent($text);
                    } elseif (method_exists($a, 'setText')) {
                        $a->setText($text);
                    }
                    $q->addAnswer($a);
                }


                $this->questionRepository->save($q, true);

                $answersOut = [];
                foreach ($q->getAnswers() as $ans) {
                    $answersOut[] = method_exists($ans, 'getLabel') ? $ans->getLabel() : (method_exists($ans, 'getContent') ? $ans->getContent() : null);
                }
                $answersOut = array_values(array_filter($answersOut, static fn($v) => $v !== null && $v !== ''));

                $created[] = [
                    'id'      => method_exists($q, 'getId') ? $q->getId() : null,
                    'label'   => $q->getLabel(),
                    'type'    => method_exists($q, 'getType') ? $q->getType()->value : $typeStr,
                    'options' => $answersOut,
                ];
            }

            return new JsonResponse($created, Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'openai_generation_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
