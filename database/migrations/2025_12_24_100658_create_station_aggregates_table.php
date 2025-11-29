<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->constrained('polling_stations')->cascadeOnDelete();
            $table->foreignId('list_id')->nullable()->constrained('electoral_lists')->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();

            $table->integer('vote_count')->default(0);
            $table->timestamp('last_updated_at');

            $table->unique(['polling_station_id', 'list_id', 'candidate_id'], 'station_aggregates_unique');
            $table->index(['polling_station_id', 'vote_count']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_aggregates');
    }
};
