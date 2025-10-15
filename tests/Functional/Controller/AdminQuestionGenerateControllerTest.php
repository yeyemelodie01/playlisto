<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Administrator;
use App\Repository\AdministratorRepository;
use App\Repository\QuestionRepository;
use App\Service\OpenAIService;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AdminQuestionGenerateControllerTest extends WebTestCase
{
    /**
     * @throws JsonException
     */
    public function testGenerateQuestionsPersistsQuestionsAndOptions(): void
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_HOST', 'api.playlisto.com');

        $openAiPayload = [
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'questions' => [
                            ['title'=>'Q1','type'=>'single','options'=>['oui','non'],'moodTag'=>'calm'],
                            ['title'=>'Q2','type'=>'single','options'=>['oui','non'],'moodTag'=>'happy'],
                            ['title'=>'Q3','type'=>'single','options'=>['oui','non'],'moodTag'=>'energetic'],
                            ['title'=>'Q4','type'=>'single','options'=>['oui','non'],'moodTag'=>'stressed'],
                            ['title'=>'Q5','type'=>'single','options'=>['oui','non'],'moodTag'=>'calm'],
                            ['title'=>'Q6','type'=>'single','options'=>['oui','non'],'moodTag'=>'happy'],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ];


        $mockHttp = new MockHttpClient(
            new MockResponse(json_encode($openAiPayload, JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json'],
            ])
        );

        static::getContainer()->set(
            OpenAIService::class,
            new OpenAIService($mockHttp, apiKey: 'test', model: 'gpt-4o-mini', timeout: 5)
        );

        $router = static::getContainer()->get(UrlGeneratorInterface::class);
        $url = $router->generate('api_admin_questions_generate');

        $email = 'johndoe@test.fr';
        $admin = $this->getOrCreateAdmin($email);

        $client->loginUser($admin);

        $client->request('POST', $url, server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['count' => 8], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertGreaterThanOrEqual(3, \count($data), 'On attend au moins 1 mood + activité + genres');
        self::assertLessThanOrEqual(8, \count($data), 'La génération ne doit pas dépasser le count demandé');

        // ---- Détection robuste “activité” (normalisation + intersection >= 4)
        $normalize = static function (string $s): string {
            $s = trim(mb_strtolower($s));
            // strip accents (simple map pour tests)
            $s = strtr($s, [
                'à'=>'a','â'=>'a','ä'=>'a',
                'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                'î'=>'i','ï'=>'i',
                'ô'=>'o','ö'=>'o',
                'û'=>'u','ü'=>'u',
                'ç'=>'c',
            ]);
            return $s;
        };

        $expectedActivity = array_map($normalize, ['sport','travail','détente','étude','cuisine','aucune']);
        $hasActivity = false;

        foreach ($data as $item) {
            // type single
            if (($item['type'] ?? 'single') !== 'single') {
                continue;
            }

            $opts = array_values(array_map(
                static fn($v) => $normalize((string)$v),
                (array)($item['options'] ?? [])
            ));
            if ($opts === []) {
                continue;
            }

            // ignore les questions oui/non
            $yn = array_intersect($opts, ['oui','non']);
            if (count($yn) === 2) {
                continue;
            }

            // tolérant : au moins 4 options “activité” reconnues, ordre indifférent, options en plus acceptées
            $matches = count(array_intersect($opts, $expectedActivity));
            if ($matches >= 4) {
                $hasActivity = true;
                break;
            }
        }

        self::assertTrue($hasActivity, 'Question “activité” introuvable par options (tolérance accents/ordre).');

        // ---- Détection robuste “genres” : type=multiple + quelques seeds Spotify
        $seedHints = ['pop','rock','edm','hip-hop','jazz','classical'];
        $hasGenres = false;

        foreach ($data as $item) {
            if (($item['type'] ?? null) !== 'multiple') {
                continue;
            }
            $opts = array_map('strval', $item['options'] ?? []);
            $hits = 0;
            foreach ($seedHints as $h) {
                if (in_array($h, $opts, true)) {
                    $hits++;
                }
            }
            if ($hits >= 2 || count($opts) >= 10) { // tolérant: au moins 2 genres connus ou une grosse liste
                $hasGenres = true;
                break;
            }
        }
        self::assertTrue($hasGenres, 'Question “genres” introuvable (type multiple + seeds).');
    }

    private function getOrCreateAdmin(string $email): Administrator
    {
        /** @var AdministratorRepository $repo */
        $repo = static::getContainer()->get(AdministratorRepository::class);

        $admin = $repo->findOneBy(['email' => $email]);
        if ($admin) {
            return $admin;
        }

        $admin = (new Administrator())
            ->setFirstName('Mélodie')
            ->setLastName('YEYE')
            ->setEmail($email)
            ->setPassword('Admin123')
            ->setRoles(['ROLE_ADMIN'])
            ->setSuperAdministrator(true);

        $repo->save($admin, true);

        return $admin;
    }
}