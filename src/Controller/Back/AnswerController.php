<?php

namespace App\Controller\Back;

use App\Entity\Answer;
use App\Repository\AnswerRepository;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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
     * @param AnswerRepository  $answerRepository
     * @param LoggerInterface     $logger
     * @param TranslatorInterface $translator
     */
    public function __construct(private readonly AnswerRepository $answerRepository, private readonly LoggerInterface $logger, private readonly TranslatorInterface $translator)
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
        $queryBuilder = $this->answerRepository->getAll();
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            $backPaginateMaxPerPage
        );

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    public function generatedPlaylist()
    {
    }



    /**
     * @param Answer|null $answer
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/delete', 'fr' => '/{id}/supprimer'], name: 'delete', requirements: ['id' => "\d+"])]
    public function delete(?Answer $answer): Response
    {
        if (null === $answer) {
            $this->addFlash('error', $this->translor->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('back_question_index');
        }

        $type = 'error';
        try {
            $this->answerRepository->remove($answer, true);
            $type = 'success';
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
        }

        $this->addFlash($type, $this->translator->trans("delete.$type", [], 'Crud'));

        return $this->redirectToRoute('back_answer_index');
    }
}
