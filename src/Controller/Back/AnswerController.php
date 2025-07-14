<?php

namespace App\Controller\Back;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * AnswerController manages answer-related operations in the back office.
 *
 * This controller provides functionality to view and manage answers.
 */
#[Route(path: ['en' => '/answers', 'fr' => '/reponses'], name: 'answer_')]
final class AnswerController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/answer';

    /**
     * @return Response
     */
    #[Route(name: 'index')]
    public function index(): Response
    {
        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', []);
    }
}
