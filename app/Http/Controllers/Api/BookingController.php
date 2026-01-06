<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Services\Booking\BookingService;
use App\Services\Booking\DTO\BookingLineDTO;
use App\Services\Booking\DTO\HoldBookingDTO;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateStore($request);

        try {
            $booking = $this->bookingService->hold($this->makeHoldDto($data));
        } catch (BookingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($booking, 201);
    }

    /**
     * Check pool availability for a given date range.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource_pool_id' => ['required', 'exists:resource_pools,id'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $availabilityService = app(\App\Services\Booking\AvailabilityService::class);

        $result = $availabilityService->searchPoolAvailability(
            (int) $data['resource_pool_id'],
            Carbon::parse($data['start_datetime']),
            Carbon::parse($data['end_datetime']),
            (int) $data['qty']
        );

        return response()->json([
            'available' => $result->availableQty >= $data['qty'],
            'requested_qty' => $data['qty'],
            'available_qty' => $result->availableQty,
            'capacity' => $result->capacity,
            'booked_qty' => $result->bookedQty,
            'blocking_rules' => $result->blockingRules,
        ]);
    }

    /**
     * Get resource pools for a product, optionally filtered by branch.
     */
    public function poolsForProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $query = \App\Models\ResourcePool::query()
            ->where('product_id', $data['product_id'])
            ->where('is_active', true);

        if (!empty($data['branch_id'])) {
            $query->where('branch_id', $data['branch_id']);
        }

        $pools = $query->get(['id', 'name', 'branch_id', 'default_capacity']);

        return response()->json([
            'pools' => $pools,
            'auto_select' => $pools->count() === 1 ? $pools->first()->id : null,
        ]);
    }

    public function confirm(Booking $booking): JsonResponse
    {
        return $this->mutateBooking(fn () => $this->bookingService->confirm($booking->id));
    }

    public function checkIn(Booking $booking): JsonResponse
    {
        return $this->mutateBooking(fn () => $this->bookingService->checkIn($booking->id));
    }

    public function checkOut(Booking $booking): JsonResponse
    {
        return $this->mutateBooking(fn () => $this->bookingService->checkOut($booking->id));
    }

    public function cancel(Booking $booking, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return $this->mutateBooking(fn () => $this->bookingService->cancel($booking->id, $data['reason']));
    }

    public function assignInstance(BookingLine $bookingLine, Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource_instance_id' => ['required', 'exists:resource_instances,id'],
        ]);

        try {
            $line = $this->bookingService->assignInstance($bookingLine->id, (int) $data['resource_instance_id']);
        } catch (BookingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($line);
    }

    private function mutateBooking(callable $callback): JsonResponse
    {
        try {
            $booking = $callback();
        } catch (BookingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($booking);
    }

    private function validateStore(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
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
            'lines.*.end_datetime' => ['required', 'date'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.deposit_required' => ['nullable', 'numeric', 'min:0'],
            'lines.*.occurrence_id' => ['nullable', 'exists:occurrences,id'],
            'lines.*.resource_instance_id' => ['nullable', 'exists:resource_instances,id'],
        ]);
    }

    private function makeHoldDto(array $data): HoldBookingDTO
    {
        return new HoldBookingDTO(
            $data['company_id'] ?? null,
            $data['branch_id'] ?? null,
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
                    $line['occurrence_id'] ?? null,
                    $line['resource_instance_id'] ?? null,
                ),
                $data['lines']
            ),
        );
    }
}

