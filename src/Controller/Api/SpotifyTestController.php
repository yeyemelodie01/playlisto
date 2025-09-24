<?php

namespace App\Controller\Api;

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

final class SpotifyTestController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/api/spotify/reco', name: 'api_spotify_reco', methods: ['GET'])]
    public function reco(Request $req, SpotifyService $spotify): JsonResponse
    {
        $mood     = (string) $req->query->get('mood', 'happy');
        $activity = (string) $req->query->get('activity', 'relax');
        $genres   = $req->query->has('genres')
            ? array_filter(array_map('trim', explode(',', (string)$req->query->get('genres'))))
            : [];

        $tracks = $spotify->tracksForMoodActivity($mood, $activity, $genres, 10);

        return $this->json([
            'query'  => $spotify->queryForMoodActivity($mood, $activity),
            'count'  => count($tracks),
            'tracks' => $tracks,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/api/spotify/search', name: 'api_spotify_search', methods: ['GET'])]
    public function search(Request $req, SpotifyService $spotify): JsonResponse
    {
        $q    = (string) $req->query->get('q', 'lofi chill');
        $type = (string) $req->query->get('type', 'playlist'); // track|artist|album|playlist
        $res  = $spotify->search($q, $type, 5);

        return $this->json(['q' => $q, 'type' => $type, 'items' => $res]);
    }
}
