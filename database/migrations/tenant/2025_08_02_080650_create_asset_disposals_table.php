<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->date('disposal_date');
            $table->string('number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('restrict');
            $table->enum('disposal_type', ['scrap', 'donation', 'theft', 'accident', 'write_off', 'other'])->default('scrap');
            $table->decimal('proceeds_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->onUpdate('cascade')->onDelete('restrict');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('rejected_by')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('approved_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('rejected_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('cancelled_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
