<?php

namespace App\Http\Controllers\Concerns;

use App\Imports\ExternalDebtsImport;
use App\Imports\ImportRollbackException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Mixed into ExternalPayableController and ExternalReceivableController.
 * Both expose a `protected string $debtType` property which this trait reads.
 */
trait HandlesExternalDebtImport
{
    use HandlesImportErrors;

    public function importTemplate()
    {
        $kind = $this->debtType === 'receivable' ? 'piutang' : 'hutang';
        $headers = ['tanggal', 'jatuh_tempo', 'partner_kode', 'cabang', 'mata_uang', 'nilai_tukar', 'jumlah', 'akun_offset_kode', 'catatan'];
        $example = [
            ['2026-01-31', '2026-02-28', 'PTR0001', 'Pusat', 'IDR', 1, 1500000, '3-100', "Saldo awal {$kind}"],
        ];

        $callback = function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($example as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, "template-{$kind}-saldo-awal.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $import = new ExternalDebtsImport($this->debtType);

        try {
            Excel::import($import, $request->file('file'));
        } catch (ImportRollbackException $e) {
            // errors already collected
        }

        if (! empty($import->errors)) {
            return redirect()->back()->withErrors($this->buildImportErrorBag($import->errors));
        }

        $indexRoute = $this->debtType === 'receivable' ? 'external-receivables.index' : 'external-payables.index';
        $kind = $this->debtType === 'receivable' ? 'piutang' : 'hutang';

        return redirect()->route($indexRoute)
            ->with('success', "Berhasil mengimpor {$import->created} {$kind}.");
    }
}
