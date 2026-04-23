<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Uom;
use App\Models\UomConversion;
use Illuminate\Database\Seeder;

class UomStarterSeeder extends Seeder
{
    public function run(): void
    {
        // Use the first company as the owner for UOMs (code is unique tenant-wide)
        $company = Company::withoutGlobalScopes()->first();
        if (! $company) {
            throw new \RuntimeException('UomStarterSeeder requires at least one company to exist.');
        }

        // Base UOMs
        $uoms = [
            ['code' => 'pcs', 'name' => 'Pieces', 'kind' => 'each'],
            ['code' => 'dozen', 'name' => 'Dozen', 'kind' => 'each'],
            ['code' => 'lsn', 'name' => 'Lusin', 'kind' => 'each'],
            ['code' => 'set', 'name' => 'Set', 'kind' => 'each'],
            ['code' => 'pack', 'name' => 'Pack', 'kind' => 'each'],
            ['code' => 'roll', 'name' => 'Roll', 'kind' => 'each'],
            ['code' => 'unit', 'name' => 'Unit', 'kind' => 'each'],
            ['code' => 'btl', 'name' => 'Botol', 'kind' => 'each'],
            ['code' => 'klg', 'name' => 'Kaleng', 'kind' => 'each'],
            ['code' => 'tbg', 'name' => 'Tabung', 'kind' => 'each'],
            ['code' => 'jrigen', 'name' => 'Jerigen', 'kind' => 'each'],
            ['code' => 'kg', 'name' => 'Kilogram', 'kind' => 'weight'],
            ['code' => 'g', 'name' => 'Gram', 'kind' => 'weight'],
            ['code' => 'mg', 'name' => 'Milligram', 'kind' => 'weight'],
            ['code' => 'ton', 'name' => 'Tonne', 'kind' => 'weight'],
            ['code' => 'm', 'name' => 'Meter', 'kind' => 'length'],
            ['code' => 'cm', 'name' => 'Centimeter', 'kind' => 'length'],
            ['code' => 'mm', 'name' => 'Millimeter', 'kind' => 'length'],
            ['code' => 'km', 'name' => 'Kilometer', 'kind' => 'length'],
            ['code' => 'm2', 'name' => 'Square Meter', 'kind' => 'area'],
            ['code' => 'cm2', 'name' => 'Square Centimeter', 'kind' => 'area'],
            ['code' => 'mm2', 'name' => 'Square Millimeter', 'kind' => 'area'],
            ['code' => 'm3', 'name' => 'Cubic Meter', 'kind' => 'volume'],
            ['code' => 'l', 'name' => 'Liter', 'kind' => 'volume'],
            ['code' => 'ml', 'name' => 'Milliliter', 'kind' => 'volume'],
            ['code' => 'hour', 'name' => 'Hour', 'kind' => 'time'],
            ['code' => 'day', 'name' => 'Day', 'kind' => 'time'],
        ];

        foreach ($uoms as $u) {
            Uom::query()->updateOrCreate(
                ['code' => $u['code']],
                [
                    'company_id' => $company->id,
                    'name' => $u['name'],
                    'kind' => $u['kind'],
                ]
            );
        }

        $pcs = Uom::where('code', 'pcs')->first();
        $dozen = Uom::where('code', 'dozen')->first();
        $kg = Uom::where('code', 'kg')->first();
        $g = Uom::where('code', 'g')->first();
        $mg = Uom::where('code', 'mg')->first();
        $ton = Uom::where('code', 'ton')->first();
        $m = Uom::where('code', 'm')->first();
        $cm = Uom::where('code', 'cm')->first();
        $mm = Uom::where('code', 'mm')->first();
        $km = Uom::where('code', 'km')->first();
        $m2 = Uom::where('code', 'm2')->first();
        $cm2 = Uom::where('code', 'cm2')->first();
        $mm2 = Uom::where('code', 'mm2')->first();
        $m3 = Uom::where('code', 'm3')->first();
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
                    'factor' => $numerator / $denominator,
                ]
            );
        };

        // Dozen <-> Pieces (1 dozen = 12 pcs)
        if ($dozen && $pcs) {
            $createConversion($dozen, $pcs, 12, 1);
            $createConversion($pcs, $dozen, 1, 12);
        }

        // Lusin <-> Pieces (1 lusin = 12 pcs)
        $lsn = Uom::where('code', 'lsn')->first();
        if ($lsn && $pcs) {
            $createConversion($lsn, $pcs, 12, 1);
            $createConversion($pcs, $lsn, 1, 12);
        }

        // Weight conversions
        if ($kg && $g && $mg) {
            $createConversion($kg, $g, 1000, 1);
            $createConversion($g, $kg, 1, 1000);
            $createConversion($g, $mg, 1000, 1);
            $createConversion($mg, $g, 1, 1000);
            $createConversion($kg, $mg, 1000000, 1);
            $createConversion($mg, $kg, 1, 1000000);
        }

        // Tonne <-> Kilogram (1 tonne = 1000 kg)
        if ($ton && $kg) {
            $createConversion($ton, $kg, 1000, 1);
            $createConversion($kg, $ton, 1, 1000);
        }

        // Length conversions
        if ($m && $cm && $mm) {
            $createConversion($m, $cm, 100, 1);
            $createConversion($cm, $m, 1, 100);
            $createConversion($cm, $mm, 10, 1);
            $createConversion($mm, $cm, 1, 10);
            $createConversion($m, $mm, 1000, 1);
            $createConversion($mm, $m, 1, 1000);
        }

        // Kilometer <-> Meter (1 km = 1000 m)
        if ($km && $m) {
            $createConversion($km, $m, 1000, 1);
            $createConversion($m, $km, 1, 1000);
        }

        // Area conversions
        if ($m2 && $cm2 && $mm2) {
            $createConversion($m2, $cm2, 10000, 1);
            $createConversion($cm2, $m2, 1, 10000);
            $createConversion($cm2, $mm2, 100, 1);
            $createConversion($mm2, $cm2, 1, 100);
            $createConversion($m2, $mm2, 1000000, 1);
            $createConversion($mm2, $m2, 1, 1000000);
        }

        // Volume conversions
        if ($m3 && $l) {
            $createConversion($m3, $l, 1000, 1);
            $createConversion($l, $m3, 1, 1000);
        }

        if ($l && $ml) {
            $createConversion($l, $ml, 1000, 1);
            $createConversion($ml, $l, 1, 1000);
        }

        // Time conversions
        if ($day && $hour) {
            $createConversion($day, $hour, 24, 1);
            $createConversion($hour, $day, 1, 24);
        }
    }
}
