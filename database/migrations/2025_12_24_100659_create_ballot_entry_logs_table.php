<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ballot_entry_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->constrained('polling_stations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('event_type', [
                'entry_created',
                'entry_corrected',
                'counting_started',
                'counting_paused',
                'counting_resumed',
                'counting_completed',
                'verification_started',
                'verification_completed',
                'official_results_entered',
                'station_finalized',
                'discrepancy_reported',
                'suspicious_activity',
            ]);

            $table->json('event_data')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at');

            $table->index(['polling_station_id', 'created_at']);
            $table->index(['user_id', 'event_type']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ballot_entry_logs');
    }
};
