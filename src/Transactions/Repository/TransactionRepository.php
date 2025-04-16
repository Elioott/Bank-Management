<?php

namespace App\Transactions\Repository;

use App\Transactions\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function getBalanceByAccount(int $accountId): float
    {
        $qb = $this->createQueryBuilder('t');

        $qb->select('
            SUM(CASE 
                WHEN t.destination_account = :compteId THEN t.amount
                ELSE 0
            END) - 
            SUM(CASE 
                WHEN t.source_account = :compteId THEN t.amount
                ELSE 0
            END) as solde
        ')
            ->setParameter('compteId', $accountId);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getTotalTransactionAmount(): float
    {
        $qb = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as totalAmount');

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    public function findByAccount($accountId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.source_account = :accountId')
            ->orWhere('t.destination_account = :accountId')
            ->setParameter('accountId', $accountId)
            ->orderBy('t.date_time', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
