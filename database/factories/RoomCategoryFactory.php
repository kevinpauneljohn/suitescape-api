<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomCategory>
 */
class RoomCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "name" => fake()->word(),
            "size" => rand(1, 50),
            "type_of_beds" => $this->generateTypeOfBeds(),
            "pax" => rand(1, 10),
            "price" => fake()->randomFloat(2, 100, 1000),
            "tax" => fake()->randomFloat(2, 0, 100),
        ];
    }

    /**
     * Generate a random array for type_of_beds.
     *
     * @return array<string, int>
     */
    protected function generateTypeOfBeds(): array
    {
        $bedTypes = [
            'single' => rand(0, 3),
            'double' => rand(0, 2),
            'queen' => rand(0, 2),
            'king' => rand(0, 2),
        ];

        // Filter out bed types with a count of zero to keep the JSON concise
        return array_filter($bedTypes, function ($count) {
            return $count > 0;
        });
    }
}
