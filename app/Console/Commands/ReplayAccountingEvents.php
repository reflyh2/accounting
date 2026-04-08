<?php

namespace App\Console\Commands;

use App\Enums\AccountingEventStatus;
use App\Jobs\Accounting\DispatchAccountingEvent;
use App\Models\AccountingEventLog;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ReplayAccountingEvents extends Command
{
    protected $signature = 'accounting:replay-events
        {--tenant= : Tenant ID to run against}
        {--id=* : Specific event log IDs to replay}
        {--event-code= : Filter by event code}
        {--document-number= : Filter by document number}
        {--status=failed : Filter by status (failed, queued, all)}
        {--dry-run : List matching events without dispatching}';

    protected $description = 'Replay failed or queued accounting events';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if (! $tenantId) {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant [{$tenantId}] not found.");

            return self::FAILURE;
        }

        return $tenant->run(function () {
            return $this->replayEvents();
        });
    }

    private function replayEvents(): int
    {
        $query = AccountingEventLog::query();

        $ids = $this->option('id');
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $status = $this->option('status');
            if ($status !== 'all') {
                $query->where('status', $status);
            } else {
                $query->whereIn('status', [
                    AccountingEventStatus::FAILED->value,
                    AccountingEventStatus::QUEUED->value,
                ]);
            }

            if ($eventCode = $this->option('event-code')) {
                $query->where('event_code', $eventCode);
            }

            if ($documentNumber = $this->option('document-number')) {
                $query->where('document_number', $documentNumber);
            }
        }

        $logs = $query->orderBy('id')->get();

        if ($logs->isEmpty()) {
            $this->info('No matching accounting events found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Event Code', 'Document #', 'Status', 'Last Error', 'Created At'],
            $logs->map(fn (AccountingEventLog $log) => [
                $log->id,
                $log->event_code,
                $log->document_number,
                $log->status,
                str($log->last_error)->limit(60),
                $log->created_at,
            ])
        );

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$logs->count()} event(s) would be replayed.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Replay {$logs->count()} accounting event(s)?")) {
            return self::SUCCESS;
        }

        $replayed = 0;

        foreach ($logs as $log) {
            $log->forceFill([
                'status' => AccountingEventStatus::QUEUED->value,
                'last_error' => null,
            ])->save();

            DispatchAccountingEvent::dispatch($log->id);
            $replayed++;

            $this->line("  Dispatched event log #{$log->id} ({$log->event_code})");
        }

        $this->info("Replayed {$replayed} accounting event(s).");

        return self::SUCCESS;
    }
}
