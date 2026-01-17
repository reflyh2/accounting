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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');                    // user global_id who performed the action
            $table->string('action');                     // created, updated, deleted, approved, posted, etc.
            $table->string('entity_type');                // model class name
            $table->unsignedBigInteger('entity_id');
            $table->json('before_state')->nullable();     // state before the action
            $table->json('after_state')->nullable();      // state after the action
            $table->json('changed_fields')->nullable();   // list of changed fields for updates
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at');

            $table->foreign('user_id')->references('global_id')->on('users');
            $table->index(['entity_type', 'entity_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
