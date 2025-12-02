<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\ResourcePool;
use App\Services\Booking\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availabilityService)
    {
    }

    public function pool(ResourcePool $pool, Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->availabilityService->searchPoolAvailability(
                $pool->id,
                Carbon::parse($data['start']),
                Carbon::parse($data['end']),
                (int) ($data['qty'] ?? 1)
            );
        } catch (BookingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => array_merge($result->toArray(), [
                'blocked' => $result->isBlocked(),
                'conflicts' => $result->conflicts->map(function ($line) {
                    return [
                        'booking_id' => $line->booking_id,
                        'booking_number' => $line->booking?->booking_number,
                        'status' => $line->booking?->status,
                        'partner' => $line->booking?->partner?->name,
                        'start' => $line->start_datetime?->toIso8601String(),
                        'end' => $line->end_datetime?->toIso8601String(),
                        'qty' => $line->qty,
                    ];
                }),
            ]),
        ]);
    }

    public function freeInstances(ResourcePool $pool, Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $instances = $this->availabilityService->findFreeInstances(
                $pool->id,
                Carbon::parse($data['start']),
                Carbon::parse($data['end']),
                (int) ($data['qty'] ?? 0)
            );
        } catch (BookingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $instances->map(fn ($instance) => [
                'id' => $instance->id,
                'code' => $instance->code,
                'status' => $instance->status,
            ]),
        ]);
    }
}

