<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthToken>
 */
final class AuthTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthToken::class);
    }

    public function findValidUserByPlainToken(string $plainToken): ?User
    {
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.user', 'u')
            ->addSelect('u')
            ->andWhere('t.tokenHash = :tokenHash')
            ->andWhere('t.expiresAt >= :now')
            ->setParameter('tokenHash', hash('sha256', $plainToken))
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1);

        $token = $qb->getQuery()->getOneOrNullResult();

        return $token?->getUser();
    }
}
