<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            // Basic Classification
            $table->string('asset_type')->default('tangible'); // tangible, intangible
            $table->string('acquisition_type')->default('outright_purchase'); // outright_purchase, financed_purchase, fixed_rental, periodic_rental, casual_rental
            
            // Financial Information
            $table->decimal('current_value', 15, 2)->nullable();
            $table->decimal('residual_value', 15, 2)->nullable();
            
            // Financing Information (for financed_purchase)
            $table->decimal('down_payment', 15, 2)->nullable();
            $table->decimal('financing_amount', 15, 2)->nullable();
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->string('payment_frequency')->nullable(); // monthly, quarterly, annually
            $table->integer('financing_term_months')->nullable();
            
            // Rental Information (for all rental types)
            $table->date('rental_start_date')->nullable();
            $table->date('rental_end_date')->nullable();
            $table->string('rental_period')->nullable();
            $table->decimal('rental_amount', 15, 2)->nullable();
            $table->text('rental_terms')->nullable();
            
            // Revaluation
            $table->string('revaluation_method')->nullable();
            $table->date('last_revaluation_date')->nullable();
            $table->decimal('last_revaluation_amount', 15, 2)->nullable();
            $table->text('revaluation_notes')->nullable();
            
            // Impairment
            $table->boolean('is_impaired')->default(false);
            $table->decimal('impairment_amount', 15, 2)->nullable();
            $table->date('impairment_date')->nullable();
            $table->text('impairment_notes')->nullable();
            
            // Location and Other Information
            $table->string('department')->nullable();
            $table->string('location')->nullable();
        });
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'asset_type',
                'acquisition_type',
                'current_value',
                'residual_value',
                'down_payment',
                'financing_amount',
                'interest_rate',
                'payment_frequency',
                'financing_term_months',
                'rental_start_date',
                'rental_end_date',
                'rental_period',
                'rental_amount',
                'rental_terms',
                'revaluation_method',
                'last_revaluation_date',
                'last_revaluation_amount',
                'revaluation_notes',
                'is_impaired',
                'impairment_amount',
                'impairment_date',
                'impairment_notes',
                'department',
                'location'
            ]);
        });
    }
}; 