<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;

class Recommendation
{
    use IdTrait;

    private \DateTime $generatedAt;
    private string $source;

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param string $source
     *
     * @return void
     */
    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    /**
     * @return \DateTime
     */
    public function getGeneratedAt(): \DateTime
    {
        return $this->generatedAt;
    }

    /**
     * @param \DateTime $generatedAt
     *
     * @return void
     */
    public function setGeneratedAt(\DateTime $generatedAt): void
    {
        $this->generatedAt = $generatedAt;
    }
}
