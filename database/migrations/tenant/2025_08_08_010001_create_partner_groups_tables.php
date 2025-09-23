<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_group_namespaces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->boolean('exclusive')->default(true);
            
        });

        Schema::create('partner_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_group_namespace_id')->constrained('partner_group_namespaces')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->jsonb('attributes_json')->default(DB::raw("'{}'::jsonb"));
            // status = active, inactive
            $table->string('status', 50)->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
        });

        Schema::create('company_partner_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('partner_group_id')->constrained('partner_groups')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('partner_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_group_id')->constrained('partner_groups')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('partner_id')->constrained('partners')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('partner_group_namespace_id')->constrained('partner_group_namespaces')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            // status = active, suspended, expired
            $table->string('status', 50)->default('active');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_group_members');
        Schema::dropIfExists('company_partner_group');
        Schema::dropIfExists('partner_groups');
        Schema::dropIfExists('partner_group_namespaces');
    }
};

