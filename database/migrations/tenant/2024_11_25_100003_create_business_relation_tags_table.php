<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_relation_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_relation_id')->constrained()->onDelete('cascade');
            $table->string('tag_name');
            $table->timestamps();

            $table->unique(['business_relation_id', 'tag_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_relation_tags');
    }
}; 