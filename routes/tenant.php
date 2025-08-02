<?php

declare(strict_types=1);

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CurrencyController;
use Stancl\Tenancy\Features\UserImpersonation;
use App\Http\Controllers\BranchGroupController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\CashBankBookController;
use App\Http\Controllers\IncomeReportController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CashPaymentJournalController;
use App\Http\Controllers\CashReceiptJournalController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CompanyDefaultAccountsController;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\AssetCategoryController;
use App\Http\Controllers\AssetPurchaseController;
use App\Http\Controllers\AssetRentalController;
use App\Http\Controllers\AssetInvoicePaymentController;
use App\Http\Controllers\PartnerBankAccountController;
use App\Http\Controllers\AssetSalesController;
use App\Http\Controllers\AssetFinancingAgreementController;
use App\Http\Controllers\AssetFinancingScheduleController;
use App\Http\Controllers\AssetFinancingPaymentController;
use App\Http\Controllers\AssetTransferController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/impersonate/{token}', function ($token) {
        return UserImpersonation::makeResponse($token);
    })->name('impersonate');

    Route::middleware('auth')->group(function() {
        Route::get('verify-email', EmailVerificationPromptController::class)
                ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
                    ->middleware(['signed', 'throttle:6,1'])
                    ->name('verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                    ->middleware('throttle:6,1')
                    ->name('verification.send');

        Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
                    ->name('password.confirm');

        Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

        Route::put('password', [PasswordController::class, 'update'])->name('password.update');

        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                    ->name('logout');

        Route::get('api/branches-by-company/{companyId}', [ApiController::class, 'getBranchesByCompany'])->name('api.branches-by-company');
        Route::get('api/accounts-by-branch/{branchId}', [ApiController::class, 'getAccountsByBranch'])->name('api.accounts-by-branch');       
        Route::get('api/financing-schedule', [ApiController::class, 'getFinancingSchedule'])->name('api.financing-schedule');
        Route::get('api/partners', [ApiController::class, 'getPartners'])->name('api.partners');
        Route::get('api/partners/{partner}', [ApiController::class, 'getPartner'])->name('api.partners.show');

        Route::get('/', function () {
            return redirect(route('dashboard'));
        });
    
        Route::get('/dashboard', function () {
            return Inertia::render('Dashboard');
        })->name('dashboard');
        
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        
        Route::get('/companies/{company}/default-accounts', [CompanyDefaultAccountsController::class, 'edit'])->name('companies.default-accounts.edit');
        Route::put('/companies/{company}/default-accounts', [CompanyDefaultAccountsController::class, 'update'])->name('companies.default-accounts.update');
        Route::delete('/companies/bulk-delete', [CompanyController::class, 'bulkDelete'])->name('companies.bulk-delete');
        Route::get('companies/export-xlsx', [CompanyController::class, 'exportXLSX'])->name('companies.export-xlsx');
        Route::get('companies/export-csv', [CompanyController::class, 'exportCSV'])->name('companies.export-csv');
        Route::get('companies/export-pdf', [CompanyController::class, 'exportPDF'])->name('companies.export-pdf');
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');

        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::delete('/branches/bulk-delete', [BranchController::class, 'bulkDelete'])->name('branches.bulk-delete');
        Route::get('/branches/export-xlsx', [BranchController::class, 'exportXLSX'])->name('branches.export-xlsx');
        Route::get('/branches/export-csv', [BranchController::class, 'exportCSV'])->name('branches.export-csv');
        Route::get('/branches/export-pdf', [BranchController::class, 'exportPDF'])->name('branches.export-pdf');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
        Route::get('/branches/{branch}', [BranchController::class, 'show'])->name('branches.show');

        Route::get('/branch-groups', [BranchGroupController::class, 'index'])->name('branch-groups.index');
        Route::delete('/branch-groups/bulk-delete', [BranchGroupController::class, 'bulkDelete'])->name('branch-groups.bulk-delete');
        Route::get('/branch-groups/export-xlsx', [BranchGroupController::class, 'exportXLSX'])->name('branch-groups.export-xlsx');
        Route::get('/branch-groups/export-csv', [BranchGroupController::class, 'exportCSV'])->name('branch-groups.export-csv');
        Route::get('/branch-groups/export-pdf', [BranchGroupController::class, 'exportPDF'])->name('branch-groups.export-pdf');
        Route::get('/branch-groups/create', [BranchGroupController::class, 'create'])->name('branch-groups.create');
        Route::post('/branch-groups', [BranchGroupController::class, 'store'])->name('branch-groups.store');
        Route::get('/branch-groups/{branchGroup}/edit', [BranchGroupController::class, 'edit'])->name('branch-groups.edit');
        Route::put('/branch-groups/{branchGroup}', [BranchGroupController::class, 'update'])->name('branch-groups.update');
        Route::delete('/branch-groups/{branchGroup}', [BranchGroupController::class, 'destroy'])->name('branch-groups.destroy');
        Route::get('/branch-groups/{branchGroup}', [BranchGroupController::class, 'show'])->name('branch-groups.show');

        Route::delete('/roles/bulk-delete', [RoleController::class, 'bulkDelete'])->name('roles.bulk-delete');
        Route::get('roles/export-xlsx', [RoleController::class, 'exportXLSX'])->name('roles.export-xlsx');
        Route::get('roles/export-csv', [RoleController::class, 'exportCSV'])->name('roles.export-csv');
        Route::get('roles/export-pdf', [RoleController::class, 'exportPDF'])->name('roles.export-pdf');
        Route::resource('roles', RoleController::class);
        Route::get('roles/{role}/permissions', [RoleController::class, 'editPermissions'])->name('roles.permissions');
        Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.update-permissions');

        Route::delete('/users/bulk-delete', [UserController::class, 'bulkDelete'])->name('users.bulk-delete');
        Route::get('users/export-xlsx', [UserController::class, 'exportXLSX'])->name('users.export-xlsx');
        Route::get('users/export-csv', [UserController::class, 'exportCSV'])->name('users.export-csv');
        Route::get('users/export-pdf', [UserController::class, 'exportPDF'])->name('users.export-pdf');
        Route::resource('users', UserController::class);

        
        Route::delete('accounts/bulk-delete', [AccountController::class, 'bulkDelete'])->name('accounts.bulk-delete');
        Route::get('accounts/export-xlsx', [AccountController::class, 'exportXLSX'])->name('accounts.export-xlsx');
        Route::get('accounts/export-csv', [AccountController::class, 'exportCSV'])->name('accounts.export-csv');
        Route::get('accounts/export-pdf', [AccountController::class, 'exportPDF'])->name('accounts.export-pdf');
        Route::resource('accounts', AccountController::class);

        Route::delete('currencies/bulk-delete', [CurrencyController::class, 'bulkDelete'])->name('currencies.bulk-delete');
        Route::get('currencies/export-xlsx', [CurrencyController::class, 'exportXLSX'])->name('currencies.export-xlsx');
        Route::get('currencies/export-csv', [CurrencyController::class, 'exportCSV'])->name('currencies.export-csv');
        Route::get('currencies/export-pdf', [CurrencyController::class, 'exportPDF'])->name('currencies.export-pdf');
        Route::resource('currencies', CurrencyController::class);

        Route::delete('journals/bulk-delete', [JournalController::class, 'bulkDelete'])->name('journals.bulk-delete');
        Route::get('journals/export-xlsx', [JournalController::class, 'exportXLSX'])->name('journals.export-xlsx');
        Route::get('journals/export-csv', [JournalController::class, 'exportCSV'])->name('journals.export-csv');
        Route::get('journals/export-pdf', [JournalController::class, 'exportPDF'])->name('journals.export-pdf');
        Route::get('journals/{journal}/print', [JournalController::class, 'print'])->name('journals.print');
        Route::resource('journals', JournalController::class);

        // Partners Routes
        Route::delete('partners/bulk-delete', [PartnerController::class, 'bulkDelete'])->name('partners.bulk-delete');
        Route::get('partners/export-xlsx', [PartnerController::class, 'exportXLSX'])->name('partners.export-xlsx');
        Route::get('partners/export-csv', [PartnerController::class, 'exportCSV'])->name('partners.export-csv');
        Route::get('partners/export-pdf', [PartnerController::class, 'exportPDF'])->name('partners.export-pdf');
        Route::resource('partners', PartnerController::class);

        Route::delete('cash-receipt-journals/bulk-delete', [CashReceiptJournalController::class, 'bulkDelete'])->name('cash-receipt-journals.bulk-delete');
        Route::get('cash-receipt-journals/export-xlsx', [CashReceiptJournalController::class, 'exportXLSX'])->name('cash-receipt-journals.export-xlsx');
        Route::get('cash-receipt-journals/export-csv', [CashReceiptJournalController::class, 'exportCSV'])->name('cash-receipt-journals.export-csv');
        Route::get('cash-receipt-journals/export-pdf', [CashReceiptJournalController::class, 'exportPDF'])->name('cash-receipt-journals.export-pdf');
        Route::get('cash-receipt-journals/{journalId}/print', [CashReceiptJournalController::class, 'print'])->name('cash-receipt-journals.print');
        Route::resource('cash-receipt-journals', CashReceiptJournalController::class);

        Route::delete('cash-payment-journals/bulk-delete', [CashPaymentJournalController::class, 'bulkDelete'])->name('cash-payment-journals.bulk-delete');
        Route::get('cash-payment-journals/export-xlsx', [CashPaymentJournalController::class, 'exportXLSX'])->name('cash-payment-journals.export-xlsx');
        Route::get('cash-payment-journals/export-csv', [CashPaymentJournalController::class, 'exportCSV'])->name('cash-payment-journals.export-csv');
        Route::get('cash-payment-journals/export-pdf', [CashPaymentJournalController::class, 'exportPDF'])->name('cash-payment-journals.export-pdf');
        Route::get('cash-payment-journals/{journalId}/print', [CashPaymentJournalController::class, 'print'])->name('cash-payment-journals.print');
        Route::resource('cash-payment-journals', CashPaymentJournalController::class);

        Route::get('general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
        Route::get('general-ledger/download', [GeneralLedgerController::class, 'download'])->name('general-ledger.download');

        Route::get('cash-bank-book', [CashBankBookController::class, 'index'])->name('cash-bank-book.index');
        Route::get('cash-bank-book/download', [CashBankBookController::class, 'download'])->name('cash-bank-book.download');

        Route::get('income', [IncomeReportController::class, 'index'])->name('income.index');
        Route::get('income/download', [IncomeReportController::class, 'download'])->name('income.download');

        Route::get('balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
        Route::get('balance-sheet/download', [BalanceSheetController::class, 'download'])->name('balance-sheet.download');

        Route::delete('/asset-categories/bulk-delete', [AssetCategoryController::class, 'bulkDelete'])->name('asset-categories.bulk-delete');
        Route::get('asset-categories/export-xlsx', [AssetCategoryController::class, 'exportXLSX'])->name('asset-categories.export-xlsx');
        Route::get('asset-categories/export-csv', [AssetCategoryController::class, 'exportCSV'])->name('asset-categories.export-csv');
        Route::get('asset-categories/export-pdf', [AssetCategoryController::class, 'exportPDF'])->name('asset-categories.export-pdf');
        Route::resource('asset-categories', AssetCategoryController::class);
        
        // Assets Routes
        Route::delete('/assets/bulk-delete', [AssetController::class, 'bulkDelete'])->name('assets.bulk-delete');
        Route::get('assets/export-xlsx', [AssetController::class, 'exportXLSX'])->name('assets.export-xlsx');
        Route::get('assets/export-csv', [AssetController::class, 'exportCSV'])->name('assets.export-csv');
        Route::get('assets/export-pdf', [AssetController::class, 'exportPDF'])->name('assets.export-pdf');
        Route::get('assets/{asset}/print', [AssetController::class, 'print'])->name('assets.print');
        Route::post('assets/ajax-store', [AssetController::class, 'ajaxStore'])->name('assets.ajax-store');
        Route::patch('assets/{asset}/update-cost-basis', [AssetController::class, 'updateCostBasis'])->name('assets.update-cost-basis');
        Route::resource('assets', AssetController::class);

        // Asset Purchases Routes (Added)
        Route::delete('asset-purchases/bulk-delete', [AssetPurchaseController::class, 'bulkDelete'])->name('asset-purchases.bulk-delete');
        Route::get('asset-purchases/export-xlsx', [AssetPurchaseController::class, 'exportXLSX'])->name('asset-purchases.export-xlsx');
        Route::get('asset-purchases/export-csv', [AssetPurchaseController::class, 'exportCSV'])->name('asset-purchases.export-csv');
        Route::get('asset-purchases/export-pdf', [AssetPurchaseController::class, 'exportPDF'])->name('asset-purchases.export-pdf');
        Route::get('asset-purchases/{assetPurchase}/print', [AssetPurchaseController::class, 'print'])->name('asset-purchases.print');
        Route::resource('asset-purchases', AssetPurchaseController::class);

        // Asset Rentals Routes
        Route::delete('asset-rentals/bulk-delete', [AssetRentalController::class, 'bulkDelete'])->name('asset-rentals.bulk-delete');
        Route::get('asset-rentals/export-xlsx', [AssetRentalController::class, 'exportXLSX'])->name('asset-rentals.export-xlsx');
        Route::get('asset-rentals/export-csv', [AssetRentalController::class, 'exportCSV'])->name('asset-rentals.export-csv');
        Route::get('asset-rentals/export-pdf', [AssetRentalController::class, 'exportPDF'])->name('asset-rentals.export-pdf');
        Route::get('asset-rentals/{assetRental}/print', [AssetRentalController::class, 'print'])->name('asset-rentals.print');
        Route::resource('asset-rentals', AssetRentalController::class);

        // Asset Sales Routes
        Route::delete('asset-sales/bulk-delete', [AssetSalesController::class, 'bulkDelete'])->name('asset-sales.bulk-delete');
        Route::get('asset-sales/export-xlsx', [AssetSalesController::class, 'exportXLSX'])->name('asset-sales.export-xlsx');
        Route::get('asset-sales/export-csv', [AssetSalesController::class, 'exportCSV'])->name('asset-sales.export-csv');
        Route::get('asset-sales/export-pdf', [AssetSalesController::class, 'exportPDF'])->name('asset-sales.export-pdf');
        Route::get('asset-sales/{assetSale}/print', [AssetSalesController::class, 'print'])->name('asset-sales.print');
        Route::resource('asset-sales', AssetSalesController::class);

        // Asset Invoice Payments Routes
        Route::delete('asset-invoice-payments/bulk-delete', [AssetInvoicePaymentController::class, 'bulkDelete'])->name('asset-invoice-payments.bulk-delete');
        Route::get('asset-invoice-payments/export-xlsx', [AssetInvoicePaymentController::class, 'exportXLSX'])->name('asset-invoice-payments.export-xlsx');
        Route::get('asset-invoice-payments/export-csv', [AssetInvoicePaymentController::class, 'exportCSV'])->name('asset-invoice-payments.export-csv');
        Route::get('asset-invoice-payments/export-pdf', [AssetInvoicePaymentController::class, 'exportPDF'])->name('asset-invoice-payments.export-pdf');
        Route::get('asset-invoice-payments/{assetInvoicePayment}/print', [AssetInvoicePaymentController::class, 'print'])->name('asset-invoice-payments.print');
        Route::resource('asset-invoice-payments', AssetInvoicePaymentController::class);

        // Asset Financing Agreements Routes
        Route::delete('asset-financing-agreements/bulk-delete', [AssetFinancingAgreementController::class, 'bulkDelete'])->name('asset-financing-agreements.bulk-delete');
        Route::get('asset-financing-agreements/export-xlsx', [AssetFinancingAgreementController::class, 'exportXLSX'])->name('asset-financing-agreements.export-xlsx');
        Route::get('asset-financing-agreements/export-csv', [AssetFinancingAgreementController::class, 'exportCSV'])->name('asset-financing-agreements.export-csv');
        Route::get('asset-financing-agreements/export-pdf', [AssetFinancingAgreementController::class, 'exportPDF'])->name('asset-financing-agreements.export-pdf');
        Route::get('asset-financing-agreements/{assetFinancingAgreement}/print', [AssetFinancingAgreementController::class, 'print'])->name('asset-financing-agreements.print');
        Route::resource('asset-financing-agreements', AssetFinancingAgreementController::class);

        // Asset Financing Payments Routes
        Route::delete('asset-financing-payments/bulk-delete', [AssetFinancingPaymentController::class, 'bulkDelete'])->name('asset-financing-payments.bulk-delete');
        Route::get('asset-financing-payments/export-xlsx', [AssetFinancingPaymentController::class, 'exportXLSX'])->name('asset-financing-payments.export-xlsx');
        Route::get('asset-financing-payments/export-csv', [AssetFinancingPaymentController::class, 'exportCSV'])->name('asset-financing-payments.export-csv');
        Route::get('asset-financing-payments/export-pdf', [AssetFinancingPaymentController::class, 'exportPDF'])->name('asset-financing-payments.export-pdf');
        Route::get('asset-financing-payments/{assetFinancingPayment}/print', [AssetFinancingPaymentController::class, 'print'])->name('asset-financing-payments.print');
        Route::resource('asset-financing-payments', AssetFinancingPaymentController::class);

        Route::put('asset-financing-schedules/{schedule}/pay', [AssetFinancingScheduleController::class, 'pay'])->name('asset-financing-schedules.pay');

        // Asset Transfers Routes
        Route::delete('asset-transfers/bulk-delete', [AssetTransferController::class, 'bulkDelete'])->name('asset-transfers.bulk-delete');
        Route::get('asset-transfers/export-xlsx', [AssetTransferController::class, 'exportXLSX'])->name('asset-transfers.export-xlsx');
        Route::get('asset-transfers/export-csv', [AssetTransferController::class, 'exportCSV'])->name('asset-transfers.export-csv');
        Route::get('asset-transfers/export-pdf', [AssetTransferController::class, 'exportPDF'])->name('asset-transfers.export-pdf');
        Route::put('asset-transfers/{assetTransfer}/approve', [AssetTransferController::class, 'approve'])->name('asset-transfers.approve');
        Route::put('asset-transfers/{assetTransfer}/reject', [AssetTransferController::class, 'reject'])->name('asset-transfers.reject');
        Route::put('asset-transfers/{assetTransfer}/cancel', [AssetTransferController::class, 'cancel'])->name('asset-transfers.cancel');
        Route::get('asset-transfers/{assetTransfer}/print', [AssetTransferController::class, 'print'])->name('asset-transfers.print');
        Route::resource('asset-transfers', AssetTransferController::class);

        // Partner Bank Account Routes
        Route::post('partner-bank-accounts', [PartnerBankAccountController::class, 'store'])->name('partner-bank-accounts.store');
        Route::put('partner-bank-accounts/{partnerBankAccount}', [PartnerBankAccountController::class, 'update'])->name('partner-bank-accounts.update');
        Route::delete('partner-bank-accounts/{partnerBankAccount}', [PartnerBankAccountController::class, 'destroy'])->name('partner-bank-accounts.destroy');
        Route::get('partners/{partner}/bank-accounts', [PartnerBankAccountController::class, 'getByPartner'])->name('partners.bank-accounts');
    });

    Route::middleware('guest')->group(function () {
        Route::get('register', [RegisteredUserController::class, 'create'])
                ->name('register');

        Route::post('register', [RegisteredUserController::class, 'store']);

        Route::get('login', [AuthenticatedSessionController::class, 'create'])
                    ->name('login');
    
        Route::post('login', [AuthenticatedSessionController::class, 'store']);
    
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
                    ->name('password.request');
    
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                    ->name('password.email');
    
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
                    ->name('password.reset');
    
        Route::post('reset-password', [NewPasswordController::class, 'store'])
                    ->name('password.store');
    });
    
});

