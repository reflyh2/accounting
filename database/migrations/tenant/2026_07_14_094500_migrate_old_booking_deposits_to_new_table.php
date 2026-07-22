<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ambil semua booking yang memiliki deposit_amount > 0
        $bookings = DB::table('bookings')
            ->whereNotNull('deposit_amount')
            ->where('deposit_amount', '>', 0)
            ->get();

        foreach ($bookings as $booking) {
            // Cek apakah sudah ada deposit terkait untuk mencegah duplikasi
            $exists = DB::table('booking_deposits')
                ->where('booking_id', $booking->id)
                ->exists();

            if (! $exists) {
                DB::table('booking_deposits')->insert([
                    'booking_id' => $booking->id,
                    'amount' => $booking->deposit_amount,
                    'payment_method' => $booking->deposit_payment_method ?? 'cash',
                    'company_bank_account_id' => $booking->deposit_company_bank_account_id,
                    'received_at' => $booking->deposit_received_at ?? $booking->created_at ?? now(),
                    'notes' => 'Migrasi dari deposit booking lama',
                    'created_by' => $booking->created_by,
                    'updated_by' => $booking->updated_by,
                    'created_at' => $booking->created_at ?? now(),
                    'updated_at' => $booking->updated_at ?? now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Hapus deposit hasil migrasi jika rollback
        DB::table('booking_deposits')
            ->where('notes', 'Migrasi dari deposit booking lama')
            ->delete();
    }
};
