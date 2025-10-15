<?php

namespace App\Tests\Unit\Service;

use App\Service\OpenAIService;
use JsonException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class OpenAIServiceTest extends TestCase
{
    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface|JsonException
     */
    public function testAnalyzeAnswerReturnsMoodAndFiltersNonBehaviour(): void
    {
        $apiPayload = [
            'choices' => [
                ['message' => ['content' => json_encode(['mood' => 'energetic'], JSON_THROW_ON_ERROR)]],
            ]
        ];
        $mockResponse = new MockResponse(
            json_encode($apiPayload, JSON_THROW_ON_ERROR),
            [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json'],
            ]
        );

        $client = new MockHttpClient($mockResponse);

        $service = new OpenAIService(
            httpClient: $client,
            apiKey: 'test',
            model: 'gpt-4o-mini',
            timeout: 5
        );

        $answers = [
            'answers' => [
                ['questionId' => 1, 'optionValue' => 'oui'],
                ['questionId' => 12, 'optionValue' => 'détente', 'isActivity' => true],
                ['questionId' => 13, 'optionValue' => ['chill', 'lo-fi'], 'isGenres' => true],
            ],
        ];

        $result = $service->analyzeAnswers($answers);

        self::assertSame(['mood' => 'energetic'], $result);
    }

}