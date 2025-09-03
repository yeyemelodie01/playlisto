<?php

namespace App\Repository;

use App\Entity\SurveyAnswer;
use App\Repository\Traits\RemoveTrait;
use App\Repository\Traits\SaveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SurveyAnswerRepository extends ServiceEntityRepository
{
    use SaveTrait;
    use RemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyAnswer::class);
    }

    public function getAll()
    {
        $qb = $this->createQueryBuilder('surveyanswer');

        return $qb->orderBy('surveyanswer.id', 'ASC');
    }
}
