<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electoral_districts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique(); // e.g., Beirut I, Mount Lebanon II
            $table->integer('total_seats'); // Total parliamentary seats for this district
            $table->boolean('is_tracked')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electoral_districts');
    }
};
