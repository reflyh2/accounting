<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ResourcePool;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingService;
use App\Services\Booking\DTO\BookingLineDTO;
use App\Services\Booking\DTO\HoldBookingDTO;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly AvailabilityService $availabilityService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('bookings.index_filters', []);
        Session::put('bookings.index_filters', $filters);

        $filterKeys = ['search', 'partner_id', 'status', 'booking_type', 'from_date', 'to_date', 'per_page', 'sort', 'order'];
        $filters = Arr::only($filters, $filterKeys);

        $query = Booking::query()
            ->with([
                'partner:id,name,code',
                'currency:id,code',
                'lines:id,booking_id,start_datetime,end_datetime,amount',
            ]);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(booking_number) like ?', ["%{$search}%"])
                    ->orWhereHas('partner', fn ($pq) => $pq->whereRaw('lower(name) like ?', ["%{$search}%"]));
            });
        }

        if ($partnerIds = Arr::wrap($filters['partner_id'] ?? [])) {
            $query->whereIn('partner_id', array_filter($partnerIds));
        }

        if ($statuses = Arr::wrap($filters['status'] ?? [])) {
            $query->whereIn('status', array_filter($statuses));
        }

        if ($filters['booking_type'] ?? null) {
            $query->where('booking_type', $filters['booking_type']);
        }

        if ($filters['from_date'] ?? null) {
            $query->whereDate('booked_at', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('booked_at', '<=', $filters['to_date']);
        }

        $sort = $filters['sort'] ?? 'booked_at';
        $order = $filters['order'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $bookings = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'partners' => $this->partnerOptions(),
            'statusOptions' => $this->statusOptions(),
            'bookingTypeOptions' => $this->bookingTypeOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Bookings/Create', [
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBooking($request);

        try {
            $dto = $this->makeHoldDto($data);
            $booking = $this->bookingService->hold($dto);
        } catch (BookingException $e) {
            return Redirect::back()->withInput()->with('error', $e->getMessage());
        }

        return Redirect::route('bookings.show', $booking->id)
            ->with('success', 'Booking berhasil dibuat dengan status Hold.');
    }

    public function show(Booking $booking): Response
    {
        $booking->load([
            'partner',
            'currency',
            'lines.product',
            'lines.productVariant',
            'lines.pool',
            'lines.assignedInstance',
            'lines.resources.resourceInstance',
            'creator',
        ]);

        return Inertia::render('Bookings/Show', [
            'booking' => $booking,
            'filters' => Session::get('bookings.index_filters', []),
            'allowedTransitions' => $this->getAllowedTransitions($booking),
        ]);
    }

    public function edit(Booking $booking): Response|RedirectResponse
    {
        if (!in_array($booking->status, [BookingStatus::HOLD->value], true)) {
            return Redirect::route('bookings.show', $booking->id)
                ->with('error', 'Booking hanya dapat diedit dalam status Hold.');
        }

        $booking->load(['lines.product', 'lines.productVariant', 'lines.pool']);

        return Inertia::render('Bookings/Edit', [
            'booking' => $booking,
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        if (!in_array($booking->status, [BookingStatus::HOLD->value], true)) {
            return Redirect::back()->with('error', 'Booking hanya dapat diedit dalam status Hold.');
        }

        $data = $this->validateBooking($request);

        try {
            // Delete old lines and recreate
            $booking->lines()->delete();

            $booking->update([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'partner_id' => $data['partner_id'],
                'currency_id' => $data['currency_id'],
                'booking_type' => $data['booking_type'],
                'held_until' => $data['held_until'] ?? null,
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'source_channel' => $data['source_channel'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $lineData) {
                $booking->lines()->create([
                    'product_id' => $lineData['product_id'],
                    'product_variant_id' => $lineData['product_variant_id'] ?? null,
                    'resource_pool_id' => $lineData['resource_pool_id'],
                    'start_datetime' => Carbon::parse($lineData['start_datetime']),
                    'end_datetime' => Carbon::parse($lineData['end_datetime']),
                    'qty' => $lineData['qty'],
                    'unit_price' => $lineData['unit_price'],
                    'amount' => $lineData['unit_price'] * $lineData['qty'],
                    'tax_amount' => $lineData['tax_amount'] ?? 0,
                    'deposit_required' => $lineData['deposit_required'] ?? 0,
                ]);
            }
        } catch (BookingException $e) {
            return Redirect::back()->withInput()->with('error', $e->getMessage());
        }

        return Redirect::route('bookings.show', $booking->id)
            ->with('success', 'Booking berhasil diperbarui.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        if (!in_array($booking->status, [BookingStatus::HOLD->value, BookingStatus::CANCELED->value], true)) {
            return Redirect::back()->with('error', 'Hanya booking Hold atau Canceled yang dapat dihapus.');
        }

        $booking->lines()->delete();
        $booking->delete();

        return Redirect::route('bookings.index')
            ->with('success', 'Booking berhasil dihapus.');
    }

    public function confirm(Booking $booking): RedirectResponse
    {
        try {
            $this->bookingService->confirm($booking->id);
        } catch (BookingException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::back()->with('success', 'Booking dikonfirmasi.');
    }

    public function checkIn(Booking $booking): RedirectResponse
    {
        try {
            $this->bookingService->checkIn($booking->id);
        } catch (BookingException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::back()->with('success', 'Booking check-in berhasil.');
    }

    public function checkOut(Booking $booking): RedirectResponse
    {
        try {
            $this->bookingService->checkOut($booking->id);
        } catch (BookingException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::back()->with('success', 'Booking check-out berhasil.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->bookingService->cancel($booking->id, $data['reason']);
        } catch (BookingException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::back()->with('success', 'Booking dibatalkan.');
    }

    public function assignInstance(Request $request, \App\Models\BookingLine $bookingLine): RedirectResponse
    {
        $data = $request->validate([
            'resource_instance_id' => ['required', 'exists:resource_instances,id'],
        ]);

        try {
            $this->bookingService->assignInstance($bookingLine->id, (int) $data['resource_instance_id']);
        } catch (BookingException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::back()->with('success', 'Instance berhasil di-assign.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:bookings,id'],
        ]);

        $deletable = Booking::whereIn('id', $data['ids'])
            ->whereIn('status', [BookingStatus::HOLD->value, BookingStatus::CANCELED->value])
            ->get();

        foreach ($deletable as $booking) {
            $booking->lines()->delete();
            $booking->delete();
        }

        $deleted = $deletable->count();
        $skipped = count($data['ids']) - $deleted;

        $message = "{$deleted} booking berhasil dihapus.";
        if ($skipped > 0) {
            $message .= " {$skipped} booking dilewati karena status tidak memenuhi syarat.";
        }

        return Redirect::back()->with('success', $message);
    }

    private function validateBooking(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'partner_id' => ['required', 'exists:partners,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'booking_type' => ['required', 'in:accommodation,rental'],
            'held_until' => ['nullable', 'date'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'source_channel' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.resource_pool_id' => ['required', 'exists:resource_pools,id'],
            'lines.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'lines.*.start_datetime' => ['required', 'date'],
            'lines.*.end_datetime' => ['required', 'date', 'after:lines.*.start_datetime'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.deposit_required' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function makeHoldDto(array $data): HoldBookingDTO
    {
        return new HoldBookingDTO(
            $data['company_id'],
            $data['branch_id'],
            $data['partner_id'],
            $data['currency_id'],
            $data['booking_type'],
            isset($data['held_until']) ? Carbon::parse($data['held_until']) : null,
            $data['deposit_amount'] ?? null,
            $data['source_channel'] ?? null,
            $data['notes'] ?? null,
            array_map(
                fn ($line) => new BookingLineDTO(
                    $line['product_id'],
                    $line['resource_pool_id'],
                    Carbon::parse($line['start_datetime']),
                    Carbon::parse($line['end_datetime']),
                    (int) $line['qty'],
                    (float) $line['unit_price'],
                    $line['product_variant_id'] ?? null,
                    $line['tax_amount'] ?? null,
                    $line['deposit_required'] ?? null,
                ),
                $data['lines']
            ),
        );
    }

    private function getAllowedTransitions(Booking $booking): array
    {
        return match ($booking->status) {
            BookingStatus::HOLD->value => ['confirm', 'cancel'],
            BookingStatus::CONFIRMED->value => ['check_in', 'cancel'],
            BookingStatus::CHECKED_IN->value => ['check_out'],
            BookingStatus::CHECKED_OUT->value => [],
            BookingStatus::COMPLETED->value => [],
            default => [],
        };
    }

    private function partnerOptions(): array
    {
        return Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Partner $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
            ])
            ->toArray();
    }

    private function statusOptions(): array
    {
        return collect(BookingStatus::cases())
            ->map(fn (BookingStatus $status) => [
                'value' => $status->value,
                'label' => ucfirst(str_replace('_', ' ', $status->value)),
            ])
            ->toArray();
    }

    private function bookingTypeOptions(): array
    {
        return [
            ['value' => 'accommodation', 'label' => 'Akomodasi'],
            ['value' => 'rental', 'label' => 'Rental'],
        ];
    }

    private function formOptions(): array
    {
        return [
            'companies' => \App\Models\Company::orderBy('name')->get(['id', 'name']),
            'branches' => \App\Models\Branch::with('branchGroup:id,company_id')
                ->orderBy('name')
                ->get()
                ->map(fn (\App\Models\Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'company_id' => $b->branchGroup?->company_id,
                ]),
            'customers' => Partner::query()
                ->with('companies:id')
                ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
                ->orderBy('name')
                ->get()
                ->map(fn (Partner $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'company_ids' => $p->companies->pluck('id')->all(),
                ]),
            'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
            'channels' => \App\Enums\SalesChannel::options(),
            'products' => Product::query()
                ->whereHas('capabilities', fn ($q) => $q->where('capability', 'bookable'))
                ->with(['variants:id,product_id,sku', 'resourcePools:id,product_id,name,branch_id'])
                ->orderBy('name')
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'variants' => $p->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'sku' => $v->sku,
                    ]),
                    'resource_pools' => $p->resourcePools->map(fn ($rp) => [
                        'id' => $rp->id,
                        'name' => $rp->name,
                        'branch_id' => $rp->branch_id,
                    ]),
                ]),
            'resourcePools' => ResourcePool::with('product:id,name')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (ResourcePool $rp) => [
                    'id' => $rp->id,
                    'name' => $rp->name,
                    'product_id' => $rp->product_id,
                    'product_name' => $rp->product?->name,
                    'branch_id' => $rp->branch_id,
                ]),
            'bookingTypeOptions' => $this->bookingTypeOptions(),
        ];
    }
}
