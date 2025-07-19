<?php

namespace App\Controller\Back;

use App\Entity\Recommendation;
use App\Repository\QuestionRepository;
use App\Repository\RecommendationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[Route(path: ['en' => '/recommendations', 'fr' => '/recommendations'], name: 'recommendation_')]
final class RecommendationController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/recommendation';

    /**
     * @param RecommendationRepository  $recommendationRepository
     * @param LoggerInterface     $logger
     * @param TranslatorInterface $translator
     */
    public function __construct(private readonly RecommendationRepository $recommendationRepository, private readonly LoggerInterface $logger, private readonly TranslatorInterface $translator)
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
        $queryBuilder = $this->recommendationRepository->getAll();
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
     * @param Recommendation|null $recommendation
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/delete', 'fr' => '/{id}/supprimer'], name: 'delete', requirements: ['id' => "\d+"])]
    public function delete(?Recommendation $recommendation): Response
    {
        if (null === $recommendation) {
            $this->addFlash('error', $this->translor->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('back_recommendation_index');
        }

        $type = 'error';
        try {
            $this->recommendationRepository->remove($recommendation, true);
            $type = 'success';
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
        }

        $this->addFlash($type, $this->translator->trans("delete.$type", [], 'Crud'));

        return $this->redirectToRoute('back_question_index');
    }
}
