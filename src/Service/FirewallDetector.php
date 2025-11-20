<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class FirewallDetector.
 */
final readonly class FirewallDetector
{
    /**
     * FirewallDetector constructor.
     *
     * @param FirewallMap  $firewallMap
     * @param RequestStack $requestStack
     *
     * @psalm-suppress
     */
    public function __construct(private FirewallMap $firewallMap, private RequestStack $requestStack)
    {
    }

    /**
     * @return FirewallConfig|null
     */
    public function getFirewallConfig(): ?FirewallConfig
    {
        return $this->requestStack->getCurrentRequest() !== null ? $this->firewallMap->getFirewallConfig($this->requestStack->getCurrentRequest()) : null;
    }

    /**
     * @return string|null
     */
    public function getFirewallName(): ?string
    {
        return $this->getFirewallConfig()?->getName();
    }

    /**
     * @return string|null
     */
    public function getFirewallShortName(): ?string
    {
        $name = $this->getFirewallName();

        return $name !== null ? str_replace('_secured_area', '', $name) : null;
    }
}
