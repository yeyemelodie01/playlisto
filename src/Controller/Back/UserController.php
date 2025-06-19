<?php

namespace App\Controller\Back;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UserController handles user-related operations in the back office.
 *
 * This controller provides functionality to manage users, including listing
 * and filtering users in the back office.
 */
#[Route(path: ['en' => '/users', 'fr' => '/utilisateurs'], name: 'user_')]
final class UserController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back/user';

    #[Route(name: 'index')]
    public function index(): Response
    {
        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', []);
    }
}
