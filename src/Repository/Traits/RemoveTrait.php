<?php

namespace App\Repository\Traits;

/**
 * Trait RemoveTrait.
 */
trait RemoveTrait
{
    /**
     * @param object $entity
     * @param bool   $flush
     *
     * @return void
     */
    public function remove(object $entity, bool $flush = false): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($entity);

        if ($flush) {
            $entityManager->flush();
        }
    }
}
