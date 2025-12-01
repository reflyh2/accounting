<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Catalog\ProductTypeTemplates;
use App\Models\AttributeSet;

class ProductTypeTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure all attribute sets referenced by templates exist
        $templates = ProductTypeTemplates::all();

        foreach ($templates as $template) {
            $sets = [];
            if (isset($template['attribute_sets']) && is_array($template['attribute_sets'])) {
                $sets = $template['attribute_sets'];
            } elseif (isset($template['attribute_set']) && is_string($template['attribute_set'])) { // legacy support
                $sets = [$template['attribute_set']];
            }

            foreach ($sets as $code) {
                if (!is_string($code) || $code === '') {
                    continue;
                }
                AttributeSet::query()->updateOrCreate(
                    ['code' => $code],
                    ['name' => ucwords(str_replace('_', ' ', $code))]
                );
            }
        }
        // The registry lives in app/Domain; this seeder's role is to ensure supporting data exists.
    }
}


