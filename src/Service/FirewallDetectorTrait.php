<?php

namespace App\Service;

/**
 * @phpstan-ignore-next-line
 * Trait FirewallDetectorTrait.
 */
trait FirewallDetectorTrait
{
    protected ?FirewallDetector $firewallDetector = null;

    /**
     * @param FirewallDetector $firewallDetector
     *
     * @return void
     */
    public function setFirewallDetector(FirewallDetector $firewallDetector): void
    {
        $this->firewallDetector = $firewallDetector;
    }

    /**
     * @param string|null $domain
     *
     * @return string|null
     */
    protected function realDomain(?string $domain = null): ?string
    {
        if (!$this->firewallDetector || !$domain) {
            return null;
        }

        $firewallShortName = (string) $this->firewallDetector->getFirewallShortName();
        $realDomainPrefix = $firewallShortName . '.';
        if (str_starts_with($domain, $realDomainPrefix)) {
            $realDomain = $realDomainPrefix . substr($domain, strlen($realDomainPrefix));
            if ($realDomain === $domain) {
                return $realDomain;
            }
        }

        return $realDomainPrefix . $domain;
    }
}
