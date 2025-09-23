<?php

namespace App\Controller\Back;

use App\Entity\Answer;
use App\Repository\AnswerRepository;
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
 * AnswerController manages answer-related operations in the back office.
 *
 * This controller provides functionality to view and manage answers.
 */
#[Route(path: ['en' => '/answers', 'fr' => '/reponses'], name: 'answer_')]
final class AnswerController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/answer';

    /**
     * @param AnswerRepository    $answerRepository
     * @param QuestionRepository  $questionRepository
     * @param UserRepository      $userRepository
     * @param LoggerInterface     $logger
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private readonly AnswerRepository $answerRepository,
        private readonly QuestionRepository $questionRepository,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator
    ) {
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

    /**
     * @param Request $request
     *
     * @return Response
     */
    #[Route(path: ['en' => '/new', 'fr' => '/nouveau'], name: 'new', methods: ['GET','POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $questionId = (int) $request->request->get('questionId', 0);
            $userId     = (int) $request->request->get('userId', 0);
            $label      = trim((string) $request->request->get('label', ''));

            $errors = [];
            if ($questionId <= 0) {
                $errors[] = 'Question is required.';
            }
            if ($userId <= 0) {
                $errors[] = 'User is required.';
            }
            if ($label === '') {
                $errors[] = 'Label is required.';
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
                $answer = new Answer();
                $answer->setQuestion($question);
                $answer->setUser($user);
                $answer->setLabel($label);
                if (method_exists($answer, 'setAnsweredAt')) {
                    $answer->setAnsweredAt(new \DateTimeImmutable());
                }

                $this->answerRepository->save($answer, true);
                $this->addFlash('success', $this->translator->trans('create.success', [], 'Crud'));
                return $this->redirectToRoute('back_answer_index');
            }

            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }
        }

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'new.html.twig');
    }

    /**
     * @param Request     $request
     * @param Answer|null $answer
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/edit', 'fr' => '/{id}/editer'], name: 'edit', methods: ['GET','POST'], requirements: ['id' => "\\d+"])]
    public function edit(Request $request, ?Answer $answer): Response
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
                $answer->setUser($user);

                $this->answerRepository->save($answer, true);
                $this->addFlash('success', $this->translator->trans('update.success', [], 'Crud'));
                return $this->redirectToRoute('back_answer_index');
            }

            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }
        }

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'edit.html.twig', [
            'answer' => $answer,
        ]);
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
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));
            return $this->redirectToRoute('answer_index');
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
