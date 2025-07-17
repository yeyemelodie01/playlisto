<?php

namespace App\Controller\Back;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class RecommendationController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/recommendation';

    /**
     * @return Response
     */
    public function index(): Response
    {
        // Logic for displaying recommendations would go here.
        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig');
    }
}
