<?php

namespace App\Enums;

enum TaxInvoiceCode: string
{
    case Code01 = '01';
    case Code02 = '02';
    case Code03 = '03';
    case Code04 = '04';
    case Code05 = '05';
    case Code06 = '06';
    case Code07 = '07';
    case Code08 = '08';
    case Code09 = '09';
    case Code10 = '10';

    public function label(): string
    {
        return match ($this) {
            self::Code01 => 'Penyerahan BKP/JKP dengan PPN dipungut penjual',
            self::Code02 => 'Penyerahan kepada Pemungut PPN Bendahara Pemerintah',
            self::Code03 => 'Penyerahan kepada Pemungut PPN Selain Bendahara',
            self::Code04 => 'Penyerahan dengan DPP Nilai Lain',
            self::Code05 => 'Penyerahan dengan Tarif PPN Tertentu',
            self::Code06 => 'Pengembalian PPN kepada Turis (VAT Refund)',
            self::Code07 => 'Penyerahan yang PPN-nya Tidak Dipungut/DTP',
            self::Code08 => 'Penyerahan yang Dibebaskan dari PPN',
            self::Code09 => 'Penyerahan Aktiva Tetap (Pasal 16D)',
            self::Code10 => 'Penyerahan dengan Tarif Khusus Lainnya',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Code01 => '01 - PPN Penjual',
            self::Code02 => '02 - Bendahara Pemerintah',
            self::Code03 => '03 - Pemungut PPN Lain',
            self::Code04 => '04 - DPP Nilai Lain',
            self::Code05 => '05 - Tarif PPN Tertentu',
            self::Code06 => '06 - VAT Refund Turis',
            self::Code07 => '07 - Tidak Dipungut/DTP',
            self::Code08 => '08 - Dibebaskan PPN',
            self::Code09 => '09 - Aktiva Tetap',
            self::Code10 => '10 - Tarif Khusus',
        };
    }

    public static function options(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->shortLabel();
        }

        return $map;
    }

    public static function default(): self
    {
        return self::Code01;
    }
}
