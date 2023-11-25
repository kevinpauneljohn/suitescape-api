<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceRating>
 */
class ServiceRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cleanliness' => fake()->numberBetween(1, 5),
            'price_affordability' => fake()->numberBetween(1, 5),
            'facility_service' => fake()->numberBetween(1, 5),
            'comfortability' => fake()->numberBetween(1, 5),
            'staff' => fake()->numberBetween(1, 5),
            'location' => fake()->numberBetween(1, 5),
            'privacy_and_security' => fake()->numberBetween(1, 5),
            'accessibility' => fake()->numberBetween(1, 5),
        ];
    }
}
