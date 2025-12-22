<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            // kind = goods, service, rental, accommodation, package, digital
            $table->string('kind', 50);
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('attribute_set_id')->nullable()->constrained('attribute_sets')->onUpdate('cascade')->onDelete('restrict');
            $table->jsonb('attrs_json')->default(DB::raw("'{}'::jsonb"));
            $table->foreignId('default_uom_id')->nullable()->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounts')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('cogs_account_id')->nullable()->constrained('accounts')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('inventory_account_id')->nullable()->constrained('accounts')->onUpdate('cascade')->onDelete('restrict');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index('product_category_id', 'idx_product_category');
            $table->index(['kind', 'is_active'], 'idx_product_kind_active');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('company_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('product_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            // capability = variantable, inventory_tracked, perishable, lot, serialized, bookable, seatable, event, rental, package, digital, service
            $table->string('capability', 50);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->string('sku', 80)->unique();
            $table->string('barcode', 80)->nullable();
            $table->jsonb('attrs_json')->default(DB::raw("'{}'::jsonb"));
            $table->boolean('track_inventory')->default(false);
            $table->foreignId('uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('weight_grams', 12, 3)->nullable();
            $table->decimal('length_cm', 12, 3)->nullable();
            $table->decimal('width_cm', 12, 3)->nullable();
            $table->decimal('height_cm', 12, 3)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index('product_id', 'idx_variant_product');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->string('supplier_sku', 80)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->decimal('moq', 18, 3)->nullable();
            $table->decimal('default_cost', 18, 2)->nullable();

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['partner_id', 'product_id'], 'idx_prod_supplier_partner_prod');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->foreignId('currency_id')->constrained('currencies')->onUpdate('cascade')->onDelete('restrict');
            $table->string('channel', 50)->nullable();
            $table->foreignId('partner_group_id')->nullable()->constrained('partner_groups')->onUpdate('cascade')->onDelete('restrict');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('min_qty', 18, 3)->default(1);
            $table->decimal('price', 18, 2);
            $table->boolean('tax_included')->default(false);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['price_list_id', 'product_variant_id'], 'idx_pli_list_variant');
            $table->index(['price_list_id', 'product_id'], 'idx_pli_list_product');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('product_suppliers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_capabilities');
        Schema::dropIfExists('company_product');
        Schema::dropIfExists('products');
    }
};

