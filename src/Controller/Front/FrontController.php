<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FrontController.
 *
 * @psalm-suppress UnusedClass
 */
final class FrontController extends AbstractController
{
    /**
     * @return Response
     */
    #[Route('/{reactRouting}', name: 'front', requirements: ['reactRouting' => '^(?!api|admin).*'], defaults: ['reactRouting' => null])]
    public function index(): Response
    {
        return $this->render('front/home.html.twig');
    }
}
