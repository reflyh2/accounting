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
        // Field permissions table - defines which fields can have restricted access
        Schema::create('field_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');        // e.g., 'App\Models\Product'
            $table->string('field_name');         // e.g., 'unit_cost', 'margin'
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['model_type', 'field_name']);
        });

        // Role-field permissions table - assigns field access to roles
        Schema::create('role_field_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'field_permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_field_permissions');
        Schema::dropIfExists('field_permissions');
    }
};
