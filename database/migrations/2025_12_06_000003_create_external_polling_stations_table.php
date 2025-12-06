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
            $table->unique(['electoral_district_id', 'external_polling_station_id'], 'ed_eps_unique');
            $table->timestamps();

            // Explicit, short foreign key names to avoid MySQL identifier length limits
            $table->foreign('electoral_district_id', 'fk_ed_eps_ed')->references('id')->on('electoral_districts')->onDelete('cascade');
            $table->foreign('external_polling_station_id', 'fk_ed_eps_eps')->references('id')->on('external_polling_stations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('electoral_district_external_polling_station', function (Blueprint $table) {
            // Drop by explicit constraint names used in the up() method
            $table->dropForeign('fk_ed_eps_ed');
            $table->dropForeign('fk_ed_eps_eps');
        });
        Schema::dropIfExists('electoral_district_external_polling_station');
        Schema::dropIfExists('external_polling_stations');
    }
};
