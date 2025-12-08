<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_imports_temp', function (Blueprint $table) {
            $table->id();

            // Core matching keys
            $table->unsignedInteger('sijil_number')->index();
            $table->unsignedBigInteger('town_id')->index();
            $table->string('first_name', 100)->index();
            $table->string('family_name', 100)->index();

            // Useful fields for review and merging
            $table->string('father_name', 100)->index();
            $table->string('mother_full_name', 150);
            $table->unsignedBigInteger('gender_id');
            $table->unsignedBigInteger('sect_id');
            $table->string('personal_sect');
            $table->string('sijil_additional_string', 100)->nullable();
            $table->date('date_of_birth')->nullable();

            // Import metadata
            $table->boolean('processed')->default(false)->index();

            $table->timestamps();
        });

        // Add merge_hash using raw SQL after table creation
        // Using VIRTUAL instead of STORED to avoid FK constraint conflicts
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE voter_imports_temp ADD COLUMN merge_hash VARCHAR(40) GENERATED ALWAYS AS (sha1(CONCAT_WS('#', sijil_number, town_id, family_name, first_name, father_name, mother_full_name))) VIRTUAL");
            DB::statement('CREATE INDEX voter_imports_merge_hash_idx ON voter_imports_temp (merge_hash)');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support generated columns with custom functions in the same way
            // We'll add a regular column and handle hashing in the application layer
            DB::statement('ALTER TABLE voter_imports_temp ADD COLUMN merge_hash VARCHAR(40)');
            DB::statement('CREATE INDEX voter_imports_merge_hash_idx ON voter_imports_temp (merge_hash)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_imports_temp');
    }
};
