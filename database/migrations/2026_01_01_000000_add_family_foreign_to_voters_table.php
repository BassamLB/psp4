<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            // voters already has `family_id` column; add the FK constraint now that
            // `families` table exists.
            $table->foreign('family_id')->references('id')->on('families')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropForeign(['family_id']);
        });
    }
};
