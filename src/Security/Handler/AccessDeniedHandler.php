<?php

namespace App\Security\Handler;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class AccessDeniedHandler.
 *
 * @psalm-suppress UnusedClass
 */
final class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    /** @var FlashBagInterface */
    private FlashBagInterface $flashBag;

    /** @var TranslatorInterface */
    private TranslatorInterface $translator;

    /**
     * AccessDeniedHandler constructor.
     *
     * @param RequestStack        $requestStack
     * @param TranslatorInterface $translator
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(RequestStack $requestStack, TranslatorInterface $translator)
    {
        $session = $requestStack->getSession();
        if ($session instanceof Session) {
            $this->flashBag = $session->getFlashBag();
        } else {
            throw new \LogicException('Session is not a full Symfony session object.');
        }
        $this->translator = $translator;
    }

    /**
     * @param Request               $request
     * @param AccessDeniedException $accessDeniedException
     *
     * @return Response
     */
    #[\Override]
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        $this->flashBag->clear();
        $this->flashBag->add('error', $this->translator->trans('error.access', [], 'SignIn'));

        return new RedirectResponse('/');
    }
}
