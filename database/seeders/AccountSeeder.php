<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;

class AccountSeeder extends Seeder
{
    public function run()
    {
        $company = Company::first();
        if (!$company) {
            $company = Company::create([
                'name' => 'PT. Sample Indonesia',
                'legal_name' => 'PT. Sample Indonesia Tbk.',
                'address' => 'Jl. Sudirman No. 123',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'postal_code' => '12930',
                'phone' => '021-5551234',
            ]);
        }

        $currency = Currency::where('code', 'IDR')->first();
        if (!$currency) {
            $currency = Currency::create([
                'name' => 'Indonesian Rupiah',
                'code' => 'IDR',
                'symbol' => 'Rp',
                'is_primary' => true,
            ]);
        }

        $accountStructure = [
            ['code' => '1', 'name' => 'Aset', 'type' => 'aset_lainnya', 'children' => [
                ['code' => '101', 'name' => 'Kas & Bank', 'type' => 'kas_bank', 'children' => [
                    ['name' => 'Kas', 'type' => 'kas_bank', 'children' => [
                        ['name' => 'Kas Kecil', 'type' => 'kas_bank'],
                        ['name' => 'Kas Besar', 'type' => 'kas_bank'],
                    ]],
                    ['name' => 'Bank', 'type' => 'kas_bank', 'children' => [
                        ['name' => 'Bank BCA', 'type' => 'kas_bank'],
                        ['name' => 'Bank Mandiri', 'type' => 'kas_bank'],
                    ]],
                ]],
                ['code' => '102', 'name' => 'Piutang', 'type' => 'piutang', 'children' => [
                    ['name' => 'Piutang Usaha', 'type' => 'piutang_usaha'],
                    ['name' => 'Piutang Karyawan', 'type' => 'piutang_usaha'],
                    ['name' => 'Piutang Antar Cabang', 'type' => 'piutang_usaha'],
                    ['name' => 'Piutang Penjualan Aset', 'type' => 'piutang_lainnya'],
                ]],
                ['code' => '103', 'name' => 'Persediaan', 'type' => 'persediaan', 'children' => [
                    ['name' => 'Persediaan Barang Dagang', 'type' => 'persediaan'],
                    ['name' => 'Persediaan Barang Setengah Jadi', 'type' => 'persediaan'],
                    ['name' => 'Persediaan Bahan Baku', 'type' => 'persediaan'],
                ]],
                ['code' => '104', 'name' => 'Aset Lancar Lainnya', 'type' => 'aset_lancar_lainnya', 'children' => [
                    ['name' => 'Perlengkapan Kantor', 'type' => 'aset_lancar_lainnya'],
                    ['name' => 'Sewa Gedung Dibayar Dimuka', 'type' => 'aset_lancar_lainnya'],
                    ['name' => 'Asuransi Dibayar Dimuka', 'type' => 'aset_lancar_lainnya'],
                    ['name' => 'Reklame Dibayar Dimuka', 'type' => 'aset_lancar_lainnya'],
                    ['name' => 'PPN Masukan', 'type' => 'aset_lancar_lainnya'],
                    ['name' => 'PPh 23 Penjualan', 'type' => 'aset_lancar_lainnya'],
                    ['name' => 'Transaksi Dalam Pelaksanaan', 'type' => 'aset_lancar_lainnya'],
                    ['name' => 'Investasi Jangka Pendek', 'type' => 'aset_lancar_lainnya', 'children' => [
                        ['name' => 'Deposito', 'type' => 'aset_lancar_lainnya'],
                        ['name' => 'Obligasi', 'type' => 'aset_lancar_lainnya'],
                        ['name' => 'Investasi Saham', 'type' => 'aset_lancar_lainnya'],
                    ]],
                ]],
                ['code' => '105', 'name' => 'Aset Tetap', 'type' => 'aset_tetap', 'children' => [
                    ['name' => 'Tanah', 'type' => 'aset_tetap'],
                    ['name' => 'Bangunan', 'type' => 'aset_tetap'],
                    ['name' => 'Kendaraan', 'type' => 'aset_tetap'],
                    ['name' => 'Peralatan Kantor', 'type' => 'aset_tetap'],
                ]],
                ['code' => '106', 'name' => 'Akumulasi Penyusutan', 'type' => 'akumulasi_penyusutan', 'children' => [
                    ['name' => 'Akumulasi Penyusutan Bangunan', 'type' => 'akumulasi_penyusutan'],
                    ['name' => 'Akumulasi Penyusutan Kendaraan', 'type' => 'akumulasi_penyusutan'],
                    ['name' => 'Akumulasi Penyusutan Peralatan Kantor', 'type' => 'akumulasi_penyusutan'],
                ]],
                ['code' => '107', 'name' => 'Aset Lain-lain', 'type' => 'aset_lainnya', 'children' => [
                    ['name' => 'Beban Organisasi', 'type' => 'aset_lainnya'],
                    ['name' => 'Beban Pra-operasi', 'type' => 'aset_lainnya'],
                    ['name' => 'Renovasi Bangunan', 'type' => 'aset_lainnya'],
                    ['name' => 'Uang Jaminan', 'type' => 'aset_lainnya'],
                    ['name' => 'Biaya Ditangguhkan', 'type' => 'aset_lainnya'],
                ]],
            ]],
            ['code' => '2', 'name' => 'Kewajiban', 'type' => 'liabilitas_jangka_panjang', 'children' => [
                ['code' => '201', 'name' => 'Hutang Usaha', 'type' => 'hutang_usaha', 'children' => [
                    ['name' => 'Hutang Usaha dari Pembelian', 'type' => 'hutang_usaha'],
                    ['name' => 'Uang Muka Penjualan', 'type' => 'hutang_usaha'],
                ]],
                ['code' => '202', 'name' => 'Hutang Bank', 'type' => 'hutang_usaha_lainnya', 'children' => [
                    ['name' => 'Hutang Bank BCA', 'type' => 'hutang_usaha_lainnya'],
                    ['name' => 'Hutang Bank Mandiri', 'type' => 'hutang_usaha_lainnya'],
                ]],
                ['code' => '203', 'name' => 'Kewajiban Jangka Pendek Lainnya', 'type' => 'liabilitas_jangka_pendek', 'children' => [
                    ['name' => 'PPN Keluaran', 'type' => 'liabilitas_jangka_pendek'],
                    ['name' => 'PPh 23 Pembelian', 'type' => 'liabilitas_jangka_pendek'],
                    ['name' => 'Hutang Pembelian Belum Difakturkan', 'type' => 'liabilitas_jangka_pendek'],
                    ['name' => 'Hutang Pembelian Aset', 'type' => 'liabilitas_jangka_pendek'],
                ]],
                ['code' => '204', 'name' => 'Hutang Jangka Panjang', 'type' => 'liabilitas_jangka_panjang', 'children' => [
                    ['name' => 'Hutang Jangka Panjang dari Pinjaman', 'type' => 'liabilitas_jangka_panjang'],
                    ['name' => 'Hutang Leasing', 'type' => 'liabilitas_jangka_panjang'],
                ]],
            ]],
            ['code' => '3', 'name' => 'Modal', 'type' => 'modal', 'children' => [
                ['code' => '301', 'name' => 'Opening Balance', 'type' => 'modal'],
                ['code' => '302', 'name' => 'Modal Saham', 'type' => 'modal'],
                ['code' => '303', 'name' => 'Laba Ditahan', 'type' => 'modal'],
                ['code' => '304', 'name' => 'Laba Tahun Sebelumnya', 'type' => 'modal'],
            ]],
            ['code' => '4', 'name' => 'Pendapatan Operasional', 'type' => 'pendapatan', 'children' => [
                ['code' => '401', 'name' => 'Penjualan Barang', 'type' => 'pendapatan'],
                ['code' => '402', 'name' => 'Pendapatan Jasa', 'type' => 'pendapatan'],
                ['code' => '403', 'name' => 'Retur Penjualan', 'type' => 'pendapatan'],
                ['code' => '404', 'name' => 'Diskon Penjualan', 'type' => 'pendapatan'],
            ]],
            ['code' => '5', 'name' => 'Beban Pokok Penjualan', 'type' => 'beban_pokok_penjualan', 'children' => [
                ['code' => '501', 'name' => 'Harga Pokok Penjualan', 'type' => 'beban_pokok_penjualan'],
                ['code' => '502', 'name' => 'Retur Pembelian', 'type' => 'beban_pokok_penjualan'],
                ['code' => '503', 'name' => 'Koreksi Persediaan', 'type' => 'beban_pokok_penjualan'],
            ]],
            ['code' => '6', 'name' => 'Beban Operasional', 'type' => 'beban', 'children' => [
                ['code' => '601', 'name' => 'Beban Gaji', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Gaji & Lembur', 'type' => 'beban'],
                    ['name' => 'Beban Bonus', 'type' => 'beban'],
                    ['name' => 'Beban Pesangon & Kompensasi', 'type' => 'beban'],
                    ['name' => 'Beban Transportasi Karyawan', 'type' => 'beban'],
                    ['name' => 'Beban Upah & Honorarium', 'type' => 'beban'],
                    ['name' => 'Beban Katering & Makanan Karyawan', 'type' => 'beban'],
                    ['name' => 'Beban Tunjangan Kesehatan', 'type' => 'beban'],
                    ['name' => 'Beban Asuransi Karyawan', 'type' => 'beban'],
                    ['name' => 'Beban THR', 'type' => 'beban'],
                ]],
                ['code' => '602', 'name' => 'Beban Pajak', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban PPh 21', 'type' => 'beban'],
                    ['name' => 'Beban Pajak Lainnya', 'type' => 'beban'],
                ]],
                ['code' => '603', 'name' => 'Beban Sewa & Service Charges', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Sewa', 'type' => 'beban'],
                    ['name' => 'Beban Service Charges', 'type' => 'beban'],
                ]],
                ['code' => '604', 'name' => 'Beban Pemasaran', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Iklan', 'type' => 'beban'],
                    ['name' => 'Beban Komisi', 'type' => 'beban'],
                    ['name' => 'Beban Entertainment', 'type' => 'beban'],
                ]],
                ['code' => '605', 'name' => 'Beban Utilitas', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Listrik', 'type' => 'beban'],
                    ['name' => 'Beban Air', 'type' => 'beban'],
                    ['name' => 'Beban Telepon & Internet', 'type' => 'beban'],
                ]],
                ['code' => '606', 'name' => 'Beban Transportasi', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Angkutan Umum', 'type' => 'beban'],
                    ['name' => 'Beban BBM Transportasi', 'type' => 'beban'],
                    ['name' => 'Beban Parkir & Tol', 'type' => 'beban'],
                    ['name' => 'Beban STNK, KIR, & Pajak Kendaraan', 'type' => 'beban'],
                ]],
                ['code' => '607', 'name' => 'Beban Perbaikan & Pemeliharaan', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Pemeliharaan Gedung', 'type' => 'beban'],
                    ['name' => 'Beban Pemeliharaan Kendaraan', 'type' => 'beban'],
                    ['name' => 'Beban Pemeliharaan Peralatan Kantor', 'type' => 'beban'],
                ]],
                ['code' => '608', 'name' => 'Beban Perjalanan Dinas', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Akomodasi', 'type' => 'beban'],
                    ['name' => 'Beban Transportasi', 'type' => 'beban'],
                    ['name' => 'Beban Uang Saku', 'type' => 'beban'],
                ]],
                ['code' => '609', 'name' => 'Beban Pajak Reklame', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Pajak Reklame', 'type' => 'beban'],
                ]],
                ['code' => '610', 'name' => 'Beban Perlengkapan Kantor', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Alat Tulis Kantor', 'type' => 'beban'],
                    ['name' => 'Beban Benda Pos', 'type' => 'beban'],
                    ['name' => 'Beban Fotocopy', 'type' => 'beban'],
                    ['name' => 'Beban Tinta Printer', 'type' => 'beban'],
                    ['name' => 'Beban Kertas', 'type' => 'beban'],
                    ['name' => 'Beban Cetakan', 'type' => 'beban'],
                ]],
                ['code' => '611', 'name' => 'Beban Rumah Tangga Kantor', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Keperluan Rumah Tangga', 'type' => 'beban'],
                    ['name' => 'Beban Air Minum', 'type' => 'beban'],
                    ['name' => 'Beban Iuran Lingkungan', 'type' => 'beban'],
                    ['name' => 'Beban Jamuan Meeting', 'type' => 'beban'],
                ]],
                ['code' => '612', 'name' => 'Beban Representasi', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Jamuan', 'type' => 'beban'],
                    ['name' => 'Beban Souvenir', 'type' => 'beban'],
                    ['name' => 'Beban Tips', 'type' => 'beban'],
                    ['name' => 'Beban Representasi Lainnya', 'type' => 'beban'],
                ]],
                ['code' => '613', 'name' => 'Beban Asuransi', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Asuransi Kendaraan Bermotor', 'type' => 'beban'],
                    ['name' => 'Beban Asuransi Peralatan Kantor', 'type' => 'beban'],
                    ['name' => 'Beban Asuransi Lainnya', 'type' => 'beban'],
                ]],
                ['code' => '614', 'name' => 'Beban Admin, Cek & Giro', 'type' => 'beban', 'children' => [
                    ['name' => 'Beban Administrasi', 'type' => 'beban'],
                    ['name' => 'Beban Cek & Giro', 'type' => 'beban'],
                ]],
                ['code' => '615', 'name' => 'Beban Pelatihan', 'type' => 'beban'],
                ['code' => '616', 'name' => 'Beban Penyusutan', 'type' => 'beban_penyusutan', 'children' => [
                    ['name' => 'Beban Penyusutan Gedung', 'type' => 'beban_penyusutan'],
                    ['name' => 'Beban Penyusutan Kendaraan', 'type' => 'beban_penyusutan'],
                    ['name' => 'Beban Penyusutan Peralatan Kantor', 'type' => 'beban_penyusutan'],
                ]],
                ['code' => '617', 'name' => 'Beban Amortisasi', 'type' => 'beban_amortisasi', 'children' => [
                    ['name' => 'Amortisasi Sewa Gedung', 'type' => 'beban_amortisasi'],
                    ['name' => 'Amortisasi Sewa Peralatan Kantor', 'type' => 'beban_amortisasi'],
                    ['name' => 'Amortisasi Beban Organisasi', 'type' => 'beban_amortisasi'],
                    ['name' => 'Amortisasi Beban Pra-operasi', 'type' => 'beban_amortisasi'],
                    ['name' => 'Amortisasi Renovasi Bangunan', 'type' => 'beban_amortisasi'],
                    ['name' => 'Amortisasi Papan Reklame', 'type' => 'beban_amortisasi'],
                    ['name' => 'Amortisasi Biaya Ditangguhkan', 'type' => 'beban_amortisasi'],
                    ['name' => 'Amortisasi Asuransi Kendaraan', 'type' => 'beban_amortisasi'],
                ]],
            ]],            
            ['code' => '701', 'name' => 'Pendapatan Diluar Usaha', 'type' => 'pendapatan_lainnya', 'children' => [
                ['name' => 'Pendapatan Jasa Giro', 'type' => 'pendapatan_lainnya'],
                ['name' => 'Pendapatan Bunga Deposito', 'type' => 'pendapatan_lainnya'],
                ['name' => 'Penjualan Persediaan/Perlengkapan', 'type' => 'pendapatan_lainnya'],
                ['name' => 'Laba Penjualan Aset Tetap', 'type' => 'pendapatan_lainnya'],
                ['name' => 'Laba Selisih Kurs', 'type' => 'pendapatan_lainnya'],
                ['name' => 'Pendapatan Lainnya', 'type' => 'pendapatan_lainnya'],
            ]],
            ['code' => '702', 'name' => 'Beban Diluar Usaha', 'type' => 'beban_lainnya', 'children' => [
                ['name' => 'Beban Pinjaman Lainnya', 'type' => 'beban_lainnya'],
                ['name' => 'Beban Adm. Bank & Buku Cek/Giro', 'type' => 'beban_lainnya'],
                ['name' => 'Pajak Jasa Giro', 'type' => 'beban_lainnya'],
                ['name' => 'Rugi Penjualan Aset Tetap', 'type' => 'beban_lainnya'],
                ['name' => 'Rugi Selisih Kurs', 'type' => 'beban_lainnya'],
                ['name' => 'Beban Lainnya', 'type' => 'beban_lainnya'],
            ]],
        ];

        $this->createAccounts($accountStructure, null, $company, $currency);
    }

    private function createAccounts($accounts, $parentId, $company, $currency)
    {
        foreach ($accounts as $accountData) {
            $account = new Account();
            $account->name = $accountData['name'];
            $account->type = $accountData['type'];
            $account->parent_id = $parentId;

            if (isset($accountData['code'])) {
                $account->code = $accountData['code'];
            } else {
                $account->code = $this->generateAccountCode($parentId);
            }

            $account->save();
            $account->companies()->attach($company->id);
            $account->currencies()->attach($currency->id);

            if (isset($accountData['children'])) {
                $this->createAccounts($accountData['children'], $account->id, $company, $currency);
            }
        }
    }

    private function generateAccountCode($parentId)
    {
        $parentAccount = Account::find($parentId);
        $siblingAccounts = Account::where('parent_id', $parentId)->get();
        $lastChildNumber = 0;

        if ($siblingAccounts->isNotEmpty()) {
            $childCodes = $siblingAccounts->map(function ($account) {
                return intval(substr($account->code, -3));
            });
            $lastChildNumber = $childCodes->max();
        }

        $newChildNumber = str_pad($lastChildNumber + 1, 3, '0', STR_PAD_LEFT);
        return $parentAccount->code . $newChildNumber;
    }
}