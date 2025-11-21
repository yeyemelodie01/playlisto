<?php

namespace App\ApiResource;

use App\Service\SpotifyService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * MoodTracksController.
 *
 * @psalm-suppress UnusedClass
 */
final class MoodTracksController extends AbstractController
{
    /**
     * @param SpotifyService $spotifyService
     */
    public function __construct(private readonly SpotifyService $spotifyService)
    {
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[Route('/api/mood-tracks', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $p = json_decode($request->getContent(), true) ?? [];
        $mood = (string) ($p['mood'] ?? 'happy');           // issu d'OpenAI
        $activity = (string) ($p['activity'] ?? 'relax');  // choisi par l'utilisateur
        $genres = is_array($p['genres'] ?? null) ? $p['genres'] : [];

        $tracks = $this->spotifyService->tracksForMoodActivity($mood, $activity, $genres, 25);

        return $this->json(['mood' => $mood, 'activity' => $activity, 'genres' => $genres, 'tracks' => $tracks]);
    }
}
