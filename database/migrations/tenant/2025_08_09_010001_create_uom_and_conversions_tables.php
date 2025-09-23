<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            // kind = each, weight, length, area, volume, time
            $table->string('kind', 50);
        });

        Schema::create('uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('to_uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('factor', 18, 6);
        });

        DB::statement("ALTER TABLE uom_conversions ADD CONSTRAINT chk_uom_factor CHECK (factor > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('uom_conversions');
        Schema::dropIfExists('uoms');
    }
};

