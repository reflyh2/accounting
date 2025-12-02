<?php

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\AccountingEventStatus;
use App\Exceptions\AccountingEventException;
use App\Jobs\Accounting\DispatchAccountingEvent;
use App\Models\AccountingEventLog;
use App\Services\Accounting\AccountingEventBuilder;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Accounting\Publisher\AccountingEventPublisherFactory;
use Illuminate\Support\Facades\Queue;

function samplePayload(): AccountingEventPayload
{
    $payload = new AccountingEventPayload(
        code: AccountingEventCode::PURCHASE_GRN_POSTED,
        companyId: 1,
        branchId: 2,
        documentType: 'purchase_grn',
        documentId: 10,
        documentNumber: 'GRN-001',
        currencyCode: 'USD',
        exchangeRate: 1.00,
        occurredAt: now(),
        actorId: 99,
        meta: ['source' => 'test'],
    );

    $payload->setLines([
        AccountingEntry::debit('inventory', 120.25),
        AccountingEntry::credit('grni', 120.25),
    ]);

    return $payload;
}

it('stores logs and queues dispatch jobs', function () {
    Queue::fake();

    $bus = app(AccountingEventBus::class);

    $payload = samplePayload();

    $log = $bus->dispatch($payload);

    expect($log->status)->toBe(AccountingEventStatus::QUEUED->value);

    $this->assertDatabaseHas('accounting_event_logs', [
        'id' => $log->id,
        'event_code' => AccountingEventCode::PURCHASE_GRN_POSTED->value,
        'company_id' => 1,
        'branch_id' => 2,
        'currency_code' => 'USD',
    ]);

    Queue::assertPushed(DispatchAccountingEvent::class, function (DispatchAccountingEvent $job) use ($log) {
        return $job->logId === $log->id;
    });
});

it('rejects unbalanced payloads', function () {
    $payload = samplePayload();

    $payload->setLines([
        AccountingEntry::debit('inventory', 100),
        AccountingEntry::credit('grni', 90),
    ]);

    expect(fn () => app(AccountingEventBus::class)->dispatch($payload))
        ->toThrow(AccountingEventException::class);
});

it('marks logs as sent after dispatch job completes', function () {
    $payload = samplePayload();

    $log = AccountingEventLog::create([
        'event_code' => $payload->code->value,
        'company_id' => $payload->companyId,
        'branch_id' => $payload->branchId,
        'document_type' => $payload->documentType,
        'document_id' => (string) $payload->documentId,
        'document_number' => $payload->documentNumber,
        'currency_code' => $payload->currencyCode,
        'exchange_rate' => $payload->exchangeRate,
        'status' => AccountingEventStatus::QUEUED->value,
        'payload' => $payload->toArray(),
    ]);

    $job = new DispatchAccountingEvent($log->id);
    $job->handle(app(AccountingEventPublisherFactory::class));

    $log->refresh();

    expect($log->status)->toBe(AccountingEventStatus::SENT->value);
    expect($log->dispatched_at)->not()->toBeNull();
});

it('builds balanced payloads using the builder helper', function () {
    $builder = AccountingEventBuilder::forDocument(AccountingEventCode::SALES_DELIVERY_POSTED, [
        'company_id' => 5,
        'branch_id' => 7,
        'document_type' => 'sales_delivery',
        'document_id' => 77,
        'document_number' => 'DO-9001',
        'currency_code' => 'IDR',
        'exchange_rate' => 15000,
        'actor_id' => 12,
    ]);

    $payload = $builder
        ->debit('cogs', 500000)
        ->credit('inventory', 500000)
        ->build();

    expect($payload->totalDebit())->toBe($payload->totalCredit());
    expect($payload->companyId)->toBe(5);
    expect($payload->documentNumber)->toBe('DO-9001');
});


