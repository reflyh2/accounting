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


