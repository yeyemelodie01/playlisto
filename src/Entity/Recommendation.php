<?php

namespace App\Entity;

use App\Entity\Traits\IdTrait;
use App\Repository\RecommendationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a music recommendation in the application.
 *
 * This entity stores metadata about a generated recommendation.
 * Each recommendation includes:
 * - Generated At (`generatedAt`): The timestamp when the recommendation was created.
 * - Source (`source`): The origin or method used to generate the recommendation (e.g., algorithm, user input).
 *
 * Useful for tracking the origin and timing of suggested playlists or tracks.
 */
#[ORM\Entity(repositoryClass: RecommendationRepository::class)]
#[ORM\Table(name: 'recommendation')]
class Recommendation
{
    use IdTrait;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $generatedAt;

    #[ORM\Column(length: 255)]
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
