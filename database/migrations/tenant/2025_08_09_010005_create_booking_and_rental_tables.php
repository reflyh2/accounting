<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('restrict');
            $table->string('name', 255);
            $table->integer('default_capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('resource_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_pool_id')->constrained('resource_pools')->onUpdate('cascade')->onDelete('cascade');
            $table->string('code', 80)->unique();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->onUpdate('cascade')->onDelete('set null');
            // status = active, maintenance, retired
            $table->string('status', 50)->default('active');
            $table->jsonb('attrs_json')->default(DB::raw("'{}'::jsonb"));
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['resource_pool_id', 'status'], 'idx_instance_pool_status');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_pool_id')->constrained('resource_pools')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('resource_instance_id')->nullable()->constrained('resource_instances')->onUpdate('cascade')->onDelete('cascade');
            // rule_type = open, close, blackout
            $table->string('rule_type', 50)->default('open');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('dow_mask', 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
        DB::statement("ALTER TABLE availability_rules ADD CONSTRAINT chk_rule_time CHECK (end_datetime > start_datetime)");

        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_pool_id')->constrained('resource_pools')->onUpdate('cascade')->onDelete('cascade');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->integer('capacity')->nullable();
            // status = open, closed, soldout
            $table->string('status', 50)->default('open');
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['resource_pool_id', 'start_datetime'], 'idx_occurrence_pool_start');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
        DB::statement("ALTER TABLE occurrence ADD CONSTRAINT chk_occ_time CHECK (end_datetime > start_datetime)");

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 50)->unique();
            $table->foreignId('partner_id')->constrained('partners')->onUpdate('cascade')->onDelete('restrict');
            // booking_type = accommodation, rental
            $table->string('booking_type', 50)->default('accommodation');
            // status = hold, confirmed, checked_in, checked_out, completed, canceled, no_show
            $table->string('status', 50)->default('hold');
            $table->dateTime('booked_at')->useCurrent();
            $table->dateTime('held_until')->nullable();
            $table->string('source_channel', 50)->nullable();
            $table->decimal('deposit_amount', 18, 2)->nullable();
            $table->foreignId('currency_id')->constrained('currencies')->onUpdate('cascade')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['partner_id', 'status'], 'idx_booking_partner_status');
            $table->index('held_until', 'idx_booking_held_until');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('booking_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('resource_pool_id')->constrained('resource_pools')->onUpdate('cascade')->onDelete('cascade');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('deposit_required', 18, 2)->nullable();
            $table->foreignId('occurrence_id')->nullable()->constrained('occurrences')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('resource_instance_id')->nullable()->constrained('resource_instances')->onUpdate('cascade')->onDelete('set null');
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['resource_pool_id', 'start_datetime'], 'idx_booking_line_pool_start');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
        DB::statement("ALTER TABLE booking_lines ADD CONSTRAINT chk_booking_line_time CHECK (end_datetime > start_datetime)");

        Schema::create('booking_line_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_line_id')->constrained('booking_lines')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('resource_instance_id')->constrained('resource_instances')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index('booking_line_id', 'idx_blr_line');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('rental_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            // billing_granularity = hour, day, week, month
            $table->string('billing_granularity', 50)->default('hour');
            $table->integer('min_duration_minutes')->nullable();
            $table->integer('max_duration_minutes')->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('return_location_id')->nullable()->constrained('locations')->onUpdate('cascade')->onDelete('set null');
            // fuel_policy = full_to_full, prepaid, return_as_is
            $table->string('fuel_policy', 50)->nullable();
            $table->integer('mileage_included')->nullable();
            $table->foreignId('mileage_uom_id')->nullable()->constrained('uoms')->onUpdate('cascade')->onDelete('set null');
            $table->jsonb('late_fee_rule')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

           $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_policies');
        Schema::dropIfExists('booking_line_resources');
        Schema::dropIfExists('booking_lines');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('occurrences');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('resource_instances');
        Schema::dropIfExists('resource_pools');
    }
};

