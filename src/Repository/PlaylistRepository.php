<?php

namespace App\Repository;

use App\Entity\Playlist;
use App\Repository\Traits\RemoveTrait;
use App\Repository\Traits\SaveTrait;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Playlist>
 */
class PlaylistRepository extends ServiceEntityRepository
{
    use SaveTrait;
    use RemoveTrait;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Playlist::class);
    }

    /**
     * @return QueryBuilder
     */
    public function getAll(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        return $qb->orderBy('p.id', 'ASC');
    }

    /**
     * Count total number of playlists.
     *
     * @return int
     */
    public function countPlaylists(): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count number of playlists created between two dates.
     *
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     *
     * @return int
     */
    public function countPlaylistFilteredByDate(DateTimeInterface $startDate, DateTimeInterface $endDate): int
    {
        $start = (clone $startDate)->setTime(0, 0, 0);
        $end   = (clone $endDate)->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     *
     * @return array
     */
    public function countByMoodBetween(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $start = (clone $startDate)->setTime(0, 0, 0);
        $end   = (clone $endDate)->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->select('p.mood AS mood, COUNT(p.id) AS total')
            ->where('p.createdAt BETWEEN :startDate AND :endDate')
            ->groupBy('p.mood')
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     *
     * @return array
     */
    public function countByActivityBetween(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $start = (clone $startDate)->setTime(0, 0, 0);
        $end   = (clone $endDate)->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->select('p.activity AS activity, COUNT(p.id) AS total')
            ->where('p.createdAt BETWEEN :startDate AND :endDate')
            ->groupBy('p.activity')
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end)
            ->getQuery()
            ->getArrayResult();
    }
}
