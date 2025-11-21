<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FavoriteCreateOutput;
use App\Enum\FavoriteType;
use App\Factory\FavoriteFactory;
use InvalidArgumentException;
use Override;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use ValueError;

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
     * @param Security $security Provides access to the currently authenticated user.
     * @param FavoriteFactory $favoriteFactory Responsible for delegating favorite creation to a proper strategy.
     * @param RequestStack $requestStack Used to retrieve the current HTTP request and its payload.
     */
    public function __construct(private Security $security, private FavoriteFactory $favoriteFactory, private RequestStack $requestStack)
    {
    }

    /**
     * Processes the incoming API request to create a favorite entry.
     *
     * @param mixed $data The request payload (unused due to deserialize:false in API Platform).
     * @param Operation $operation The API Platform operation metadata.
     * @param array $uriVariables Variables extracted from the URI, including favoriteType.
     * @param array $context Additional processing context.
     *
     * @return FavoriteCreateOutput A response containing a success message.
     *
     * @throws NotFoundHttpException If the user is not authenticated or the favorite type is missing/invalid.
     * @throws BadRequestHttpException If the provided payload is invalid.
     * @throws RuntimeException If an unexpected error occurs while creating the favorite.
     */
    #[Override]
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
        } catch (ValueError $e) {
            throw new NotFoundHttpException(sprintf('Unsupported favorite type "%s".', $favoriteType));
        }

        $request = $this->requestStack->getCurrentRequest();
        $payload = json_decode($request?->getContent() ?? '{}', true);
        if (!is_array($payload) || !isset($payload['target'])) {
            throw new \InvalidArgumentException('Invalid payload: missing placedChampions.');
        }

        $target = $request; // @TODO à adapter
        try {
            $this->favoriteFactory->addFavorite($type, $user, $target);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (Throwable $e) {
            throw new RuntimeException('Unexpected error while creating favorite.');
        }

        return new FavoriteCreateOutput('Favorite added successfully.');
    }
}