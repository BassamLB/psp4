<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name', 255)->index();
            $table->unsignedBigInteger('sijil_number')->nullable()->index();
            $table->integer('town_id')->nullable();
            $table->integer('sect_id')->nullable();
            $table->string('slug', 255)->nullable();
            $table->timestamps();

            // Composite Indexes
            $table->index(['sijil_number', 'town_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
