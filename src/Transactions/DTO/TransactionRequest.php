<?php

namespace App\Transactions\DTO;

use App\Account\Entity\Account;
use App\Auth\Entity\User;
use App\Transactions\Enum\TransactionType;

class TransactionRequest {
    public function __construct(
        public float           $amount,
        public User            $user,
        public ?Account        $sourceAccount,
        public ?Account        $destinationAccount,
        public TransactionType $type
    ) {
    }
}