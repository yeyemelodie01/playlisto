<?php

namespace App\Repository;

use App\Entity\SurveySubmission;
use App\Repository\Traits\RemoveTrait;
use App\Repository\Traits\SaveTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SurveySubmissionRepository extends ServiceEntityRepository
{
    use SaveTrait;
    use RemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveySubmission::class);
    }

    public function getAll()
    {
        $qb = $this->createQueryBuilder('surveysubmission');

        return $qb->orderBy('surveysubmission.id', 'ASC');
    }
}
