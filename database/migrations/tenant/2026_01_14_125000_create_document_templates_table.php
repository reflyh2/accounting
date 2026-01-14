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
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type'); // sales_order, sales_delivery, sales_invoice
            $table->string('name');
            $table->longText('content'); // HTML template with placeholders
            $table->text('css_styles')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('page_size')->default('A4');
            $table->string('page_orientation')->default('portrait');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->nullOnDelete();
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->nullOnDelete();

            // Ensure only one default template per company+type combination
            $table->index(['company_id', 'document_type', 'is_default'], 'idx_company_type_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
