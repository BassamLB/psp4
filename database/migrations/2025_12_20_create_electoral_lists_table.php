<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electoral_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('electoral_district_id')->constrained('electoral_districts')->cascadeOnDelete();
            $table->string('name'); // List name
            $table->integer('number'); // List number
            $table->string('color')->nullable(); // Electoral List color
            $table->boolean('passed_factor')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electoral_lists');
    }
};
