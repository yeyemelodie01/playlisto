<?php

namespace App\Controller\Back;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use App\Service\OpenAIService;
use Doctrine\ORM\EntityManagerInterface;
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
    #[Route(path: ['en' => '/create', 'fr' => '/creer'], name: 'create')]
    public function createQuestion(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $label   = trim((string)$request->request->get('label', ''));
            $type    = trim((string)$request->request->get('type', ''));

            $errors = [];
            if ($label === '') {
                $errors[] = 'Label is required.';
            }
            if ($type === '') {
                $errors[] = 'Type is required.';
            }
            if (!\in_array($type, ['single_choice','multiple_choice','scale','text'], true)) {
                $errors[] = 'Invalid type.';
            }

            if (empty($errors)) {
                $question = new Question();
                if (method_exists($question, 'setLabel')) {
                    $question->setLabel($label);
                }
                if (method_exists($question, 'setType')) {
                    $question->setType($type);
                }

                $this->questionRepository->save($question, true);

                $this->addFlash('success', $this->translator->trans('create.success', [], 'Crud'));
                return $this->redirectToRoute('question_index');
            }

            // Display errors
            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }
        }

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'new.html.twig', [
            'data' => [
                'label'   => $request->request->get('label'),
                'type'    => $request->request->get('type'),
            ],
        ]);
    }

    /**
     * @param Request $request
     * @param Question|null $question
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/edit', 'fr' => '/{id}/editer'], name: 'edit', requirements: ['id' => "\d+"])]
    public function edit(Request $request, ?Question $question): Response
    {
        if (null === $question) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));
            return $this->redirectToRoute('question_index');
        }

        if ($request->isMethod('POST')) {
            $label   = trim((string)$request->request->get('label', ''));
            $type    = trim((string)$request->request->get('type', ''));

            $errors = [];
            if ($label === '') {
                $errors[] = 'Label is required.';
            }
            if ($type === '') {
                $errors[] = 'Type is required.';
            }
            if (!\in_array($type, ['single_choice','multiple_choice','scale','text'], true)) {
                $errors[] = 'Invalid type.';
            }

            if (empty($errors)) {
                if (method_exists($question, 'setLabel')) {
                    $question->setLabel($label);
                }
                if (method_exists($question, 'setType')) {
                    $question->setType($type);
                }

                $this->questionRepository->save($question, true);

                $this->addFlash('success', $this->translator->trans('update.success', [], 'Crud'));
                return $this->redirectToRoute('question_index');
            }

            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }
        }

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'edit.html.twig', [
            'question' => $question,
        ]);
    }


    /**
     * @param Question|null $question
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}', 'fr' => '/{id}'], name: 'show', requirements: ['id' => "\d+"], methods: ['GET'])]
    public function show(?Question $question): Response
    {
        if (null === $question) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));
            return $this->redirectToRoute('question_index');
        }

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'show.html.twig', [
            'question' => $question,
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
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));

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

        return $this->redirectToRoute('question_index');
    }

    /**
     * Generate questions using OpenAI and save them to the database.
     *
     * @param OpenAIService           $openAI
     * @param EntityManagerInterface  $em
     *
     * @return Response
     */
    #[Route(path: ['en' => '/generate', 'fr' => '/generer'], name: 'generate')]
    public function generate(OpenAIService $openAI, EntityManagerInterface $em): Response
    {
        $items = $openAI->generateQuestions(6);
        foreach ($items as $i) {
            $q = new Question();
            $q->setTitle($i['title']);
            $q->setType($i['type']); // string, ou ton enum si tu en as un pour le type
            if (isset($i['options'])) {
                $q->setOptions($i['options']); // array JSON dans l’entité (json type)
            }
            $em->persist($q);
        }
        $em->flush();

        $this->addFlash('success', 'Questions générées');
        return $this->redirectToRoute('back_question_index');
    }
}
