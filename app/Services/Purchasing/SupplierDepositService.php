<?php

namespace App\Services\Purchasing;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Exceptions\SupplierDepositException;
use App\Models\CompanyBankAccount;
use App\Models\GlEventConfiguration;
use App\Models\SupplierDeposit;
use App\Services\Accounting\AccountingEventBus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Manages cash advances paid to suppliers. The advance sits as an asset
 * ("Uang Muka Pemasok") until cost obligations (booking COGS or SI
 * direct costs) draw it down via SupplierObligationRouter in phase 6.
 *
 * This service handles:
 *   - record(): paying out the deposit (Dr supplier_advance / Cr cash/bank)
 *   - refund(): refunding any unused balance (Dr cash/bank / Cr supplier_advance)
 *   - findAvailableForPartner(): FIFO lookup the router will call at obligation post time
 *
 * Consumption itself (the per-obligation draw) lives in the router/consumer in phase 6.
 */
class SupplierDepositService
{
    public function __construct(
        private readonly AccountingEventBus $accountingEventBus,
    ) {}

    /**
     * Record a new supplier deposit. Resolves the cash account from the
     * selected CompanyBankAccount (if any) or falls back to the role-mapped
     * default "cash". Dispatches SUPPLIER_DEPOSIT_PAID.
     *
     * @param  array{
     *     company_id:int, branch_id?:int|null, partner_id:int, currency_id:int,
     *     deposit_date:string, amount:float, exchange_rate?:float,
     *     payment_method?:string|null, company_bank_account_id?:int|null,
     *     notes?:string|null,
     * }  $payload
     */
    public function record(array $payload, ?Authenticatable $actor = null): SupplierDeposit
    {
        $actor ??= Auth::user();

        $amount = (float) $payload['amount'];
        if ($amount <= 0) {
            throw new SupplierDepositException('Jumlah deposit harus lebih dari nol.');
        }

        return DB::transaction(function () use ($payload, $amount, $actor) {
            $advanceAccountId = $this->resolveAdvanceAccountId((int) $payload['company_id']);
            $paymentAccountId = $this->resolvePaymentAccountId(
                (int) $payload['company_id'],
                $payload['company_bank_account_id'] ?? null
            );
            $exchangeRate = (float) ($payload['exchange_rate'] ?? 1);

            $deposit = SupplierDeposit::create([
                'deposit_number' => $this->generateNumber(
                    (int) $payload['company_id'],
                    (int) ($payload['branch_id'] ?? 0),
                    Carbon::parse($payload['deposit_date'])
                ),
                'company_id' => $payload['company_id'],
                'branch_id' => $payload['branch_id'] ?? null,
                'partner_id' => $payload['partner_id'],
                'currency_id' => $payload['currency_id'],
                'exchange_rate' => $exchangeRate,
                'deposit_date' => Carbon::parse($payload['deposit_date']),
                'amount' => $amount,
                'balance' => $amount,
                'advance_account_id' => $advanceAccountId,
                'payment_account_id' => $paymentAccountId,
                'payment_method' => $payload['payment_method'] ?? null,
                'company_bank_account_id' => $payload['company_bank_account_id'] ?? null,
                'status' => 'open',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $this->dispatchDepositPaidEvent($deposit, $actor);

            return $deposit->fresh(['partner', 'currency', 'companyBankAccount']);
        });
    }

    /**
     * Refund the remaining balance (or a partial amount) of a deposit.
     */
    public function refund(SupplierDeposit $deposit, ?float $amount = null, ?Authenticatable $actor = null): SupplierDeposit
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($deposit, $amount, $actor) {
            /** @var SupplierDeposit $locked */
            $locked = SupplierDeposit::query()->whereKey($deposit->id)->lockForUpdate()->first();

            $balance = (float) $locked->balance;
            $refundAmount = $amount === null ? $balance : (float) $amount;

            if ($refundAmount <= 0) {
                throw new SupplierDepositException('Jumlah refund harus lebih dari nol.');
            }
            if ($refundAmount > $balance + 0.001) {
                throw new SupplierDepositException(sprintf(
                    'Saldo deposit hanya %s, tidak cukup untuk refund %s.',
                    number_format($balance, 2),
                    number_format($refundAmount, 2)
                ));
            }

            $locked->update([
                'balance' => round($balance - $refundAmount, 2),
                'refunded_amount' => round(((float) $locked->refunded_amount) + $refundAmount, 2),
                'refunded_at' => now(),
                'status' => abs($balance - $refundAmount) < 0.005 ? 'refunded' : 'open',
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $this->dispatchDepositReversedEvent($locked, $refundAmount, $actor);

            return $locked->fresh();
        });
    }

    /**
     * Open deposits for a (company, partner) ordered FIFO. Phase 6 router uses this.
     *
     * @return Collection<int, SupplierDeposit>
     */
    public function findAvailableForPartner(int $companyId, int $partnerId): Collection
    {
        return SupplierDeposit::query()
            ->where('company_id', $companyId)
            ->where('partner_id', $partnerId)
            ->where('status', 'open')
            ->where('balance', '>', 0)
            ->orderBy('deposit_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve the supplier_advance account via the GL Event Configuration
     * for SUPPLIER_DEPOSIT_PAID (seeded in phase 1 to Uang Muka Pemasok).
     */
    private function resolveAdvanceAccountId(int $companyId): int
    {
        $config = GlEventConfiguration::query()
            ->with(['lines' => fn ($q) => $q->where('role', 'supplier_advance')])
            ->where('company_id', $companyId)
            ->where('event_code', AccountingEventCode::SUPPLIER_DEPOSIT_PAID->value)
            ->where('is_active', true)
            ->first();

        $accountId = $config?->lines->first()?->account_id;

        if (! $accountId) {
            throw new SupplierDepositException(
                'GL Event Configuration untuk role supplier_advance tidak ditemukan untuk perusahaan ini. Jalankan ulang GlEventConfigurationSeeder.'
            );
        }

        return (int) $accountId;
    }

    /**
     * If a bank account is selected, use its GL account directly. Otherwise
     * fall back to the role-mapped default "cash" on the SUPPLIER_DEPOSIT_PAID
     * config (typically Kas Besar).
     */
    private function resolvePaymentAccountId(int $companyId, ?int $bankAccountId): ?int
    {
        if ($bankAccountId) {
            $bank = CompanyBankAccount::find($bankAccountId);
            if ($bank?->account_id) {
                return (int) $bank->account_id;
            }
        }

        $config = GlEventConfiguration::query()
            ->with(['lines' => fn ($q) => $q->where('role', 'cash')])
            ->where('company_id', $companyId)
            ->where('event_code', AccountingEventCode::SUPPLIER_DEPOSIT_PAID->value)
            ->where('is_active', true)
            ->first();

        return $config?->lines->first()?->account_id;
    }

    private function dispatchDepositPaidEvent(SupplierDeposit $deposit, ?Authenticatable $actor): void
    {
        $amount = (float) $deposit->amount;
        $exchangeRate = (float) ($deposit->exchange_rate ?: 1);
        $amountBase = round($amount * $exchangeRate, 4);

        $payload = new AccountingEventPayload(
            AccountingEventCode::SUPPLIER_DEPOSIT_PAID,
            $deposit->company_id,
            $deposit->branch_id,
            'supplier_deposit',
            $deposit->id,
            $deposit->deposit_number,
            $deposit->currency?->code ?? 'IDR',
            $exchangeRate,
            CarbonImmutable::parse($deposit->deposit_date),
            $actor?->getAuthIdentifier(),
        );

        $cashEntry = AccountingEntry::credit(
            'cash',
            $amountBase,
            $deposit->payment_account_id ? ['account_id' => (int) $deposit->payment_account_id] : []
        );

        $payload->setLines([
            AccountingEntry::debit('supplier_advance', $amountBase, ['account_id' => (int) $deposit->advance_account_id]),
            $cashEntry,
        ]);

        $this->accountingEventBus->dispatch($payload);
    }

    private function dispatchDepositReversedEvent(SupplierDeposit $deposit, float $amount, ?Authenticatable $actor): void
    {
        $exchangeRate = (float) ($deposit->exchange_rate ?: 1);
        $amountBase = round($amount * $exchangeRate, 4);

        $payload = new AccountingEventPayload(
            AccountingEventCode::SUPPLIER_DEPOSIT_REVERSED,
            $deposit->company_id,
            $deposit->branch_id,
            'supplier_deposit',
            $deposit->id,
            $deposit->deposit_number.'-REV',
            $deposit->currency?->code ?? 'IDR',
            $exchangeRate,
            CarbonImmutable::now(),
            $actor?->getAuthIdentifier(),
        );

        $cashEntry = AccountingEntry::debit(
            'cash',
            $amountBase,
            $deposit->payment_account_id ? ['account_id' => (int) $deposit->payment_account_id] : []
        );

        $payload->setLines([
            $cashEntry,
            AccountingEntry::credit('supplier_advance', $amountBase, ['account_id' => (int) $deposit->advance_account_id]),
        ]);

        $this->accountingEventBus->dispatch($payload);
    }

    private function generateNumber(int $companyId, int $branchId, Carbon $depositDate): string
    {
        $prefix = 'SDEP';
        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $depositDate->format('y');
        $monthSegment = $depositDate->format('m');

        $latest = SupplierDeposit::query()
            ->where('company_id', $companyId)
            ->whereYear('deposit_date', $depositDate->year)
            ->whereMonth('deposit_date', $depositDate->month)
            ->orderByDesc('id')
            ->value('deposit_number');

        $nextSequence = 1;
        if ($latest) {
            $segments = explode('.', $latest);
            $last = (int) (end($segments) ?: 0);
            $nextSequence = $last + 1;
        }

        $sequence = str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);

        return sprintf('%s.%s%s.%s%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $monthSegment, $sequence);
    }
}
