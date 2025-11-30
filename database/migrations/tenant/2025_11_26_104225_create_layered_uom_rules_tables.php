<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uom_conversion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('to_uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');

            $table->foreignId('product_id')->nullable()->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->onUpdate('cascade')->onDelete('restrict');

            $table->text('context')->nullable();
            $table->string('method', 255)->default('fixed_ratio');
            $table->decimal('numerator', 18, 6)->nullable();
            $table->decimal('denominator', 18, 6)->nullable();
            $table->decimal('factor', 24, 12)->nullable();
            $table->decimal('avg_weight_g', 18, 6)->nullable();
            $table->decimal('density_kg_per_l', 18, 6)->nullable();
            $table->string('rounding_mode', 255)->default('nearest');
            $table->integer('decimal_places')->default(3);

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['from_uom_id', 'to_uom_id'], 'idx_uomrule_pair');
            $table->index(['product_id', 'variant_id', 'company_id', 'partner_id', 'context'], 'idx_uomrule_scopes');
            $table->index(['effective_from', 'effective_to'], 'idx_uomrule_effective');
        });

        DB::statement("ALTER TABLE uom_conversion_rules ADD CONSTRAINT chk_uom_ctx CHECK (context IS NULL OR context IN ('purchase','sales','inventory','pricing'))");
        DB::statement("ALTER TABLE uom_conversion_rules ADD CONSTRAINT chk_rule_item_scope CHECK ((variant_id IS NULL) OR (product_id IS NULL OR product_id IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idx_uomrule_pair');
        Schema::dropIfExists('idx_uomrule_scopes');
        Schema::dropIfExists('idx_uomrule_effective');
        Schema::dropIfExists('uom_conversion_rules');
    }
};
