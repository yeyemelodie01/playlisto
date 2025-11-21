<?php

namespace App\Controller\Back;

use App\Entity\AnswerOption;
use App\Repository\AnswerOptionRepository;
use App\Repository\QuestionRepository;
use App\Repository\UserRepository;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * AnswerOptionController manages answer-related operations in the back office.
 *
 * This controller provides functionality to view and manage answers.
 */
#[Route(path: ['en' => '/answer_options', 'fr' => '/option_reponses'], name: 'answer_option_')]
final class AnswerOptionController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/answer';

    /**
     * @param AnswerOptionRepository $answerOptionRepository
     * @param QuestionRepository     $questionRepository
     * @param UserRepository         $userRepository
     * @param LoggerInterface        $logger
     * @param TranslatorInterface    $translator
     */
    public function __construct(private readonly AnswerOptionRepository $answerOptionRepository, private readonly QuestionRepository $questionRepository, private readonly UserRepository $userRepository, private readonly LoggerInterface $logger, private readonly TranslatorInterface $translator)
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
        $queryBuilder = $this->answerOptionRepository->getAll();
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            $backPaginateMaxPerPage
        );

        return $this->render(self::TEMPLATE_DIR.DIRECTORY_SEPARATOR.'index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    /**
     * @param Request           $request
     * @param AnswerOption|null $answer
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/edit', 'fr' => '/{id}/editer'], name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => "\\d+"])]
    public function edit(Request $request, ?AnswerOption $answer): Response
    {
        if (null === $answer) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('answer_index');
        }

        if ($request->isMethod('POST')) {
            $label      = trim((string) $request->request->get('label', ''));
            $questionId = (int) $request->request->get('questionId', 0);
            $userId     = (int) $request->request->get('userId', 0);

            $errors = [];
            if ($label === '') {
                $errors[] = 'Label is required.';
            }
            if ($questionId <= 0) {
                $errors[] = 'Question is required.';
            }
            if ($userId <= 0) {
                $errors[] = 'User is required.';
            }

            $question = $questionId > 0 ? $this->questionRepository->find($questionId) : null;
            $user     = $userId > 0 ? $this->userRepository->find($userId) : null;
            if (!$question) {
                $errors[] = 'Question not found.';
            }
            if (!$user) {
                $errors[] = 'User not found.';
            }

            if (empty($errors)) {
                $answer->setLabel($label);
                $answer->setQuestion($question);

                $this->answerOptionRepository->save($answer, true);
                $this->addFlash('success', $this->translator->trans('update.success', [], 'Crud'));

                return $this->redirectToRoute('back_answer_index');
            }

            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }
        }

        return $this->render(self::TEMPLATE_DIR.DIRECTORY_SEPARATOR.'edit.html.twig', [
            'answer' => $answer,
        ]);
    }

    /**
     * @param AnswerOption|null $answer
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/delete', 'fr' => '/{id}/supprimer'], name: 'delete', requirements: ['id' => "\d+"])]
    public function delete(?AnswerOption $answer): Response
    {
        if (null === $answer) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('answer_index');
        }

        $type = 'error';
        try {
            $this->answerOptionRepository->remove($answer, true);
            $type = 'success';
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
        }

        $this->addFlash($type, $this->translator->trans("delete.$type", [], 'Crud'));

        return $this->redirectToRoute('back_answer_index');
    }
}
