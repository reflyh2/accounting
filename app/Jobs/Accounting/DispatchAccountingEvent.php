<?php

namespace App\Jobs\Accounting;

use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventStatus;
use App\Exceptions\AccountingEventException;
use App\Models\AccountingEventLog;
use App\Services\Accounting\Publisher\AccountingEventPublisherFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchAccountingEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $logId)
    {
    }

    public function handle(AccountingEventPublisherFactory $publisherFactory): void
    {
        $log = AccountingEventLog::find($this->logId);

        if (!$log) {
            return;
        }

        if ($log->status === AccountingEventStatus::SENT->value) {
            return;
        }

        $payload = AccountingEventPayload::fromArray($log->payload);

        // Debug: Log payload reconstruction
        \Log::info('DispatchAccountingEvent: Payload reconstructed', [
            'log_id' => $log->id,
            'event_code' => $payload->code->value,
            'lines_count' => count($payload->lines()),
            'lines' => array_map(function($entry) {
                return [
                    'role' => $entry->role,
                    'direction' => $entry->direction,
                    'amount' => $entry->amount,
                    'meta' => $entry->meta,
                ];
            }, $payload->lines()),
        ]);

        $publisher = $publisherFactory->make();

        try {
            $publisher->send($payload, $log);
            $log->markSent();
        } catch (Throwable $e) {
            $log->markFailed($e->getMessage());
            throw $e;
        }
    }
}


