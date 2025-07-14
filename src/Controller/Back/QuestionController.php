<?php

namespace App\Controller\Back;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * QuestionController manages question-related operations in the back office.
 *
 * This controller provides functionality to view and manage questions.
 */
#[Route(path: ['en' => '/questions', 'fr' => '/questions'], name: 'question_')]
final class QuestionController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/question';

    /**
     * @return Response
     */
    #[Route(name: 'index')]
    public function index(): Response
    {
        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', []);
    }
}
