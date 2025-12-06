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
        Schema::table('families', function (Blueprint $table) {
            $table->unsignedBigInteger('father_id')->nullable()->after('sijil_number');
            $table->unsignedBigInteger('mother_id')->nullable()->after('father_id');
            
            // Add foreign key constraints
            $table->foreign('father_id')->references('id')->on('voters')->onDelete('set null');
            $table->foreign('mother_id')->references('id')->on('voters')->onDelete('set null');
            
            // Add index for lookups
            $table->index(['father_id', 'mother_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropForeign(['father_id']);
            $table->dropForeign(['mother_id']);
            $table->dropIndex(['father_id', 'mother_id']);
            $table->dropColumn(['father_id', 'mother_id']);
        });
    }
};
