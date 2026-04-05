<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Journal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JournalReportController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $filters = $request->all();

        if (empty($filters['start_date'])) {
            $filters['start_date'] = date('Y-m-01');
        }

        if (empty($filters['end_date'])) {
            $filters['end_date'] = date('Y-m-d');
        }

        $companies = Company::orderBy('name', 'asc')->get();

        $query = Branch::query();
        if (! empty($filters['company_id'])) {
            $query->whereHas('branchGroup', fn ($q) => $q->whereIn('company_id', $filters['company_id']));
        }
        $branches = $query->orderBy('name', 'asc')->get();

        $journalTypes = collect(Journal::journalTypesLabel())
            ->reject(fn ($label, $value) => $value === 'retained_earnings')
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->toArray();

        $journalData = $this->getJournalData($filters);

        return Inertia::render('Reports/JournalReport', [
            'companies' => $companies,
            'branches' => $branches,
            'journalTypes' => $journalTypes,
            'filters' => $filters,
            'journalData' => $journalData,
            'typeLabels' => Journal::journalTypesLabel(),
        ]);
    }

    private function getJournalData(array $filters)
    {
        return Journal::with(['journalEntries.account', 'branch', 'user'])
            ->where('journal_type', '!=', 'retained_earnings')
            ->whereBetween('date', [$filters['start_date'], $filters['end_date']])
            ->when(! empty($filters['journal_type']), fn ($q) => $q->where('journal_type', $filters['journal_type']))
            ->when(! empty($filters['company_id']), fn ($q) => $q->whereHas('branch.branchGroup',
                fn ($sub) => $sub->whereIn('company_id', $filters['company_id'])))
            ->when(! empty($filters['branch_id']), fn ($q) => $q->whereIn('branch_id', $filters['branch_id']))
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }
}
