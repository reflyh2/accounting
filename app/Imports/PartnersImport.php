<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\Partner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk-create partners with one or more roles per row.
 *
 * Nested contacts, addresses, and bank accounts are intentionally out of scope
 * — users can edit a partner individually to add those. All rows are assigned
 * to all tenant companies (matching the products importer convention).
 */
class PartnersImport implements ToCollection, WithHeadingRow
{
    private const ALLOWED_ROLES = ['supplier', 'customer', 'asset_supplier', 'asset_customer', 'creditor', 'others'];

    private const ALLOWED_STATUSES = ['active', 'inactive'];

    /** @var string[] */
    public array $errors = [];

    public int $created = 0;

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'Berkas kosong atau tidak memiliki baris data.';

            return;
        }

        $companyIds = Company::query()->pluck('id')->toArray();
        if (empty($companyIds)) {
            $this->errors[] = 'Tidak ada perusahaan terdaftar pada tenant ini.';

            return;
        }

        $staged = [];
        foreach ($rows as $index => $row) {
            $lineNo = $index + 2;
            $name = trim((string) ($row['nama'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rolesRaw = trim((string) ($row['peran'] ?? ''));
            if ($rolesRaw === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'peran': wajib diisi (minimal satu).";

                continue;
            }

            $roles = array_values(array_filter(array_map('trim', explode(',', $rolesRaw))));
            $invalidRoles = array_diff($roles, self::ALLOWED_ROLES);
            if (! empty($invalidRoles)) {
                $invalid = implode(', ', $invalidRoles);
                $this->errors[] = "Baris {$lineNo}, kolom 'peran': nilai '{$invalid}' tidak dikenal. Gunakan: ".implode(', ', self::ALLOWED_ROLES).'.';

                continue;
            }

            $status = mb_strtolower(trim((string) ($row['status'] ?? 'active')));
            if ($status === '') {
                $status = 'active';
            }
            if (! in_array($status, self::ALLOWED_STATUSES, true)) {
                $this->errors[] = "Baris {$lineNo}, kolom 'status': harus 'active' atau 'inactive'.";

                continue;
            }

            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Baris {$lineNo}, kolom 'email': '{$email}' bukan alamat email yang valid.";

                continue;
            }

            $creditLimit = $row['batas_kredit'] ?? null;
            if ($creditLimit !== null && $creditLimit !== '' && ! is_numeric($creditLimit)) {
                $this->errors[] = "Baris {$lineNo}, kolom 'batas_kredit': harus berupa angka.";

                continue;
            }

            $paymentTerm = $row['termin_hari'] ?? null;
            if ($paymentTerm !== null && $paymentTerm !== '' && ! is_numeric($paymentTerm)) {
                $this->errors[] = "Baris {$lineNo}, kolom 'termin_hari': harus berupa angka.";

                continue;
            }

            $staged[] = [
                'name' => $name,
                'phone' => trim((string) ($row['telepon'] ?? '')) ?: null,
                'email' => $email ?: null,
                'address' => trim((string) ($row['alamat'] ?? '')) ?: null,
                'city' => trim((string) ($row['kota'] ?? '')) ?: null,
                'tax_id' => trim((string) ($row['npwp'] ?? '')) ?: null,
                'status' => $status,
                'roles' => array_values(array_unique($roles)),
                'credit_limit' => $creditLimit !== null && $creditLimit !== '' ? (float) $creditLimit : 0,
                'payment_term_days' => $paymentTerm !== null && $paymentTerm !== '' ? (int) $paymentTerm : 0,
            ];
        }

        if (! empty($this->errors)) {
            return;
        }

        DB::transaction(function () use ($staged, $companyIds) {
            foreach ($staged as $row) {
                $partner = Partner::create([
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'address' => $row['address'],
                    'city' => $row['city'],
                    'tax_id' => $row['tax_id'],
                    'status' => $row['status'],
                ]);

                $partner->companies()->sync($companyIds);

                foreach ($row['roles'] as $role) {
                    $partner->roles()->create([
                        'role' => $role,
                        'credit_limit' => $row['credit_limit'],
                        'payment_term_days' => $row['payment_term_days'],
                        'status' => 'active',
                    ]);
                }

                $this->created++;
            }
        });
    }
}
