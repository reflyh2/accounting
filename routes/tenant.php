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
use App\Http\Controllers\AssetCategoryController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\AssetMaintenanceController;
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
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\EmployeeController;

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
        Route::resource('companies', CompanyController::class);

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

        Route::delete('assets/bulk-delete', [AssetController::class, 'bulkDelete'])->name('assets.bulk-delete');
        Route::get('assets/export-xlsx', [AssetController::class, 'exportXLSX'])->name('assets.export-xlsx');
        Route::get('assets/export-csv', [AssetController::class, 'exportCSV'])->name('assets.export-csv');
        Route::get('assets/export-pdf', [AssetController::class, 'exportPDF'])->name('assets.export-pdf');
        Route::resource('assets', AssetController::class);

        Route::delete('asset-categories/bulk-delete', [AssetCategoryController::class, 'bulkDelete'])->name('asset-categories.bulk-delete');
        Route::get('asset-categories/export-xlsx', [AssetCategoryController::class, 'exportXLSX'])->name('asset-categories.export-xlsx');
        Route::get('asset-categories/export-csv', [AssetCategoryController::class, 'exportCSV'])->name('asset-categories.export-csv');
        Route::get('asset-categories/export-pdf', [AssetCategoryController::class, 'exportPDF'])->name('asset-categories.export-pdf');
        Route::resource('asset-categories', AssetCategoryController::class);

        Route::delete('asset-maintenance/bulk-delete', [AssetMaintenanceController::class, 'bulkDelete'])->name('asset-maintenance.bulk-delete');
        Route::get('asset-maintenance/export-xlsx', [AssetMaintenanceController::class, 'exportXLSX'])->name('asset-maintenance.export-xlsx');
        Route::get('asset-maintenance/export-csv', [AssetMaintenanceController::class, 'exportCSV'])->name('asset-maintenance.export-csv');
        Route::get('asset-maintenance/export-pdf', [AssetMaintenanceController::class, 'exportPDF'])->name('asset-maintenance.export-pdf');
        Route::post('asset-maintenance/{maintenanceRecord}/complete', [AssetMaintenanceController::class, 'complete'])->name('asset-maintenance.complete');
        Route::get('asset-maintenance/{asset}', [AssetMaintenanceController::class, 'index'])->name('asset-maintenance.index');
        Route::get('asset-maintenance/{asset}/create', [AssetMaintenanceController::class, 'create'])->name('asset-maintenance.create');
        Route::post('asset-maintenance/{asset}', [AssetMaintenanceController::class, 'store'])->name('asset-maintenance.store');
        Route::get('asset-maintenance/{maintenanceRecord}', [AssetMaintenanceController::class, 'show'])->name('asset-maintenance.show');
        Route::get('asset-maintenance/{maintenanceRecord}/edit', [AssetMaintenanceController::class, 'edit'])->name('asset-maintenance.edit');
        Route::put('asset-maintenance/{maintenanceRecord}', [AssetMaintenanceController::class, 'update'])->name('asset-maintenance.update');
        Route::delete('asset-maintenance/{maintenanceRecord}', [AssetMaintenanceController::class, 'destroy'])->name('asset-maintenance.destroy');

        Route::get('general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
        Route::get('general-ledger/download', [GeneralLedgerController::class, 'download'])->name('general-ledger.download');

        Route::get('cash-bank-book', [CashBankBookController::class, 'index'])->name('cash-bank-book.index');
        Route::get('cash-bank-book/download', [CashBankBookController::class, 'download'])->name('cash-bank-book.download');

        Route::get('income', [IncomeReportController::class, 'index'])->name('income.index');
        Route::get('income/download', [IncomeReportController::class, 'download'])->name('income.download');

        Route::get('balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
        Route::get('balance-sheet/download', [BalanceSheetController::class, 'download'])->name('balance-sheet.download');

        // Suppliers routes
        Route::delete('suppliers/bulk-delete', [SupplierController::class, 'bulkDelete'])->name('suppliers.bulk-delete');
        Route::get('suppliers/export-xlsx', [SupplierController::class, 'exportXLSX'])->name('suppliers.export-xlsx');
        Route::get('suppliers/export-csv', [SupplierController::class, 'exportCSV'])->name('suppliers.export-csv');
        Route::get('suppliers/export-pdf', [SupplierController::class, 'exportPDF'])->name('suppliers.export-pdf');
        Route::resource('suppliers', SupplierController::class);

        // Customers routes
        Route::delete('customers/bulk-delete', [CustomerController::class, 'bulkDelete'])->name('customers.bulk-delete');
        Route::get('customers/export-xlsx', [CustomerController::class, 'exportXLSX'])->name('customers.export-xlsx');
        Route::get('customers/export-csv', [CustomerController::class, 'exportCSV'])->name('customers.export-csv');
        Route::get('customers/export-pdf', [CustomerController::class, 'exportPDF'])->name('customers.export-pdf');
        Route::resource('customers', CustomerController::class);

        // Members routes
        Route::delete('members/bulk-delete', [MemberController::class, 'bulkDelete'])->name('members.bulk-delete');
        Route::get('members/export-xlsx', [MemberController::class, 'exportXLSX'])->name('members.export-xlsx');
        Route::get('members/export-csv', [MemberController::class, 'exportCSV'])->name('members.export-csv');
        Route::get('members/export-pdf', [MemberController::class, 'exportPDF'])->name('members.export-pdf');
        Route::resource('members', MemberController::class);

        // Partners routes
        Route::delete('partners/bulk-delete', [PartnerController::class, 'bulkDelete'])->name('partners.bulk-delete');
        Route::get('partners/export-xlsx', [PartnerController::class, 'exportXLSX'])->name('partners.export-xlsx');
        Route::get('partners/export-csv', [PartnerController::class, 'exportCSV'])->name('partners.export-csv');
        Route::get('partners/export-pdf', [PartnerController::class, 'exportPDF'])->name('partners.export-pdf');
        Route::resource('partners', PartnerController::class);

        // Employees routes
        Route::delete('employees/bulk-delete', [EmployeeController::class, 'bulkDelete'])->name('employees.bulk-delete');
        Route::get('employees/export-xlsx', [EmployeeController::class, 'exportXLSX'])->name('employees.export-xlsx');
        Route::get('employees/export-csv', [EmployeeController::class, 'exportCSV'])->name('employees.export-csv');
        Route::get('employees/export-pdf', [EmployeeController::class, 'exportPDF'])->name('employees.export-pdf');
        Route::resource('employees', EmployeeController::class);
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

