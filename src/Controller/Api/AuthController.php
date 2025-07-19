<?php

namespace App\Controller\Api;

use App\Entity\Administrator;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AuthController
{
    /**
     * @param User|Administrator|null $user
     *
     * @return JsonResponse
     */
    public function __invoke(#[CurrentUser] User|Administrator|null $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => 'Email ou mot de passe incorrect.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

}
