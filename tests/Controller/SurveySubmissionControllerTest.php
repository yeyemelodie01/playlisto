<?php

namespace App\Tests\Controller;

use App\Controller\Api\SurveySubmissionController;
use App\Entity\SurveyAnswer;
use App\Entity\SurveySubmission;
use App\Entity\User;
use App\Repository\SurveyAnswerRepository;
use App\Repository\SurveySubmissionRepository;
use App\Service\OpenAIService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SurveySubmissionControllerTest extends TestCase
{
    public function test_submit_merges_activity_and_genres_and_persists_answers(): void
    {
        // -- Mocks
        /** @var Security&MockObject $security */
        $security = $this->createMock(Security::class);
        $user = (new User());
        $security->method('getUser')->willReturn($user);

        /** @var SurveySubmissionRepository&MockObject $submissionRepo */
        $submissionRepo = $this->createMock(SurveySubmissionRepository::class);
        $submissionRepo->expects($this->atLeastOnce())
            ->method('save')
            ->with($this->isInstanceOf(SurveySubmission::class), $this->isType('bool'));

        /** @var SurveyAnswerRepository&MockObject $answerRepo */
        $answerRepo = $this->createMock(SurveyAnswerRepository::class);
        $answerRepo->expects($this->exactly(6 + 1 + 3)) // 6 singles + Q11 + 3 genres de Q12
        ->method('save')
            ->with($this->callback(function(SurveyAnswer $a) {
                // optionValue jamais NULL
                return $a->getSubmission() !== null && $a->getQuestionId() > 0;
            }), false);

        /** @var OpenAIService&MockObject $openAI */
        $openAI = $this->createMock(OpenAIService::class);
        $openAI->method($this->logicalOr(
            $this->equalTo('analyzeSurvey'),
            $this->equalTo('analyzeAnswers'),
            $this->equalTo('analyze'),
            $this->equalTo('inferMoodActivityGenres')
        ))->willReturn(['mood' => 'stressed', 'activity' => 'ignored-by-override', 'genres' => ['ignored']]);

        // -- Controller
        $controller = new SurveySubmissionController($security, $submissionRepo, $answerRepo, $openAI);

        // -- Payload (Q11 activité, Q12 genres)
        $payload = [
            'surveyId' => 1,
            'answers' => [
                ['questionId' => 1, 'optionValue' => 'oui'],
                ['questionId' => 2, 'optionValue' => 'non'],
                ['questionId' => 3, 'optionValue' => 'oui'],
                ['questionId' => 4, 'optionValue' => 'oui'],
                ['questionId' => 5, 'optionValue' => 'non'],
                ['questionId' => 6, 'optionValue' => 'non'],
                ['questionId' => 11, 'optionValue' => 'sport'],
                ['questionId' => 12, 'optionValues' => ['zouk', 'dancehall', 'hip-hop']],
            ],
        ];
        $request = new Request(content: json_encode($payload));

        // -- Act
        /** @var JsonResponse $response */
        $response = $controller($request);
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        // -- Assert merge/override
        $this->assertSame('ok', $json['status']);
        $this->assertSame(1, $json['survey_id']);
        $this->assertArrayHasKey('analysis', $json);
        $this->assertSame('stressed', $json['analysis']['mood']);          // mood vient d’OpenAI
        $this->assertSame('sport', $json['analysis']['activity']);         // activity override par Q11
        $this->assertSame(['zouk','dancehall','hip-hop'], $json['analysis']['genres']); // genres override par Q12
    }
}