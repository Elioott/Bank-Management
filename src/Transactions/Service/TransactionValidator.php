<?php

namespace App\Transactions\Service;

use App\Account\Enum\AccountType;
use App\Transactions\DTO\TransactionRequest;
use App\Transactions\Enum\TransactionType;
use App\Transactions\Exception\TransactionException;

class TransactionValidator {
    public function validate(TransactionRequest $request): void {
        $type = $request->type;
        $source = $request->sourceAccount;
        $destination = $request->destinationAccount;

        if (in_array($type->value, ['TRANSFER', 'WITHDRAWAL']) && !$source) {
            throw new TransactionException('Un compte source est requis.');
        }

        if (in_array($type->value, ['TRANSFER', 'DEPOSIT']) && !$destination) {
            throw new TransactionException('Un compte destination est requis.');
        }

        if ($source && $source->getOwner() !== $request->user) {
            throw new TransactionException('Vous n\'êtes pas propriétaire du compte source.');
        }

        if ($source && !$source->isActive()) {
            throw new TransactionException('Le compte source est inactif.');
        }

        if ($destination && !$destination->isActive()) {
            throw new TransactionException('Le compte destination est inactif.');
        }

        if ($type === TransactionType::WITHDRAWAL && $source?->getType() === AccountType::SAVINGS) {
            throw new TransactionException('Un compte épargne ne peut pas faire de retrait.');
        }

        if ($type === TransactionType::TRANSFER && $destination?->getType() !== AccountType::CURRENT) {
            throw new TransactionException('Un transfert ne peut être effectué que vers un compte courant.');
        }

        if ($source && $source->getBalance() < $request->amount) {
            throw new TransactionException('Solde insuffisant sur le compte source.');
        }

        if ($destination && !$destination->canDeposit($request->amount)) {
            throw new TransactionException('Le compte destination a atteint sa limite de dépôt.');
        }
    }
}