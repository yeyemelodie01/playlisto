<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FavoriteCreateOutput;
use App\Enum\FavoriteType;
use App\Factory\FavoriteFactory;
use App\Repository\PlaylistRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processor responsible for handling the creation of favorites from API requests.
 * Validates request data, resolves the correct favorite type, and delegates creation
 * to the FavoriteFactory.
 *
 * This class acts as an application layer to prevent exposing business logic in the API layer.
 */
final readonly class FavoriteCreateProcessor implements ProcessorInterface
{
    /**
     * Constructor.
     *
     * @param Security        $security        provides access to the currently authenticated user
     * @param FavoriteFactory $favoriteFactory responsible for delegating favorite creation to a proper strategy
     * @param RequestStack    $requestStack    used to retrieve the current HTTP request and its payload
     */
    public function __construct(private Security $security, private FavoriteFactory $favoriteFactory, private RequestStack $requestStack, private PlaylistRepository $playlistRepository)
    {
    }

    /**
     * Processes the incoming API request to create a favorite entry.
     *
     * @param mixed     $data         the request payload (unused due to deserialize:false in API Platform)
     * @param Operation $operation    the API Platform operation metadata
     * @param array     $uriVariables variables extracted from the URI, including favoriteType
     * @param array     $context      additional processing context
     *
     * @return FavoriteCreateOutput a response containing a success message
     *
     * @throws NotFoundHttpException   if the user is not authenticated or the favorite type is missing/invalid
     * @throws BadRequestHttpException if the provided payload is invalid
     * @throws \RuntimeException       if an unexpected error occurs while creating the favorite
     */
    #[\Override]
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): FavoriteCreateOutput
    {
        $user = $this->security->getUser();
        if (null === $user) {
            throw new NotFoundHttpException('User not found.');
        }

        $favoriteType = $uriVariables['favoriteType'] ?? null;
        if (null === $favoriteType) {
            throw new NotFoundHttpException('A favorite type must be provided in the request URL.');
        }

        try {
            $type = FavoriteType::from($favoriteType);
        } catch (\ValueError $e) {
            throw new NotFoundHttpException(sprintf('Unsupported favorite type "%s".', $favoriteType));
        }

        $request = $this->requestStack->getCurrentRequest();
        $payload = json_decode($request?->getContent() ?? '{}', true);

        if (!is_array($payload) || !array_key_exists('targetId', $payload)) {
            throw new BadRequestHttpException('Invalid payload: missing "targetId" field.');
        }

        $targetId = $payload['targetId'];

        if (FavoriteType::PLAYLIST === $type) {
            $playlist = $this->playlistRepository->find($targetId);
            if (null === $playlist) {
                throw new NotFoundHttpException(sprintf('Playlist with id "%s" not found.', $targetId));
            }

            $target = $playlist;
        } else {
            throw new NotFoundHttpException(sprintf('Favorite type "%s" not yet supported for creation.', $type->value));
        }

        try {
            $this->favoriteFactory->addFavorite($type, $user, $target);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unexpected error while creating favorite.', 0, $e);
        }

        return new FavoriteCreateOutput('Favorite added successfully.');
    }
}
