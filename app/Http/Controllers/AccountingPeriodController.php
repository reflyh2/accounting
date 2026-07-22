<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumentStateException;
use App\Http\Requests\StoreAccountingPeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class AccountingPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('accounting_periods.index_filters', []);
        Session::put('accounting_periods.index_filters', $filters);

        $query = AccountingPeriod::with(['company', 'closedByUser']);

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', '%'.$search.'%')
                    ->orWhere(DB::raw('lower(notes)'), 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'start_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $periods = $query->paginate($perPage)->withQueryString();
        $companies = Company::orderBy('name', 'asc')->get();

        $selectedCompany = ! empty($filters['company_id'])
            ? (is_array($filters['company_id']) ? $filters['company_id'][0] : $filters['company_id'])
            : $companies->first()?->id;

        return Inertia::render('AccountingPeriods/Index', [
            'periods' => $periods,
            'companies' => $companies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'selectedCompanyId' => (int) $selectedCompany,
        ]);
    }

    public function store(StoreAccountingPeriodRequest $request): RedirectResponse
    {
        $month = (int) $request->validated('month');
        $year = (int) $request->validated('year');
        $companyId = (int) $request->validated('company_id');

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        $name = $monthNames[$month].' '.$year;

        try {
            AccountingPeriod::validateSequentialClose($companyId, $startDate);
        } catch (DocumentStateException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        $user = Auth::user();

        AccountingPeriod::create([
            'company_id' => $companyId,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'notes' => $request->validated('notes'),
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_by' => $user?->global_id,
            'closed_at' => now(),
        ]);

        return Redirect::back()->with('success', "Periode akuntansi {$name} berhasil ditutup.");
    }

    public function close(AccountingPeriod $accountingPeriod): RedirectResponse
    {
        try {
            $accountingPeriod->close();
        } catch (DocumentStateException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::back()->with('success', 'Periode akuntansi berhasil ditutup.');
    }

    public function destroy(AccountingPeriod $accountingPeriod): RedirectResponse
    {
        try {
            AccountingPeriod::validateSequentialReopen($accountingPeriod->company_id, $accountingPeriod->start_date);
        } catch (DocumentStateException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        $accountingPeriod->delete();

        return Redirect::back()->with('success', 'Periode akuntansi berhasil dihapus (dibuka kembali).');
    }
}
