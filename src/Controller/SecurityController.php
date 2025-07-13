<?php

namespace App\Controller;

use App\Form\Type\SignInType;
use App\Service\FirewallDetector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Class SecurityController.
 *
 * @psalm-suppress UnusedClass
 */
final class SecurityController extends AbstractController
{
    /**
     * @var FirewallDetector
     */
    private FirewallDetector $firewallDetector;

    /**
     * SecurityController constructor.
     *
     * @param FirewallDetector $firewallDetector
     */
    public function __construct(FirewallDetector $firewallDetector)
    {
        $this->firewallDetector = $firewallDetector;
    }

    /**
     * @param AuthenticationUtils           $authenticationUtils
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param TokenStorageInterface         $tokenStorage
     *
     * @return Response
     */
    #[Route(path: '/connexion', name: 'login', options: ['anonymous' => true, 'expose' => true])]
    public function login(AuthenticationUtils $authenticationUtils, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage): Response
    {
        if ($tokenStorage->getToken()?->getUser() !== null && $authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('back_index');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        if ('back' === $this->firewallDetector->getFirewallShortName()) {
            $view = 'back/security/login.html.twig';
        } elseif ('front' === $this->firewallDetector->getFirewallShortName()) {
            $view = 'front/security/login.html.twig';
        } else {
            throw new NotFoundHttpException('The firewall is not recognized.');
        }

        return $this->render($view, [
            'signInForm' => $this->createForm(
                SignInType::class,
                null,
                [
                    'action' => $this->generateUrl('login'),
                    'method' => 'POST',
                ]
            )->createView(),
            'error' => $error,
            'last_username' => $lastUsername,
        ]);
    }

    /**
     * @return void
     */
    #[Route(path: '/deconnexion', name: 'logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
