<?php

namespace App\Imports;

use App\Enums\DebtStatus;
use App\Events\Debt\ExternalDebtCreated;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExternalDebt;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk-import open AR/AP balances (typically opening balances at cutover).
 *
 * Each row creates one ExternalDebt and dispatches ExternalDebtCreated, which
 * the existing event subscriber turns into a balanced journal entry. The debt
 * type ('payable' or 'receivable') is fixed per import call by the controller.
 */
class ExternalDebtsImport implements ToCollection, WithHeadingRow
{
    /** @var string[] */
    public array $errors = [];

    public int $created = 0;

    public function __construct(private readonly string $debtType)
    {
        if (! in_array($debtType, ['payable', 'receivable'], true)) {
            throw new \InvalidArgumentException("Unknown debt type [{$debtType}].");
        }
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'Berkas kosong atau tidak memiliki baris data.';

            return;
        }

        $partnersByCode = Partner::query()->get()->keyBy('code');
        $branchesByName = Branch::query()->get()->keyBy(fn ($b) => mb_strtolower($b->name));
        $currenciesByCode = Currency::query()->get()->keyBy('code');
        $accountsByCode = Account::query()->get()->keyBy('code');
        $companiesById = Company::query()->get()->keyBy('id');
        $primaryCurrency = Currency::query()->where('is_primary', true)->first();

        $staged = [];
        foreach ($rows as $index => $row) {
            $lineNo = $index + 2;
            $partnerCode = trim((string) ($row['partner_kode'] ?? ''));
            $branchName = trim((string) ($row['cabang'] ?? ''));

            if ($partnerCode === '' && $branchName === '' && empty($row['jumlah'])) {
                continue;
            }

            if ($partnerCode === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'partner_kode': wajib diisi.";

                continue;
            }
            $partner = $partnersByCode->get($partnerCode);
            if (! $partner) {
                $this->errors[] = "Baris {$lineNo}, kolom 'partner_kode': '{$partnerCode}' tidak ditemukan.";

                continue;
            }

            if ($branchName === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'cabang': wajib diisi.";

                continue;
            }
            $branch = $branchesByName->get(mb_strtolower($branchName));
            if (! $branch) {
                $this->errors[] = "Baris {$lineNo}, kolom 'cabang': '{$branchName}' tidak ditemukan.";

                continue;
            }
            $branch->loadMissing('branchGroup');
            $companyId = $branch->branchGroup?->company_id;
            if (! $companyId) {
                $this->errors[] = "Baris {$lineNo}, kolom 'cabang': cabang '{$branchName}' tidak terhubung ke perusahaan manapun.";

                continue;
            }

            $issueDate = $this->parseDate($row['tanggal'] ?? null);
            if ($issueDate === null) {
                $this->errors[] = "Baris {$lineNo}, kolom 'tanggal': wajib diisi dengan format tanggal yang valid (mis. 2026-01-31).";

                continue;
            }

            $dueDate = null;
            if (! empty($row['jatuh_tempo'])) {
                $dueDate = $this->parseDate($row['jatuh_tempo']);
                if ($dueDate === null) {
                    $this->errors[] = "Baris {$lineNo}, kolom 'jatuh_tempo': format tanggal tidak valid.";

                    continue;
                }
                if ($dueDate->lessThan($issueDate)) {
                    $this->errors[] = "Baris {$lineNo}, kolom 'jatuh_tempo': harus sama dengan atau setelah tanggal terbit.";

                    continue;
                }
            }

            $currencyCode = trim((string) ($row['mata_uang'] ?? ''));
            $currency = $currencyCode !== ''
                ? $currenciesByCode->get($currencyCode)
                : $primaryCurrency;
            if (! $currency) {
                $msg = $currencyCode !== '' ? "'{$currencyCode}' tidak ditemukan" : 'mata_uang utama belum diset';
                $this->errors[] = "Baris {$lineNo}, kolom 'mata_uang': {$msg}.";

                continue;
            }

            $exchangeRate = $row['nilai_tukar'] ?? null;
            if ($exchangeRate === null || $exchangeRate === '') {
                $exchangeRate = 1;
            }
            if (! is_numeric($exchangeRate) || (float) $exchangeRate <= 0) {
                $this->errors[] = "Baris {$lineNo}, kolom 'nilai_tukar': harus berupa angka positif.";

                continue;
            }

            $amount = $row['jumlah'] ?? null;
            if (! is_numeric($amount) || (float) $amount <= 0) {
                $this->errors[] = "Baris {$lineNo}, kolom 'jumlah': harus berupa angka positif.";

                continue;
            }

            $offsetCode = trim((string) ($row['akun_offset_kode'] ?? ''));
            if ($offsetCode === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'akun_offset_kode': wajib diisi (mis. akun Modal Awal untuk saldo awal).";

                continue;
            }
            $offsetAccount = $accountsByCode->get($offsetCode);
            if (! $offsetAccount) {
                $this->errors[] = "Baris {$lineNo}, kolom 'akun_offset_kode': '{$offsetCode}' tidak ditemukan.";

                continue;
            }

            $company = $companiesById->get($companyId);
            $defaultDebtAccountId = $this->debtType === 'receivable'
                ? $company?->default_receivable_account_id
                : $company?->default_payable_account_id;

            if (! $defaultDebtAccountId) {
                $kind = $this->debtType === 'receivable' ? 'piutang' : 'hutang';
                $this->errors[] = "Baris {$lineNo}: perusahaan untuk cabang '{$branchName}' belum memiliki akun default {$kind}. Setel akun default terlebih dulu.";

                continue;
            }

            $staged[] = [
                'branch_id' => $branch->id,
                'partner_id' => $partner->id,
                'currency_id' => $currency->id,
                'exchange_rate' => (float) $exchangeRate,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate?->toDateString(),
                'amount' => (float) $amount,
                'primary_currency_amount' => (float) $amount * (float) $exchangeRate,
                'debt_account_id' => $defaultDebtAccountId,
                'offset_account_id' => $offsetAccount->id,
                'notes' => trim((string) ($row['catatan'] ?? '')) ?: null,
            ];
        }

        if (! empty($this->errors)) {
            return;
        }

        DB::transaction(function () use ($staged) {
            $userGlobalId = Auth::user()?->global_id;

            foreach ($staged as $row) {
                $debt = ExternalDebt::create(array_merge($row, [
                    'type' => $this->debtType,
                    'status' => DebtStatus::OPEN->value,
                    'created_by' => $userGlobalId,
                ]));

                ExternalDebtCreated::dispatch($debt);
                $this->created++;
            }
        });
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            // Excel serial date: days since 1899-12-30
            try {
                $unix = (int) round(((float) $value - 25569) * 86400);

                return Carbon::createFromTimestampUTC($unix)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
