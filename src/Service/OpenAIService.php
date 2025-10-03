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
    public function generateQuestions(int $count = 10): array
    {
        // Clamp requested count between 1 and 20 (safety bound)
        $count = max(1, min(20, $count));

        // System prompt: generate behaviour-only questions to infer MOOD (not activity/genres).
        $system = <<<SYS
        You are an assistant generating SHORT questionnaire items for a music playlist personalization app (Playlisto).

        Goal:
        - Ask 10 BEHAVIOUR-BASED questions whose answers will LET THE SYSTEM INFER the user's MOOD.
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
        Constraints for mood-diagnostic questions (the only ones you generate):
        - Language: French.
        - Count: exactly 10 questions.
        - Type: "single" ONLY (binary).
        - For type = "single": options MUST be exactly ["oui", "non"].
        - Wording: behaviour/situation or recent action (ex: "Avez-vous eu envie de bouger aujourd'hui ?").
        - Avoid naming mood words (happy/sad/chill/etc.). Focus on cues: énergie, motivation, sommeil, concentration, envie de socialiser, irritabilité, stress perçu, patience, etc.
        - Always include the "options" array.
        SYS;

        // User hint to the model (kept short — we control details in the system message)
        $user = sprintf('Generate %d behaviour questions for everyday life contexts (travail, sport, détente, étude, trajets, maison).', $count);

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

        // Full official Spotify seed genres list (kept in PHP for backend-driven select).
        // Source: Spotify "available-genre-seeds" (flattened and curated; update if Spotify adds more).
        $spotifySeeds = [
            'acoustic','afrobeat','alt-rock','alternative','ambient','black-metal','bluegrass','blues','bossa-nova','brazil','breakbeat','britpop','chicago-house','children','chill','classical','club','comedy','country','dance','dancehall','deep-house','disco','drum-and-bass','dub','dubstep','edm','electro','electronic','emo','folk','forro','funk','garage','german','gospel','goth','grindcore','groove','grunge','guitar','happy','hard-rock','hardcore','hardstyle','heavy-metal','hip-hop','house','idm','indian','indie','indie-pop','industrial','jazz','k-pop','latin','lo-fi','metal','minimal-techno','mpb','new-age','opera','pagode','party','piano','pop','progressive-house','punk','r-n-b','reggae','reggaeton','rock','rock-n-roll','romance','salsa','samba','sertanejo','show-tunes','singer-songwriter','ska','sleep','songwriter','soul','soundtracks','spanish','study','swedish','synthpop','tango','techno','trance','trap','trip-hop','turkish','work-out','world-music'
        ];

        // Append structured questions handled by the backend:
        // Full official Spotify seed genres list (kept in PHP for backend-driven select).
        // Source: Spotify "available-genre-seeds" (flattened and curated; update if Spotify adds more).
        // Append structured questions handled by the backend (kept here to guide front):
        $out[] = [
            'title' => 'Quelle activité faites-vous (ou allez-vous faire) ?',
            'type'  => 'single',
            'options' => ['sport', 'travail', 'détente', 'étude', 'cuisine']
        ];

        // For genres, present the entire Spotify seed list (plus a few mapped extras).
        $out[] = [
            'title' => 'Quels genres musicaux préférez-vous ?',
            'type'  => 'multiple',
            'options' => $spotifySeeds,
        ];

        return $out;
    }

    /**
     * Analyze user answers and infer ONLY the mood.
     *
     * Notes:
     * - We intentionally exclude activity (Q7) and genres (Q8) from the LLM prompt.
     * - Return array contains only: ['mood' => 'happy|sad|energetic|stressed|calm'].
     *
     * @param array $answers
     *   Supported shapes:
     *   1) ['answers' => [['questionId'=>int,'optionValue'=>string]|['questionId'=>int,'optionValues'=>string[]], ...], 'activity' => ..., 'genres' => [...]]
     *   2) Flat behaviour payload under keys 'behaviour'|'behavior'|'behaviour_answers' (activity/genres keys, if present, are dropped before sending).
     *
     * @return array{mood:string}
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
        - Input is a list of short statements/questions with answers "oui"/"non".
        - Do NOT ask or rely on direct mood words.
        - Consider energy, motivation, sleep quality, focus, social desire, irritability.
        - Output STRICT JSON only: {"mood":"happy|sad|energetic|stressed|calm"}
        - No explanations.
        SYS;

        // Build a behaviour-only payload for OpenAI (exclude activity & genres prompts).
        // We support two shapes:
        //  1) { answers: [{questionId, optionValue|optionValues}, ...], activity: ..., genres: [...] }
        //  2) a flat object with keys like 'behaviour' / 'behavior' / 'behaviour_answers'
        if (isset($answers['answers']) && is_array($answers['answers'])) {
            // Filter out the last two UI questions (activity = Q7, genres = Q8)
            $behaviourPayload = array_values(array_filter($answers['answers'], static function ($a) {
                $qid = $a['questionId'] ?? null;
                return $qid !== 7 && $qid !== 8;
            }));
        } else {
            // Legacy / flat shape
            $behaviourPayload = $answers['behaviour'] ?? $answers['behavior'] ?? $answers['behaviour_answers'] ?? $answers;
            if (is_array($behaviourPayload)) {
                unset($behaviourPayload['activity'], $behaviourPayload['genres']);
            }
        }

        $user = "Behaviour Q/A (JSON):\n" . json_encode($behaviourPayload, JSON_UNESCAPED_UNICODE);

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ];

        $raw  = $this->request($payload);
        $json = json_decode($raw, true);

        $mood = $json['mood'] ?? 'calm';
        $allowed = ["happy","sad","energetic","stressed","calm"];
        if (!in_array($mood, $allowed, true)) {
            $mood = 'calm';
        }

        return ['mood' => $mood];
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
