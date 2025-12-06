<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Guard: in some test environments (sqlite in-memory) the families table
        // may not exist yet. Skip the alteration if the table is missing to
        // avoid migration errors during test runs.
        if (! Schema::hasTable('families')) {
            return;
        }

        Schema::table('families', function (Blueprint $table) {
            if (! Schema::hasColumn('families', 'father_id')) {
                $table->unsignedBigInteger('father_id')->nullable()->after('sijil_number');
            }
            if (! Schema::hasColumn('families', 'mother_id')) {
                $table->unsignedBigInteger('mother_id')->nullable()->after('father_id');
            }

            // Add foreign key constraints if DB supports them and columns exist
            if (Schema::hasColumn('families', 'father_id')) {
                $table->foreign('father_id')->references('id')->on('voters')->onDelete('set null');
            }
            if (Schema::hasColumn('families', 'mother_id')) {
                $table->foreign('mother_id')->references('id')->on('voters')->onDelete('set null');
            }

            // Add index for lookups
            if (! Schema::hasColumn('families', 'father_id') || ! Schema::hasColumn('families', 'mother_id')) {
                // If either column was just created, ensure index exists
                $table->index(['father_id', 'mother_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('families')) {
            return;
        }

        Schema::table('families', function (Blueprint $table) {
            if (Schema::hasColumn('families', 'father_id')) {
                try {
                    $table->dropForeign(['father_id']);
                } catch (\Throwable $e) {
                    // ignore if foreign key doesn't exist in this connection
                }
            }
            if (Schema::hasColumn('families', 'mother_id')) {
                try {
                    $table->dropForeign(['mother_id']);
                } catch (\Throwable $e) {
                    // ignore if foreign key doesn't exist in this connection
                }
            }

            try {
                $table->dropIndex(['father_id', 'mother_id']);
            } catch (\Throwable $e) {
                // ignore if index doesn't exist
            }

            if (Schema::hasColumn('families', 'father_id') || Schema::hasColumn('families', 'mother_id')) {
                $table->dropColumn(['father_id', 'mother_id']);
            }
        });
    }
};
