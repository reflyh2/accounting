<?php

use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::create([
        'name' => 'Test Company',
        'legal_name' => 'Test Company Ltd',
        'costing_policy' => 'fifo',
        'reservation_strictness' => 'soft',
        'default_backflush' => false,
    ]);

    $this->branchGroup = BranchGroup::create([
        'name' => 'Test Group',
        'company_id' => $this->company->id,
    ]);

    $this->branch = Branch::create([
        'name' => 'Test Branch',
        'address' => 'Jl. Test',
        'branch_group_id' => $this->branchGroup->id,
    ]);

    $this->user = User::factory()->create();
});

it('can create a location', function () {
    $location = Location::create([
        'code' => 'WH-001',
        'name' => 'Gudang Utama',
        'type' => 'warehouse',
        'branch_id' => $this->branch->id,
        'is_active' => true,
        'created_by' => $this->user->global_id,
    ]);

    expect($location)->toBeInstanceOf(Location::class)
        ->and($location->code)->toBe('WH-001')
        ->and($location->name)->toBe('Gudang Utama')
        ->and($location->type)->toBe('warehouse')
        ->and($location->branch_id)->toBe($this->branch->id)
        ->and($location->is_active)->toBeTrue();
});

it('belongs to a branch', function () {
    $location = Location::create([
        'code' => 'WH-002',
        'name' => 'Gudang Kedua',
        'type' => 'warehouse',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    expect($location->branch)->toBeInstanceOf(Branch::class)
        ->and($location->branch->id)->toBe($this->branch->id);
});

it('can update a location', function () {
    $location = Location::create([
        'code' => 'WH-003',
        'name' => 'Gudang Lama',
        'type' => 'warehouse',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    $location->update([
        'name' => 'Gudang Baru',
        'type' => 'store',
        'is_active' => false,
        'updated_by' => $this->user->global_id,
    ]);

    $location->refresh();

    expect($location->name)->toBe('Gudang Baru')
        ->and($location->type)->toBe('store')
        ->and($location->is_active)->toBeFalse()
        ->and($location->updated_by)->toBe($this->user->global_id);
});

it('can delete a location', function () {
    $location = Location::create([
        'code' => 'WH-004',
        'name' => 'Gudang Hapus',
        'type' => 'warehouse',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    $locationId = $location->id;
    $location->delete();

    expect(Location::find($locationId))->toBeNull();
});

it('casts is_active as boolean', function () {
    $location = Location::create([
        'code' => 'WH-005',
        'name' => 'Gudang Bool',
        'type' => 'warehouse',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    expect($location->is_active)->toBeBool();
});

it('enforces unique code constraint', function () {
    Location::create([
        'code' => 'UNIQUE-001',
        'name' => 'Lokasi Satu',
        'type' => 'warehouse',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    expect(fn () => Location::create([
        'code' => 'UNIQUE-001',
        'name' => 'Lokasi Dua',
        'type' => 'store',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('supports all location types', function (string $type) {
    $location = Location::create([
        'code' => 'TYPE-'.strtoupper($type),
        'name' => 'Lokasi '.$type,
        'type' => $type,
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    expect($location->type)->toBe($type);
})->with(['warehouse', 'store', 'room', 'yard', 'vehicle']);

it('defaults is_active to true', function () {
    $location = Location::create([
        'code' => 'WH-DEFAULT',
        'name' => 'Gudang Default',
        'type' => 'warehouse',
        'branch_id' => $this->branch->id,
    ]);

    expect($location->is_active)->toBeTrue();
});

it('can bulk delete locations', function () {
    $location1 = Location::create([
        'code' => 'BULK-001',
        'name' => 'Bulk Satu',
        'type' => 'warehouse',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    $location2 = Location::create([
        'code' => 'BULK-002',
        'name' => 'Bulk Dua',
        'type' => 'store',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    Location::whereIn('id', [$location1->id, $location2->id])->delete();

    expect(Location::find($location1->id))->toBeNull()
        ->and(Location::find($location2->id))->toBeNull();
});
