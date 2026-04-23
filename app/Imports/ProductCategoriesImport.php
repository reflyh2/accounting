<?php

namespace App\Imports;

use App\Models\AttributeSet;
use App\Models\Company;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductCategoriesImport implements ToCollection, WithHeadingRow
{
    /**
     * @var string[] Row-level error messages collected during processing.
     */
    public array $errors = [];

    public int $created = 0;

    public function __construct(private readonly ?string $userGlobalId = null) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'Berkas kosong atau tidak memiliki baris data.';

            return;
        }

        $companiesByName = Company::query()->get()->keyBy(fn ($c) => mb_strtolower($c->name));
        $attributeSetsByCode = AttributeSet::query()->get()->keyBy(fn ($s) => mb_strtolower($s->code));
        $existingByCode = ProductCategory::query()->get()->keyBy('code');

        $singleCompany = $companiesByName->count() === 1 ? $companiesByName->first() : null;

        $staged = [];
        foreach ($rows as $index => $row) {
            $lineNo = $index + 2;
            $code = trim((string) ($row['kode'] ?? ''));
            $name = trim((string) ($row['nama'] ?? ''));

            if ($code === '' && $name === '') {
                continue;
            }

            if ($code === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'kode': wajib diisi.";

                continue;
            }
            if ($name === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'nama': wajib diisi.";

                continue;
            }
            if (isset($staged[$code]) || $existingByCode->has($code)) {
                $this->errors[] = "Baris {$lineNo}, kolom 'kode': nilai '{$code}' sudah dipakai.";

                continue;
            }

            $companyName = trim((string) ($row['perusahaan'] ?? ''));
            if ($companyName === '' && $singleCompany) {
                $company = $singleCompany;
            } else {
                $company = $companyName !== '' ? $companiesByName->get(mb_strtolower($companyName)) : null;
            }
            if (! $company) {
                $this->errors[] = "Baris {$lineNo}, kolom 'perusahaan': '{$companyName}' tidak ditemukan.";

                continue;
            }

            $attrSetCode = trim((string) ($row['set_atribut'] ?? ''));
            if ($attrSetCode === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'set_atribut': wajib diisi.";

                continue;
            }
            $attrSet = $attributeSetsByCode->get(mb_strtolower($attrSetCode));
            if (! $attrSet) {
                $this->errors[] = "Baris {$lineNo}, kolom 'set_atribut': '{$attrSetCode}' tidak ditemukan.";

                continue;
            }

            $parentCode = trim((string) ($row['parent_kode'] ?? ''));
            $sortOrder = $row['urutan'] ?? 0;
            if (! is_numeric($sortOrder)) {
                $this->errors[] = "Baris {$lineNo}, kolom 'urutan': harus berupa angka.";

                continue;
            }

            $staged[$code] = [
                'line' => $lineNo,
                'code' => $code,
                'name' => $name,
                'company_id' => $company->id,
                'attribute_set_id' => $attrSet->id,
                'parent_code' => $parentCode !== '' ? $parentCode : null,
                'sort_order' => (int) $sortOrder,
            ];
        }

        if (! empty($this->errors)) {
            return;
        }

        DB::transaction(function () use (&$staged, $existingByCode) {
            $resolved = $existingByCode->map(fn ($c) => $c)->all();
            $pending = $staged;
            $guard = count($pending) + 1;

            while (! empty($pending) && $guard-- > 0) {
                $progress = false;
                foreach ($pending as $code => $row) {
                    $parent = null;
                    if ($row['parent_code']) {
                        if (! isset($resolved[$row['parent_code']])) {
                            continue;
                        }
                        $parent = $resolved[$row['parent_code']];
                    }

                    $category = ProductCategory::create([
                        'company_id' => $row['company_id'],
                        'attribute_set_id' => $row['attribute_set_id'],
                        'parent_id' => $parent?->id,
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'sort_order' => $row['sort_order'],
                        'path' => $parent ? trim(($parent->path ?: $parent->code).'/'.$row['code'], '/') : $row['code'],
                        'created_by' => $this->userGlobalId,
                        'updated_by' => $this->userGlobalId,
                    ]);

                    $resolved[$code] = $category;
                    unset($pending[$code]);
                    $this->created++;
                    $progress = true;
                }

                if (! $progress) {
                    break;
                }
            }

            if (! empty($pending)) {
                foreach ($pending as $row) {
                    $this->errors[] = "Baris {$row['line']}, kolom 'parent_kode': '{$row['parent_code']}' tidak ditemukan.";
                }
                throw new ImportRollbackException;
            }
        });
    }
}
