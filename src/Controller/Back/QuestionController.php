<?php

namespace App\Controller\Back;

use App\Repository\QuestionRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
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
     * @param QuestionRepository $questionRepository
     */
    public function __construct(private readonly QuestionRepository $questionRepository)
    {
    }

    /**
     * @param Request            $request
     * @param PaginatorInterface $paginator
     * @param int                $backPaginateMaxPerPage
     *
     * @return Response
     */
    #[Route(name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator, #[Autowire('%back_paginate_max_per_page%')] int $backPaginateMaxPerPage): Response
    {
        $queryBuilder = $this->questionRepository->getAll();
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            $backPaginateMaxPerPage
        );

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    #[Route(path: ['en' => '/', 'fr' => '/nouveau'], name: 'new')]
    public function addQuestion(Request $request): Response
    {
        // Logic for creating a new question would go here.
        // This is a placeholder for the actual implementation.


        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'new.html.twig', [
            // Pass necessary data to the template.
        ]);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/edit', 'fr' => '/{id}/editer'], name: 'edit', requirements: ['id' => "\d+"])]
    public function edit(Request $request): Response
    {
        // Logic for editing a question would go here.
        // This is a placeholder for the actual implementation.

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'edit.html.twig', [
            // Pass necessary data to the template.
        ]);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/delete', 'fr' => '/{id}/supprimer'], name: 'delete', requirements: ['id' => "\d+"])]
    public function delete(Request $request): Response
    {
        // Logic for deleting a question would go here.
        // This is a placeholder for the actual implementation.

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'delete.html.twig', [
            // Pass necessary data to the template.
        ]);
    }
}
