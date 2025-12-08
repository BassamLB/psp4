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
            $table->unsignedInteger('sijil_number')->nullable()->index();
            $table->unsignedBigInteger('father_id')->nullable();
            $table->unsignedBigInteger('mother_id')->nullable();
            $table->foreign('father_id')->references('id')->on('voters')->onDelete('set null');
            $table->foreign('mother_id')->references('id')->on('voters')->onDelete('set null');
            $table->integer('town_id')->nullable();
            $table->integer('sect_id')->nullable();
            $table->string('slug', 255)->nullable();
            $table->timestamps();

            // Composite Indexes
            $table->index(['sijil_number', 'town_id']);
            $table->index(['father_id', 'mother_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
