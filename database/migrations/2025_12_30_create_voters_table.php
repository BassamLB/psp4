<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            // Family relation: store family_id but defer foreign key constraint
            // to avoid ordering issues when `families` migration runs later.
            $table->unsignedBigInteger('family_id')->nullable()->index();
            // Personal Information
            $table->string('first_name', 100)->nullable()->index();
            $table->string('family_name', 100)->nullable()->index();
            $table->string('father_name', 100)->nullable();
            $table->string('mother_full_name', 150)->nullable();
            $table->date('date_of_birth')->nullable();
            // Foreign Keys
            $table->foreignId('gender_id')->nullable()->constrained('genders')->nullOnDelete();
            $table->string('personal_sect', 100)->nullable();
            $table->foreignId('sect_id')->nullable()->constrained('sects')->nullOnDelete();
            $table->foreignId('town_id')->nullable()->constrained('towns')->nullOnDelete();
            $table->foreignId('profession_id')->nullable()->constrained('professions')->nullOnDelete();
            $table->foreignId('belong_id')->nullable()->constrained('belongs')->nullOnDelete();

            // Sijil Information
            $table->unsignedInteger('sijil_number')->index();
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

        // Add merge_hash using raw SQL after all constraints are created
        // Using VIRTUAL instead of STORED to avoid FK constraint conflicts
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE voters ADD COLUMN merge_hash VARCHAR(40) GENERATED ALWAYS AS (sha1(CONCAT_WS('#', sijil_number, town_id, family_name, first_name, father_name, mother_full_name))) VIRTUAL");
            DB::statement('CREATE INDEX voters_merge_hash_idx ON voters (merge_hash)');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support generated columns with custom functions in the same way
            // We'll add a regular column and handle hashing in the application layer
            DB::statement('ALTER TABLE voters ADD COLUMN merge_hash VARCHAR(40)');
            DB::statement('CREATE INDEX voters_merge_hash_idx ON voters (merge_hash)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voters');
    }
};
