<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingAllocationException;
use App\Models\BookingAllocationRun;
use App\Models\CostPool;
use App\Services\Booking\Allocation\BookingAllocationOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class BookingAllocationController extends Controller
{
    public function __construct(
        private readonly BookingAllocationOrchestrator $orchestrator,
    ) {}

    public function index(Request $request): Response
    {
        $filters = Arr::only($request->all(), ['cost_pool_id', 'status', 'period', 'per_page']);

        $runs = BookingAllocationRun::query()
            ->with(['costPool:id,name,asset_id', 'asset:id,name', 'company:id,name'])
            ->when($filters['cost_pool_id'] ?? null, fn ($q, $v) => $q->where('cost_pool_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('period_start')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        return Inertia::render('Bookings/Allocation/Index', [
            'runs' => $runs,
            'filters' => $filters,
            'pools' => CostPool::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'asset_id', 'company_id'])
                ->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'cost_pool_id' => ['required', 'exists:cost_pools,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $run = $this->orchestrator->run(
                (int) $data['company_id'],
                (int) $data['cost_pool_id'],
                CarbonImmutable::parse($data['period_start']),
                CarbonImmutable::parse($data['period_end']),
            );
        } catch (BookingAllocationException $e) {
            return Redirect::back()->withInput()->with('error', $e->getMessage());
        }

        return Redirect::route('booking-allocations.show', $run->id)
            ->with('success', 'Allocation run berhasil diproses.');
    }

    public function show(BookingAllocationRun $allocation): Response
    {
        $allocation->load([
            'costPool',
            'asset',
            'company',
            'allocations.salesInvoiceLine.invoice:id,invoice_number,invoice_date',
            'allocations.salesInvoiceLine:id,sales_invoice_id,description,line_total_base',
        ]);

        return Inertia::render('Bookings/Allocation/Show', [
            'run' => $allocation,
        ]);
    }

    public function reverse(BookingAllocationRun $allocation): RedirectResponse
    {
        try {
            $this->orchestrator->reverse($allocation);
        } catch (BookingAllocationException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::back()->with('success', 'Allocation run berhasil direverse.');
    }
}
