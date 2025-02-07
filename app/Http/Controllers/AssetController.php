<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Exports\AssetsExport;
use App\Models\AssetCategory;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('assets.index_filters', []);
        Session::put('assets.index_filters', $filters);

        $query = Asset::with('category', 'branch.branchGroup.company');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(serial_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(supplier)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('category', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['category_id'])) {
            $query->whereIn('category_id', $filters['category_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branch', function ($query) use ($filters) {
                $query->whereHas('branchGroup', function ($query) use ($filters) {
                    $query->whereIn('company_id', $filters['company_id']);
                });
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['asset_type'])) {
            $query->whereIn('asset_type', $filters['asset_type']);
        }

        if (!empty($filters['acquisition_type'])) {
            $query->whereIn('acquisition_type', $filters['acquisition_type']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('purchase_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('purchase_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $assets = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        // Load all data independently
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = Branch::with('branchGroup.company')
            ->orderBy('name', 'asc')
            ->get();
        $categories = AssetCategory::orderBy('name', 'asc')->get();

        $assetTypes = [
            ['value' => 'tangible', 'label' => 'Berwujud'],
            ['value' => 'intangible', 'label' => 'Tidak Berwujud'],
        ];

        $acquisitionTypes = [
            ['value' => 'outright_purchase', 'label' => 'Pembelian Langsung'],
            ['value' => 'financed_purchase', 'label' => 'Pembelian Kredit'],
            ['value' => 'fixed_rental', 'label' => 'Sewa Periode Tetap'],
            ['value' => 'periodic_rental', 'label' => 'Sewa Berkala'],
            ['value' => 'casual_rental', 'label' => 'Sewa Sekali Pakai'],
        ];

        $statuses = [
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'inactive', 'label' => 'Tidak Aktif'],
            ['value' => 'maintenance', 'label' => 'Pemeliharaan'],
            ['value' => 'disposed', 'label' => 'Dilepas'],
        ];

        return Inertia::render('Assets/Index', [
            'companies' => $companies,
            'branches' => $branches,
            'assets' => $assets,
            'categories' => $categories,
            'assetTypes' => $assetTypes,
            'acquisitionTypes' => $acquisitionTypes,
            'statuses' => $statuses,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create()
    {
        return Inertia::render('Assets/Create', [
            'companies' => Company::orderBy('name')->get(),
            'branches' => Branch::with('branchGroup.company')
                ->orderBy('name')
                ->get(),
            'categories' => AssetCategory::orderBy('name')
                ->get(),
            'filters' => request()->all(['company', 'branch', 'category', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:asset_categories,id',
                'asset_type' => 'required|in:tangible,intangible',
                'acquisition_type' => 'required|in:outright_purchase,financed_purchase,fixed_rental,periodic_rental,casual_rental',
                'serial_number' => 'nullable|string|max:255',
                'status' => 'required|in:active,inactive,maintenance,disposed',
                'purchase_cost' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|numeric|min:0',
                'purchase_date' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|date',
                'supplier' => 'nullable|string|max:255',
                'warranty_expiry' => 'nullable|date',
                'location' => 'nullable|string|max:255',
                'department' => 'nullable|string|max:255',
                'notes' => 'nullable|string',

                // Depreciation fields (required for outright_purchase and financed_purchase)
                'depreciation_method' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|in:straight-line,declining-balance',
                'useful_life_months' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|integer|min:1',
                'salvage_value' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|numeric|min:0',
                'first_depreciation_date' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|date',

                // Financing fields (required for financed_purchase)
                'down_payment' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
                'financing_amount' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
                'interest_rate' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
                'financing_term_months' => 'required_if:acquisition_type,financed_purchase|nullable|integer|min:1',
                'first_payment_date' => 'required_if:acquisition_type,financed_purchase|nullable|date',

                // Rental fields
                'rental_start_date' => 'required_if:acquisition_type,fixed_rental,periodic_rental|nullable|date',
                'rental_end_date' => 'required_if:acquisition_type,fixed_rental,periodic_rental|nullable|date|after:rental_start_date',
                'rental_amount' => 'required_if:acquisition_type,fixed_rental,periodic_rental|nullable|numeric|min:0',
                'rental_terms' => 'nullable|string',
                'payment_frequency' => 'required_if:acquisition_type,periodic_rental|nullable|in:monthly,quarterly,annually',

                // Revaluation fields
                'revaluation_method' => 'nullable|string|max:255',
                'last_revaluation_date' => 'nullable|date',
                'last_revaluation_amount' => 'nullable|numeric|min:0',
                'revaluation_notes' => 'nullable|string',

                // Impairment fields
                'is_impaired' => 'boolean',
                'impairment_amount' => 'required_if:is_impaired,true|nullable|numeric|min:0',
                'impairment_date' => 'required_if:is_impaired,true|nullable|date',
                'impairment_notes' => 'nullable|string',
            ], [
                'required' => 'Kolom :attribute wajib diisi.',
                'required_if' => 'Kolom :attribute wajib diisi untuk jenis perolehan yang dipilih.',
                'numeric' => 'Kolom :attribute harus berupa angka.',
                'min' => 'Kolom :attribute minimal :min.',
                'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
                'after' => 'Kolom :attribute harus setelah tanggal mulai sewa.',
                'exists' => 'Pilihan :attribute tidak valid.',
                'in' => 'Pilihan :attribute tidak valid.',
                'string' => 'Kolom :attribute harus berupa teks.',
                'max' => 'Kolom :attribute maksimal :max karakter.',
                'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
                'boolean' => 'Kolom :attribute harus berupa nilai boolean.',
            ]);

            DB::beginTransaction();

            $asset = Asset::create($validated);

            if ($asset->acquisition_type === 'outright_purchase' || $asset->acquisition_type === 'financed_purchase') {
                $this->createFinancingPayments($asset);
            }
            else {
                $this->createRentalPayments($asset);
            }

            DB::commit();

            if ($request->input('create_another', false)) {
                return redirect()->route('assets.create')
                    ->with('success', 'Aset berhasil dibuat. Anda dapat membuat aset lainnya.');
            }

            return redirect()->route('assets.show', $asset->id)
                ->with('success', 'Aset berhasil dibuat.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat menyimpan aset: ' . $e->getMessage()])->withInput();
        }
    }

    public function show(Request $request, Asset $asset)
    {
        $filters = Session::get('assets.index_filters', []);
        $asset->load('category', 'branch.branchGroup.company', 'maintenanceRecords', 'transfers', 'disposals');
        
        // Calculate current value based on depreciation or amortization
        $currentValue = $asset->calculateDepreciation();
        
        return Inertia::render('Assets/Show', [
            'asset' => $asset,
            'currentValue' => $currentValue,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, Asset $asset)
    {
        $filters = Session::get('assets.index_filters', []);

        $companyId = $asset->branch->branchGroup->company_id;
        
        if ($request->company_id) {
            $companyId = $request->company_id;
        }
        
        return Inertia::render('Assets/Edit', [
            'asset' => $asset->load(['category', 'branch.branchGroup.company']),
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'categories' => AssetCategory::whereHas('companies', function ($query) use ($companyId) {
                $query->where('companies.id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:asset_categories,id',
                'asset_type' => 'required|in:tangible,intangible',
                'acquisition_type' => 'required|in:outright_purchase,financed_purchase,fixed_rental,periodic_rental,casual_rental',
                'serial_number' => 'nullable|string|max:255',
                'status' => 'required|in:active,inactive,maintenance,disposed',
                'purchase_cost' => 'required_if:acquisition_type,outright_purchase,financed_purchase|numeric|min:0',
                'purchase_date' => 'required_if:acquisition_type,outright_purchase,financed_purchase|date',
                'supplier' => 'nullable|string|max:255',
                'warranty_expiry' => 'nullable|date',
                'location' => 'nullable|string|max:255',
                'department' => 'nullable|string|max:255',
                'notes' => 'nullable|string',

                // Depreciation fields (required for outright_purchase and financed_purchase)
                'depreciation_method' => 'required_if:acquisition_type,outright_purchase,financed_purchase|in:straight-line,declining-balance',
                'useful_life_months' => 'required_if:acquisition_type,outright_purchase,financed_purchase|integer|min:1',
                'salvage_value' => 'required_if:acquisition_type,outright_purchase,financed_purchase|numeric|min:0',
                'first_depreciation_date' => 'required_if:acquisition_type,outright_purchase,financed_purchase|date',

                // Financing fields (required for financed_purchase)
                'down_payment' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
                'financing_amount' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
                'interest_rate' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
                'financing_term_months' => 'required_if:acquisition_type,financed_purchase|nullable|integer|min:1',
                'first_payment_date' => 'required_if:acquisition_type,financed_purchase|nullable|date',

                // Rental fields
                'rental_start_date' => 'required_if:acquisition_type,fixed_rental,periodic_rental,casual_rental|nullable|date',
                'rental_end_date' => 'required_if:acquisition_type,fixed_rental,periodic_rental|nullable|date|after:rental_start_date',
                'rental_amount' => 'required_if:acquisition_type,fixed_rental,periodic_rental,casual_rental|nullable|numeric|min:0',
                'rental_terms' => 'nullable|string',
                'payment_frequency' => 'required_if:acquisition_type,periodic_rental|nullable|in:monthly,quarterly,annually',

                // Revaluation fields
                'revaluation_method' => 'nullable|string|max:255',
                'last_revaluation_date' => 'nullable|date',
                'last_revaluation_amount' => 'nullable|numeric|min:0',
                'revaluation_notes' => 'nullable|string',

                // Impairment fields
                'is_impaired' => 'boolean',
                'impairment_amount' => 'required_if:is_impaired,true|nullable|numeric|min:0',
                'impairment_date' => 'required_if:is_impaired,true|nullable|date',
                'impairment_notes' => 'nullable|string',
            ], [
                'required' => 'Kolom :attribute wajib diisi.',
                'required_if' => 'Kolom :attribute wajib diisi untuk jenis perolehan yang dipilih.',
                'numeric' => 'Kolom :attribute harus berupa angka.',
                'min' => 'Kolom :attribute minimal :min.',
                'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
                'after' => 'Kolom :attribute harus setelah tanggal mulai sewa.',
                'exists' => 'Pilihan :attribute tidak valid.',
                'in' => 'Pilihan :attribute tidak valid.',
                'string' => 'Kolom :attribute harus berupa teks.',
                'max' => 'Kolom :attribute maksimal :max karakter.',
                'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
                'boolean' => 'Kolom :attribute harus berupa nilai boolean.',
            ]);

            DB::beginTransaction();

            // Check if acquisition type changed to outright_purchase
            $wasOutrightPurchase = $asset->acquisition_type === 'outright_purchase';
            $isOutrightPurchase = $validated['acquisition_type'] === 'outright_purchase';
            $wasFinancedPurchase = $asset->acquisition_type === 'financed_purchase';
            $isFinancedPurchase = $validated['acquisition_type'] === 'financed_purchase';

            $asset->update($validated);

            // Handle financing payment for outright purchase
            if ($validated['acquisition_type'] === 'outright_purchase') {
                $this->updateFinancingPayments($asset);
            }
            else {
                $this->updateRentalPayments($asset);
            }

            DB::commit();

            return redirect()->route('assets.show', $asset->id)
                ->with('success', 'Aset berhasil diubah.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat mengubah aset.'])->withInput();
        }
    }

    public function destroy(Asset $asset)
    {
        // Check if asset has any related records
        if ($asset->financingPayments()->exists() || 
            $asset->rentalPayments()->exists() || 
            $asset->transfers()->exists() || 
            $asset->disposals()->exists() ||
            $asset->maintenances()->exists()) {
            return redirect()->back()
                ->with('error', 'Aset tidak dapat dihapus karena memiliki data terkait.');
        }

        $asset->delete();

        return redirect()->route('assets.index')
            ->with('success', 'Aset berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $assets = Asset::whereIn('id', $request->ids)->get();

        foreach ($assets as $asset) {
            if ($asset->financingPayments()->exists() || 
                $asset->rentalPayments()->exists() || 
                $asset->transfers()->exists() || 
                $asset->disposals()->exists() ||
                $asset->maintenances()->exists()) {
                return redirect()->back()
                    ->with('error', 'Aset ' . $asset->name . ' tidak dapat dihapus karena memiliki data terkait.');
            }
        }

        Asset::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('assets.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Assets berhasil dihapus.');
        }
    }

    private function getFilteredAssets(Request $request)
    {
        $filters = $request->all() ?: Session::get('assets.index_filters', []);

        $query = Asset::with('category', 'branch.branchGroup.company');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(serial_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(supplier)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('category', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        // Apply all other filters
        if (!empty($filters['category_id'])) {
            $query->whereIn('category_id', $filters['category_id']);
        }

        if (!empty($filters['asset_type'])) {
            $query->whereIn('asset_type', $filters['asset_type']);
        }

        if (!empty($filters['acquisition_type'])) {
            $query->whereIn('acquisition_type', $filters['acquisition_type']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('purchase_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('purchase_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $assets = $this->getFilteredAssets($request);
        return Excel::download(new AssetsExport($assets), 'assets.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $assets = $this->getFilteredAssets($request);
        return Excel::download(new AssetsExport($assets), 'assets.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $assets = $this->getFilteredAssets($request);
        return Excel::download(new AssetsExport($assets), 'assets.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public function print(Asset $asset)
    {
        return Inertia::render('Assets/Print', [
            'asset' => $asset->load('category', 'branch.branchGroup.company', 'maintenanceRecords', 'lease', 'transfers', 'disposals'),
            'currentValue' => $asset->calculateDepreciation(),
        ]);
    }

    private function createRentalPayments($asset)
    {
        if ($asset->acquisition_type === 'fixed_rental') {
            // For fixed rental, create a single payment for the full amount
            $asset->rentalPayments()->create([
                'period_start' => $asset->rental_start_date,
                'period_end' => $asset->rental_end_date,
                'amount' => $asset->rental_amount,
                'status' => 'pending',
                'notes' => 'Pembayaran sewa periode tetap',
            ]);
        } elseif ($asset->acquisition_type === 'periodic_rental') {
            // For periodic rental, create payments based on frequency
            $startDate = new \DateTime($asset->rental_start_date);
            $endDate = new \DateTime($asset->rental_end_date);
            $currentDate = clone $startDate;

            while ($currentDate <= $endDate) {
                $periodEnd = clone $currentDate;
                
                // Set period end based on payment frequency
                switch ($asset->payment_frequency) {
                    case 'monthly':
                        $periodEnd->modify('+1 month -1 day');
                        break;
                    case 'quarterly':
                        $periodEnd->modify('+3 months -1 day');
                        break;
                    case 'annually':
                        $periodEnd->modify('+1 year -1 day');
                        break;
                }

                // If period end is after rental end date, adjust it
                if ($periodEnd > $endDate) {
                    $periodEnd = clone $endDate;
                }

                // Create payment record
                $asset->rentalPayments()->create([
                    'period_start' => $currentDate->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                    'amount' => $asset->rental_amount,
                    'status' => 'pending',
                    'notes' => 'Pembayaran sewa berkala',
                ]);

                // Move to next period
                $currentDate = clone $periodEnd;
                $currentDate->modify('+1 day');

                // Break if we've passed the end date
                if ($currentDate > $endDate) {
                    break;
                }
            }
        }
    }

    private function updateRentalPayments($asset)
    {
        // Delete existing financing payments that haven't been paid
        $asset->financingPayments()->where('status', 'pending')->delete();

        // Delete existing rental payments that haven't been paid
        $asset->rentalPayments()->where('status', 'pending')->delete();

        // Create new rental payments
        $this->createRentalPayments($asset);
    }

    private function createFinancingPayments($asset)
    {
        // Create financing payment for outright purchase
        if ($asset->acquisition_type === 'outright_purchase') {
            $asset->financingPayments()->create([
                'due_date' => $asset->purchase_date,
                'principal_portion' => $asset->purchase_cost,
                'interest_portion' => 0,
                'amount' => $asset->purchase_cost,
                'status' => 'pending',
                'notes' => 'Pembayaran pembelian langsung',
            ]);
        }
        // Create financing payments for financed purchase
        elseif ($asset->acquisition_type === 'financed_purchase') {
            // Create down payment record first
            $asset->financingPayments()->create([
                'due_date' => $asset->purchase_date,
                'principal_portion' => $asset->down_payment,
                'interest_portion' => 0,
                'amount' => $asset->down_payment,
                'status' => 'pending',
                'notes' => 'Uang muka',
            ]);

            $totalAmount = $asset->financing_amount;
            $interestRate = $asset->interest_rate / 100; // Convert percentage to decimal
            $termMonths = $asset->financing_term_months;
            $firstPaymentDate = new \DateTime($asset->first_payment_date);
            
            // Calculate monthly payment using PMT formula
            $monthlyInterestRate = $interestRate / 12;
            $monthlyPayment = ($totalAmount * $monthlyInterestRate * pow(1 + $monthlyInterestRate, $termMonths)) / 
                             (pow(1 + $monthlyInterestRate, $termMonths) - 1);
            
            $remainingPrincipal = $totalAmount;
            
            for ($i = 0; $i < $termMonths; $i++) {
                // Calculate interest and principal portions
                $interestPortion = $remainingPrincipal * $monthlyInterestRate;
                $principalPortion = $monthlyPayment - $interestPortion;
                
                // Update remaining principal
                $remainingPrincipal -= $principalPortion;
                
                // Calculate due date for this payment
                $dueDate = clone $firstPaymentDate;
                $dueDate->modify("+{$i} months");
                
                $asset->financingPayments()->create([
                    'due_date' => $dueDate->format('Y-m-d'),
                    'principal_portion' => round($principalPortion, 2),
                    'interest_portion' => round($interestPortion, 2),
                    'amount' => round($monthlyPayment, 2),
                    'status' => 'pending',
                    'notes' => 'Pembayaran cicilan ke-' . ($i + 1),
                ]);
            }
        }
    }

    private function updateFinancingPayments($asset)
    {
        // Delete existing rental payments that haven't been paid
        $asset->rentalPayments()->where('status', 'pending')->delete();

        // Delete existing financing payments that haven't been paid
        $asset->financingPayments()->where('status', 'pending')->delete();

        // Create new financing payments
        $this->createFinancingPayments($asset);
    }
} 