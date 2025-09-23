<?php

namespace App\Repository;

use App\Entity\User;
use App\Repository\Traits\RemoveTrait;
use App\Repository\Traits\SaveTrait;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    use SaveTrait;
    use RemoveTrait;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return QueryBuilder
     */
    public function getAll(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        return $qb->orderBy('u.id', 'ASC');
    }

    /**
     * Count total number of users.
     *
     * @return int
     */
    public function countUsers(): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Retrieve the last ten users who connected, ordered by last login date descending.
     *
     * @return User[]
     */
    public function lastTenUserConnected(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt IS NOT NULL')
            ->orderBy('u.lastLoginAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count new users between two dates.
     *
     * @param DateTimeInterface $from Start date (inclusive)
     * @param DateTimeInterface $to   End date (exclusive)
     *
     * @return int
     */
    public function countNewUsersBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :from')
            ->andWhere('u.createdAt < :to')
            ->setParameter('from', (clone $from)->setTime(0, 0, 0))
            ->setParameter('to', (clone $to)->setTime(23, 59, 59))
            ->getQuery()->getSingleScalarResult();
    }
}
