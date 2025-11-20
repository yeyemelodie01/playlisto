<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ApiLogoutController
{
    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);

        $response->headers->clearCookie('auth_token', '/', null, false, true, 'Strict');

        $hostParts = explode('.', $request->getHost());

        if (count($hostParts) >= 2) {
            $parentDomain = '.' . implode('.', \array_slice($hostParts, -2));
            $response->headers->clearCookie('auth_token', '/', $parentDomain, false, true, 'Strict');
        }

        return $response;
    }
}
