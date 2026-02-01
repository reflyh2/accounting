<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PaymentTerm;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class PaymentTermController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('payment_terms.index_filters', []);
        Session::put('payment_terms.index_filters', $filters);

        $query = PaymentTerm::with(['company:id,name']);

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(code) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(name) like ?', ["%{$search}%"]);
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active'] === 'true' || $filters['is_active'] === '1');
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $sort = $filters['sort'] ?? 'name';
        $order = $filters['order'] ?? 'asc';

        $paymentTerms = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Settings/PaymentTerms/Index', [
            'paymentTerms' => $paymentTerms,
            'filters' => $filters,
            'perPage' => $perPage,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Settings/PaymentTerms/Create', [
            'filters' => Session::get('payment_terms.index_filters', []),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|max:20|unique:payment_terms,code,NULL,id,company_id,'.$request->company_id,
            'name' => 'required|string|max:100',
            'days' => 'required|integer|min:0|max:999',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $paymentTerm = PaymentTerm::create($data);

        if ($request->boolean('create_another')) {
            return Redirect::route('settings.payment-terms.create')
                ->with('success', 'Payment Term berhasil dibuat.');
        }

        return Redirect::route('settings.payment-terms.show', $paymentTerm)
            ->with('success', 'Payment Term berhasil dibuat.');
    }

    public function show(PaymentTerm $paymentTerm): Response
    {
        $paymentTerm->load(['company']);

        return Inertia::render('Settings/PaymentTerms/Show', [
            'paymentTerm' => $paymentTerm,
            'filters' => Session::get('payment_terms.index_filters', []),
        ]);
    }

    public function edit(PaymentTerm $paymentTerm): Response
    {
        $paymentTerm->load(['company']);

        return Inertia::render('Settings/PaymentTerms/Edit', [
            'paymentTerm' => $paymentTerm,
            'filters' => Session::get('payment_terms.index_filters', []),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, PaymentTerm $paymentTerm): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|max:20|unique:payment_terms,code,'.$paymentTerm->id.',id,company_id,'.$request->company_id,
            'name' => 'required|string|max:100',
            'days' => 'required|integer|min:0|max:999',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $paymentTerm->update($data);

        return Redirect::route('settings.payment-terms.show', $paymentTerm)
            ->with('success', 'Payment Term berhasil diperbarui.');
    }

    public function destroy(PaymentTerm $paymentTerm): RedirectResponse
    {
        if (SalesOrder::where('payment_term_id', $paymentTerm->id)->exists()) {
            return Redirect::back()
                ->with('error', 'Payment Term tidak dapat dihapus karena sudah digunakan.');
        }

        $paymentTerm->delete();

        return Redirect::route('settings.payment-terms.index')
            ->with('success', 'Payment Term berhasil dihapus.');
    }

    private function formOptions(): array
    {
        return [
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ];
    }
}
