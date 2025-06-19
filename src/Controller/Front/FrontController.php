<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class FrontController extends AbstractController
{
    /**
     * @return string
     */
    public function index(): string
    {
        return $this->render('front/home.html.twig');
    }
}
