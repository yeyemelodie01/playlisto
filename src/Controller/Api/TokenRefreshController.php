<?php

namespace App\Controller\Api;

use App\Entity\Administrator;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Class TokenRefreshController.
 *
 * This controller handles the token refresh functionality for authenticated users.
 *
 * @psalm-suppress UnusedClass
 */
final class TokenRefreshController
{
    /**
     * @param User|Administrator|null  $user
     * @param Request                  $request
     * @param JWTTokenManagerInterface $JWTManager
     *
     * @return JsonResponse
     */
    public function __invoke(#[CurrentUser] User|Administrator|null $user, Request $request, JWTTokenManagerInterface $JWTManager): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(['token' => $JWTManager->create($user)]);
    }
}
