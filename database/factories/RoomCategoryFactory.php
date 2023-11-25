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
     *
     * @var array
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
            'size' => fake()->numberBetween(10, 100),
            'type_of_beds' => $this->generateTypeOfBeds(),
            'pax' => fake()->numberBetween(1, 10),
            'price' => fake()->randomFloat(2, 100, 1000),
            'tax' => fake()->randomFloat(2, 0, 100),
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

        foreach ($this->bedTypes as $type) {
            $count = fake()->numberBetween(0, 5);
            if ($count > 0) {
                $bedTypes[$type] = $count;
            }
        }

        return $bedTypes;
    }
}
