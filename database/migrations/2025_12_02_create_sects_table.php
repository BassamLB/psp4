<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); // دروزي, ماروني, سنّي, شيعي
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sects');
    }
};
