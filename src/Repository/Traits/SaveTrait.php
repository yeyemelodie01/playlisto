<?php

namespace App\Repository\Traits;

/**
 * Trait SaveTrait.
 */
trait SaveTrait
{
    /**
     * @param object $entity
     * @param bool   $flush
     *
     * @return void
     */
    public function save(object $entity, bool $flush = false): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($entity);

        if ($flush || count($entityManager->getUnitOfWork()->getScheduledEntityInsertions()) > 10) {
            $entityManager->flush();
        }
    }
}
