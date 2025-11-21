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

final readonly class FavoriteCreateProcessor implements ProcessorInterface
{
    public function __construct(private Security $security, private FavoriteFactory $favoriteFactory, private RequestStack $requestStack)
    {
    }

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

        $target = $request->get(); // à adapter
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