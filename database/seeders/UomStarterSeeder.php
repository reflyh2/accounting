<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Uom;
use App\Models\UomConversion;
use App\Models\Company;

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

        // Helper to create conversion with numerator/denominator
        $createConversion = function ($from, $to, $numerator, $denominator) {
            UomConversion::query()->updateOrCreate(
                ['from_uom_id' => $from->id, 'to_uom_id' => $to->id],
                [
                    'numerator' => $numerator,
                    'denominator' => $denominator,
                    'factor' => $numerator / $denominator, // Keep factor for backward compatibility
                ]
            );
        };

        // Dozen <-> Pieces (1 dozen = 12 pcs)
        if ($dozen && $pcs) {
            $createConversion($dozen, $pcs, 12, 1);   // 1 dozen = 12 pcs
            $createConversion($pcs, $dozen, 1, 12);   // 1 pcs = 1/12 dozen
        }

        // Weight conversions
        if ($kg && $g && $mg) {
            $createConversion($kg, $g, 1000, 1);       // 1 kg = 1000 g
            $createConversion($g, $kg, 1, 1000);       // 1 g = 1/1000 kg
            $createConversion($g, $mg, 1000, 1);       // 1 g = 1000 mg
            $createConversion($mg, $g, 1, 1000);       // 1 mg = 1/1000 g
            $createConversion($kg, $mg, 1000000, 1);   // 1 kg = 1000000 mg
            $createConversion($mg, $kg, 1, 1000000);   // 1 mg = 1/1000000 kg
        }

        // Length conversions
        if ($m && $cm && $mm) {
            $createConversion($m, $cm, 100, 1);        // 1 m = 100 cm
            $createConversion($cm, $m, 1, 100);        // 1 cm = 1/100 m
            $createConversion($cm, $mm, 10, 1);        // 1 cm = 10 mm
            $createConversion($mm, $cm, 1, 10);        // 1 mm = 1/10 cm
            $createConversion($m, $mm, 1000, 1);       // 1 m = 1000 mm
            $createConversion($mm, $m, 1, 1000);       // 1 mm = 1/1000 m
        }

        // Area conversions
        if ($m2 && $cm2 && $mm2) {
            $createConversion($m2, $cm2, 10000, 1);    // 1 m2 = 10000 cm2
            $createConversion($cm2, $m2, 1, 10000);    // 1 cm2 = 1/10000 m2
            $createConversion($cm2, $mm2, 100, 1);     // 1 cm2 = 100 mm2
            $createConversion($mm2, $cm2, 1, 100);     // 1 mm2 = 1/100 cm2
            $createConversion($m2, $mm2, 1000000, 1);  // 1 m2 = 1000000 mm2
            $createConversion($mm2, $m2, 1, 1000000);  // 1 mm2 = 1/1000000 m2
        }

        // Volume conversions
        if ($l && $ml) {
            $createConversion($l, $ml, 1000, 1);       // 1 l = 1000 ml
            $createConversion($ml, $l, 1, 1000);       // 1 ml = 1/1000 l
        }
        
        // Time conversions
        if ($day && $hour) {
            $createConversion($day, $hour, 24, 1);     // 1 day = 24 hours
            $createConversion($hour, $day, 1, 24);     // 1 hour = 1/24 day
        }
    }
}


