<?php

namespace App\Controller\Back;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PlaylistController manages playlist-related operations in the back office.
 *
 * This controller provides functionality to view and manage playlists.
 */
#[Route(path: ['en' => '/playlists', 'fr' => '/playlists'], name: 'playlist_')]
final class PlaylistController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/playlist';

    /**
     * @return Response
     */
    #[Route(name: 'index')]
    public function index(): Response
    {
        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', []);
    }
}
