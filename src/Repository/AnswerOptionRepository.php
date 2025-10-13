<?php

namespace App\Repository;

use App\Entity\AnswerOption;
use App\Repository\Traits\RemoveTrait;
use App\Repository\Traits\SaveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnswerOption>
 */
class AnswerOptionRepository extends ServiceEntityRepository
{
    use SaveTrait;
    use RemoveTrait;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnswerOption::class);
    }

    /**
     * @return QueryBuilder
     */
    public function getAll(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a');

        return $qb->orderBy('a.id', 'ASC');
    }
}
