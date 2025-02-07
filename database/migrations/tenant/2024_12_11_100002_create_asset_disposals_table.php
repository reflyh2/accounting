<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->date('disposal_date');
            $table->string('disposal_method'); // sale, scrap, donation
            $table->decimal('disposal_amount', 15, 2)->nullable();
            $table->decimal('book_value_at_disposal', 15, 2);
            $table->decimal('gain_loss_amount', 15, 2);
            $table->string('status')->default('pending'); // pending, completed, cancelled
            $table->string('requested_by');
            $table->string('approved_by')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_disposals');
    }
}; 