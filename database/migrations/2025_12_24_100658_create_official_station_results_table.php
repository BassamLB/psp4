<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_station_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->constrained('polling_stations')->cascadeOnDelete();
            $table->foreignId('list_id')->nullable()->constrained('electoral_lists')->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();

            $table->integer('official_vote_count'); // من محضر الفرز
            $table->string('document_reference')->nullable(); // رقم محضر الفرز

            // Authorization
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamp('entered_at');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();

            $table->boolean('is_final')->default(false);
            $table->integer('discrepancy_amount')->nullable(); // الفرق بين الرسمي والفعلي
            $table->text('notes')->nullable();

            $table->unique(['polling_station_id', 'list_id', 'candidate_id'], 'official_results_unique');
            $table->index(['polling_station_id', 'is_final']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_station_results');
    }
};
