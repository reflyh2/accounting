<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('partner_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_holder_name');
            $table->string('branch_name')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('iban')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['partner_id', 'is_primary']);
            $table->index(['partner_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_bank_accounts');
    }
}; 