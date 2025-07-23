<?php

namespace App\Controller\Back;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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
     * @param QuestionRepository  $questionRepository
     * @param LoggerInterface     $logger
     * @param TranslatorInterface $translator
     */
    public function __construct(private readonly QuestionRepository $questionRepository, private readonly LoggerInterface $logger, private readonly TranslatorInterface $translator)
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
     * @param Question|null $question
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/delete', 'fr' => '/{id}/supprimer'], name: 'delete', requirements: ['id' => "\d+"])]
    public function delete(?Question $question): Response
    {
        if (null === $question) {
            $this->addFlash('error', $this->translor->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('back_question_index');
        }

        $type = 'error';
        try {
            $this->questionRepository->remove($question, true);
            $type = 'success';
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
        }

        $this->addFlash($type, $this->translator->trans("delete.$type", [], 'Crud'));

        return $this->redirectToRoute('back_question_index');
    }
}
