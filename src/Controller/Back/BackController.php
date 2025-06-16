<?php

namespace App\Controller\Back;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

final class BackController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back';

    #[Route(name: 'index')]
    public function index(): Response
    {
        dd('ok');
        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', []);
    }
}
