<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Models\PartnerGroupMember;
use App\Models\PartnerGroupNamespace;
use App\Models\PriceList;
use App\Models\PriceListTarget;
use App\Services\Catalog\PriceListResolver;
use Illuminate\Support\Facades\DB;

it('prefers partner and company scoped targets', function () {
    [$company, $currency] = createCompanyCurrency();
    $partner = Partner::create(['name' => 'Acme Buyer']);

    $primary = PriceList::create([
        'company_id' => $company->id,
        'code' => 'PL-PRIMARY',
        'name' => 'Primary List',
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);
    $backup = PriceList::create([
        'company_id' => $company->id,
        'code' => 'PL-BACKUP',
        'name' => 'Backup List',
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);

    PriceListTarget::create([
        'price_list_id' => $backup->id,
        'company_id' => $company->id,
        'priority' => 10,
    ]);

    PriceListTarget::create([
        'price_list_id' => $primary->id,
        'company_id' => $company->id,
        'partner_id' => $partner->id,
        'priority' => 0,
    ]);

    $resolver = app(PriceListResolver::class);
    $priceList = $resolver->resolve([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
    ]);

    expect($priceList?->id)->toBe($primary->id);
});

it('falls back to partner group when partner scoped target missing', function () {
    [$company, $currency] = createCompanyCurrency();
    $partner = Partner::create(['name' => 'Group Buyer']);
    $namespace = PartnerGroupNamespace::create(['code' => 'tier', 'name' => 'Tiered', 'exclusive' => true]);
    $group = PartnerGroup::create([
        'partner_group_namespace_id' => $namespace->id,
        'code' => 'VIP',
        'name' => 'VIP',
    ]);

    PartnerGroupMember::create([
        'partner_group_id' => $group->id,
        'partner_id' => $partner->id,
        'partner_group_namespace_id' => $namespace->id,
        'company_id' => $company->id,
        'status' => 'active',
        'valid_from' => now()->subDay(),
    ]);

    $groupList = PriceList::create([
        'company_id' => $company->id,
        'code' => 'PL-GROUP',
        'name' => 'Group List',
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);

    PriceListTarget::create([
        'price_list_id' => $groupList->id,
        'partner_group_id' => $group->id,
        'company_id' => $company->id,
        'priority' => 5,
    ]);

    $resolver = app(PriceListResolver::class);
    $priceList = $resolver->resolve([
        'company_id' => $company->id,
        'partner_id' => $partner->id,
    ]);

    expect($priceList?->id)->toBe($groupList->id);
});

it('returns company scoped price list when no target matches', function () {
    [$company, $currency] = createCompanyCurrency();

    $companyList = PriceList::create([
        'company_id' => $company->id,
        'code' => 'PL-COMP',
        'name' => 'Company Default',
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);

    PriceListTarget::create([
        'price_list_id' => $companyList->id,
        'company_id' => $company->id,
        'priority' => 20,
    ]);

    $resolver = app(PriceListResolver::class);
    $priceList = $resolver->resolve([
        'company_id' => $company->id,
    ]);

    expect($priceList?->id)->toBe($companyList->id);
});

function createCompanyCurrency(): array
{
    $company = Company::create([
        'name' => 'Resolver Co',
        'legal_name' => 'Resolver Co',
        'tax_id' => 'NPWP500',
        'business_registration_number' => 'BRN500',
        'address' => 'Jl. Example',
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'postal_code' => '12950',
        'phone' => '021000000',
        'email' => 'info@resolver.test',
    ]);

    $currency = Currency::firstOrCreate(
        ['code' => 'IDR'],
        ['name' => 'Rupiah', 'symbol' => 'Rp', 'is_primary' => true]
    );

    return [$company, $currency];
}

