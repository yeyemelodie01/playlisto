<?php

namespace App\Controller\Back;

use App\Entity\User;
use App\Form\Type\Back\UserTypeForm;
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
 * UserController handles user-related operations in the back office.
 *
 * This controller provides functionality to manage users, including listing
 * and filtering users in the back office.
 *
 * @psalm-suppress UnusedClass
 */
#[Route(path: ['en' => '/users', 'fr' => '/utilisateurs'], name: 'user_')]
final class UserController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/user';

    /**
     * @param UserRepository      $userRepository
     * @param LoggerInterface     $logger
     * @param TranslatorInterface $translator
     */
    public function __construct(private readonly UserRepository $userRepository, private readonly LoggerInterface $logger, private readonly TranslatorInterface $translator)
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
        $queryBuilder = $this->userRepository->getAll();
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
     * @param User|null $user
     * @param Request   $request
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/edit', 'fr' => '/{id}/editer'], name: 'edit', requirements: ['id' => "\d+"])]
    public function edit(?User $user, Request $request): Response
    {
        if (null === $user) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('back_user_index');
        }

        $form = $this->createForm(UserTypeForm::class, $user)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $type = 'error';
            try {
                $this->userRepository->save($user, true);
                $type = 'success';
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage(), $exception->getTrace());
            }

            $this->addFlash($type, $this->translator->trans("edit.$type", [], 'Crud'));

            if (null !== $request->request->get('submit_button_save_continue')) {
                return $this->redirectToRoute('back_user_edit', ['id' => $user->getId()]);
            }

            return $this->redirectToRoute('back_user_index');
        }

        return $this->render(self::TEMPLATE_DIR.DIRECTORY_SEPARATOR.'edit.html.twig', [
            'userTypeForm' => $form->createView(),
            'user' => $user,
        ]);
    }

    /**
     * @param User|null $user
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/delete', 'fr' => '/{id}/supprimer'], name: 'delete', requirements: ['id' => "\d+"])]
    public function delete(?User $user): Response
    {
        if (null === $user) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('back_user_index');
        }

        $type = 'error';
        try {
            $this->userRepository->remove($user, true);
            $type = 'success';
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
        }

        $this->addFlash($type, $this->translator->trans("delete.$type", [], 'Crud'));

        return $this->redirectToRoute('back_user_index');
    }

    /**
     * @param User|null $user
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/enable', 'fr' => '/{id}/activer'], name: 'enable', requirements: ['id' => "\d+"])]
    #[Route(path: ['en' => '/{id}/disable', 'fr' => '/{id}/desactiver'], name: 'disable', requirements: ['id' => "\d+"])]
    public function enable(?User $user): Response
    {
        if (null === $user) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('back_user_index');
        }

        $user->setActive(!$user->isActive());
        try {
            $this->userRepository->save($user, true);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
        }

        $this->addFlash('success', $this->translator->trans($user->isActive() ? 'disabled.message' : 'enabled.message', [], 'Crud'));

        return $this->redirectToRoute('back_user_index');
    }
}
