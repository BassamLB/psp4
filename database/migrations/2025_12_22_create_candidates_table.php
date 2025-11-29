<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('electoral_lists')->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->unsignedBigInteger('voter_id')->nullable(); // No FK constraint - voters table doesn't exist yet
            $table->integer('position_on_list'); // Order on the list
            $table->foreignId('sect_id')->nullable()->constrained()->nullOnDelete();
            $table->string('party_affiliation', 150)->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('withdrawn')->default(false);
            $table->date('withdrawn_at')->nullable();
            $table->integer('preferential_votes_count')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['list_id', 'position_on_list']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
