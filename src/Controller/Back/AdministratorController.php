<?php

namespace App\Controller\Back;

use App\Entity\Administrator;
use App\Repository\AdministratorRepository;
use Knp\Component\Pager\Paginator;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * AdministratorController.
 *
 * @psalm-suppress UnusedClass
 */
#[Route('/administrator', name: 'administrator_')]
final class AdministratorController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/administrator';

    /**
     * AdministratorController constructor.
     *
     * @param AdministratorRepository $administratorRepository
     * @param LoggerInterface         $logger
     * @param TranslatorInterface     $translator
     */
    public function __construct(private readonly AdministratorRepository $administratorRepository, private readonly LoggerInterface $logger, private readonly TranslatorInterface $translator)
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
        $queryBuilder = $this->administratorRepository->getAll();
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
     * @param Administrator|null $administrator
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/enable', 'fr' => '/{id}/activer'], name: 'enable', requirements: ['id' => "\d+"])]
    #[Route(path: ['en' => '/{id}/disable', 'fr' => '/{id}/desactiver'], name: 'disable', requirements: ['id' => "\d+"])]
    public function enable(?Administrator $administrator): Response
    {
        if (null === $administrator) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('back_administrator_index');
        }

        $administrator->setActive(!$administrator->isActive());
        try {
            $this->administratorRepository->save($administrator, true);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
        }

        $this->addFlash('success', $this->translator->trans($administrator->isActive() ? 'disabled.message' : 'enabled.message', [], 'Crud'));

        return $this->redirectToRoute('back_administrator_index');
    }
}
