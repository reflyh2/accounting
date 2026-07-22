<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Http\Requests\StoreBookingDepositRequest;
use App\Models\Booking;
use App\Models\BookingDeposit;
use App\Services\Accounting\AccountingEventBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class BookingDepositController extends Controller
{
    public function __construct(
        private readonly AccountingEventBus $accountingEventBus
    ) {}

    /**
     * Store a newly created deposit in storage.
     */
    public function store(StoreBookingDepositRequest $request, Booking $booking): RedirectResponse
    {
        if ($booking->hasInvoice()) {
            return Redirect::back()->with('error', 'Deposit tidak dapat ditambahkan setelah booking dibuatkan faktur.');
        }

        $depositData = $request->validated();

        // 1. Create the deposit record
        $deposit = $booking->deposits()->create($depositData);

        // 2. Update the bookings table stamp fields to sync with total deposits
        $totalDeposits = (float) $booking->deposits()->sum('amount');
        $booking->forceFill([
            'deposit_received_amount' => $totalDeposits,
            'deposit_received_at' => $deposit->received_at,
        ])->save();

        // 3. Dispatch the journal entry
        $bankAccount = $deposit->company_bank_account_id
            ? \App\Models\CompanyBankAccount::find($deposit->company_bank_account_id)
            : null;
        $cashAccountId = $bankAccount?->account_id;

        $actor = Auth::user();

        $payload = new AccountingEventPayload(
            AccountingEventCode::BOOKING_DEPOSIT_RECEIVED,
            $booking->company_id,
            $booking->branch_id,
            'booking_deposit',
            $deposit->id,
            $booking->booking_number,
            'IDR',
            1.0,
            $deposit->received_at,
            $actor?->getAuthIdentifier(),
            ['notes' => $deposit->notes],
        );

        $payload->setLines([
            AccountingEntry::debit('cash', (float) $deposit->amount, $cashAccountId ? ['account_id' => $cashAccountId] : []),
            AccountingEntry::credit('customer_deposit', (float) $deposit->amount),
        ]);

        $this->accountingEventBus->dispatch($payload);

        return Redirect::back()->with('success', 'Deposit berhasil ditambahkan.');
    }

    /**
     * Remove the specified deposit from storage.
     */
    public function destroy(Booking $booking, BookingDeposit $deposit): RedirectResponse
    {
        if ($booking->hasInvoice()) {
            return Redirect::back()->with('error', 'Deposit tidak dapat dihapus setelah booking dibuatkan faktur.');
        }

        if ($deposit->booking_id !== $booking->id) {
            abort(404);
        }

        // 1. Dispatch the reverse journal entry
        $bankAccount = $deposit->company_bank_account_id
            ? \App\Models\CompanyBankAccount::find($deposit->company_bank_account_id)
            : null;
        $cashAccountId = $bankAccount?->account_id;

        $actor = Auth::user();

        $payload = new AccountingEventPayload(
            AccountingEventCode::BOOKING_DEPOSIT_REVERSED,
            $booking->company_id,
            $booking->branch_id,
            'booking_deposit',
            $deposit->id,
            $booking->booking_number,
            'IDR',
            1.0,
            now(),
            $actor?->getAuthIdentifier(),
        );

        $payload->setLines([
            AccountingEntry::debit('customer_deposit', (float) $deposit->amount),
            AccountingEntry::credit('cash', (float) $deposit->amount, $cashAccountId ? ['account_id' => $cashAccountId] : []),
        ]);

        $this->accountingEventBus->dispatch($payload);

        // 2. Delete the deposit record
        $deposit->delete();

        // 3. Update the bookings table stamp fields to sync with remaining deposits
        $remainingDeposits = $booking->deposits();
        $totalDeposits = (float) $remainingDeposits->sum('amount');

        $latestDeposit = $remainingDeposits->latest('received_at')->first();

        $booking->forceFill([
            'deposit_received_amount' => $totalDeposits,
            'deposit_received_at' => $latestDeposit ? $latestDeposit->received_at : null,
        ])->save();

        return Redirect::back()->with('success', 'Deposit berhasil dihapus.');
    }
}
