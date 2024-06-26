<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Addon>
 */
class AddonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isConsumable = fake()->boolean;

        return [
            'price' => fake()->randomFloat(2, 500, 1000),
            'quantity' => $isConsumable ? fake()->numberBetween(1, 10) : null,
            'is_consumable' => $isConsumable,
        ];
    }
}
