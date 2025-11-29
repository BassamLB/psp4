<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ballot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->constrained('polling_stations')->cascadeOnDelete();
            $table->foreignId('list_id')->nullable()->constrained('electoral_lists', 'id')->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();

            $table->enum('ballot_type', [
                'valid_list',           // صوت صحيح للائحة فقط
                'valid_preferential',   // صوت صحيح تفضيلي (لائحة + مرشح)
                'white',                // ورقة بيضاء
                'cancelled',             // ورقة ملغاة
            ]);
            $table->string('cancellation_reason')->nullable();

            // Audit trail - immutable
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamp('entered_at');
            $table->ipAddress('ip_address')->nullable();
            $table->json('metadata')->nullable();

            $table->index(['polling_station_id', 'entered_at']);
            $table->index(['list_id', 'polling_station_id']);
            $table->index(['candidate_id', 'polling_station_id']);
            $table->index('entered_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ballot_entries');
    }
};
