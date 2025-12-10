<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\TaxJurisdiction;
use App\Models\TaxComponent;
use App\Models\TaxCategory;
use App\Models\TaxRule;

class TaxStarterSeeder extends Seeder
{
    public function run(): void
    {
        // Create Indonesia jurisdiction
        $indonesia = TaxJurisdiction::query()->updateOrCreate(
            ['code' => 'ID', 'country_code' => 'ID'],
            [
                'name' => 'Indonesia',
                'level' => 'country',
                'tax_authority' => 'Direktorat Jenderal Pajak',
            ]
        );

        // Create tax components for Indonesia
        $components = [
            [
                'code' => 'PPN',
                'name' => 'Pajak Pertambahan Nilai',
                'kind' => 'vat',
                'cascade_mode' => 'parallel',
                'deductible_mode' => 'deductible',
            ],
            [
                'code' => 'PPH21',
                'name' => 'PPh Pasal 21 - Penghasilan Pegawai',
                'kind' => 'withholding',
                'cascade_mode' => 'parallel',
                'deductible_mode' => 'non_deductible',
            ],
            [
                'code' => 'PPH22',
                'name' => 'PPh Pasal 22 - Impor/Bendahara',
                'kind' => 'withholding',
                'cascade_mode' => 'parallel',
                'deductible_mode' => 'deductible',
            ],
            [
                'code' => 'PPH23',
                'name' => 'PPh Pasal 23 - Jasa/Royalti',
                'kind' => 'withholding',
                'cascade_mode' => 'parallel',
                'deductible_mode' => 'deductible',
            ],
            [
                'code' => 'PPH4-2',
                'name' => 'PPh Pasal 4 ayat 2 - Final',
                'kind' => 'withholding',
                'cascade_mode' => 'parallel',
                'deductible_mode' => 'non_deductible',
            ],
            [
                'code' => 'PPNBM',
                'name' => 'Pajak Penjualan Barang Mewah',
                'kind' => 'luxury',
                'cascade_mode' => 'on_top_of_prev',
                'deductible_mode' => 'non_deductible',
            ],
        ];

        $createdComponents = [];
        foreach ($components as $comp) {
            $createdComponents[$comp['code']] = TaxComponent::query()->updateOrCreate(
                ['code' => $comp['code']],
                array_merge($comp, ['tax_jurisdiction_id' => $indonesia->id])
            );
        }

        // Create tax categories for each company
        $companies = Company::withoutGlobalScopes()->get();
        
        $categoryTemplates = [
            [
                'code' => 'STANDARD_GOODS',
                'name' => 'Barang Kena Pajak Standar',
                'applies_to' => 'goods',
                'default_behavior' => 'taxable',
                'description' => 'Barang yang dikenakan PPN dengan tarif standar',
            ],
            [
                'code' => 'STANDARD_SERVICES',
                'name' => 'Jasa Kena Pajak Standar',
                'applies_to' => 'services',
                'default_behavior' => 'taxable',
                'description' => 'Jasa yang dikenakan PPN dengan tarif standar',
            ],
            [
                'code' => 'ZERO_RATED',
                'name' => 'Tarif Nol Persen',
                'applies_to' => 'both',
                'default_behavior' => 'zero_rated',
                'description' => 'Ekspor barang/jasa dan barang strategis tertentu',
            ],
            [
                'code' => 'EXEMPT',
                'name' => 'Bebas PPN',
                'applies_to' => 'both',
                'default_behavior' => 'exempt',
                'description' => 'Barang/jasa yang dikecualikan dari pengenaan PPN',
            ],
            [
                'code' => 'LUXURY_GOODS',
                'name' => 'Barang Mewah',
                'applies_to' => 'goods',
                'default_behavior' => 'taxable',
                'description' => 'Barang yang dikenakan PPnBM',
            ],
        ];

        foreach ($companies as $company) {
            $createdCategories = [];
            
            foreach ($categoryTemplates as $catTemplate) {
                $createdCategories[$catTemplate['code']] = TaxCategory::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $catTemplate['code'],
                    ],
                    $catTemplate
                );
            }

            // Create default tax rules
            $effectiveFrom = now()->startOfYear();

            // PPN 11% for standard goods
            TaxRule::query()->updateOrCreate(
                [
                    'tax_category_id' => $createdCategories['STANDARD_GOODS']->id,
                    'tax_jurisdiction_id' => $indonesia->id,
                    'tax_component_id' => $createdComponents['PPN']->id,
                ],
                [
                    'rate_type' => 'percent',
                    'rate_value' => 11,
                    'tax_inclusive' => false,
                    'priority' => 10,
                    'effective_from' => $effectiveFrom,
                ]
            );

            // PPN 11% for standard services
            TaxRule::query()->updateOrCreate(
                [
                    'tax_category_id' => $createdCategories['STANDARD_SERVICES']->id,
                    'tax_jurisdiction_id' => $indonesia->id,
                    'tax_component_id' => $createdComponents['PPN']->id,
                ],
                [
                    'rate_type' => 'percent',
                    'rate_value' => 11,
                    'tax_inclusive' => false,
                    'priority' => 10,
                    'effective_from' => $effectiveFrom,
                ]
            );

            // PPN 0% for zero-rated
            TaxRule::query()->updateOrCreate(
                [
                    'tax_category_id' => $createdCategories['ZERO_RATED']->id,
                    'tax_jurisdiction_id' => $indonesia->id,
                    'tax_component_id' => $createdComponents['PPN']->id,
                ],
                [
                    'rate_type' => 'percent',
                    'rate_value' => 0,
                    'tax_inclusive' => false,
                    'export_zero_rate' => true,
                    'priority' => 10,
                    'effective_from' => $effectiveFrom,
                ]
            );

            // PPnBM 10% for luxury goods (example rate)
            TaxRule::query()->updateOrCreate(
                [
                    'tax_category_id' => $createdCategories['LUXURY_GOODS']->id,
                    'tax_jurisdiction_id' => $indonesia->id,
                    'tax_component_id' => $createdComponents['PPNBM']->id,
                ],
                [
                    'rate_type' => 'percent',
                    'rate_value' => 10,
                    'tax_inclusive' => false,
                    'priority' => 20,
                    'effective_from' => $effectiveFrom,
                ]
            );

            // Also add PPN for luxury goods
            TaxRule::query()->updateOrCreate(
                [
                    'tax_category_id' => $createdCategories['LUXURY_GOODS']->id,
                    'tax_jurisdiction_id' => $indonesia->id,
                    'tax_component_id' => $createdComponents['PPN']->id,
                ],
                [
                    'rate_type' => 'percent',
                    'rate_value' => 11,
                    'tax_inclusive' => false,
                    'priority' => 10,
                    'effective_from' => $effectiveFrom,
                ]
            );
        }
    }
}
