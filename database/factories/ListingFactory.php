<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::all()->random()->id,
            'name' => ucwords(fake()->words(3, true)),
            'location' => fake()->streetAddress().', '.fake()->city().', '.fake()->stateAbbr().' '.fake()->postcode(),
            'description' => fake()->paragraphs(3, true),
        ];
    }

    public function mine(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'user_id' => User::first()->id,
            ];
        });
    }
}
