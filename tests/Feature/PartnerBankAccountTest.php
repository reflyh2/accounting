<?php

use App\Models\Partner;
use App\Models\PartnerBankAccount;

beforeEach(function () {
    $this->partner = Partner::create([
        'name' => 'Test Partner',
        'status' => 'active',
    ]);
});

it('defaults currency to IDR when created with a null currency', function () {
    $account = $this->partner->bankAccounts()->create([
        'bank_name' => 'Bank Test',
        'account_number' => '123456789',
        'account_holder_name' => 'Test Holder',
        'currency' => null,
        'is_primary' => true,
        'is_active' => true,
    ]);

    expect($account->fresh()->currency)->toBe('IDR');
});

it('defaults currency to IDR when created with an empty currency', function () {
    $account = $this->partner->bankAccounts()->create([
        'bank_name' => 'Bank Test',
        'account_number' => '123456789',
        'account_holder_name' => 'Test Holder',
        'currency' => '',
        'is_primary' => true,
        'is_active' => true,
    ]);

    expect($account->fresh()->currency)->toBe('IDR');
});

it('keeps the provided currency when one is given', function () {
    $account = $this->partner->bankAccounts()->create([
        'bank_name' => 'Bank Test',
        'account_number' => '123456789',
        'account_holder_name' => 'Test Holder',
        'currency' => 'USD',
        'is_primary' => true,
        'is_active' => true,
    ]);

    expect($account->fresh()->currency)->toBe('USD');
});

it('coalesces the currency attribute in memory without touching the database', function () {
    $account = new PartnerBankAccount;
    $account->currency = null;

    expect($account->currency)->toBe('IDR');
});
