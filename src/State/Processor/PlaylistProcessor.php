<?php

namespace App\State\Processor;

use _PHPStan_ac6dae9b0\Symfony\Component\Finder\Exception\AccessDeniedException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PlaylistInput;
use App\ApiResource\PlaylistOutput;
use App\Entity\Playlist;
use App\Entity\Track;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processor for handling playlist-related operations.
 *
 * This class implements the ProcessorInterface to process data related to playlists.
 */
final readonly class PlaylistProcessor implements ProcessorInterface
{
    /**
     * Constructor for PlaylistProcessor.
     *
     * @param EntityManagerInterface $entityManager the Doctrine entity manager
     * @param Security               $security      the security component used to fetch the current authenticated user
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(private EntityManagerInterface $entityManager, private Security $security)
    {
    }

    /**
     * Processes the given data for a playlist operation.
     *
     * @param mixed                $data         The data to be processed.
     * @param Operation            $operation    The operation being performed.
     * @param array<string, mixed> $uriVariables Any URI variables that may have been provided.
     * @param array<string, mixed> $context      Additional context passed by API Platform.
     *
     * @return mixed The processed data.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedException('Authentication required.');
        }

        $method = strtoupper((string)($context['request_method'] ?? 'GET'));

        if ($method === 'POST') {
            \assert($data instanceof PlaylistInput);

            $playlist = new Playlist();
            $playlist->setTitle($data['title']);
            if (method_exists($playlist, 'setDescription')) {
                $playlist->setDescription($data['description']);
            }
            if (method_exists($playlist, 'setMood')) {
                $playlist->setMood($data['mood']);
            }
            if (method_exists($playlist, 'setActivity')) {
                $playlist->setActivity($data['activity']);
            }
            if (method_exists($playlist, 'setUser')) {
                $playlist->setUser($user);
            }

            // Optionnel: attacher des tracks
            if ($data->trackIds && method_exists($playlist, 'addTrack')) {
                $repoTrack = $this->entityManager->getRepository(Track::class);
                foreach ($data->trackIds as $tid) {
                    $t = $repoTrack->find((int)$tid);
                    if ($t) {
                        $playlist->addTrack($t);
                    }
                }
            }

            $this->entityManager->persist($playlist);
            $this->entityManager->flush();

            return $this->toOutput($playlist);
        }

        if ($method === 'PATCH' || $method === 'PUT') {
            \assert($data instanceof PlaylistInput);
            $id = (int)($uriVariables['id'] ?? 0);

        /** @var Playlist|null $playlist */
            $playlist = $this->entityManager->getRepository(Playlist::class)->find($id);
            if (!$playlist) {
                throw new NotFoundHttpException('Playlist not found');
            }
            if (method_exists($playlist, 'getUser') && $playlist->getUser() !== $user) {
                throw new AccessDeniedHttpException('Not your playlist.');
            }

        // PATCH: on applique uniquement les champs fournis
            if (isset($data->title)) {
                $playlist->setTitle($data->title);
            }
            if (property_exists($data, 'description')) {
                $playlist->setDescription($data->description ?? null);
            }
            if (property_exists($data, 'mood')) {
                $playlist->setMood($data->mood ?? null);
            }
            if (property_exists($data, 'activity')) {
                $playlist->setActivity($data->activity ?? null);
            }

            if ($data->trackIds !== null && method_exists($playlist, 'getTracks') && method_exists($playlist, 'addTrack') && method_exists($playlist, 'removeTrack')) {
                // Remplacement simple : on vide puis on remet (ajuste selon ta logique)
                $tracks = $playlist->getTracks();
                if (is_iterable($tracks)) {
                    foreach ($tracks as $t) {
                        $playlist->removeTrack($t);
                    }
                }
                $repoTrack = $this->entityManager->getRepository(Track::class);
                foreach ($data->trackIds as $tid) {
                    $t = $repoTrack->find((int)$tid);
                    if ($t) {
                        $playlist->addTrack($t);
                    }
                }
            }

            $this->entityManager->flush();
            return $this->toOutput($playlist);
        }

        if ($method === 'DELETE') {
            $id = (int)($uriVariables['id'] ?? 0);
            /** @var Playlist|null $playlist */
            $playlist = $this->entityManager->getRepository(Playlist::class)->find($id);
            if (!$playlist) {
                throw new NotFoundHttpException('Playlist not found');
            }
            if (method_exists($playlist, 'getUser') && $playlist->getUser() !== $user) {
                throw new AccessDeniedHttpException('Not your playlist.');
            }

            $this->entityManager->remove($playlist);
            $this->entityManager->flush();

            // API Platform renverra 204 No Content
            return null;
        }

        // fallback
        return $data;
    }

    private function toOutput(Playlist $playlist): PlaylistOutput
    {
        $tracks = method_exists($playlist, 'getTracks') ? $playlist->getTracks() : null;
        $trackCount = is_object($tracks) && method_exists($tracks, 'count') ? $tracks->count() : (is_countable($tracks) ? \count($tracks) : 0);

        return new PlaylistOutput(
            id: $playlist->getId(),
            title: $playlist->getTitle(),
            description: method_exists($playlist, 'getDescription') ? $playlist->getDescription() : null,
            mood: method_exists($playlist, 'getMood') ? $playlist->getMood() : null,
            activity: method_exists($playlist, 'getActivity') ? $playlist->getActivity() : null,
            trackCount: $trackCount,
            createdAt: method_exists($playlist, 'getCreatedAt') ? $playlist->getCreatedAt() : null,
        );
    }
}
