<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('path', 500)->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('attribute_sets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('attribute_defs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_set_id')->constrained('attribute_sets')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50);
            $table->string('label', 255);
            // data_type = string, number, boolean, date, datetime, select, multiselect
            $table->string('data_type', 50);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_variant_axis')->default(false);
            $table->jsonb('options_json')->nullable();
            $table->string('default_value', 255)->nullable();

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->unique(['attribute_set_id', 'code']);

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    
        Schema::create('attribute_set_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_set_id')->constrained('attribute_sets')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('tax_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            // applies_to = goods, services, both
            $table->string('applies_to', 50)->default('both');
            // default_behavior = taxable, zero_rated, exempt, out_of_scope, reverse_charge_candidate
            $table->string('default_behavior', 50)->default('taxable');
            $table->jsonb('attributes_json')->default('{}');
        });

        Schema::create('tax_jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('tax_jurisdictions')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50)->nullable();
            $table->string('name', 255);
            $table->string('country_code', 2);
            // level = country, state, province, county, city, municipality, district, special_purpose_district, custom
            $table->string('level', 50);
            $table->string('tax_authority', 255)->nullable();
            $table->jsonb('attributes_json')->default('{}');
        });

        Schema::create('tax_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_jurisdiction_id')->constrained('tax_jurisdictions')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            // kind = vat, gst, sales_tax, service_tax, excise, luxury, fee, withholding, other
            $table->string('kind', 50);
            // cascade_mode = parallel, on_top_of_prev
            $table->string('cascade_mode', 50)->default('parallel');
            // deductible_mode = deductible, non_deductible, partial
            $table->string('deductible_mode', 50)->default('deductible');
            $table->jsonb('attributes_json')->default('{}');
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_category_id')->constrained('tax_categories')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('tax_jurisdiction_id')->constrained('tax_jurisdictions')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('tax_component_id')->constrained('tax_components')->onUpdate('cascade')->onDelete('restrict');
            
            // rate_type = percent, fixed_per_unit
            $table->string('rate_type', 50)->default('percent');
            $table->decimal('rate_value', 18, 6);
            $table->foreignId('per_unit_uom_id')->nullable()->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->boolean('tax_inclusive')->default(false);
            $table->boolean('b2b_applicable')->nullable();
            $table->boolean('reverse_charge')->default(false);
            $table->boolean('export_zero_rate')->default(false);
            $table->decimal('threshold_amount', 18, 6)->nullable();
            $table->integer('priority')->default(10);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->jsonb('conditions_json')->default('{}');

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_components');
        Schema::dropIfExists('tax_jurisdictions');
        Schema::dropIfExists('tax_categories');
        Schema::dropIfExists('attribute_set_company');
        Schema::dropIfExists('attribute_defs');
        Schema::dropIfExists('attribute_sets');
        Schema::dropIfExists('product_categories');
    }
};

