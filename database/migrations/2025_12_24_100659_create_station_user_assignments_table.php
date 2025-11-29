<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->constrained('polling_stations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('role', [
                'counter',      // مندوب إدخال البيانات
                'verifier',     // مدقق
                'supervisor',    // مشرف
            ]);

            $table->timestamp('assigned_at');
            $table->foreignId('assigned_by')->constrained('users');
            $table->boolean('is_active')->default(true);

            $table->unique(['polling_station_id', 'user_id', 'role'], 'station_user_role_unique');
            $table->index(['user_id', 'is_active']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_user_assignments');
    }
};
