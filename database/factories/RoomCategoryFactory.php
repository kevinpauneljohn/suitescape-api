<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomCategory>
 */
class RoomCategoryFactory extends Factory
{
    /**
     * The array of bed types.
     */
    protected array $bedTypes = [
        'futon',
        'metal',
        'bunk',
        'adjustable',
        'tatami',
        'simple',
        'trundle',
        'storage',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->paragraphs(3, true),
            'floor_area' => fake()->numberBetween(10, 100),
            'type_of_beds' => $this->generateTypeOfBeds(),
            'pax' => fake()->numberBetween(1, 10),
            'weekday_price' => fake()->randomFloat(2, 1000, 10000),
            'weekend_price' => fake()->randomFloat(2, 1000, 10000),
            'quantity' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Generate a random array for type_of_beds.
     *
     * @return array<string, int>
     */
    protected function generateTypeOfBeds(): array
    {
        $bedTypes = [];

        $typesCount = fake()->numberBetween(1, 3);

        foreach (fake()->randomElements($this->bedTypes, $typesCount) as $type) {
            $count = fake()->numberBetween(1, 5);
            $bedTypes[$type] = $count;
        }

        return $bedTypes;
    }
}
