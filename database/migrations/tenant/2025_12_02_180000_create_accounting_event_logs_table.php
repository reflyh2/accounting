<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_code');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_id')->nullable();
            $table->string('document_number')->nullable();
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->string('status')->index();
            $table->json('payload');
            $table->text('last_error')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'branch_id']);
            $table->index('event_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_event_logs');
    }
};


