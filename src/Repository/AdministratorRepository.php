<?php

namespace App\Repository;

use App\Entity\Administrator;
use App\Repository\Traits\RemoveTrait;
use App\Repository\Traits\SaveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Administrator>
 */
class AdministratorRepository extends ServiceEntityRepository
{
    use SaveTrait;
    use RemoveTrait;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Administrator::class);
    }

    /**
     * @return QueryBuilder
     */
    public function getAll(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('admin');

        return $qb->orderBy('admin.id', 'ASC');
    }
}
