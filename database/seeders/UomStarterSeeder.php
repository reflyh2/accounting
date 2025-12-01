<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Uom;
use App\Models\UomConversion;

class UomStarterSeeder extends Seeder
{
    public function run(): void
    {
        // Base UOMs
        $uoms = [
            ['code' => 'pcs', 'name' => 'Pieces', 'kind' => 'each'],
            ['code' => 'dozen', 'name' => 'Dozen', 'kind' => 'each'],
            ['code' => 'kg', 'name' => 'Kilogram', 'kind' => 'weight'],
            ['code' => 'g', 'name' => 'Gram', 'kind' => 'weight'],
            ['code' => 'mg', 'name' => 'Milligram', 'kind' => 'weight'],
            ['code' => 'm', 'name' => 'Meter', 'kind' => 'length'],
            ['code' => 'cm', 'name' => 'Centimeter', 'kind' => 'length'],
            ['code' => 'mm', 'name' => 'Millimeter', 'kind' => 'length'],
            ['code' => 'm2', 'name' => 'Square Meter', 'kind' => 'area'],
            ['code' => 'cm2', 'name' => 'Square Centimeter', 'kind' => 'area'],
            ['code' => 'mm2', 'name' => 'Square Millimeter', 'kind' => 'area'],
            ['code' => 'l', 'name' => 'Liter', 'kind' => 'volume'],
            ['code' => 'ml', 'name' => 'Milliliter', 'kind' => 'volume'],
            ['code' => 'hour', 'name' => 'Hour', 'kind' => 'time'],
            ['code' => 'day', 'name' => 'Day', 'kind' => 'time'],
        ];

        $companies = Company::get()->pluck('id');

        foreach ($uoms as $u) {
            foreach ($companies as $company) {
                Uom::query()->updateOrCreate([
                    'company_id' => $company,
                    'code' => $u['code'],
                    'name' => $u['name'],
                    'kind' => $u['kind'],
                ]);
            }
        }

        $pcs = Uom::where('code', 'pcs')->first();
        $dozen = Uom::where('code', 'dozen')->first();
        $kg = Uom::where('code', 'kg')->first();
        $g = Uom::where('code', 'g')->first();
        $mg = Uom::where('code', 'mg')->first();
        $m = Uom::where('code', 'm')->first();
        $cm = Uom::where('code', 'cm')->first();
        $mm = Uom::where('code', 'mm')->first();
        $m2 = Uom::where('code', 'm2')->first();
        $cm2 = Uom::where('code', 'cm2')->first();
        $mm2 = Uom::where('code', 'mm2')->first();
        $l = Uom::where('code', 'l')->first();
        $ml = Uom::where('code', 'ml')->first();
        $hour = Uom::where('code', 'hour')->first();
        $day = Uom::where('code', 'day')->first();

        // Simple conversions (add both directions where practical)
        if ($dozen && $pcs) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $dozen->id, 'to_uom_id' => $pcs->id],
                ['factor' => 12]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $pcs->id, 'to_uom_id' => $dozen->id],
                ['factor' => 1/12]
            );
        }

        if ($kg && $g && $mg) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $kg->id, 'to_uom_id' => $g->id],
                ['factor' => 1000]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $g->id, 'to_uom_id' => $kg->id],
                ['factor' => 0.001]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $g->id, 'to_uom_id' => $mg->id],
                ['factor' => 1000]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $mg->id, 'to_uom_id' => $g->id],
                ['factor' => 0.001]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $mg->id, 'to_uom_id' => $kg->id],
                ['factor' => 0.000001]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $kg->id, 'to_uom_id' => $mg->id],
                ['factor' => 1000000]
            );
        }

        if ($m && $cm && $mm) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $m->id, 'to_uom_id' => $cm->id],
                ['factor' => 100]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $cm->id, 'to_uom_id' => $m->id],
                ['factor' => 0.01]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $cm->id, 'to_uom_id' => $mm->id],
                ['factor' => 10]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $mm->id, 'to_uom_id' => $cm->id],
                ['factor' => 0.1]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $m->id, 'to_uom_id' => $mm->id],
                ['factor' => 1000]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $mm->id, 'to_uom_id' => $m->id],
                ['factor' => 0.001]
            );
        }

        if ($m2 && $cm2 && $mm2) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $m2->id, 'to_uom_id' => $cm2->id],
                ['factor' => 10000]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $cm2->id, 'to_uom_id' => $m2->id],
                ['factor' => 0.0001]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $cm2->id, 'to_uom_id' => $mm2->id],
                ['factor' => 100]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $mm2->id, 'to_uom_id' => $cm2->id],
                ['factor' => 0.01]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $m2->id, 'to_uom_id' => $mm2->id],
                ['factor' => 1000000]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $mm2->id, 'to_uom_id' => $m2->id],
                ['factor' => 0.000001]
            );
        }

        if ($l && $ml) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $l->id, 'to_uom_id' => $ml->id],
                ['factor' => 1000]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $ml->id, 'to_uom_id' => $l->id],
                ['factor' => 0.001]
            );
        }
        
        if ($day && $hour) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $day->id, 'to_uom_id' => $hour->id],
                ['factor' => 24]
            );
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $hour->id, 'to_uom_id' => $day->id],
                ['factor' => 1/24]
            );
        }
    }
}


