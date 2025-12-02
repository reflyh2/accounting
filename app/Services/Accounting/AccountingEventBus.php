<?php

namespace App\Services\Accounting;

use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventStatus;
use App\Jobs\Accounting\DispatchAccountingEvent;
use App\Models\AccountingEventLog;

class AccountingEventBus
{
    public function dispatch(AccountingEventPayload $payload): AccountingEventLog
    {
        $payload->assertBalanced();

        $log = AccountingEventLog::create([
            'event_code' => $payload->code->value,
            'company_id' => $payload->companyId,
            'branch_id' => $payload->branchId,
            'document_type' => $payload->documentType,
            'document_id' => $payload->documentId ? (string) $payload->documentId : null,
            'document_number' => $payload->documentNumber,
            'currency_code' => $payload->currencyCode,
            'exchange_rate' => $payload->exchangeRate,
            'status' => AccountingEventStatus::QUEUED->value,
            'payload' => $payload->toArray(),
        ]);

        DispatchAccountingEvent::dispatch($log->id);

        return $log;
    }
}


