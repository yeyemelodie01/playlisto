<?php

namespace App\Service;

use App\Enum\ActivityType;
use App\Enum\MoodType;
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
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model = 'gpt-4o-mini',
        private int $timeout = 30
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
        $total = max(5, min(50, $total));
        $reservedTail = 2;
        $behaviourTarget = max(3, $total - $reservedTail);

        $spotifySeeds = array_map(static fn(SpotifyGenre $g) => $g->value, SpotifyGenre::cases());

        $maxHaveYou = (int) ceil($behaviourTarget / 3);
        $nonce = bin2hex(random_bytes(4));

        $system = <<<SYS
        You are an assistant generating SHORT questionnaire items for a music playlist personalization app (Playlisto).
        
        Goal:
        - Generate French yes/no questions that help deduce the user's MOOD.
        - Generate EXACTLY {$behaviourTarget} French yes/no questions that help deduce the user's MOOD.
        - Do NOT include any questions about activity or genres; ONLY behaviour questions here.
        
        Stylistic constraints:
        - All questions are yes/no with options exactly ["oui","non"].
        - Vary openings: "Est-ce que…", "Aujourd'hui…", "Ces derniers jours…", "Vous est-il arrivé de…", "Votre journée s'est-elle…", "A-t-il été…", and limited "Avez-vous".
        - At most {$maxHaveYou} items may start with "Avez-vous".
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

        $seen = [];
        $behaviourOut = [];
        foreach ($questions as $q) {
            if (count($behaviourOut) >= $behaviourTarget) {
                break;
            }
            $title = isset($q['title']) ? trim((string) $q['title']) : '';
            if ($title === '') {
                continue;
            }
            $key = mb_strtolower($title);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $tag = isset($q['moodTag']) ? strtolower((string) $q['moodTag']) : null;
            if (!in_array($tag, ['happy', 'sad', 'energetic', 'stressed', 'calm'], true)) {
                continue;
            }

            $behaviourOut[] = [
                'title'   => $title,
                'type'    => 'single',
                'options' => ['oui', 'non'],
                'moodTag' => $tag,
            ];
        }

        $out = $behaviourOut;

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

        if (count($out) > $total) {
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
     * @param array $answers

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

        $activityChoices = ['sport', 'travail', 'détente', 'étude', 'cuisine', 'aucune'];

        $behaviourPayload = [];
        foreach ($rawAnswers as $a) {
            if (!is_array($a)) {
                continue;
            }

            if (!empty($a['isActivity']) || !empty($a['isGenres'])) {
                continue;
            }

            if (isset($a['optionValues']) && is_array($a['optionValues'])) {
                continue;
            }

            $val = isset($a['optionValue']) ? (string) $a['optionValue'] : null;
            if ($val !== null && in_array(mb_strtolower($val), $activityChoices, true)) {
                continue;
            }

            if ($val !== null) {
                $lv = mb_strtolower($val);
                if ($lv === 'oui' || $lv === 'non') {
                    $behaviourPayload[] = $a;
                }
            }
        }

        if (empty($behaviourPayload) && count($rawAnswers) >= 2) {
            $behaviourPayload = array_slice($rawAnswers, 0, -2);
        }

        $user = "Behaviour Q/A (JSON):\n".json_encode($behaviourPayload, JSON_UNESCAPED_UNICODE);

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
        $allowed = ["happy", "sad", "energetic", "stressed", "calm"];
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
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => max(60, $this->timeout),
            'max_duration' => 180,
            'json' => $payload,
        ]);

        try {
            $status = $resp->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('OpenAI request transport error: '.$e->getMessage(), 0, $e);
        }
        $raw    = $resp->getContent(false);
        $data   = json_decode($raw, true);

        if ($status >= 400) {
            $msg = is_array($data) && isset($data['error']['message'])
                ? $data['error']['message']
                : ($raw ?: ('HTTP '.$status));
            throw new RuntimeException('OpenAI request failed: '.$msg);
        }

        if (is_array($data) && isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'unknown error';
            throw new RuntimeException('OpenAI error: '.$msg);
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || $content === '') {
            $snippet = is_string($raw) ? substr($raw, 0, 400) : '';
            throw new RuntimeException('OpenAI empty/invalid content. Raw: '.$snippet);
        }

        return $content;
    }

    /**
     * Génère un titre court de playlist en combinant MoodType, ActivityType et genres.
     *
     * @param MoodType          $mood
     * @param ActivityType|null $activity
     * @param string[]          $genres
     * @param string            $locale
     * @param int               $maxLen
     *
     * @return string
     */
    public function generatePlaylistTitle(
        MoodType $mood,
        ?ActivityType $activity,
        array $genres,
        string $locale = 'fr',
        int $maxLen = 48
    ): string {
        $moodVal = strtolower($mood->value);
        $activityVal = $activity?->value ? strtolower($activity->value) : null;

        $genres = array_values(array_filter(array_map(
            static fn($g) => strtolower(trim((string) $g)),
            $genres
        )));
        if (empty($genres)) {
            $genres = ['pop'];
        }
        $genresUsed = array_slice($genres, 0, 3);

        $system = <<<SYS
        You are a playlist title creator for a music app (Playlisto).
        
        Goal:
        Create ONE original, catchy playlist title inspired by:
        - the user's mood
        - the user's activity
        - 3 to 5 selected genres
        
        Rules:
        - Language: French by default unless locale="en".
        - Style: short, punchy, creative (10–48 characters).
        - You may invent metaphors, vibes, atmospheres.
        - Do NOT just concatenate mood + activity + genres.
        - The title must feel like a real playlist name.
        - Optional: 1 tasteful emoji maximum.
        - No quotes, no trailing punctuation.
        - Avoid the word “playlist”.
        
        VERY IMPORTANT:
        - Return a SINGLE JSON OBJECT, NOT an array.
        - Do NOT use a "titles" field, do NOT return a list.
        - The "title" field must be a simple string, not an array.
        
        Output STRICT JSON, for example (shape only):
        {
          "title": "string",
          "debug": {
            "mood": "...",
            "activity": "...",
            "genres": ["...", "..."]
          }
        }
        SYS;

        $user = json_encode([
            'mood'    => $moodVal,
            'activity' => $activityVal,
            'genres'  => $genresUsed,
            'locale'  => $locale,
            'max_len' => $maxLen,
        ], JSON_UNESCAPED_UNICODE);

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.5,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 120,
        ];

        try {
            $raw = $this->request($payload);
            $data = json_decode($raw, true);
            $title = trim((string) ($data['title'] ?? ''));

            if ($title !== '') {
                return $this->truncateTitle($title, $maxLen);
            }
        } catch (\Throwable) {
        }

        return $this->fallbackPlaylistTitle($mood, $activity, $genresUsed, $locale, $maxLen);
    }

    private function fallbackPlaylistTitle(
        MoodType $mood,
        ?ActivityType $activity,
        array $genres,
        string $locale,
        int $maxLen
    ): string {
        $moodMap = [
            MoodType::HAPPY->value => ['fr' => 'Joyeux'],
            MoodType::SAD->value => ['fr' => 'triste'],
            MoodType::ENERGETIC->value => ['fr' => 'Énergique'],
            MoodType::STRESSED->value => ['fr' => 'Stresse'],
            MoodType::CALM->value => ['fr' => 'Calme'],
        ];

        $activityMap = [
            ActivityType::SPORT->value => 'Sport',
            ActivityType::WORK->value => 'Travail',
            ActivityType::RELAX->value => 'Détente',
            ActivityType::STUDY->value => 'Étude',
            ActivityType::COOKING->value => 'Cuisine',
            ActivityType::NONE->value => null,
        ];

        $moodLabel = $moodMap[$mood->value][$locale === 'en' ? 'en' : 'fr'] ?? 'Calme';
        $activityLabel = $activity ? ($activityMap[$activity->value] ?? null) : null;
        $genresLabel = implode(', ', $genres);

        $parts = array_filter([$moodLabel, $activityLabel, $genresLabel]);
        $title = implode(' · ', $parts);

        return $this->truncateTitle($title, $maxLen);
    }

    /**
     * @param string $title
     * @param int    $maxLen
     *
     * @return string
     */
    private function truncateTitle(string $title, int $maxLen): string
    {
        $title = trim($title);
        if (mb_strlen($title) <= $maxLen) {
            return $title;
        }

        return rtrim(mb_substr($title, 0, $maxLen - 1)).'…';
    }
}
