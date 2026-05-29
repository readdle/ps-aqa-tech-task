<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SignupCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SignupCode>
 */
final class SignupCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SignupCode::class);
    }

    public function findActiveCode(User $user, string $plainCode): ?SignupCode
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.codeHash = :codeHash')
            ->andWhere('c.usedAt IS NULL')
            ->andWhere('c.expiresAt >= :now')
            ->setParameter('user', $user)
            ->setParameter('codeHash', hash('sha256', trim($plainCode)))
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
