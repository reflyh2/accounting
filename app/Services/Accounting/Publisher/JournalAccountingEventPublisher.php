<?php

namespace App\Services\Accounting\Publisher;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Exceptions\AccountingEventException;
use App\Models\AccountingEventLog;
use App\Models\GlEventConfiguration;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;

class JournalAccountingEventPublisher implements AccountingEventPublisher
{
    public function send(AccountingEventPayload $payload, AccountingEventLog $log): void
    {
        DB::transaction(function () use ($payload) {
            $config = $this->resolveConfiguration($payload);

            $journal = $this->createJournal($payload);

            foreach ($payload->lines() as $entry) {
                $this->createJournalEntry($journal, $entry, $config, $payload);
            }
        });
    }

    private function resolveConfiguration(AccountingEventPayload $payload): GlEventConfiguration
    {
        // Try to find specific branch configuration first
        $query = GlEventConfiguration::where('event_code', $payload->code->value)
            ->where('is_active', true);

        if ($payload->branchId) {
            $branchConfig = (clone $query)
                ->where('branch_id', $payload->branchId)
                ->first();

            if ($branchConfig) {
                return $branchConfig;
            }
        }

        // Fallback to company configuration
        $companyConfig = (clone $query)
            ->where('company_id', $payload->companyId)
            ->whereNull('branch_id')
            ->first();

        if ($companyConfig) {
            return $companyConfig;
        }

        throw new AccountingEventException(sprintf(
            'No active GL event configuration found for event [%s] in company [%s] (Branch: %s).',
            $payload->code->value,
            $payload->companyId,
            $payload->branchId ?? 'N/A'
        ));
    }

    private function createJournal(AccountingEventPayload $payload): Journal
    {
        $journalType = $this->mapEventCodeToJournalType($payload->code->value);

        return Journal::create([
            'branch_id' => $payload->branchId,
            'user_global_id' => $payload->actorId ?: 'system', // Ideally we'd have a system user ID or similar.
            // The user will need to ensure actorId is populated or 'system' exists.
            'date' => $payload->occurredAt->format('Y-m-d'),
            'journal_type' => $journalType,
            'reference_number' => $payload->documentNumber,
            'description' => $this->proposeDescription($payload),
        ]);
    }

    private function createJournalEntry(
        Journal $journal,
        AccountingEntry $entry,
        GlEventConfiguration $config,
        AccountingEventPayload $payload
    ): void {
        // Check if account_id is provided in entry meta (override)
        $accountId = $entry->meta['account_id'] ?? null;

        \Log::info('createJournalEntry: Processing entry', [
            'journal_id' => $journal->id,
            'role' => $entry->role,
            'direction' => $entry->direction,
            'amount' => $entry->amount,
            'meta' => $entry->meta,
            'meta_account_id' => $accountId,
        ]);

        if (! $accountId) {
            // Find account mapping for the role from GL Event Configuration
            $mapping = $config->lines->first(function ($line) use ($entry) {
                return $line->role === $entry->role;
            });

            if (! $mapping) {
                throw new AccountingEventException(sprintf(
                    'No account mapping found for role [%s] in GL Event Configuration [%s].',
                    $entry->role,
                    $config->id
                ));
            }

            $accountId = $mapping->account_id;

            \Log::info('createJournalEntry: Using GL Event Configuration account', [
                'role' => $entry->role,
                'account_id' => $accountId,
            ]);
        } else {
            \Log::info('createJournalEntry: Using meta override account', [
                'role' => $entry->role,
                'account_id' => $accountId,
            ]);
        }

        $amount = $entry->amount;
        $primaryAmount = $amount * $payload->exchangeRate;

        $currencyId = $this->getCurrencyId($payload->currencyCode);

        $journal->journalEntries()->create([
            'account_id' => $accountId,
            'debit' => $entry->direction === 'debit' ? $amount : 0,
            'credit' => $entry->direction === 'credit' ? $amount : 0,
            'currency_id' => $currencyId,
            'exchange_rate' => $payload->exchangeRate,
            'primary_currency_debit' => $entry->direction === 'debit' ? $primaryAmount : 0,
            'primary_currency_credit' => $entry->direction === 'credit' ? $primaryAmount : 0,
        ]);
    }

    private function mapEventCodeToJournalType(string $eventCode): string
    {
        if (str_starts_with($eventCode, 'sales.')) {
            return 'sales';
        }

        if (str_starts_with($eventCode, 'purchase.')) {
            return 'purchase';
        }

        return 'general';
    }

    private function proposeDescription(AccountingEventPayload $payload): string
    {
        return sprintf(
            '%s - %s',
            $payload->code->label(),
            $payload->documentNumber ?? 'No Ref'
        );
    }

    private function getCurrencyId(string $code): int
    {
        static $cache = [];
        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $id = DB::table('currencies')->where('code', $code)->value('id');

        if (! $id) {
            throw new AccountingEventException("Currency code [{$code}] not found.");
        }

        $cache[$code] = $id;

        return $id;
    }
}
