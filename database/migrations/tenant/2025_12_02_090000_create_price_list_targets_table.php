<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('partner_group_id')->nullable()->constrained('partner_groups')->onUpdate('cascade')->onDelete('restrict');
            $table->string('channel', 50)->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->unique(
                ['price_list_id', 'company_id', 'partner_id', 'partner_group_id', 'channel'],
                'uniq_price_list_target_scope'
            );
            $table->index(['partner_id', 'partner_group_id'], 'idx_price_list_target_partner');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_targets');
    }
};

