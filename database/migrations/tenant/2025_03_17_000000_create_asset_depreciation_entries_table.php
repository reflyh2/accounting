<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->date('entry_date');
            $table->string('type')->default('depreciation'); // Options: 'depreciation', 'amortization'
            $table->string('status')->default('scheduled'); // Options: 'scheduled', 'processed'
            $table->decimal('amount', 15, 2);
            $table->decimal('cumulative_amount', 15, 2);
            $table->decimal('remaining_value', 15, 2);
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->date('period_start');
            $table->date('period_end');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_depreciation_entries');
    }
}; 