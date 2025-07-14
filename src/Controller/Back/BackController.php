<?php

namespace App\Controller\Back;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * BackController serves as the main entry point for the back office.
 *
 * It provides a dashboard view for administrators.
 *
 * @psalm-suppress UnusedClass
 */
final class BackController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back';

    /**
     * Displays the main dashboard of the back office.
     *
     * @return Response
     */
    #[Route(name: 'index')]
    public function index(): Response
    {
        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', []);
    }
}
