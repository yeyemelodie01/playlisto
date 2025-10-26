<?php

namespace App\Tests\Functional\Controller;

use App\Entity\AnswerOption;
use App\Entity\Question;
use App\Entity\User;
use App\Repository\AnswerOptionRepository;
use App\Repository\QuestionRepository;
use App\Repository\SurveySubmissionRepository;
use App\Repository\UserRepository;
use App\Service\OpenAIService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AnalyzeAnswersControllerTest extends WebTestCase
{
    /**
     * @throws \JsonException
     */
    public function testAnalyzeAnswersWithExistingQuestions(): void
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_HOST', 'api.playlisto.com');

        $mockBody = json_encode([
            'choices' => [[
                'message' => ['content' => '{"mood":"energetic"}']
            ]]
        ], JSON_THROW_ON_ERROR);

        $mockHttp = new MockHttpClient(
            new MockResponse($mockBody, [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json'],
            ])
        );
        static::getContainer()->set(
            OpenAIService::class,
            new OpenAIService($mockHttp, apiKey: 'test', model: 'gpt-4o-mini', timeout: 5)
        );

        $this->getOrCreateUser('user.functional@test.local');
        $token = $this->getJwt($client, 'user.functional@test.local', 'User123');

        $qRepo   = static::getContainer()->get(QuestionRepository::class);

        $anyQ = $qRepo->findOneBy([], ['surveyId' => 'DESC']);
        self::assertNotNull($anyQ, 'Aucune question en base (exécute d’abord le test de génération).');
        $surveyId = $anyQ->getSurveyId();

        $answers = [
            ['questionId' => 1,  'optionValue' => 'oui'],
            ['questionId' => 2,  'optionValue' => 'oui'],
            ['questionId' => 3,  'optionValue' => 'non'],
            ['questionId' => 4,  'optionValue' => 'oui'],
            ['questionId' => 5,  'optionValue' => 'oui'],
            ['questionId' => 6,  'optionValue' => 'oui'],
            ['questionId' => 7,  'optionValue' => 'non'],
            ['questionId' => 8,  'optionValue' => 'oui'],
            ['questionId' => 9,  'optionValue' => 'non'],
            ['questionId' => 10, 'optionValue' => 'oui'],
            ['questionId' => 11, 'optionValue' => 'oui'],
            ['questionId' => 12, 'optionValue' => 'oui'],
            ['questionId' => 13, 'optionValue' => 'non'],
            ['questionId' => 14, 'optionValue' => 'sport'],
            ['questionId' => 15, 'optionValues' => ['pop', 'rock', 'edm']],
        ];

        $client->request('POST', '/api/me/surveys/submit', server: [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_ACCEPT'        => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'surveyId' => $surveyId,
            'answers'  => $answers,
        ], JSON_THROW_ON_ERROR));

        self::assertTrue(
            \in_array($client->getResponse()->getStatusCode(), [200, 201], true),
            'HTTP attendu 200/201, reçu '.$client->getResponse()->getStatusCode()."\n".$client->getResponse()->getContent()
        );

        $resp = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ok', $resp['status'] ?? null, 'status doit être "ok"');

        self::assertArrayHasKey('submission_id', $resp);
        self::assertIsInt($resp['submission_id']);

        self::assertArrayHasKey('survey_id', $resp);
        self::assertIsInt($resp['survey_id']);

        self::assertArrayHasKey('analysis', $resp);
        self::assertIsArray($resp['analysis']);

        $analysis = $resp['analysis'];

        self::assertArrayHasKey('mood', $analysis);
        self::assertContains($analysis['mood'], ['happy','sad','energetic','stressed','calm']);

        self::assertArrayHasKey('activity', $analysis);
        self::assertTrue($analysis['activity'] === null || \is_string($analysis['activity']));

        self::assertArrayHasKey('genres', $analysis);
        self::assertIsArray($analysis['genres']);
        self::assertNotEmpty($analysis['genres'], 'Les genres préférés ne doivent pas être vides');
    }

    /**
     * @param string $email
     *
     * @return User
     */
    private function getOrCreateUser(string $email): User
    {
        $repo = static::getContainer()->get(UserRepository::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = $repo->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            $needsUpdate = false;

            if (!in_array('ROLE_USER', $user->getRoles(), true)) {
                $user->setRoles(['ROLE_USER']);
                $needsUpdate = true;
            }

            $hash = $user->getPassword();
            if (!\is_string($hash) || $hash === '' || !str_starts_with($hash, '$2')) {
                $user->setPassword($hasher->hashPassword($user, 'User123'));
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $repo->save($user, true);
            }

            return $user;
        }
        
        $user = new User();
        $user->setEmail($email);
        $user->setUsername('functional-user-test');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($hasher->hashPassword($user, 'User123'));

        $repo->save($user, true);

        return $user;
    }

    /**
     * @param        $client
     * @param string $email
     * @param string $password
     *
     * @return string
     *
     * @throws \JsonException
     */
    private function getJwt($client, string $email, string $password): string
    {
        $client->request('POST', '/api/authentication_token', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
        ], content: json_encode([
            'email'    => $email,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200, 'Login JWT doit répondre 200');

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $payload, 'Réponse login JWT doit contenir "token"');

        return $payload['token'];
    }
}