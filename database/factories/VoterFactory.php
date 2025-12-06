<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voter>
 */
class VoterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'family_name' => fake()->lastName(),
            'father_name' => fake()->firstName(),
            'mother_full_name' => fake()->name(),
            'date_of_birth' => fake()->date(),
            'gender_id' => fake()->numberBetween(1, 2),
            'personal_sect' => fake()->word(),
            'sect_id' => fake()->numberBetween(1, 10),
            'town_id' => fake()->numberBetween(1, 100),
            'sijil_number' => fake()->unique()->numberBetween(10000, 99999999),
            'travelled' => false,
            'deceased' => false,
        ];
    }
}
