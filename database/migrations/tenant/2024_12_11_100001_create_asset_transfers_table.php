<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->string('from_department')->nullable();
            $table->string('to_department')->nullable();
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->date('transfer_date');
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
        Schema::dropIfExists('asset_transfers');
    }
}; 