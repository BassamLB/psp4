<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قلم الإقتراع راجع لجان القيد للمعنى
        Schema::create('polling_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('town_id')->constrained('towns')->cascadeOnDelete();
            $table->integer('station_number'); // رقم قلم الاقتراع
            $table->string('location');
            $table->integer('registered_voters')->default(0); // عدد الناخبين المسجلين
            $table->integer('white_papers_count')->default(0); // عدد الأوراق البيضاء
            $table->integer('cancelled_papers_count')->default(0); // عدد الأوراق الملغاة
            $table->integer('voters_count')->default(0); // عدد المقترعين
            $table->boolean('is_open')->default(false); // Indicates if the polling station is open When the first vote is submitted it will be set to true
            $table->boolean('is_on_hold')->default(false); // Indicates if the polling station is on hold (due to any issue)
            $table->boolean('is_closed')->default(false); // Indicates if the polling station is closed and starting to count votes
            $table->boolean('is_done')->default(false); // Indicates if the polling station has completed all processes
            $table->boolean('is_checked')->default(false); // Indicates if the polling station finished and checked from rassiss l qalam
            $table->boolean('is_final')->default(false); // Indicates if the polling station from lijan l qaid is final and cannot be changed

            $table->unique(['election_id', 'town_id', 'station_number']); // unique per election, town, and station number
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_stations');
    }
};
