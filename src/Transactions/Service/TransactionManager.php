<?php

namespace App\Transactions\Service;

use App\Transactions\DTO\TransactionRequest;
use App\Transactions\Enum\TransactionStatus;
use App\Transactions\Exception\TransactionException;

readonly class TransactionManager {
    public function __construct(
        private TransactionValidator $validator,
        private TransactionProcessor $processor
    ) {}

    public function handle(TransactionRequest $request): TransactionStatus
    {
        try {
            $this->validator->validate($request);
            $this->processor->process($request, TransactionStatus::SUCCESSED);
            return TransactionStatus::SUCCESSED;
        } catch (TransactionException $e) {
            $this->processor->process($request, TransactionStatus::FAILED);
            throw $e;
        }
    }
}