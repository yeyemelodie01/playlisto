<?php

namespace App\Service;

use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service to interact with OpenAI's API for generating questions.
 *
 * @psalm-suppress PossiblyUnusedMethod
 */
final readonly class OpenAIService
{
    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * @param HttpClientInterface $httpClient
     * @param string              $apiKey
     * @param string              $model
     * @param int                 $timeout
     *
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
        private readonly int $timeout = 15
    ) {
    }

    /**
     * Generate a list of questions for user profiling.
     *
     * @param int $count Number of questions to generate (1-20)
     *
     * @return array Generated questions
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function generateQuestions(int $count = 6): array
    {
        $count = max(1, min(20, $count));

        $system = <<<SYS
            You are an assistant generating short questionnaire items for a music playlist personalization app (Playlisto).
            Output JSON ONLY matching the schema. No prose.
            Schema:
            {
              "questions": [
                { "title": "string",
                  "type": "single|multiple",
                  "options": ["string", "..."]
                }
              ]
            }
            Constraints:
            - Titles concise (<= 90 chars), French.
            - Types allowed: "single" or "multiple" only (no other value).
            - For type = "single": options MUST be exactly ["oui", "non"].
            - For type = "multiple": provide 4 to 6 relevant options (French, lowercase, 1-3 words.
            - Always include the "options" array.
            SYS;

        $user = sprintf('Generate %d questions tailored to mood/activity/music preferences for daily life (travail, sport, détente, etc.).', $count);

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.4,
            'response_format' => ['type' => 'json_object'],
        ];

        $raw = $this->request($payload);

        $json = json_decode($raw, true);
        $questions = $json['questions'] ?? [];

        $out = [];
        foreach ($questions as $q) {
            if (!isset($q['title'], $q['type'])) {
                continue;
            }
            $type = in_array($q['type'], ['single','multiple'], true) ? $q['type'] : 'single';
            $item = [
                'title' => trim((string)$q['title']),
                'type'  => $type,
            ];
            if ($type === 'single') {
                $item['options'] = ['oui', 'non'];
            } elseif ($type === 'multiple') {
                $opts = array_values(array_filter(array_map('strval', $q['options'] ?? [])));
                // Ensure between 4 and 6 options for multiple; fallback if missing
                if (count($opts) < 4) {
                    $opts = ['travail', 'sport', 'détente', "étude"];
                }
                $item['options'] = array_slice($opts, 0, 6);
            }
            $out[] = $item;
            if (count($out) >= $count) {
                break;
            }
        }

        return $out;
    }

    /**
     * Analyze user answers to classify into mood and activity.
     *
     * @param array $answers
     *
     * @return array ['mood' => string, 'activity' => string]
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function analyzeAnswers(array $answers): array
    {
        $system = <<<SYS
        You classify a user's answers into three labels for a music app:
        - "mood": one of ["happy","sad","energetic","stressed","calm"]
        - "activity": one of ["sport","work","relax","study","cooking"]
        - "genres": array of 1–3 short genre names (free form, e.g., hip hop, rap, salsa, jazz)

        Return STRICT JSON only:
        {"mood":"...","activity":"...","genres":["..."]}
        No explanations.
        SYS;

        $user = "Answers (JSON):\n" . json_encode($answers, JSON_UNESCAPED_UNICODE);

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ];

        $raw = $this->request($payload);
        $json = json_decode($raw, true);

        $mood = $json['mood'] ?? 'calm';
        $activity = $json['activity'] ?? 'relax';
        $genresIn = $json['genres'] ?? [];

        $moods = ["happy","sad","energetic","stressed","calm"];
        $activities = ["sport","work","relax","study","cooking"];

        if (!in_array($mood, $moods, true)) {
            $mood = 'calm';
        }
        if (!in_array($activity, $activities, true)) {
            $activity = 'relax';
        }

        // Normalize free-form genres and map to Spotify-friendly seeds when possible
        $seedMap = [
            'hip hop' => 'hip-hop', 'hip-hop' => 'hip-hop', 'rap' => 'hip-hop',
            'r&b' => 'r-n-b', 'rnb' => 'r-n-b', 'r-n-b' => 'r-n-b',
            'lofi' => 'lo-fi', 'lo-fi' => 'lo-fi', 'chill' => 'chill',
            'ambient' => 'ambient', 'acoustic' => 'acoustic', 'classical' => 'classical',
            'pop' => 'pop', 'rock' => 'rock', 'jazz' => 'jazz', 'blues' => 'blues',
            'soul' => 'soul', 'funk' => 'funk', 'edm' => 'edm', 'dance' => 'dance',
            'electro' => 'edm', 'electronic' => 'edm',
            'latin' => 'latin', 'salsa' => 'salsa', 'reggaeton' => 'reggaeton',
            'afrobeat' => 'afrobeat', 'k-pop' => 'k-pop', 'metal' => 'metal',
            'punk' => 'punk', 'country' => 'country', 'house' => 'house',
            'techno' => 'techno', 'trap' => 'trap', 'dubstep' => 'dubstep'
        ];

        $genres = [];
        foreach ((array)$genresIn as $g) {
            $g = strtolower(trim((string)$g));
            $g = preg_replace('/\s+/', ' ', $g); // normalize spaces
            $mapped = $seedMap[$g] ?? null;
            $genres[] = $mapped ?: $g; // keep original if unmapped
        }
        // de-dup and keep 1–3
        $genres = array_values(array_unique(array_filter($genres, fn($v) => $v !== '')));
        if (count($genres) === 0) {
            $genres = ['pop'];
        }
        if (count($genres) > 3) {
            $genres = array_slice($genres, 0, 3);
        }

        return ['mood' => $mood, 'activity' => $activity, 'genres' => $genres];
    }


    /**
     * Send a request to OpenAI's API.
     *
     * @param array $payload
     *
     * @return string
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function request(array $payload): string
    {
        $resp = $this->httpClient->request('POST', self::OPENAI_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => $this->timeout,
            'json' => $payload,
        ]);

        $status = $resp->getStatusCode();
        $raw    = $resp->getContent(false);
        $data   = json_decode($raw, true);

        if ($status >= 400) {
            $msg = is_array($data) && isset($data['error']['message'])
                ? $data['error']['message']
                : ($raw ?: ('HTTP ' . $status));
            throw new RuntimeException('OpenAI request failed: ' . $msg);
        }

        if (is_array($data) && isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'unknown error';
            throw new RuntimeException('OpenAI error: ' . $msg);
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || $content === '') {
            $snippet = is_string($raw) ? substr($raw, 0, 400) : '';
            throw new RuntimeException('OpenAI empty/invalid content. Raw: ' . $snippet);
        }

        return $content;
    }
}
