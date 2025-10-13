<?php

namespace App\Service;

use App\Enum\SpotifyGenre;
use Random\RandomException;
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
        private readonly int $timeout = 30
    ) {
    }

    /**
     * Generate a list of questions for user profiling.
     *
     * @param int $total
     *
     * @return array Generated questions
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws RandomException
     */
    public function generateQuestions(int $total = 16): array
    {
        // keep a strict total of $total (default 16), reserving 2 for activity + genres
        $total = max(5, min(50, $total)); // safety headroom
        $reservedTail = 2;
        $maxBehaviour = max(1, $total - $reservedTail); // mood Qs budget
        $minBehaviour = min(4, $maxBehaviour);          // at least 4 if possible

        // Build seeds from enum (no hard-coded list)
        $spotifySeeds = array_map(static fn(SpotifyGenre $g) => $g->value, SpotifyGenre::cases());

        // Limit of “Avez-vous” starters (⅓ of behaviour questions, rounded up)
        $maxAvezVous = (int)ceil($maxBehaviour / 3);
        $nonce = bin2hex(random_bytes(4));

        $system = <<<SYS
        You are an assistant generating SHORT questionnaire items for a music playlist personalization app (Playlisto).
        
        Goal:
        - Generate French yes/no questions that help deduce the user's MOOD.
        - Let K be the number of mood-diagnostic questions you choose.
        - Constraints on K: {$minBehaviour} ≤ K ≤ {$maxBehaviour}.
        - Do NOT include any questions about activity or genres; ONLY behaviour questions here.
        
        Stylistic constraints:
        - All questions are yes/no with options exactly ["oui","non"].
        - Vary openings: "Est-ce que…", "Aujourd'hui…", "Ces derniers jours…", "Vous est-il arrivé de…", "Votre journée s'est-elle…", "A-t-il été…", and limited "Avez-vous".
        - At most {$maxAvezVous} items may start with "Avez-vous".
        - Keep neutral wording (no explicit emotion words), everyday contexts (travail, sport, maison, étude, trajets, repos).
        - No parentheses. <= 90 chars per title.
        
        Schema (STRICT JSON, no prose):
        {
          "questions": [
            {
              "title": "string (FR, <= 90 chars)",
              "type": "single",
              "options": ["oui","non"],
              "moodTag": "happy|sad|energetic|stressed|calm"
            }
          ]
        }
        
        Hard constraints:
        - Language: French.
        - Count: exactly K items you choose within bounds.
        - type MUST be "single".
        - options MUST be exactly ["oui","non"].
        - moodTag MUST be one of: happy, sad, energetic, stressed, calm.
        
        Return ONLY the JSON object above. Do not include explanations or extra text.
        Nonce: {$nonce}
        SYS;

        $user = 'Produce the behaviour questions only (no activity, no genres).';

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.4,
            'presence_penalty' => 0.4,
            'frequency_penalty' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 800,
        ];

        $raw = $this->request($payload);
        $json = json_decode($raw, true);
        $questions = is_array($json['questions'] ?? null) ? $json['questions'] : [];

        // Post-filter + dedup, enforce bounds (in case model over-produced)
        $seen = [];
        $behaviourOut = [];
        foreach ($questions as $q) {
            if (count($behaviourOut) >= $maxBehaviour) {
                break;
            }
            $title = isset($q['title']) ? trim((string)$q['title']) : '';
            if ($title === '') {
                continue;
            }
            $key = mb_strtolower($title);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $tag = isset($q['moodTag']) ? strtolower((string)$q['moodTag']) : null;
            if (!in_array($tag, ['happy','sad','energetic','stressed','calm'], true)) {
                continue;
            }

            $behaviourOut[] = [
                'title'   => $title,
                'type'    => 'single',
                'options' => ['oui','non'],
                'moodTag' => $tag,
            ];
        }


        if (count($behaviourOut) < $minBehaviour && count($behaviourOut) > 0) {
        }

        $out = $behaviourOut;

        $out[] = [
            'title'   => 'Quelle activité faites-vous ou allez-vous faire',
            'type'    => 'single',
            'options' => ['sport', 'travail', 'détente', 'étude', 'cuisine', 'aucune'],
        ];

        $out[] = [
            'title'   => 'Quels genres musicaux préférez-vous',
            'type'    => 'multiple',
            'options' => $spotifySeeds, // from enum
        ];

        // Keep the grand total to $total (hard cap)
        if (count($out) > $total) {
            // Prefer trimming behaviour part (never drop the 2 tail)
            $keepBehaviour = max(0, $total - 2);
            $out = array_slice($behaviourOut, 0, $keepBehaviour);
            $out[] = [
                'title'   => 'Quelle activité faites-vous ou allez-vous faire ?',
                'type'    => 'single',
                'options' => ['sport', 'travail', 'détente', 'étude', 'cuisine', 'aucune'],
            ];
            $out[] = [
                'title'   => 'Quels genres musicaux préférez-vous ?',
                'type'    => 'multiple',
                'options' => $spotifySeeds,
            ];
        }

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

        // Normalize input shape
        $rawAnswers = [];
        if (isset($answers['answers']) && is_array($answers['answers'])) {
            $rawAnswers = $answers['answers'];
        } elseif (isset($answers['behaviour']) && is_array($answers['behaviour'])) {
            $rawAnswers = $answers['behaviour'];
        } elseif (isset($answers['behavior']) && is_array($answers['behavior'])) {
            $rawAnswers = $answers['behavior'];
        } elseif (isset($answers['behaviour_answers']) && is_array($answers['behaviour_answers'])) {
            $rawAnswers = $answers['behaviour_answers'];
        } elseif (is_array($answers)) {
            $rawAnswers = $answers;
        }

        // Known activity choices (FR) — used to filter out activity
        $activityChoices = ['sport','travail','détente','étude','cuisine','aucune'];

        // Filter: remove activity & genres answers dynamically
        $behaviourPayload = [];
        foreach ($rawAnswers as $a) {
            if (!is_array($a)) {
                continue;
            }

            // If explicitly flagged
            if (!empty($a['isActivity']) || !empty($a['isGenres'])) {
                continue;
            }

            // Multiple-choice ⇒ likely genres
            if (isset($a['optionValues']) && is_array($a['optionValues'])) {
                // treat as 'genres' → skip
                continue;
            }

            // Single value but is an activity keyword
            $val = isset($a['optionValue']) ? (string)$a['optionValue'] : null;
            if ($val !== null && in_array(mb_strtolower($val), $activityChoices, true)) {
                continue;
            }

            // Keep only yes/no type answers
            if ($val !== null) {
                $lv = mb_strtolower($val);
                if ($lv === 'oui' || $lv === 'non') {
                    $behaviourPayload[] = $a;
                }
            }
        }

        // Fallback: if we filtered nothing (e.g., client didn’t include activity/genres markers),
        // and we still have at least 2 answers total, drop the last two as a heuristic (they are appended last).
        if (empty($behaviourPayload) && count($rawAnswers) >= 2) {
            $behaviourPayload = array_slice($rawAnswers, 0, -2);
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
            'timeout' => max(60, $this->timeout),
            'max_duration' => 180,
            'json' => $payload,
        ]);

        try {
            $status = $resp->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('OpenAI request transport error: ' . $e->getMessage(), 0, $e);
        }
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
