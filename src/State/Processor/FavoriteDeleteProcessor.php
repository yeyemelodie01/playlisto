<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FavoriteDeleteOutput;
use App\Enum\FavoriteType;
use App\Factory\FavoriteFactory;
use App\Repository\PlaylistRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processor responsible for handling the deletion of favorites from API requests.
 *
 * This class acts as an application layer to prevent exposing business logic in the API layer.
 */
final readonly class FavoriteDeleteProcessor implements ProcessorInterface
{
    /**
     * Constructor.
     *
     * @param Security        $security        provides access to the currently authenticated user
     * @param FavoriteFactory $favoriteFactory responsible for delegating favorite creation to a proper strategy
     */
    public function __construct(private Security $security, private FavoriteFactory $favoriteFactory, private PlaylistRepository $playlistRepository)
    {
    }

    /**
     * Processes the incoming API request to delete a favorite entry.
     *
     * @param mixed     $data         unused (deserialize:false)
     * @param Operation $operation    the API Platform operation metadata
     * @param array     $uriVariables variables extracted from the URI (favoriteType, targetId)
     * @param array     $context      additional processing context
     *
     * @return FavoriteDeleteOutput a response containing a message about the deletion result
     *
     * @throws NotFoundHttpException   if the user is not authenticated or the favorite type is invalid
     * @throws BadRequestHttpException if the provided URI variables are invalid
     * @throws \RuntimeException       if an unexpected error occurs while deleting the favorite
     */
    #[\Override]
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): FavoriteDeleteOutput
    {
        $user = $this->security->getUser();
        if (null === $user) {
            throw new NotFoundHttpException('User not found.');
        }

        $favoriteType = $uriVariables['favoriteType'] ?? null;
        $targetId = $uriVariables['targetId'] ?? null;

        if (null === $favoriteType || null === $targetId) {
            throw new BadRequestHttpException('Both favoriteType and targetId must be provided in the request URL.');
        }

        try {
            $type = FavoriteType::from($favoriteType);
        } catch (\ValueError $e) {
            throw new NotFoundHttpException(sprintf('Unsupported favorite type "%s".', $favoriteType));
        }

        if (FavoriteType::PLAYLIST === $type) {
            $playlist = $this->playlistRepository->find($targetId);
            if (null === $playlist) {
                throw new NotFoundHttpException(sprintf('Playlist with id "%s" not found.', $targetId));
            }

            $target = $playlist;
        } else {
            throw new NotFoundHttpException(sprintf('Favorite type "%s" not yet supported for deletion.', $type->value));
        }

        try {
            $deleted = $this->favoriteFactory->removeFavorite($type, $user, $target);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unexpected error while deleting favorite.', 0, $e);
        }

        if ($deleted) {
            return new FavoriteDeleteOutput('Favorite deleted successfully.');
        }

        return new FavoriteDeleteOutput('No favorite found for given favorite type and targetId.');
    }
}
