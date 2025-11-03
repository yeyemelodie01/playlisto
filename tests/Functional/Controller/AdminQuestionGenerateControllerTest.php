<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Administrator;
use App\Repository\AdministratorRepository;
use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdAdminQuestionGenerateControllerTest extends WebTestCase
{
    /**
     * @group live-openai
     */
    public function testGenerateQuestionsPersistsQuestionsAndOptions(): void
    {
        $apiKey = $_SERVER['OPENAI_API_KEY'] ?? $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: null;
        $live   = $_SERVER['OPENAI_LIVE']    ?? $_ENV['OPENAI_LIVE']    ?? getenv('OPENAI_LIVE')    ?: '0';

        if (!$apiKey || $live !== '1') {
            $this->markTestSkipped('Live OpenAI test skipped (set OPENAI_API_KEY and OPENAI_LIVE=1 to run).');
        }

        $client = static::createClient();
        $client->setServerParameter('HTTP_HOST', 'api.playlisto.com');

        $admin = $this->getOrCreateAdmin('functional-live-openai@test.local');
        $client->loginUser($admin);

        $surveyId = random_int(1_000_000_000, 2_000_000_000);


        $router = static::getContainer()->get(UrlGeneratorInterface::class);
        $url = $router->generate('api_admin_questions_generate');

        $client->request('POST', $url, server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'count' => 16,
            'surveyId' => $surveyId,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $items = [];
        if (isset($payload['questions']) && is_array($payload['questions'])) {
            $items = $payload['questions'];
        } elseif (is_array($payload) && array_keys($payload) === range(0, count($payload))) {
            $items = $payload;
        }

        self::assertGreaterThanOrEqual(3, count($items), 'Réponse doit contenir au moins mood + activité + genres');
        self::assertLessThanOrEqual(16, count($items), 'Ne doit pas dépasser le count demandé');

        $qRepo = static::getContainer()->get(QuestionRepository::class);

        $activityQ = $qRepo->findOneBy(['label' => 'Quelle activité faites-vous ou allez-vous faire ?']);
        self::assertNotNull($activityQ, 'Question "activité" non persistée en base.');
        self::assertSame('single', $activityQ->getType()->value);
        self::assertGreaterThanOrEqual(4, $activityQ->getAnswers()->count(), 'Options activité insuffisantes (>=4).');

        $genresQ = $qRepo->findOneBy(['label' => 'Quels genres musicaux préférez-vous ?']);
        self::assertNotNull($genresQ, 'Question "genres" non persistée en base.');
        self::assertSame('multiple', $genresQ->getType()->value);
        self::assertGreaterThanOrEqual(10, $genresQ->getAnswers()->count(), 'Options genres insuffisantes (>=10).');

        $hasYesNo = false;
        foreach ($items as $it) {
            $opts = array_map('strval', (array)($it['options'] ?? []));
            if ($it['type'] === 'single' && $opts === ['oui','non']) {
                $hasYesNo = true;
                break;
            }
        }
        self::assertTrue($hasYesNo, 'Aucune question yes/no détectée dans la génération.');
    }

    /**
     * @param string $email
     *
     * @return Administrator
     */
    private function getOrCreateAdmin(string $email): Administrator
    {
        $repo = static::getContainer()->get(AdministratorRepository::class);

        $admin = $repo->findOneBy(['email' => $email]);
        if ($admin) {
            return $admin;
        }

        $admin = (new Administrator());
        $admin->setFirstName('Mélodie');
        $admin->setLastName('YEYE');
        $admin->setEmail($email);
        $admin->setPassword('Admin123');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setSuperAdministrator(true);

        $repo->save($admin, true);

        return $admin;
    }
}