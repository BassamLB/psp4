<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_imports_temp', function (Blueprint $table) {
            $table->id();

            // Core matching keys
            $table->unsignedBigInteger('sijil_number')->index();
            $table->unsignedBigInteger('town_id')->index();
            $table->string('first_name')->index();
            $table->string('family_name')->index();
            $table->string('father_name')->index();

            // Useful fields for review and merging
            $table->string('mother_full_name');
            $table->unsignedBigInteger('gender_id');
            $table->unsignedBigInteger('sect_id');
            $table->string('personal_sect');
            $table->string('sijil_additional_string', 100)->nullable();
            $table->date('date_of_birth')->nullable();

            // Import metadata
            $table->boolean('processed')->default(false)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_imports_temp');
    }
};
