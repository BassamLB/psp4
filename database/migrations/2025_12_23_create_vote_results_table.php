<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vote_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->constrained('polling_stations')->cascadeOnDelete();
            $table->foreignId('list_id')->constrained('electoral_lists')->cascadeOnDelete();
            $table->integer('list_votes')->default(0); // Total votes for the list
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamp('entered_at')->nullable();
            $table->ipAddress('entered_from_ip')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->ipAddress('verified_from_ip')->nullable();
            $table->string('primary_reg_com')->nullable(); // Primary registration committee لجنة القيد الابتدائية
            $table->integer('primary_reg_number')->nullable(); // Primary registration number

            $table->timestamps();

            $table->softDeletes();
            $table->unique(['polling_station_id', 'list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_results');
    }
};
