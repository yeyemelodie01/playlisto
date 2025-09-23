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
        You are an assistant generating SHORT questionnaire items for a music playlist personalization app (Playlisto).

        Goal:
        - Ask ~5–6 BEHAVIOUR-BASED questions whose answers will LET THE SYSTEM INFER the user's MOOD.
        - Do NOT ask directly "how do you feel" or any question that reveals mood explicitly.
        - After those mood-diagnostic questions, the UI will ask ACTIVITY and GENRES separately (added by backend).

        Output JSON ONLY matching the schema below. No prose.
        Schema:
        {
          "questions": [{ 
              "title": "string (FR, concise ≤ 90 chars)",
              "type": "single|multiple",
              "options": ["string", "..."]
          }]
        }
        Constraints for mood-diagnostic questions (the only ones you generate):
        - Language: French.
        - Type: "single" ONLY (binary).
        - For type = "single": options MUST be exactly ["oui", "non"].
        - Wording: behaviour/situation or recent action (ex: "Avez-vous eu envie de bouger aujourd'hui ?").
        - Avoid naming mood words (happy/sad/chill/etc.). Focus on cues: énergie, motivation, sommeil, concentration, envie de socialiser, etc.
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

        // Append structured questions handled by the backend:
        $out[] = [
            'title' => 'Quelle activité faites-vous (ou allez-vous faire) ?',
            'type'  => 'single',
            'options' => ['sport', 'travail', 'détente', 'étude', 'cuisine']
        ];

        $out[] = [
            'title' => 'Quels genres musicaux préférez-vous ?',
            'type'  => 'multiple',
            'options' => ['pop', 'rock', 'jazz', 'hip-hop', 'salsa', 'lo-fi']
        ];

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
        You will infer ONLY the user's MOOD for a music app based on behaviour-style yes/no cues.
        - Input you receive is a list of short statements/questions with answers "oui"/"non".
        - Do NOT ask or rely on direct mood words.
        - Consider energy, motivation, sleep quality, focus, social desire, irritability.
        - Output STRICT JSON only: {"mood":"happy|sad|energetic|stressed|calm"}
        - No explanations.
        SYS;

        // If the caller supplies structured behaviour answers, prefer them; otherwise send raw answers.
        $behaviour = $answers['behaviour'] ?? $answers['behavior'] ?? $answers['behaviour_answers'] ?? null;
        $user = "Behaviour Q/A (JSON):\n" . json_encode($behaviour ?? $answers, JSON_UNESCAPED_UNICODE);

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
        $activity = (string)($answers['activity'] ?? 'relax');
        $genresIn = (array)($answers['genres'] ?? []);

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
