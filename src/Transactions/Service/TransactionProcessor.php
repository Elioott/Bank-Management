<?php

namespace App\Transactions\Service;

use App\Transactions\DTO\TransactionRequest;
use App\Transactions\Entity\Transaction;
use App\Transactions\Enum\TransactionStatus;
use Doctrine\ORM\EntityManagerInterface;

readonly class TransactionProcessor {
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function process(TransactionRequest $request, TransactionStatus $status): Transaction
    {
        $transaction = new Transaction();
        $transaction->setAmount($request->amount);
        $transaction->setDateTime(new \DateTime());
        $transaction->setType($request->type);
        $transaction->setStatus($status);

        if ($request->sourceAccount) {
            $transaction->setSourceAccount($request->sourceAccount);
        }

        if ($request->destinationAccount) {
            $transaction->setDestinationAccount($request->destinationAccount);
        }

        if ($status === TransactionStatus::SUCCESSED) {
            if ($request->sourceAccount) {
                $request->sourceAccount->setBalance(
                    $request->sourceAccount->getBalance() - $request->amount
                );
                $this->em->persist($request->sourceAccount);
            }

            if ($request->destinationAccount) {
                $request->destinationAccount->setBalance(
                    $request->destinationAccount->getBalance() + $request->amount
                );
                $this->em->persist($request->destinationAccount);
            }
        }

        $this->em->persist($transaction);
        $this->em->flush();

        return $transaction;
    }
}