<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polling_st_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->constrained('polling_stations')->cascadeOnDelete();
            $table->integer('from_sijil')->default(0); // من رقم السجل
            $table->integer('to_sijil')->default(0); // الى رقم السجل
            $table->foreignId('sect_id')->constrained('sects')->cascadeOnDelete();
            $table->foreignId('gender_id')->constrained('genders')->cascadeOnDelete();

            $table->unique(['polling_station_id', 'sect_id', 'gender_id']); // unique per polling station, sect, and gender
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_st_details');
    }
};
