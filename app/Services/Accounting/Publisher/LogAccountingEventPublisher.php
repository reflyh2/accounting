<?php

namespace App\Services\Accounting\Publisher;

use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Models\AccountingEventLog;
use Illuminate\Support\Facades\Log;

class LogAccountingEventPublisher implements AccountingEventPublisher
{
    public function __construct(private readonly string $channel)
    {
    }

    public function send(AccountingEventPayload $payload, AccountingEventLog $log): void
    {
        Log::channel($this->channel)->info('Dispatching accounting event to GL stub', [
            'log_id' => $log->id,
            'event_code' => $payload->code->value,
            'company_id' => $payload->companyId,
            'branch_id' => $payload->branchId,
            'document' => [
                'type' => $payload->documentType,
                'id' => $payload->documentId,
                'number' => $payload->documentNumber,
            ],
            'currency' => [
                'code' => $payload->currencyCode,
                'exchange_rate' => $payload->exchangeRate,
            ],
            'totals' => [
                'debit' => $payload->totalDebit(),
                'credit' => $payload->totalCredit(),
            ],
            'lines' => array_map(
                static fn ($entry) => $entry->toArray(),
                $payload->lines(),
            ),
            'meta' => $payload->meta,
        ]);
    }
}


