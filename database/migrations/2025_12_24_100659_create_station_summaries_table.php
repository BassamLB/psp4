<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->unique()->constrained('polling_stations')->cascadeOnDelete();

            // Vote counts
            $table->integer('total_ballots_entered')->default(0);
            $table->integer('valid_list_votes')->default(0);
            $table->integer('valid_preferential_votes')->default(0);
            $table->integer('white_papers')->default(0);
            $table->integer('cancelled_papers')->default(0);

            // Timing
            $table->timestamp('first_entry_at')->nullable();
            $table->timestamp('last_entry_at')->nullable();
            $table->timestamp('counting_completed_at')->nullable();

            // Official results
            $table->timestamp('official_results_entered_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('has_discrepancies')->default(false);

            // Verification
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_summaries');
    }
};
