<?php

namespace App\Controller\Api;

use App\Entity\AnswerOption;
use App\Repository\QuestionRepository;
use App\Service\OpenAIService;
use App\Entity\Question;
use App\Enum\QuestionType;
use JsonException;
use Random\RandomException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

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
     * @throws JsonException|RandomException
     */
    #[Route('/api/admin/questions/generate', name: 'api_admin_questions_generate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            $raw = $request->getContent() ?: '{}';
            $payload = json_decode($raw, true) ?: [];
        }

        $surveyId = 0;

        $fromPayload = $payload['surveyId'] ?? null;
        if ($fromPayload !== null && is_numeric($fromPayload)) {
            $surveyId = (int) $fromPayload;
        }

        if ($surveyId <= 0 && $request->query->has('surveyId')) {
            $surveyId = $request->query->getInt('surveyId');
        }

        if ($surveyId <= 0 && $request->request->has('surveyId')) {
            $surveyId = (int) $request->request->get('surveyId');
        }

        if ($surveyId <= 0) {
            $hdr = $request->headers->get('X-Survey-Id');
            if ($hdr !== null && is_numeric($hdr)) {
                $surveyId = (int) $hdr;
            }
        }

        $count = (int)($payload['count'] ?? 6);
        if ($count < 5) {
            $count = 5;
        }
        if ($count > 50) {
            $count = 50;
        }

        $generatedSurveyId = false;
        if ($surveyId <= 0) {
            $surveyId = (int) floor(microtime(true));
            if ($surveyId <= 0) {
                $surveyId = random_int(1_000_000, 9_999_999);
            }
            $generatedSurveyId = true;
        }

        try {
            $items = $this->openAIService->generateQuestions($count);

            $created = [];
            foreach ($items as $i) {
                $rawTitle = isset($i['title']) ? trim((string) $i['title']) : '';
                $rawOpts  = isset($i['options']) && is_array($i['options']) ? array_values($i['options']) : [];

                $typeStr = isset($i['type']) ? strtolower((string) $i['type']) : 'single';
                $typeStr = in_array($typeStr, ['single','multiple'], true) ? $typeStr : 'single';

                $title = $rawTitle;
                if ($typeStr === 'single' && $rawOpts === ['oui','non'] && !str_ends_with($title, '?')) {
                    $title .= ' ?';
                    $title = preg_replace('/\s+\?$/u', ' ?', $title);
                }
                if ($title === '') {
                    continue;
                }

                $q = new Question();
                $q->setLabel($title);
                $q->setSurveyId($surveyId);
                $typeEnum = match ($typeStr) {
                    'multiple' => QuestionType::MULTIPLE,
                    default    => QuestionType::SINGLE,
                };
                $q->setType($typeEnum);

                foreach ($rawOpts as $opt) {
                    $text = trim((string)$opt);
                    if ($text === '') {
                        continue;
                    }

                    $a = new AnswerOption();
                    $a->setLabel($text);
                    $q->addAnswer($a);
                }

                $this->questionRepository->save($q, true);

                $answersOut = [];
                foreach ($q->getAnswers() as $ans) {
                    $answersOut[] = $ans->getLabel();
                }
                $answersOut = array_values(array_unique(array_filter($answersOut, static fn($v) => $v !== null && $v !== '')));

                $created[] = [
                    'id'       => $q->getId(),
                    'label'    => $q->getLabel(),
                    'type'     => $q->getType()->value,
                    'options'  => $answersOut,
                    'surveyId' => $surveyId,
                ];
            }

            return new JsonResponse([
                'surveyId'        => $surveyId,
                'generatedServer' => $generatedSurveyId,
                'questions'       => $created,
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return new JsonResponse([
                'error' => 'openai_generation_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
