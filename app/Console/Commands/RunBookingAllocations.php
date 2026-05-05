<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CostPool;
use App\Services\Booking\Allocation\BookingAllocationOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RunBookingAllocations extends Command
{
    protected $signature = 'booking:run-allocations
        {--company= : Optional company id to limit the run}
        {--pool= : Optional cost pool id to limit the run}
        {--period= : Period in YYYY-MM (defaults to last calendar month)}
        {--dry-run : Compute and report but do not persist}';

    protected $description = 'Run booking cost-pool allocations for self-operated bookings.';

    public function handle(BookingAllocationOrchestrator $orchestrator): int
    {
        $period = $this->option('period') ?: CarbonImmutable::now()->subMonthNoOverflow()->format('Y-m');
        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        } catch (\Throwable $e) {
            $this->error('Period must be YYYY-MM (got '.$period.').');

            return self::INVALID;
        }
        $end = $start->endOfMonth();

        $companies = Company::query()
            ->when($this->option('company'), fn ($q) => $q->where('id', (int) $this->option('company')))
            ->get();

        $count = 0;

        foreach ($companies as $company) {
            $pools = CostPool::query()
                ->where('company_id', $company->id)
                ->when($this->option('pool'), fn ($q) => $q->where('id', (int) $this->option('pool')))
                ->where('is_active', true)
                ->get();

            foreach ($pools as $pool) {
                $this->info("Running allocation for company {$company->id}, pool {$pool->id} ({$pool->name})");

                if ($this->option('dry-run')) {
                    $this->line('  [dry-run] would call orchestrator->run('.$start->toDateString().' .. '.$end->toDateString().')');

                    continue;
                }

                try {
                    $run = $orchestrator->run($company->id, $pool->id, $start, $end);
                    $this->line('  -> run #'.$run->id.' status='.$run->status.' allocated='.$run->pool_amount);
                    $count++;
                } catch (\Throwable $e) {
                    $this->warn('  ! skipped: '.$e->getMessage());
                }
            }
        }

        $this->info("Done. {$count} run(s) posted.");

        return self::SUCCESS;
    }
}
