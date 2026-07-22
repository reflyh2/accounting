<?php

use App\Exceptions\DocumentStateException;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\CentralUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        InitializeTenancyByDomainOrSubdomain::class,
        PreventAccessFromCentralDomains::class,
    ]);
});

function createTestCompany(): Company
{
    return Company::create([
        'name' => 'Test Company',
        'legal_name' => 'Test Company',
        'address' => 'Jl. Test No. 1',
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'postal_code' => '12950',
        'phone' => '021789012',
    ]);
}

function createTestUser(): CentralUser
{
    $globalId = (string) Str::uuid();
    $centralUser = CentralUser::withoutEvents(function () use ($globalId) {
        return CentralUser::create([
            'name' => 'Test Central User',
            'email' => 'central.'.$globalId.'@example.com',
            'password' => bcrypt('password'),
            'global_id' => $globalId,
        ]);
    });

    User::withoutEvents(function () use ($globalId) {
        return User::create([
            'name' => 'Test User',
            'email' => 'user.'.$globalId.'@example.com',
            'password' => bcrypt('password'),
            'global_id' => $globalId,
        ]);
    });

    return $centralUser;
}

it('allows posting when period is open or does not exist', function () {
    $company = createTestCompany();

    AccountingPeriod::validatePostingAllowed('2026-01-15', $company->id);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'name' => 'Januari 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriod::STATUS_OPEN,
    ]);

    AccountingPeriod::validatePostingAllowed('2026-01-15', $company->id);

    expect(true)->toBeTrue();
});

it('blocks posting when period is closed', function () {
    $company = createTestCompany();

    AccountingPeriod::create([
        'company_id' => $company->id,
        'name' => 'Januari 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriod::STATUS_CLOSED,
    ]);

    expect(fn () => AccountingPeriod::validatePostingAllowed('2026-01-15', $company->id))
        ->toThrow(DocumentStateException::class, 'Tidak dapat melakukan transaksi/perubahan pada periode akuntansi yang sudah ditutup.');
});

it('enforces strict sequential period closing', function () {
    $company = createTestCompany();

    $periodMay = AccountingPeriod::create([
        'company_id' => $company->id,
        'name' => 'Mei 2026',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'status' => AccountingPeriod::STATUS_CLOSED,
    ]);

    // Trying to close July 2026 when June 2026 is not yet closed must fail
    expect(fn () => AccountingPeriod::validateSequentialClose($company->id, '2026-07-01'))
        ->toThrow(DocumentStateException::class, 'Periode akuntansi bulan sebelumnya (Juni 2026) belum ditutup. Penutupan periode harus dilakukan secara berurutan.');
});

it('enforces strict sequential period reopening / deletion', function () {
    $company = createTestCompany();

    $periodMay = AccountingPeriod::create([
        'company_id' => $company->id,
        'name' => 'Mei 2026',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'status' => AccountingPeriod::STATUS_CLOSED,
    ]);

    $periodJun = AccountingPeriod::create([
        'company_id' => $company->id,
        'name' => 'Juni 2026',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'status' => AccountingPeriod::STATUS_CLOSED,
    ]);

    $periodJul = AccountingPeriod::create([
        'company_id' => $company->id,
        'name' => 'Juli 2026',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'status' => AccountingPeriod::STATUS_CLOSED,
    ]);

    // Trying to reopen/delete Mei 2026 while Juli 2026 is still closed must fail
    expect(fn () => AccountingPeriod::validateSequentialReopen($company->id, '2026-05-01'))
        ->toThrow(DocumentStateException::class, 'Periode akuntansi setelahnya (Juli 2026) harus dibuka/dihapus terlebih dahulu secara berurutan.');

    // Deleting July first succeeds
    AccountingPeriod::validateSequentialReopen($company->id, '2026-07-01');
    $periodJul->delete();

    // Deleting June next succeeds
    AccountingPeriod::validateSequentialReopen($company->id, '2026-06-01');
    $periodJun->delete();

    // Now deleting May succeeds
    AccountingPeriod::validateSequentialReopen($company->id, '2026-05-01');
    $periodMay->delete();

    expect(AccountingPeriod::where('company_id', $company->id)->count())->toBe(0);
});

it('creates a period directly as CLOSED via HTTP store request', function () {
    $company = createTestCompany();
    $user = createTestUser();
    $this->actingAs($user);

    $response = $this->post(route('accounting-periods.store'), [
        'company_id' => $company->id,
        'month' => 4,
        'year' => 2026,
        'notes' => 'Closing April',
    ]);

    $response->assertSessionHasNoErrors();
    $period = AccountingPeriod::where('company_id', $company->id)->first();
    expect($period)->not->toBeNull();
    expect($period->status)->toBe(AccountingPeriod::STATUS_CLOSED);
    expect($period->start_date->format('Y-m-d'))->toBe('2026-04-01');
    expect($period->end_date->format('Y-m-d'))->toBe('2026-04-30');
});

it('blocks HTTP debt payment transaction in a closed period via middleware', function () {
    $company = createTestCompany();
    $branchGroup = BranchGroup::create(['company_id' => $company->id, 'name' => 'Main Group']);
    $branch = Branch::create(['branch_group_id' => $branchGroup->id, 'name' => 'Main Branch', 'code' => 'MB', 'address' => 'Jl. Branch No. 1']);

    $user = createTestUser();
    $this->actingAs($user);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'name' => 'Januari 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriod::STATUS_CLOSED,
    ]);

    $response = $this->post(route('external-payable-payments.store'), [
        'branch_id' => $branch->id,
        'company_id' => $company->id,
        'payment_date' => '2026-01-15',
        'payment_method' => 'tunai',
    ]);

    $response->assertSessionHas('error', 'Tidak dapat melakukan transaksi/perubahan pada periode akuntansi yang sudah ditutup.');
});

it('reopens posting when period is deleted', function () {
    $company = createTestCompany();

    $period = AccountingPeriod::create([
        'company_id' => $company->id,
        'name' => 'Januari 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriod::STATUS_CLOSED,
    ]);

    expect(fn () => AccountingPeriod::validatePostingAllowed('2026-01-15', $company->id))
        ->toThrow(DocumentStateException::class);

    $period->delete();

    // After delete, posting should be allowed again
    AccountingPeriod::validatePostingAllowed('2026-01-15', $company->id);
    expect(true)->toBeTrue();
});
