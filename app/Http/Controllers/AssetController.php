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
use App\Http\Requests\AssetRequest;

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

    public function store(AssetRequest $request)
    {
        try {
            DB::beginTransaction();

            $asset = Asset::create($request->validated());

            if ($asset->acquisition_type === 'outright_purchase' || $asset->acquisition_type === 'financed_purchase') {
                $this->createFinancingPayments($asset);
                $this->createDepreciationEntries($asset);
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

    public function update(AssetRequest $request, Asset $asset)
    {
        try {
            DB::beginTransaction();

            // Check if acquisition type changed
            $wasOutrightPurchase = $asset->acquisition_type === 'outright_purchase';
            $isOutrightPurchase = $request->validated()['acquisition_type'] === 'outright_purchase';
            $wasFinancedPurchase = $asset->acquisition_type === 'financed_purchase';
            $isFinancedPurchase = $request->validated()['acquisition_type'] === 'financed_purchase';

            // Check for paid payments
            $hasPaidFinancingPayments = $asset->financingPayments()->where('status', 'paid')->exists();
            $hasPaidRentalPayments = $asset->rentalPayments()->where('status', 'paid')->exists();
            $hasDepreciationEntries = $asset->depreciationEntries()->where('status', 'processed')->exists();

            // Define fields that can't be changed if there are paid payments
            $financingFields = [
                'acquisition_type',
                'purchase_cost',
                'down_payment',
                'financing_amount',
                'interest_rate',
                'financing_term_months',
                'first_payment_date',
            ];

            $rentalFields = [
                'acquisition_type',
                'rental_start_date',
                'rental_end_date',
                'rental_amount',
                'payment_frequency',
                'amortization_term_months',
                'first_amortization_date',
            ];

            // Define fields that can't be changed if there are depreciation entries
            $depreciationFields = [
                'acquisition_type',
                'purchase_cost',
                'useful_life_months',
                'first_depreciation_date',
                'depreciation_method',
                'salvage_value',
            ];

            // Check if any financing fields are being changed while having paid payments
            if ($hasPaidFinancingPayments && 
                in_array($asset->acquisition_type, ['outright_purchase', 'financed_purchase'])) {
                foreach ($financingFields as $field) {
                    if (isset($request->validated()[$field]) && $request->validated()[$field] != $asset->$field) {
                        return back()
                            ->with(['error' => 'Tidak dapat mengubah informasi pembiayaan karena sudah ada pembayaran yang dilakukan.'])
                            ->withInput();
                    }
                }
            }

            // Check if any rental fields are being changed while having paid payments
            if ($hasPaidRentalPayments && 
                in_array($asset->acquisition_type, ['fixed_rental', 'periodic_rental'])) {
                foreach ($rentalFields as $field) {
                    if (isset($request->validated()[$field]) && $request->validated()[$field] != $asset->$field) {
                        return back()
                            ->with(['error' => 'Tidak dapat mengubah informasi sewa karena sudah ada pembayaran yang dilakukan.'])
                            ->withInput();
                    }
                }
            }

            // Check if any depreciation fields are being changed while having depreciation entries
            if ($hasDepreciationEntries && 
                in_array($asset->acquisition_type, ['outright_purchase', 'financed_purchase'])) {
                foreach ($depreciationFields as $field) {
                    if (isset($request->validated()[$field]) && $request->validated()[$field] != $asset->$field) {
                        return back()
                            ->with(['error' => 'Tidak dapat mengubah informasi penyusutan karena sudah ada penyusutan yang diproses.'])
                            ->withInput();
                    }
                }
            }

            // If we get here, either there are no paid payments or no restricted fields are being changed
            $asset->update($request->validated());

            // Handle financing payment and depreciation entries for outright purchase and financed purchase
            if (in_array($asset->acquisition_type, ['outright_purchase', 'financed_purchase'])) {
                $this->updateFinancingPayments($asset);
                $this->updateDepreciationEntries($asset);
            } else {
                $this->updateRentalPayments($asset);
            }

            DB::commit();

            return redirect()->route('assets.edit', $asset->id)
                ->with('success', 'Aset berhasil diubah.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat memperbarui aset: ' . $e->getMessage()])->withInput();
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

    private function createDepreciationEntries($asset)
    {
        // Only create depreciation entries for depreciable assets
        if (!in_array($asset->acquisition_type, ['outright_purchase', 'financed_purchase']) || 
            !$asset->first_depreciation_date ||
            !$asset->useful_life_months ||
            $asset->useful_life_months <= 0) {
            return;
        }

        // Calculate depreciable amount
        $depreciableAmount = $asset->purchase_cost - ($asset->salvage_value ?? 0);
        
        // Skip if there's nothing to depreciate
        if ($depreciableAmount <= 0) {
            return;
        }
        
        // Calculate monthly depreciation amount
        $monthlyAmount = 0;
        if ($asset->depreciation_method === 'straight-line') {
            $monthlyAmount = $depreciableAmount / $asset->useful_life_months;
        } else {
            // Declining balance method - first month
            $rate = (2 / $asset->useful_life_months);
            $monthlyAmount = $depreciableAmount * $rate;
        }
        
        // Set first entry date
        $firstEntryDate = new \DateTime($asset->first_depreciation_date);
        
        // Set purchase date as period start
        $periodStart = new \DateTime($asset->purchase_date);
        
        // Calculate first period end (one month after purchase date)
        $periodEnd = clone $periodStart;
        $periodEnd->modify('+1 month -1 day');
        
        // Create entries for the useful life
        $remainingValue = $asset->purchase_cost;
        $cumulativeAmount = 0;
        
        for ($i = 0; $i < $asset->useful_life_months; $i++) {
            // Calculate entry date
            $entryDate = clone $firstEntryDate;
            
            if ($i > 0) {
                // For subsequent entries, add months to the first entry date
                // But handle the date calculation carefully to avoid skipping months
                $targetMonth = (int)$firstEntryDate->format('m') + $i;
                $targetYear = (int)$firstEntryDate->format('Y') + floor(($targetMonth - 1) / 12);
                $targetMonth = (($targetMonth - 1) % 12) + 1;
                
                // Get the last day of target month
                $lastDayOfMonth = (new \DateTime())->setDate($targetYear, $targetMonth, 1)
                    ->modify('last day of this month')
                    ->format('d');
                
                // Use the minimum between original day and last day of target month
                $targetDay = min((int)$firstEntryDate->format('d'), (int)$lastDayOfMonth);
                
                $entryDate->setDate($targetYear, $targetMonth, $targetDay);
                
                // Set period dates for subsequent entries
                $periodStart = clone $periodEnd;
                $periodStart->modify('+1 day');
                
                $periodEnd = clone $periodStart;
                $periodEnd->modify('+1 month -1 day');
            }
            
            // Calculate amount for declining balance method
            if ($asset->depreciation_method === 'declining-balance' && $i > 0) {
                $rate = (2 / $asset->useful_life_months);
                $monthlyAmount = ($remainingValue - ($asset->salvage_value ?? 0)) * $rate;
            }
            
            // Ensure we don't depreciate below salvage value
            if ($remainingValue - $monthlyAmount < ($asset->salvage_value ?? 0)) {
                $monthlyAmount = $remainingValue - ($asset->salvage_value ?? 0);
            }
            
            // If monthly amount becomes zero or negative, stop creating entries
            if ($monthlyAmount <= 0) {
                break;
            }
            
            // Update running totals
            $cumulativeAmount += $monthlyAmount;
            $remainingValue -= $monthlyAmount;
            
            // Create the depreciation entry
            $asset->depreciationEntries()->create([
                'entry_date' => $entryDate->format('Y-m-d'),
                'type' => $asset->asset_type === 'tangible' ? 'depreciation' : 'amortization',
                'status' => 'scheduled',
                'amount' => round($monthlyAmount, 2),
                'cumulative_amount' => round($cumulativeAmount, 2),
                'remaining_value' => round($remainingValue, 2),
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'notes' => 'Jadwal ' . ($asset->asset_type === 'tangible' ? 'penyusutan' : 'amortisasi') . ' otomatis bulan ke-' . ($i + 1),
            ]);
            
            // If we've depreciated to salvage value, stop creating entries
            if (abs($remainingValue - ($asset->salvage_value ?? 0)) < 0.01) {
                break;
            }
        }
    }
    
    private function updateDepreciationEntries($asset)
    {
        // Delete existing scheduled depreciation entries
        $asset->depreciationEntries()->where('status', 'scheduled')->delete();
        
        // Create new depreciation entries
        $this->createDepreciationEntries($asset);
    }
} 