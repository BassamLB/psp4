<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            // Family relation: define the column then the constraint
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            // Personal Information
            $table->string('first_name')->nullable()->index();
            $table->string('family_name')->nullable()->index();
            $table->string('father_name')->nullable();
            $table->string('mother_full_name')->nullable();
            $table->date('date_of_birth')->nullable();
            // Foreign Keys
            $table->foreignId('gender_id')->nullable()->constrained('genders')->nullOnDelete();
            $table->string('personal_sect', 100)->nullable();
            $table->foreignId('sect_id')->nullable()->constrained('sects')->nullOnDelete();
            $table->foreignId('town_id')->nullable()->constrained('towns')->nullOnDelete();
            $table->foreignId('profession_id')->nullable()->constrained('professions')->nullOnDelete();
            $table->foreignId('belong_id')->nullable()->constrained('belongs')->nullOnDelete();

            // Sijil Information
            $table->unsignedBigInteger('sijil_number')->index();
            $table->string('sijil_additional_string', 100)->nullable();

            // Travel Information
            $table->boolean('travelled')->default(false)->index();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();

            // Status
            $table->boolean('deceased')->default(false)->index();

            // Contact Information
            $table->string('mobile_number', 20)->nullable();

            // Additional Fields
            $table->string('admin_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite Indexes
            $table->index(['family_name', 'first_name']);
            $table->index(['town_id', 'deceased']);
            $table->index(['sect_id', 'deceased']);
            $table->index(['sijil_number', 'town_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voters');
    }
};
