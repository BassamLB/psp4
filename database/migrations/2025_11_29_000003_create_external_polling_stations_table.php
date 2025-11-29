<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('external_polling_stations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->comment('Station number or identifier');
            // reference city
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();

            $table->string('center_name')->nullable()->comment('مركز الاقتراع - polling center name');
            $table->text('center_address')->nullable()->comment('عنوان المركز - full address');
            $table->unsignedBigInteger('from_id_number')->nullable()->comment('From ID number range');
            $table->unsignedBigInteger('to_id_number')->nullable()->comment('To ID number range');
            $table->json('meta')->nullable()->comment('Additional metadata');
            $table->timestamps();
        });

        Schema::create('electoral_district_external_polling_station', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('electoral_district_id');
            $table->unsignedBigInteger('external_polling_station_id');
            // Avoid adding FK constraint to electoral_districts here to prevent ordering issues
            // when electoral_districts migration may run later. The pivot still enforces
            // uniqueness and can be constrained later if desired.
            $table->foreign('external_polling_station_id', 'ed_eps_eps_id_fk')
                ->references('id')->on('external_polling_stations')->cascadeOnDelete();
            $table->unique(['electoral_district_id', 'external_polling_station_id'], 'ed_eps_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('electoral_district_external_polling_station');
        Schema::dropIfExists('external_polling_stations');
    }
};
