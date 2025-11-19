<?php

namespace App\Controller\Back;

use App\Entity\Playlist;
use App\Repository\PlaylistRepository;
use Exception;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * PlaylistController manages playlist-related operations in the back office.
 *
 * This controller provides functionality to view and manage playlists.
 *
 * @psalm-suppress UnusedClass
 */
#[Route(path: ['en' => '/playlists', 'fr' => '/playlists'], name: 'playlist_')]
final class PlaylistController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/playlist';

    /**
     * @param PlaylistRepository  $playlistRepository
     * @param LoggerInterface     $logger
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private readonly PlaylistRepository $playlistRepository,
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
        $queryBuilder = $this->playlistRepository->getAll();
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
    #[Route(path: ['en' => '/new', 'fr' => '/nouveau'], name: 'new')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $description = $request->request->get('description');

            if ($title === '') {
                $this->addFlash('error', $this->translator->trans('form.invalid', [], 'Crud'));
            } else {
                $playlist = new Playlist();
                if (method_exists($playlist, 'setTitle')) {
                    $playlist->setTitle($title);
                }
                if (method_exists($playlist, 'setDescription')) {
                    $playlist->setDescription($description ?: null);
                }

                $this->playlistRepository->save($playlist, true);

                $this->addFlash('success', $this->translator->trans('create.success', [], 'Crud'));
                return $this->redirectToRoute('back_playlist_index');
            }
        }

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'new.html.twig');
    }

    /**
     * @param Playlist|null $playlist
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}', 'fr' => '/{id}'], name: 'show', requirements: ['id' => "\\d+"])]
    public function show(?Playlist $playlist): Response
    {
        if (null === $playlist) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));
            return $this->redirectToRoute('back_playlist_index');
        }

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'show.html.twig', [
            'playlist' => $playlist,
        ]);
    }

    /**
     * @param Request       $request
     * @param Playlist|null $playlist
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/edit', 'fr' => '/{id}/editer'], name: 'edit', requirements: ['id' => "\\d+"])]
    public function edit(Request $request, ?Playlist $playlist): Response
    {
        if (null === $playlist) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));
            return $this->redirectToRoute('back_playlist_index');
        }

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $description = $request->request->get('description');

            if ($title === '') {
                $this->addFlash('error', $this->translator->trans('form.invalid', [], 'Crud'));
            } else {
                if (method_exists($playlist, 'setTitle')) {
                    $playlist->setTitle($title);
                }
                if (method_exists($playlist, 'setDescription')) {
                    $playlist->setDescription($description ?: null);
                }

                $this->playlistRepository->save($playlist, true);

                $this->addFlash('success', $this->translator->trans('update.success', [], 'Crud'));
                return $this->redirectToRoute('back_playlist_index');
            }
        }

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'edit.html.twig', [
            'playlist' => $playlist,
        ]);
    }

    /**
     * @param Playlist|null $playlist
     *
     * @return Response
     */
    #[Route(path: ['en' => '/{id}/delete', 'fr' => '/{id}/supprimer'], name: 'delete', requirements: ['id' => "\d+"])]
    public function delete(?Playlist $playlist): Response
    {
        if (null === $playlist) {
            $this->addFlash('error', $this->translator->trans('no_element', [], 'Crud'));

            return $this->redirectToRoute('back_user_index');
        }

        $type = 'error';
        try {
            $this->playlistRepository->remove($playlist, true);
            $type = 'success';
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
        }

        $this->addFlash($type, $this->translator->trans("delete.$type", [], 'Crud'));

        return $this->redirectToRoute('back_user_index');
    }
}
