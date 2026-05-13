<?php

namespace App\Services\Booking;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\BookingStatus;
use App\Events\Booking\BookingConfirmed;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Product;
use App\Models\RentalPolicy;
use App\Models\ResourceInstance;
use App\Models\ResourcePool;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Booking\DTO\BookingLineDTO;
use App\Services\Booking\DTO\HoldBookingDTO;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly AccountingEventBus $accountingEventBus,
    ) {}

    public function hold(HoldBookingDTO $dto): Booking
    {
        if (empty($dto->lines)) {
            throw new BookingException('At least one booking line is required.');
        }

        return DB::transaction(function () use ($dto) {
            $lineCollection = collect($dto->lines);
            $products = $this->loadProducts($lineCollection);
            $pools = $this->loadPools($lineCollection);

            foreach ($lineCollection as $lineDto) {
                $availability = $this->availabilityService->searchPoolAvailability(
                    $lineDto->resourcePoolId,
                    $lineDto->start,
                    $lineDto->end,
                    $lineDto->qty
                );

                if ($availability->availableQty < $lineDto->qty || $availability->isBlocked()) {
                    throw new BookingException('Availability tidak mencukupi untuk periode yang dipilih.');
                }

                $pool = $pools->get($lineDto->resourcePoolId);
                if (! $pool || $pool->product_id !== $lineDto->productId) {
                    throw new BookingException('Pool resource tidak sesuai dengan produk.');
                }

                if ($lineDto->qty <= 0) {
                    throw new BookingException('Quantity harus lebih dari nol.');
                }
            }

            $booking = Booking::create([
                'booking_number' => $this->generateBookingNumber(),
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'partner_id' => $dto->partnerId,
                'booking_type' => $dto->bookingType,
                'status' => BookingStatus::HOLD->value,
                'booked_at' => $dto->bookedAt ?? now(),
                'held_until' => $dto->heldUntil,
                'source_channel' => $dto->sourceChannel,
                'deposit_amount' => $dto->depositAmount,
                'currency_id' => $dto->currencyId,
                'notes' => $dto->notes,
            ]);

            $totalDeposit = 0;

            foreach ($lineCollection as $lineDto) {
                $product = $products->get($lineDto->productId);
                if (! $product) {
                    throw new BookingException('Produk tidak ditemukan.');
                }

                $line = $this->createBookingLine($booking, $product, $lineDto);
                $totalDeposit += (float) ($line->deposit_required ?? 0);
            }

            if ($dto->depositAmount === null && $totalDeposit > 0) {
                $booking->update(['deposit_amount' => $totalDeposit]);
            }

            return $booking->load(['lines.product', 'lines.pool', 'partner']);
        });
    }

    public function confirm(int $bookingId): Booking
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::with('lines')->lockForUpdate()->findOrFail($bookingId);
            if ($booking->status !== BookingStatus::HOLD->value) {
                throw new BookingException('Booking sudah diproses.');
            }

            $booking->update([
                'status' => BookingStatus::CONFIRMED->value,
                'held_until' => null,
            ]);

            $this->dispatchDepositReceivedIfNeeded($booking->fresh());

            BookingConfirmed::dispatch($booking->fresh('lines'));

            return $booking->fresh();
        });
    }

    public function assignInstance(int $bookingLineId, int $resourceInstanceId): BookingLine
    {
        return DB::transaction(function () use ($bookingLineId, $resourceInstanceId) {
            $line = BookingLine::with(['booking', 'resources'])->lockForUpdate()->findOrFail($bookingLineId);
            $booking = $line->booking;

            if (! in_array($booking->status, [BookingStatus::CONFIRMED->value, BookingStatus::CHECKED_IN->value], true)) {
                throw new BookingException('Assign instance hanya bisa setelah booking dikonfirmasi.');
            }

            $instance = ResourceInstance::where('resource_pool_id', $line->resource_pool_id)
                ->where('status', 'active')
                ->findOrFail($resourceInstanceId);

            $freeInstances = $this->availabilityService
                ->findFreeInstances($line->resource_pool_id, $line->start_datetime, $line->end_datetime, 0)
                ->pluck('id');

            if (! $freeInstances->contains($instance->id)) {
                throw new BookingException('Instance sudah dibooking.');
            }

            if ($line->resources()->where('resource_instance_id', $instance->id)->exists()) {
                throw new BookingException('Instance sudah terhubung ke line ini.');
            }

            if ($line->resources()->count() >= $line->qty) {
                throw new BookingException('Jumlah instance melebihi qty pemesanan.');
            }

            $line->resources()->create([
                'resource_instance_id' => $instance->id,
            ]);

            if ($line->qty === 1 && ! $line->resource_instance_id) {
                $line->resource_instance_id = $instance->id;
                $line->save();
            }

            return $line->fresh('resources.resourceInstance');
        });
    }

    public function checkIn(int $bookingId): Booking
    {
        return $this->updateStatus($bookingId, [BookingStatus::CONFIRMED->value], BookingStatus::CHECKED_IN->value);
    }

    public function checkOut(int $bookingId): Booking
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::with('lines')->lockForUpdate()->findOrFail($bookingId);
            if ($booking->status !== BookingStatus::CHECKED_IN->value) {
                throw new BookingException('Booking belum check-in.');
            }

            $nextStatus = $this->allLinesCompleted($booking) ? BookingStatus::COMPLETED->value : BookingStatus::CHECKED_OUT->value;
            $booking->update(['status' => $nextStatus]);

            return $booking;
        });
    }

    public function cancel(int $bookingId, string $reason): Booking
    {
        return DB::transaction(function () use ($bookingId, $reason) {
            $booking = Booking::lockForUpdate()->findOrFail($bookingId);
            if (in_array($booking->status, [BookingStatus::COMPLETED->value, BookingStatus::CANCELED->value], true)) {
                throw new BookingException('Booking sudah tidak dapat dibatalkan.');
            }

            $notes = trim(($booking->notes ? "{$booking->notes}\n" : '').'Canceled: '.$reason);
            $booking->update([
                'status' => BookingStatus::CANCELED->value,
                'notes' => $notes,
            ]);

            $this->dispatchDepositReversedIfNeeded($booking->fresh());

            return $booking->fresh();
        });
    }

    /**
     * On confirm: if the booking captured a deposit and we haven't already
     * recorded the cash receipt, dispatch BOOKING_DEPOSIT_RECEIVED and stamp
     * the booking with the snapshot amount + timestamp.
     *
     * If a CompanyBankAccount is selected, its account_id overrides the cash
     * role mapping for this entry, routing the cash leg to that bank account.
     */
    private function dispatchDepositReceivedIfNeeded(Booking $booking, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        $amount = (float) ($booking->deposit_amount ?? 0);
        if ($amount <= 0 || $booking->deposit_received_at !== null) {
            return;
        }

        $cashAccountId = $this->resolveDepositCashAccountId($booking);

        $payload = new AccountingEventPayload(
            AccountingEventCode::BOOKING_DEPOSIT_RECEIVED,
            $booking->company_id,
            $booking->branch_id,
            'booking',
            $booking->id,
            $booking->booking_number,
            'IDR',
            1.0,
            CarbonImmutable::now(),
            $actor?->getAuthIdentifier(),
        );
        $payload->setLines([
            AccountingEntry::debit('cash', $amount, $cashAccountId ? ['account_id' => $cashAccountId] : []),
            AccountingEntry::credit('customer_deposit', $amount),
        ]);

        $this->accountingEventBus->dispatch($payload);

        $booking->forceFill([
            'deposit_received_amount' => $amount,
            'deposit_received_at' => now(),
        ])->save();
    }

    /**
     * On cancel: if a deposit was received but never applied to an invoice,
     * dispatch the reversal so the cash held against this booking is refunded
     * to the same account it came from.
     */
    private function dispatchDepositReversedIfNeeded(Booking $booking, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        if ($booking->deposit_received_at === null) {
            return;
        }
        if ($booking->deposit_applied_at !== null) {
            return;
        }

        $amount = (float) ($booking->deposit_received_amount ?? 0);
        if ($amount <= 0) {
            return;
        }

        $cashAccountId = $this->resolveDepositCashAccountId($booking);

        $payload = new AccountingEventPayload(
            AccountingEventCode::BOOKING_DEPOSIT_REVERSED,
            $booking->company_id,
            $booking->branch_id,
            'booking',
            $booking->id,
            $booking->booking_number.'-REV',
            'IDR',
            1.0,
            CarbonImmutable::now(),
            $actor?->getAuthIdentifier(),
        );
        $payload->setLines([
            AccountingEntry::debit('customer_deposit', $amount),
            AccountingEntry::credit('cash', $amount, $cashAccountId ? ['account_id' => $cashAccountId] : []),
        ]);

        $this->accountingEventBus->dispatch($payload);

        $booking->forceFill([
            'deposit_received_amount' => 0,
            'deposit_received_at' => null,
        ])->save();
    }

    /**
     * If the booking carries a CompanyBankAccount selection, return its
     * GL account id so it can override the default `cash` role mapping.
     * Returns null for cash-on-hand methods (CASH) or when no bank is selected.
     */
    private function resolveDepositCashAccountId(Booking $booking): ?int
    {
        if (! $booking->deposit_company_bank_account_id) {
            return null;
        }

        $bankAccount = \App\Models\CompanyBankAccount::find($booking->deposit_company_bank_account_id);

        return $bankAccount?->account_id;
    }

    private function updateStatus(int $bookingId, array $allowedStatuses, string $nextStatus): Booking
    {
        return DB::transaction(function () use ($bookingId, $allowedStatuses, $nextStatus) {
            $booking = Booking::lockForUpdate()->findOrFail($bookingId);
            if (! in_array($booking->status, $allowedStatuses, true)) {
                throw new BookingException('Transisi status tidak valid.');
            }

            $booking->update(['status' => $nextStatus]);

            return $booking;
        });
    }

    private function allLinesCompleted(Booking $booking): bool
    {
        $now = now();

        return $booking->lines->every(function (BookingLine $line) use ($now) {
            return $line->end_datetime <= $now;
        });
    }

    private function createBookingLine(Booking $booking, Product $product, BookingLineDTO $dto): BookingLine
    {
        $amount = $this->calculateLineAmount($booking->booking_type, $product, $dto);

        return $booking->lines()->create([
            'product_id' => $dto->productId,
            'product_variant_id' => $dto->productVariantId,
            'resource_pool_id' => $dto->resourcePoolId,
            'start_datetime' => $dto->start,
            'end_datetime' => $dto->end,
            'qty' => $dto->qty,
            'unit_price' => $dto->unitPrice,
            'amount' => $amount,
            'tax_amount' => $dto->taxAmount ?? 0,
            'deposit_required' => $dto->depositRequired ?? 0,
            'occurrence_id' => $dto->occurrenceId,
        ]);
    }

    private function calculateLineAmount(string $bookingType, Product $product, BookingLineDTO $dto): float
    {
        return match ($bookingType) {
            'accommodation' => $this->calculateAccommodationAmount($dto),
            'rental' => $this->calculateRentalAmount($product->rentalPolicy, $dto),
            default => (float) ($dto->unitPrice * $dto->qty),
        };
    }

    private function calculateAccommodationAmount(BookingLineDTO $dto): float
    {
        $nights = max(1, $dto->start->diffInDays($dto->end));

        return (float) ($dto->unitPrice * $dto->qty * $nights);
    }

    private function calculateRentalAmount(?RentalPolicy $policy, BookingLineDTO $dto): float
    {
        $granularity = $policy?->billing_granularity ?? 'hour';
        $minutes = max($dto->end->diffInMinutes($dto->start), 60);

        $units = match ($granularity) {
            'day' => (int) ceil($minutes / (60 * 24)),
            'week' => (int) ceil($minutes / (60 * 24 * 7)),
            'month' => (int) ceil($minutes / (60 * 24 * 30)),
            default => (int) ceil($minutes / 60),
        };

        return (float) ($dto->unitPrice * $dto->qty * $units);
    }

    private function generateBookingNumber(): string
    {
        $prefix = 'BK-'.now()->format('ymd');
        $latest = Booking::where('booking_number', 'like', "{$prefix}-%")
            ->orderByDesc('booking_number')
            ->first();

        $sequence = 1;
        if ($latest) {
            $sequence = (int) Str::afterLast($latest->booking_number, '-') + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    private function loadProducts(Collection $lineCollection): EloquentCollection
    {
        return Product::with('rentalPolicy')
            ->whereIn('id', $lineCollection->pluck('productId'))
            ->get()
            ->keyBy('id');
    }

    private function loadPools(Collection $lineCollection): EloquentCollection
    {
        return ResourcePool::query()
            ->whereIn('id', $lineCollection->pluck('resourcePoolId'))
            ->get()
            ->keyBy('id');
    }
}
