<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->string('user_global_id');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->foreign('user_global_id')->references('global_id')->on('users')->onDelete('cascade');
            $table->unique(['user_global_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
